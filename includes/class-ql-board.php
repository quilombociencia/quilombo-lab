<?php
/**
 * Classe para gestão de quadros
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Board {
    
    private static $instance = null;
    private $table_name;
    
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
        $this->table_name = $wpdb->prefix . 'ql_boards';
    }
    
    /**
     * Criar quadro
     */
    public function create($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['project_id'])) {
            return new WP_Error('missing_data', 'Nome e ID do projeto são obrigatórios');
        }
        
        $board_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => !empty($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'project_id' => intval($data['project_id']),
            'user_id' => get_current_user_id(),
            'status' => !empty($data['status']) ? sanitize_text_field($data['status']) : 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->table_name, $board_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar quadro: ' . $wpdb->last_error);
        }
        
        $board_id = $wpdb->insert_id;
        
        // Criar colunas padrão
        $this->create_default_columns($board_id);
        
        // Hook para extensibilidade
        do_action('ql_board_created', $board_id, $board_data);
        
        return $board_id;
    }
    
    /**
     * Criar colunas padrão para um quadro
     */
    private function create_default_columns($board_id) {
        if (!class_exists('QL_Column')) {
            return;
        }
        
        $columns = $this->get_default_columns_config();
        $column_model = QL_Column::get_instance();
        
        foreach ($columns as $column) {
            $column['board_id'] = $board_id;
            $column_model->create($column);
        }
    }
    
    /**
     * Obter configuração de colunas padrão
     */
    private function get_default_columns_config() {
        // Obter configurações do admin
        $settings = get_option('quilombo_laboratorio_settings', []);
        $default_columns_config = $settings['default_columns'] ?? '';
        
        // Se há configuração personalizada, processar
        if (!empty($default_columns_config)) {
            $columns = [];
            $lines = explode("\n", $default_columns_config);
            $position = 0;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $parts = explode(',', $line);
                if (count($parts) >= 1) {
                    $name = trim($parts[0]);
                    $color = isset($parts[1]) ? trim($parts[1]) : '#f4f4f4';
                    
                    // Validar cor hex
                    if (!preg_match('/^#[a-fA-F0-9]{6}$/', $color)) {
                        $color = '#f4f4f4';
                    }
                    
                    $columns[] = [
                        'name' => $name,
                        'position' => $position++,
                        'color' => $color
                    ];
                }
            }
            
            if (!empty($columns)) {
                return $columns;
            }
        }
        
        // Configuração padrão do sistema
        return [
            ['name' => 'Backlog', 'position' => 0, 'color' => '#6c757d'],
            ['name' => 'Para Fazer', 'position' => 1, 'color' => '#ffc107'],
            ['name' => 'Em Progresso', 'position' => 2, 'color' => '#17a2b8'],
            ['name' => 'Revisão', 'position' => 3, 'color' => '#fd7e14'],
            ['name' => 'Concluído', 'position' => 4, 'color' => '#28a745']
        ];
    }
    
    /**
     * Obter quadro por ID
     */
    public function get_by_id($board_id) {
        global $wpdb;
        
        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $board_id
        ), ARRAY_A);
        
        if (!$board) {
            return null;
        }
        
        // Adicionar colunas e tarefas
        $board['columns'] = $this->get_board_columns($board_id);
        $board['tasks_count'] = $this->get_tasks_count($board_id);
        
        return $board;
    }
    
    /**
     * Obter board por projeto
     */
    public function get_by_project($project_id) {
        global $wpdb;
        
        $boards = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE project_id = %d ORDER BY created_at DESC",
            $project_id
        ), ARRAY_A);
        
        // Adicionar dados adicionais para cada board
        foreach ($boards as &$board) {
            $board['columns'] = $this->get_board_columns($board['id']);
            $board['tasks_count'] = $this->get_tasks_count($board['id']);
        }
        
        return $boards;
    }
    
    /**
     * Obter colunas do board
     */
    private function get_board_columns($board_id) {
        if (!class_exists('QL_Column')) {
            return [];
        }
        
        $column_model = QL_Column::get_instance();
        return $column_model->get_by_board($board_id);
    }
    
    /**
     * Obter contagem de tarefas do board
     */
    private function get_tasks_count($board_id) {
        if (!class_exists('QL_Task')) {
            return 0;
        }
        
        global $wpdb;
        $task_table = $wpdb->prefix . 'ql_tasks';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$task_table} WHERE board_id = %d",
            $board_id
        ));
        
        return intval($count);
    }
    
    /**
     * Atualizar board
     */
    public function update($board_id, $data) {
        global $wpdb;
        
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $board_id],
            null,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar board: ' . $wpdb->last_error);
        }
        
        // Hook para extensibilidade
        do_action('ql_board_updated', $board_id, $update_data);
        
        return true;
    }
    
    /**
     * Excluir board
     */
    public function delete($board_id) {
        global $wpdb;
        
        // Verificar se board existe
        $board = $this->get_by_id($board_id);
        if (!$board) {
            return new WP_Error('not_found', 'Board não encontrado');
        }
        
        // Excluir tarefas associadas
        if (class_exists('QL_Task')) {
            $task_model = QL_Task::get_instance();
            $task_model->delete_by_board($board_id);
        }
        
        // Excluir colunas associadas
        if (class_exists('QL_Column')) {
            $column_model = QL_Column::get_instance();
            $column_model->delete_by_board($board_id);
        }
        
        // Excluir board
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $board_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao excluir board: ' . $wpdb->last_error);
        }
        
        // Hook para extensibilidade
        do_action('ql_board_deleted', $board_id);
        
        return true;
    }
    
    /**
     * Listar boards do usuário
     */
    public function get_user_boards($user_id = null) {
        global $wpdb;
        
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Verificar se usuário tem acesso através de projetos
        $project_table = $wpdb->prefix . 'ql_projects';
        
        $boards = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.name as project_name 
             FROM {$this->table_name} b
             LEFT JOIN {$project_table} p ON b.project_id = p.id
             WHERE b.user_id = %d OR p.user_id = %d
             ORDER BY b.updated_at DESC",
            $user_id, $user_id
        ), ARRAY_A);
        
        // Adicionar dados adicionais
        foreach ($boards as &$board) {
            $board['columns_count'] = count($this->get_board_columns($board['id']));
            $board['tasks_count'] = $this->get_tasks_count($board['id']);
        }
        
        return $boards;
    }
    
    /**
     * Obter estatísticas do board
     */
    public function get_board_stats($board_id) {
        if (!class_exists('QL_Task')) {
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'pending_tasks' => 0,
                'overdue_tasks' => 0
            ];
        }
        
        global $wpdb;
        $task_table = $wpdb->prefix . 'ql_tasks';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN due_date < NOW() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
             FROM {$task_table} 
             WHERE board_id = %d",
            $board_id
        ), ARRAY_A);
        
        return [
            'total_tasks' => intval($stats['total_tasks']),
            'completed_tasks' => intval($stats['completed_tasks']),
            'pending_tasks' => intval($stats['pending_tasks']),
            'overdue_tasks' => intval($stats['overdue_tasks'])
        ];
    }
    
    /**
     * Duplicar board
     */
    public function duplicate($board_id, $new_name = null) {
        $original_board = $this->get_by_id($board_id);
        
        if (!$original_board) {
            return new WP_Error('not_found', 'Board original não encontrado');
        }
        
        // Criar novo board
        $new_board_data = [
            'name' => $new_name ?: $original_board['name'] . ' (Cópia)',
            'description' => $original_board['description'],
            'project_id' => $original_board['project_id'],
            'status' => 'active'
        ];
        
        $new_board_id = $this->create($new_board_data);
        
        if (is_wp_error($new_board_id)) {
            return $new_board_id;
        }
        
        // Duplicar colunas (sem tarefas por padrão)
        if (class_exists('QL_Column')) {
            $column_model = QL_Column::get_instance();
            $column_model->duplicate_board_columns($board_id, $new_board_id);
        }
        
        return $new_board_id;
    }
    
    /**
     * Buscar boards
     */
    public function search($search_term, $project_id = null) {
        global $wpdb;
        
        $where_clause = "WHERE (b.name LIKE %s OR b.description LIKE %s)";
        $params = ["%{$search_term}%", "%{$search_term}%"];
        
        if ($project_id) {
            $where_clause .= " AND b.project_id = %d";
            $params[] = $project_id;
        }
        
        $sql = "SELECT b.*, p.name as project_name 
                FROM {$this->table_name} b
                LEFT JOIN {$wpdb->prefix}ql_projects p ON b.project_id = p.id
                {$where_clause}
                ORDER BY b.updated_at DESC";
        
        $boards = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        // Adicionar dados adicionais
        foreach ($boards as &$board) {
            $board['columns_count'] = count($this->get_board_columns($board['id']));
            $board['tasks_count'] = $this->get_tasks_count($board['id']);
        }
        
        return $boards;
    }
}