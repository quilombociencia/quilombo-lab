<?php
/**
 * Classe para gestão de tarefas
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Task {
    
    private static $instance = null;
    private $table_name;
    
    // Status das tarefas
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_IN_PROGRESS = 'in_progress';
    
    // Prioridades
    const PRIORITY_LOW = 1;
    const PRIORITY_NORMAL = 2;
    const PRIORITY_HIGH = 3;
    const PRIORITY_URGENT = 4;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ql_tasks';
    }
    
    /**
     * Criar tarefa
     */
    public function create($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['title']) || empty($data['column_id'])) {
            return new WP_Error('missing_data', 'Título e ID da coluna são obrigatórios');
        }
        
        // Verificar limite de tarefas na coluna
        if (class_exists('QL_Column')) {
            $column_model = QL_Column::get_instance();
            if (!$column_model->check_task_limit($data['column_id'])) {
                return new WP_Error('column_limit', 'Limite de tarefas na coluna atingido');
            }
        }
        
        // Se posição não foi especificada, usar próxima disponível
        if (!isset($data['position'])) {
            $data['position'] = $this->get_next_position($data['column_id']);
        }
        
        // Versão mínima usando apenas campos essenciais
        $task_data = [
            'title' => sanitize_text_field($data['title']),
            'description' => !empty($data['description']) ? wp_kses_post($data['description']) : '',
            'column_id' => intval($data['column_id']),
            'creator_id' => get_current_user_id()
        ];
        
        // Adicionar campos opcionais se existirem na tabela
        if (!empty($data['board_id'])) {
            $task_data['board_id'] = intval($data['board_id']);
        }
        if (!empty($data['project_id'])) {
            $task_data['project_id'] = intval($data['project_id']);
        }
        if (!empty($data['assignee_id'])) {
            $task_data['assigned_user_id'] = intval($data['assignee_id']);
        }
        if (!empty($data['status'])) {
            $task_data['status'] = sanitize_text_field($data['status']);
        }
        if (!empty($data['priority'])) {
            $task_data['priority'] = sanitize_text_field($data['priority']);
        }
        if (isset($data['position'])) {
            $task_data['position'] = intval($data['position']);
        }
        
        $result = $wpdb->insert($this->table_name, $task_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar tarefa: ' . $wpdb->last_error);
        }
        
        $task_id = $wpdb->insert_id;
        
        // Hook para extensibilidade
        do_action('ql_task_created', $task_id, $task_data);
        
        return $task_id;
    }
    
    /**
     * Gerar número único para tarefa
     */
    private function generate_task_number() {
        return 'QL-' . strtoupper(wp_generate_password(6, false, false));
    }
    
    /**
     * Obter próxima posição disponível
     */
    private function get_next_position($column_id) {
        global $wpdb;
        
        $max_position = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM {$this->table_name} WHERE column_id = %d",
            $column_id
        ));
        
        return ($max_position !== null) ? $max_position + 1 : 0;
    }
    
    /**
     * Obter tarefa por ID completa com subtarefas e atividades
     */
    public function get_by_id($task_id) {
        global $wpdb;
        
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, 
                    u1.display_name as creator_name,
                    u1.user_email as creator_email,
                    u2.display_name as assigned_user_name,
                    u2.user_email as assigned_user_email,
                    c.name as column_name,
                    c.color as column_color,
                    b.name as board_name,
                    p.name as project_name
             FROM {$this->table_name} t
             LEFT JOIN {$wpdb->users} u1 ON t.creator_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON t.assigned_user_id = u2.ID
             LEFT JOIN {$wpdb->prefix}ql_columns c ON t.column_id = c.id
             LEFT JOIN {$wpdb->prefix}ql_boards b ON t.board_id = b.id
             LEFT JOIN {$wpdb->prefix}ql_projects p ON t.project_id = p.id
             WHERE t.id = %d",
            $task_id
        ), ARRAY_A);
        
        if (!$task) {
            return null;
        }
        
        // Funcionalidade de subtarefas temporariamente desabilitada
        $task['subtasks'] = [];
        
        // Adicionar atividades recentes
        $task['activities'] = $this->get_task_activities($task_id, 10);
        
        // Adicionar anexos
        $task['attachments'] = $this->get_task_attachments($task_id);
        
        return $task;
    }
    
    /**
     * Obter subtarefas de uma tarefa
     */
    public function get_subtasks($parent_task_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, 
                    u1.display_name as creator_name,
                    u2.display_name as assigned_user_name,
                    c.name as column_name,
                    c.color as column_color
             FROM {$this->table_name} t
             LEFT JOIN {$wpdb->users} u1 ON t.creator_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON t.assigned_user_id = u2.ID
             LEFT JOIN {$wpdb->prefix}ql_columns c ON t.column_id = c.id
             WHERE 1=0
             ORDER BY t.position ASC, t.created_at ASC",
            $parent_task_id
        ), ARRAY_A);
    }
    
    /**
     * Obter atividades de uma tarefa
     */
    public function get_task_activities($task_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as user_name, u.user_email
             FROM {$wpdb->prefix}ql_task_activities a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.task_id = %d
             ORDER BY a.created_at DESC
             LIMIT %d",
            $task_id, $limit
        ), ARRAY_A);
    }
    
    /**
     * Obter anexos de uma tarefa
     */
    public function get_task_attachments($task_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name as uploader_name
             FROM {$wpdb->prefix}ql_attachments a
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
             WHERE a.task_id = %d
             ORDER BY a.created_at DESC",
            $task_id
        ), ARRAY_A);
    }
    
    /**
     * Criar subtarefa - Temporariamente desabilitado
     */
    public function create_subtask($parent_task_id, $data) {
        return new WP_Error('feature_disabled', 'Funcionalidade temporariamente desabilitada');
    }
    
    /**
     * Atualizar tarefa
     */
    public function update($task_id, $data) {
        global $wpdb;
        
        // Verificar se tarefa existe
        $existing_task = $this->get_by_id($task_id);
        if (!$existing_task) {
            return new WP_Error('task_not_found', 'Tarefa não encontrada');
        }
        
        // Verificar permissões
        if (!current_user_can('edit_posts') && $existing_task['creator_id'] != get_current_user_id()) {
            return new WP_Error('insufficient_permissions', 'Sem permissão para editar esta tarefa');
        }
        
        // Preparar dados para atualização
        $update_data = [];
        $old_values = [];
        
        $allowed_fields = [
            'title', 'description', 'status', 'priority', // 'task_type', // Removido temporariamente
            'assigned_user_id', 'owner_id', 'start_date', 'due_date',
            'estimated_hours', 'actual_hours', 'story_points',
            'estimated_cost', 'actual_cost', 'color', 'tags'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $old_values[$field] = $existing_task[$field] ?? null;
                
                switch ($field) {
                    case 'title':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                    case 'description':
                        $update_data[$field] = wp_kses_post($data[$field]);
                        break;
                    case 'color':
                        $update_data[$field] = sanitize_hex_color($data[$field]);
                        break;
                    case 'tags':
                        $update_data[$field] = sanitize_text_field($data[$field]);
                        break;
                    default:
                        $update_data[$field] = $data[$field];
                }
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'Nenhum dado para atualizar');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $task_id],
            null,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar tarefa: ' . $wpdb->last_error);
        }
        
        // Registrar atividades das mudanças
        foreach ($update_data as $field => $new_value) {
            if ($field === 'updated_at') continue;
            
            $old_value = $old_values[$field] ?? null;
            if ($old_value != $new_value) {
                $this->add_activity($task_id, 'field_change', "Campo '$field' alterado", [
                    'field' => $field,
                    'old_value' => $old_value,
                    'new_value' => $new_value
                ]);
            }
        }
        
        // Hook para extensibilidade
        do_action('ql_task_updated', $task_id, $update_data, $old_values);
        
        return true;
    }
    
    /**
     * Adicionar atividade à tarefa
     */
    public function add_activity($task_id, $activity_type, $content, $metadata = []) {
        global $wpdb;
        
        $activity_data = [
            'task_id' => intval($task_id),
            'user_id' => get_current_user_id(),
            'activity_type' => sanitize_text_field($activity_type),
            'content' => sanitize_text_field($content),
            'metadata' => json_encode($metadata),
            'created_at' => current_time('mysql')
        ];
        
        return $wpdb->insert(
            $wpdb->prefix . 'ql_task_activities',
            $activity_data
        );
    }
    
    /**
     * Adicionar comentário à tarefa
     */
    public function add_comment($task_id, $comment, $is_private = false) {
        return $this->add_activity($task_id, 'comment', $comment, [
            'is_private' => $is_private
        ]);
    }
    
    /**
     * Mover tarefa para outra coluna
     */
    public function move_to_column($task_id, $column_id, $position = null) {
        global $wpdb;
        
        // Verificar se coluna existe
        $column = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_columns WHERE id = %d",
            $column_id
        ));
        
        if (!$column) {
            return new WP_Error('column_not_found', 'Coluna não encontrada');
        }
        
        // Se posição não especificada, usar final da coluna
        if ($position === null) {
            $position = $this->get_next_position($column_id);
        }
        
        $old_data = $this->get_by_id($task_id);
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'column_id' => $column_id,
                'position' => $position,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $task_id],
            ['%d', '%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            // Registrar atividade
            $this->add_activity($task_id, 'moved', "Tarefa movida para coluna '{$column->name}'", [
                'old_column_id' => $old_data['column_id'],
                'new_column_id' => $column_id,
                'old_column_name' => $old_data['column_name'],
                'new_column_name' => $column->name
            ]);
            
            do_action('ql_task_moved', $task_id, $column_id, $old_data['column_id']);
        }
        
        return $result !== false;
    }
    
    /**
     * Excluir tarefa
     */
    public function delete($task_id) {
        global $wpdb;
        
        $task = $this->get_by_id($task_id);
        if (!$task) {
            return new WP_Error('task_not_found', 'Tarefa não encontrada');
        }
        
        // Verificar permissões
        if (!current_user_can('delete_posts') && $task['creator_id'] != get_current_user_id()) {
            return new WP_Error('insufficient_permissions', 'Sem permissão para excluir esta tarefa');
        }
        
        // Verificação de subtarefas temporariamente desabilitada
        
        $result = $wpdb->delete($this->table_name, ['id' => $task_id], ['%d']);
        
        if ($result !== false) {
            do_action('ql_task_deleted', $task_id, $task);
        }
        
        return $result !== false;
    }
    
    /**
     * Obter tarefas por coluna
     */
    public function get_by_column($column_id) {
        global $wpdb;
        
        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT t.*, 
                    u1.display_name as creator_name,
                    u2.display_name as assigned_user_name
             FROM {$this->table_name} t
             LEFT JOIN {$wpdb->users} u1 ON t.creator_id = u1.ID
             LEFT JOIN {$wpdb->users} u2 ON t.assigned_user_id = u2.ID
             WHERE t.column_id = %d
             ORDER BY t.position ASC, t.created_at DESC",
            $column_id
        ), ARRAY_A);
        
        return $tasks ?: [];
    }

    /**
     * Obter projetos do usuário
     */
    public static function get_user_projects($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        
        // Buscar projetos onde o usuário é membro ou owner
        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT p.* FROM {$wpdb->prefix}ql_projects p 
             LEFT JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id 
             WHERE p.owner_id = %d OR pm.user_id = %d 
             GROUP BY p.id 
             ORDER BY p.updated_at DESC",
            $user_id, $user_id
        ));
        
        return $projects ?: [];
    }

    /**
     * Criar projeto a partir de dados GC
     */
    public static function create_from_gc_project($gc_projeto_id, $projeto_data) {
        // Esta função é chamada via integração
        return QL_GC_Integration::get_instance()->on_gc_project_created($gc_projeto_id, $projeto_data);
    }
    
    /**
     * Sincronizar projeto com dados do GC
     */
    public static function sync_from_gc_project($ql_project_id, $projeto_data) {
        global $wpdb;
        
        $update_data = [
            'name' => $projeto_data['nome'],
            'description' => $projeto_data['descricao'] ?? '',
            'status' => self::map_gc_status_to_ql($projeto_data['status']),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            $update_data,
            ['id' => $ql_project_id]
        );
        
        return $result;
    }
    
    /**
     * Mapear status do GC para QL
     */
    private static function map_gc_status_to_ql($gc_status) {
        $status_map = [
            'ativo' => 'active',
            'pausado' => 'on_hold',
            'concluido' => 'completed',
            'cancelado' => 'archived'
        ];
        
        return $status_map[$gc_status] ?? 'active';
    }
}