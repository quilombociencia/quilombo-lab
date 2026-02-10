<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciamento de projetos do Quilombo Laboratório
 */
class QL_Project {
    
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
        $this->table_name = $wpdb->prefix . 'ql_projects';
        
        // Hooks para integrar com sistema de responsabilidades
        add_action('ql_circle_formed', [$this, 'on_circle_formed'], 10, 2);
        add_action('ql_member_added_to_instance', [$this, 'check_circle_completion'], 10, 3);
        add_action('ql_member_removed_from_instance', [$this, 'check_circle_deactivation'], 10, 3);
    }
    
    /**
     * Criar projeto do laboratório a partir de projeto GC
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
    
    /**
     * Obter projetos do usuário
     */
    public function get_user_projects($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        global $wpdb;
        
        // Buscar projetos onde o usuário é membro ou owner
        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT p.* FROM {$this->table_name} p 
             LEFT JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id 
             WHERE p.owner_id = %d OR pm.user_id = %d 
             GROUP BY p.id 
             ORDER BY p.updated_at DESC",
            $user_id, $user_id
        ));
        
        return $projects ?: [];
    }
    
    /**
     * Criar projeto
     * Conforme modelo organizativo: projeto inicia inativo até formar círculo completo
     */
    public function create($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['name'])) {
            return new WP_Error('missing_data', 'Nome é obrigatório');
        }
        
        // Projetos começam como "initial" (círculo inicial) até formarem círculo completo
        $initial_status = 'initial';
        if (!empty($data['status']) && in_array($data['status'], ['initial', 'forming', 'active', 'inactive'])) {
            $initial_status = $data['status'];
        }
        
        $project_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => !empty($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'status' => $initial_status,
            'visibility' => !empty($data['visibility']) ? sanitize_text_field($data['visibility']) : 'team',
            'owner_id' => !empty($data['owner_id']) ? intval($data['owner_id']) : get_current_user_id(),
            'start_date' => !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null,
            'end_date' => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null,
            'priority' => !empty($data['priority']) ? sanitize_text_field($data['priority']) : 'normal',
            'gc_projeto_id' => !empty($data['gc_projeto_id']) ? intval($data['gc_projeto_id']) : null,
            'moodle_course_id' => !empty($data['moodle_course_id']) ? intval($data['moodle_course_id']) : null,
            'project_type' => !empty($data['project_type']) ? sanitize_text_field($data['project_type']) : 'creation',
            'activation_ready' => false,
            'circle_complete' => false,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'metadata' => json_encode([
                'circle_formation_stage' => 'initial',
                'required_roles_filled' => [],
                'activation_requirements' => [
                    'min_members' => 3,
                    'required_roles' => ['guia'],
                    'all_roles_assigned' => false
                ]
            ])
        ];
        
        $result = $wpdb->insert($this->table_name, $project_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar projeto: ' . $wpdb->last_error);
        }
        
        $project_id = $wpdb->insert_id;
        
        // Adicionar owner como membro admin com papel de Guia
        $this->add_member($project_id, $project_data['owner_id'], 'manager');
        
        // Atribuir responsabilidade de Guia ao criador
        if (class_exists('QL_Responsibility_System')) {
            $responsibility_system = QL_Responsibility_System::get_instance();
            $responsibility_system->assign_responsibility(
                'guia',
                'person',
                $project_data['owner_id'],
                'project',
                $project_id
            );
        }
        
        // Criar círculo inicial associado ao projeto
        if (class_exists('QL_Instances')) {
            $instances = QL_Instances::get_instance();
            $circle_id = $instances->create_instance(
                'circulo',
                'Círculo Inicial - ' . $data['name'],
                $project_data['owner_id'],
                [
                    'description' => 'Círculo inicial do projeto ' . $data['name'],
                    'project_id' => $project_id,
                    'status' => 'forming',
                    'metadata' => [
                        'is_initial_circle' => true,
                        'project_id' => $project_id,
                        'formation_stage' => 'initial'
                    ]
                ]
            );
            
            if (!is_wp_error($circle_id)) {
                // Adicionar o dono ao círculo inicial
                $instances->add_member_to_instance($circle_id, $project_data['owner_id'], 'founder');
            }
        }
        
        // Hook para extensibilidade
        do_action('ql_project_created', $project_id, $project_data);
        
        return $project_id;
    }
    
    /**
     * Obter projeto por ID
     */
    public function get_by_id($project_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $project_id
        ), ARRAY_A);
    }
    
    /**
     * Adicionar membro ao projeto
     */
    public function add_member($project_id, $user_id, $role = 'member') {
        global $wpdb;
        
        $member_data = [
            'project_id' => intval($project_id),
            'user_id' => intval($user_id),
            'role' => sanitize_text_field($role),
            'joined_at' => current_time('mysql')
        ];
        
        return $wpdb->insert($wpdb->prefix . 'ql_project_members', $member_data);
    }
    
    /**
     * Verificar se círculo de projeto está completo
     * Conforme modelo organizativo: mínimo 3 pessoas para ativação
     */
    public function check_circle_completion($instance_id, $user_id, $role) {
        if (!class_exists('QL_Instances')) {
            return;
        }
        
        $instances = QL_Instances::get_instance();
        $instance = $instances->get_instance_by_id($instance_id);
        
        if (!$instance || $instance->type !== 'circulo') {
            return;
        }
        
        // Verificar se é círculo de projeto
        $metadata = json_decode($instance->metadata ?: '{}', true);
        $project_id = $metadata['project_id'] ?? null;
        
        if (!$project_id) {
            return;
        }
        
        $this->evaluate_project_activation($project_id);
    }
    
    /**
     * Verificar se projeto deve ser desativado quando membro sai
     */
    public function check_circle_deactivation($instance_id, $user_id, $role) {
        if (!class_exists('QL_Instances')) {
            return;
        }
        
        $instances = QL_Instances::get_instance();
        $instance = $instances->get_instance_by_id($instance_id);
        
        if (!$instance || $instance->type !== 'circulo') {
            return;
        }
        
        // Verificar se é círculo de projeto
        $metadata = json_decode($instance->metadata ?: '{}', true);
        $project_id = $metadata['project_id'] ?? null;
        
        if (!$project_id) {
            return;
        }
        
        // Contar membros restantes
        $members = $instances->get_instance_members($instance_id);
        
        if (count($members) < 3) {
            $this->deactivate_project($project_id, 'insufficient_members');
        }
    }
    
    /**
     * Avaliar se projeto pode ser ativado
     */
    private function evaluate_project_activation($project_id) {
        global $wpdb;
        
        $project = $this->get_by_id($project_id);
        if (!$project || $project['status'] === 'active') {
            return;
        }
        
        if (!class_exists('QL_Instances')) {
            return;
        }
        
        $instances = QL_Instances::get_instance();
        
        // Buscar círculo principal do projeto
        $project_circles = $this->get_project_circles($project_id);
        if (empty($project_circles)) {
            return;
        }
        
        $main_circle = $project_circles[0]; // Primeiro círculo (inicial)
        $members = $instances->get_instance_members($main_circle->id);
        
        // Verificar requisitos de ativação
        $activation_ready = $this->check_activation_requirements($project_id, $main_circle->id, $members);
        
        if ($activation_ready) {
            $this->activate_project($project_id);
        } else {
            // Atualizar status para "forming" se tem pelo menos 2 pessoas
            if (count($members) >= 2 && $project['status'] === 'initial') {
                $this->update_project_status($project_id, 'forming');
            }
        }
    }
    
    /**
     * Verificar requisitos de ativação
     */
    private function check_activation_requirements($project_id, $circle_id, $members) {
        // Requisito mínimo: 3 pessoas
        if (count($members) < 3) {
            return false;
        }
        
        // Verificar se tem pessoa com responsabilidade de Guia
        if (class_exists('QL_Responsibility_System')) {
            $responsibility_system = QL_Responsibility_System::get_instance();
            
            $has_guide = false;
            foreach ($members as $member) {
                if ($responsibility_system->has_responsibility($member->user_id, 'guia', 'project', $project_id)) {
                    $has_guide = true;
                    break;
                }
            }
            
            if (!$has_guide) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Ativar projeto
     */
    public function activate_project($project_id) {
        global $wpdb;
        
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return false;
        }
        
        // Atualizar status do projeto
        $result = $this->update_project_status($project_id, 'active');
        
        if (!$result) {
            return false;
        }
        
        // Atualizar círculo para "complete"
        $project_circles = $this->get_project_circles($project_id);
        if (!empty($project_circles)) {
            $main_circle = $project_circles[0];
            
            if (class_exists('QL_Instances')) {
                $instances = QL_Instances::get_instance();
                $instances->update_instance_status($main_circle->id, 'complete');
            }
        }
        
        // Criar fundo na Gestão Coletiva (se integrado)
        if (class_exists('QL_GC_Integration')) {
            $gc_integration = QL_GC_Integration::get_instance();
            $gc_integration->create_project_fund($project_id);
        }
        
        // Publicar página do projeto no WordPress
        $this->publish_project_page($project_id);
        
        // Disparar hooks
        do_action('ql_project_activated', $project_id, $project);
        do_action('ql_circle_formed', $project_circles[0]->id, $project_id);
        
        error_log("QL Project: Projeto {$project_id} ativado - círculo completo formado");
        
        return true;
    }
    
    /**
     * Desativar projeto
     */
    public function deactivate_project($project_id, $reason = 'manual') {
        global $wpdb;
        
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return false;
        }
        
        // Atualizar status do projeto
        $result = $this->update_project_status($project_id, 'inactive');
        
        if (!$result) {
            return false;
        }
        
        // Pausar fundo na Gestão Coletiva
        if (class_exists('QL_GC_Integration')) {
            $gc_integration = QL_GC_Integration::get_instance();
            $gc_integration->pause_project_fund($project_id);
        }
        
        // Atualizar página do projeto para rascunho
        $this->unpublish_project_page($project_id);
        
        // Disparar hooks
        do_action('ql_project_deactivated', $project_id, $reason, $project);
        
        error_log("QL Project: Projeto {$project_id} desativado - motivo: {$reason}");
        
        return true;
    }
    
    /**
     * Atualizar status do projeto
     */
    private function update_project_status($project_id, $new_status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_name,
            [
                'status' => $new_status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $project_id],
            ['%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Obter círculos de um projeto
     */
    private function get_project_circles($project_id) {
        if (!class_exists('QL_Instances')) {
            return [];
        }
        
        global $wpdb;
        $instances_table = $wpdb->prefix . 'ql_instances';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $instances_table 
             WHERE type = 'circulo' 
             AND JSON_EXTRACT(metadata, '$.project_id') = %d 
             ORDER BY created_at ASC",
            $project_id
        ));
    }
    
    /**
     * Publicar página do projeto no WordPress
     */
    private function publish_project_page($project_id) {
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return false;
        }
        
        // Verificar se já existe página
        $existing_page = get_posts([
            'post_type' => 'page',
            'meta_query' => [
                [
                    'key' => 'ql_project_id',
                    'value' => $project_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);
        
        if (!empty($existing_page)) {
            // Atualizar página existente para publicada
            wp_update_post([
                'ID' => $existing_page[0]->ID,
                'post_status' => 'publish'
            ]);
            return $existing_page[0]->ID;
        }
        
        // Criar nova página
        $page_data = [
            'post_title' => $project['name'],
            'post_content' => $project['description'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => $project['owner_id'],
            'meta_input' => [
                'ql_project_id' => $project_id,
                'ql_project_type' => $project['project_type'],
                'ql_auto_generated' => true
            ]
        ];
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Atualizar projeto com ID da página
            global $wpdb;
            $wpdb->update(
                $this->table_name,
                ['wordpress_page_id' => $page_id],
                ['id' => $project_id],
                ['%d'],
                ['%d']
            );
            
            return $page_id;
        }
        
        return false;
    }
    
    /**
     * Despublicar página do projeto
     */
    private function unpublish_project_page($project_id) {
        $project = $this->get_by_id($project_id);
        if (!$project || empty($project['wordpress_page_id'])) {
            return false;
        }
        
        wp_update_post([
            'ID' => $project['wordpress_page_id'],
            'post_status' => 'draft'
        ]);
        
        return true;
    }
    
    /**
     * Hook: Quando círculo é formado
     */
    public function on_circle_formed($circle_id, $project_id) {
        // Círculo já foi ativado, não precisa fazer nada adicional
        error_log("QL Project: Círculo {$circle_id} do projeto {$project_id} foi formado");
    }
    
    /**
     * Atualizar projeto
     */
    public function update($project_id, $data) {
        global $wpdb;
        
        // Verificar se projeto existe
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return new WP_Error('not_found', 'Projeto não encontrado');
        }
        
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
        
        if (isset($data['visibility'])) {
            $update_data['visibility'] = sanitize_text_field($data['visibility']);
        }
        
        if (isset($data['start_date'])) {
            $update_data['start_date'] = !empty($data['start_date']) ? sanitize_text_field($data['start_date']) : null;
        }
        
        if (isset($data['end_date'])) {
            $update_data['end_date'] = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : null;
        }
        
        if (isset($data['priority'])) {
            $update_data['priority'] = sanitize_text_field($data['priority']);
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $project_id],
            null,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar projeto: ' . $wpdb->last_error);
        }
        
        // Hook para extensibilidade
        do_action('ql_project_updated', $project_id, $update_data);
        
        return true;
    }
    
    /**
     * Excluir projeto
     */
    public function delete($project_id) {
        global $wpdb;
        
        // Verificar se projeto existe
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return new WP_Error('not_found', 'Projeto não encontrado');
        }
        
        // Excluir dados relacionados
        if (class_exists('QL_Board')) {
            $board_model = QL_Board::get_instance();
            $boards = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ql_boards WHERE project_id = %d",
                $project_id
            ));
            
            foreach ($boards as $board_id) {
                $board_model->delete($board_id);
            }
        }
        
        // Excluir membros do projeto
        $wpdb->delete(
            $wpdb->prefix . 'ql_project_members',
            ['project_id' => $project_id],
            ['%d']
        );
        
        // Excluir projeto
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $project_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao excluir projeto: ' . $wpdb->last_error);
        }
        
        // Hook para extensibilidade
        do_action('ql_project_deleted', $project_id);
        
        return true;
    }
    
    /**
     * Verificar se usuário tem acesso ao projeto
     */
    public function user_has_access($project_id, $user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // Admin sempre tem acesso
        if (current_user_can('manage_options')) {
            return true;
        }
        
        global $wpdb;
        
        // Verificar se é owner ou membro
        $has_access = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} p 
             LEFT JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id 
             WHERE p.id = %d AND (p.owner_id = %d OR pm.user_id = %d)",
            $project_id, $user_id, $user_id
        ));
        
        return $has_access > 0;
    }
    
    /**
     * Obter membros do projeto
     */
    public function get_project_members($project_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT pm.*, u.display_name, u.user_email 
             FROM {$wpdb->prefix}ql_project_members pm 
             JOIN {$wpdb->users} u ON pm.user_id = u.ID 
             WHERE pm.project_id = %d 
             ORDER BY pm.joined_at ASC",
            $project_id
        ), ARRAY_A);
    }
    
    /**
     * Buscar projetos
     */
    public function search($search_term, $filters = []) {
        global $wpdb;
        
        $where_conditions = ["(p.name LIKE %s OR p.description LIKE %s)"];
        $params = ["%{$search_term}%", "%{$search_term}%"];
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = %s";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['owner_id'])) {
            $where_conditions[] = "p.owner_id = %d";
            $params[] = $filters['owner_id'];
        }
        
        if (!empty($filters['visibility'])) {
            $where_conditions[] = "p.visibility = %s";
            $params[] = $filters['visibility'];
        }
        
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
        
        $sql = "SELECT p.*, u.display_name as owner_name 
                FROM {$this->table_name} p 
                LEFT JOIN {$wpdb->users} u ON p.owner_id = u.ID 
                {$where_clause} 
                ORDER BY p.updated_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }
    
    /**
     * Duplicar projeto
     */
    public function duplicate($project_id, $new_name = null) {
        $original_project = $this->get_by_id($project_id);
        
        if (!$original_project) {
            return new WP_Error('not_found', 'Projeto original não encontrado');
        }
        
        // Criar novo projeto
        $new_project_data = [
            'name' => $new_name ?: $original_project['name'] . ' (Cópia)',
            'description' => $original_project['description'],
            'status' => 'active',
            'visibility' => $original_project['visibility'],
            'priority' => $original_project['priority']
        ];
        
        $new_project_id = $this->create($new_project_data);
        
        if (is_wp_error($new_project_id)) {
            return $new_project_id;
        }
        
        // Duplicar boards (se existir classe QL_Board)
        if (class_exists('QL_Board')) {
            global $wpdb;
            $boards = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ql_boards WHERE project_id = %d",
                $project_id
            ), ARRAY_A);
            
            $board_model = QL_Board::get_instance();
            foreach ($boards as $board) {
                $new_board_data = [
                    'name' => $board['name'],
                    'description' => $board['description'],
                    'project_id' => $new_project_id
                ];
                $board_model->create($new_board_data);
            }
        }
        
        return $new_project_id;
    }
    
    /**
     * Marcar projeto como projeto coletivo
     */
    public function mark_as_coletivo($project_id) {
        global $wpdb;
        
        // Verificar se projeto existe
        $project = $this->get_by_id($project_id);
        if (!$project) {
            return new WP_Error('not_found', 'Projeto não encontrado');
        }
        
        // Desmarcar outros projetos como coletivo (só pode haver um)
        $wpdb->update(
            $this->table_name,
            ['is_coletivo' => 0],
            [],
            ['%d'],
            []
        );
        
        // Marcar este projeto como coletivo
        $result = $wpdb->update(
            $this->table_name,
            ['is_coletivo' => 1, 'updated_at' => current_time('mysql')],
            ['id' => $project_id],
            ['%d', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao marcar projeto como coletivo: ' . $wpdb->last_error);
        }
        
        // Hook para notificar outros plugins
        do_action('ql_project_marked_as_coletivo', $project_id);
        
        error_log("Quilombo Laboratório: Projeto {$project_id} marcado como coletivo");
        
        return true;
    }
    
    /**
     * Obter projeto coletivo atual
     */
    public function get_coletivo() {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE is_coletivo = %d LIMIT 1",
            1
        ));
    }
}