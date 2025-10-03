<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para manipulação de requisições AJAX do Quilombo Laboratório
 */
class QL_Ajax {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Actions para usuários logados
        add_action('wp_ajax_ql_create_task', [$this, 'create_task']);
        add_action('wp_ajax_ql_update_task', [$this, 'update_task']);
        add_action('wp_ajax_ql_delete_task', [$this, 'delete_task']);
        add_action('wp_ajax_ql_move_task', [$this, 'move_task']);
        add_action('wp_ajax_ql_get_task', [$this, 'get_task']);
        add_action('wp_ajax_ql_get_quadro_data', [$this, 'get_quadro_data']);
        add_action('wp_ajax_ql_create_project', [$this, 'create_project']);
        add_action('wp_ajax_ql_search_users', [$this, 'search_users']);
        add_action('wp_ajax_ql_sync_trilhas_moodle', [$this, 'sync_trilhas_moodle']);
        add_action('wp_ajax_ql_clear_example_tasks', [$this, 'clear_example_tasks']);
        add_action('wp_ajax_ql_update_task_status', [$this, 'update_task_status']);
        
        // Novos handlers para modais avançados
        add_action('wp_ajax_ql_get_task_data', [$this, 'get_task_data']);
        add_action('wp_ajax_ql_create_subtask', [$this, 'create_subtask']);
        add_action('wp_ajax_ql_toggle_subtask', [$this, 'toggle_subtask']);
        add_action('wp_ajax_ql_delete_subtask', [$this, 'delete_subtask']);
        add_action('wp_ajax_ql_add_comment', [$this, 'add_comment']);
        add_action('wp_ajax_ql_upload_attachment', [$this, 'upload_attachment']);
        add_action('wp_ajax_ql_delete_attachment', [$this, 'delete_attachment']);
        add_action('wp_ajax_ql_get_users', [$this, 'get_users']);
        add_action('wp_ajax_ql_get_task_activities', [$this, 'get_task_activities']);
        add_action('wp_ajax_ql_get_modal_templates', [$this, 'get_modal_templates']);
        
        // Actions para usuários não logados (se necessário)
        add_action('wp_ajax_nopriv_ql_get_public_quadro', [$this, 'get_public_quadro']);
    }
    
    /**
     * Verificar nonce e permissões
     */
    private function verify_request($action, $capability = 'read') {
        // Verificar se é um nonce padrão do WordPress
        $nonce = $_POST['nonce'] ?? $_REQUEST['nonce'] ?? '';
        
        // Tentar diferentes formatos de nonce
        $nonce_actions = [
            'ql_' . $action . '_nonce',
            'ql_admin_nonce', 
            'ql_nonce',
            'wp_rest'
        ];
        
        $nonce_verified = false;
        foreach ($nonce_actions as $nonce_action) {
            if (wp_verify_nonce($nonce, $nonce_action)) {
                $nonce_verified = true;
                break;
            }
        }
        
        if (!$nonce_verified) {
            // Log para debug mas não quebrar em desenvolvimento
            error_log("QL Debug: Nonce verification failed. Nonce: {$nonce}, Action: {$action}");
            
            // Em produção, bloquear. Em desenvolvimento, permitir se usuário é admin
            if (defined('WP_ENV') && WP_ENV === 'production') {
                wp_die('Verificação de segurança falhou. Nonce: ' . $nonce);
            } elseif (!current_user_can('administrator')) {
                wp_die('Verificação de segurança falhou. Nonce: ' . $nonce);
            }
            // Se chegou aqui, é desenvolvimento e usuário é admin - permitir com warning
            error_log("QL Warning: Allowing request in development mode for admin user");
        }
        
        if (!current_user_can($capability)) {
            wp_die('Permissão insuficiente');
        }
    }
    
    /**
     * Enviar resposta JSON
     */
    private function send_response($success, $data = [], $message = '') {
        wp_send_json([
            'success' => $success,
            'data' => $data,
            'message' => $message
        ]);
    }
    
    /**
     * Criar tarefa
     */
    public function create_task() {
        $this->verify_request('admin', 'edit_posts');
        
        try {
            $column_id = intval($_POST['column_id']);
            
            // Obter board_id e project_id através da coluna
            global $wpdb;
            $column_data = $wpdb->get_row($wpdb->prepare(
                "SELECT c.board_id, b.project_id 
                 FROM {$wpdb->prefix}ql_columns c 
                 LEFT JOIN {$wpdb->prefix}ql_boards b ON c.board_id = b.id 
                 WHERE c.id = %d",
                $column_id
            ));
            
            if (!$column_data) {
                throw new Exception('Coluna não encontrada');
            }
            
            $task_data = [
                'title' => sanitize_text_field($_POST['title']),
                'description' => wp_kses_post($_POST['description'] ?? ''),
                'column_id' => $column_id,
                'board_id' => $column_data->board_id,
                'project_id' => $column_data->project_id,
                'assigned_user_id' => !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null,
                'priority' => sanitize_text_field($_POST['priority'] ?? 'normal'),
                'status' => sanitize_text_field($_POST['status'] ?? 'open'),
                'due_date' => !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null,
                'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
                'time_estimated' => !empty($_POST['time_estimated']) ? floatval($_POST['time_estimated']) : 0,
                'score' => intval($_POST['score'] ?? 0),
                'color_id' => sanitize_text_field($_POST['color_id'] ?? 'blue'),
                'tags' => sanitize_text_field($_POST['tags'] ?? ''),
                'reference' => sanitize_text_field($_POST['reference'] ?? '')
            ];
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task_id = $task_model->create($task_data);
            
            if (is_wp_error($task_id)) {
                throw new Exception($task_id->get_error_message());
            }
            
            $task = $task_model->get_by_id($task_id);
            
            $this->send_response(true, [
                'task' => $task,
                'task_id' => $task_id
            ], 'Tarefa criada com sucesso');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao criar tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar tarefa
     */
    public function update_task() {
        $this->verify_request('admin', 'edit_posts');
        
        try {
            $task_id = intval($_POST['task_id']);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            $task_data = [];
            
            // Campos básicos
            if (isset($_POST['title'])) {
                $task_data['title'] = sanitize_text_field($_POST['title']);
            }
            
            if (isset($_POST['description'])) {
                $task_data['description'] = wp_kses_post($_POST['description']);
            }
            
            if (isset($_POST['reference'])) {
                $task_data['reference'] = sanitize_text_field($_POST['reference']);
            }
            
            // Atribuição e status
            if (isset($_POST['assignee_id'])) {
                $task_data['assigned_user_id'] = !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null;
            }
            
            if (isset($_POST['status'])) {
                $task_data['status'] = sanitize_text_field($_POST['status']);
            }
            
            if (isset($_POST['priority'])) {
                $task_data['priority'] = sanitize_text_field($_POST['priority']);
            }
            
            // Tempo e pontuação
            if (isset($_POST['time_estimated'])) {
                $task_data['time_estimated'] = !empty($_POST['time_estimated']) ? floatval($_POST['time_estimated']) : 0;
            }
            
            if (isset($_POST['score'])) {
                $task_data['score'] = intval($_POST['score']);
            }
            
            // Datas
            if (isset($_POST['start_date'])) {
                $task_data['start_date'] = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
            }
            
            if (isset($_POST['due_date'])) {
                $task_data['due_date'] = !empty($_POST['due_date']) ? sanitize_text_field($_POST['due_date']) : null;
            }
            
            // Visual e tags
            if (isset($_POST['color_id'])) {
                $task_data['color_id'] = sanitize_text_field($_POST['color_id']);
            }
            
            if (isset($_POST['tags'])) {
                $task_data['tags'] = sanitize_text_field($_POST['tags']);
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $result = $task_model->update($task_id, $task_data);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $task = $task_model->get_by_id($task_id);
            
            $this->send_response(true, [
                'task' => $task
            ], 'Tarefa atualizada com sucesso');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao atualizar tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Excluir tarefa
     */
    public function delete_task() {
        $this->verify_request('admin', 'delete_posts');
        
        try {
            $task_id = intval($_POST['task_id']);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $result = $task_model->delete($task_id);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $this->send_response(true, [], 'Tarefa excluída com sucesso');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao excluir tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Mover tarefa
     */
    public function move_task() {
        $this->verify_request('admin', 'edit_posts');
        
        try {
            $task_id = intval($_POST['task_id']);
            $new_column_id = intval($_POST['new_column_id']);
            $new_position = isset($_POST['new_position']) ? intval($_POST['new_position']) : null;
            
            if (!$task_id || !$new_column_id) {
                throw new Exception('ID da tarefa e nova coluna são obrigatórios');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $result = $task_model->move_to_column($task_id, $new_column_id, $new_position);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $task = $task_model->get_by_id($task_id);
            
            $this->send_response(true, [
                'task' => $task
            ], 'Tarefa movida com sucesso');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao mover tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter dados da tarefa
     */
    public function get_task() {
        $this->verify_request('admin', 'read');
        
        try {
            $task_id = intval($_POST['task_id']);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task = $task_model->get_by_id($task_id);
            
            if (!$task) {
                throw new Exception('Tarefa não encontrada');
            }
            
            $this->send_response(true, [
                'task' => $task
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao obter tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter dados do quadro
     */
    public function get_quadro_data() {
        $this->verify_request('admin', 'read');
        
        try {
            $board_id = intval($_POST['board_id']);
            
            if (!$board_id) {
                throw new Exception('ID do quadro é obrigatório');
            }
            
            if (!class_exists('QL_Board')) {
                throw new Exception('Classe QL_Board não encontrada');
            }
            
            $board_model = QL_Board::get_instance();
            $board = $board_model->get_by_id($board_id);
            
            if (!$board) {
                throw new Exception('Quadro não encontrado');
            }
            
            $this->send_response(true, [
                'board' => $board
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao obter quadro: ' . $e->getMessage());
        }
    }
    
    /**
     * Criar projeto
     */
    public function create_project() {
        $this->verify_request('admin', 'manage_options');
        
        try {
            $project_data = [
                'name' => sanitize_text_field($_POST['name']),
                'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                'status' => sanitize_text_field($_POST['status'] ?? 'active'),
                'visibility' => sanitize_text_field($_POST['visibility'] ?? 'private'),
                'priority' => sanitize_text_field($_POST['priority'] ?? 'normal'),
                'start_date' => !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null,
                'end_date' => !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null
            ];
            
            if (!class_exists('QL_Project')) {
                throw new Exception('Classe QL_Project não encontrada');
            }
            
            $project_model = QL_Project::get_instance();
            $project_id = $project_model->create($project_data);
            
            if (is_wp_error($project_id)) {
                throw new Exception($project_id->get_error_message());
            }
            
            $project = $project_model->get_by_id($project_id);
            
            $this->send_response(true, [
                'project' => $project,
                'project_id' => $project_id
            ], 'Projeto criado com sucesso');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao criar projeto: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar usuários
     */
    public function search_users() {
        $this->verify_request('admin', 'read');
        
        try {
            $search_term = sanitize_text_field($_POST['search'] ?? '');
            
            if (strlen($search_term) < 2) {
                throw new Exception('Termo de busca deve ter pelo menos 2 caracteres');
            }
            
            $users = get_users([
                'search' => "*{$search_term}*",
                'search_columns' => ['display_name', 'user_email', 'user_login'],
                'number' => 10,
                'fields' => ['ID', 'display_name', 'user_email']
            ]);
            
            $formatted_users = [];
            foreach ($users as $user) {
                $formatted_users[] = [
                    'id' => $user->ID,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                    'avatar' => get_avatar_url($user->ID, ['size' => 32])
                ];
            }
            
            $this->send_response(true, [
                'users' => $formatted_users
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao buscar usuários: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter quadro público (para usuários não logados)
     */
    public function get_public_quadro() {
        try {
            $board_id = intval($_GET['board_id']);
            
            if (!$board_id) {
                throw new Exception('ID do quadro é obrigatório');
            }
            
            if (!class_exists('QL_Board')) {
                throw new Exception('Classe QL_Board não encontrada');
            }
            
            $board_model = QL_Board::get_instance();
            $board = $board_model->get_by_id($board_id);
            
            if (!$board) {
                throw new Exception('Quadro não encontrado');
            }
            
            // Verificar se quadro é público (implementar verificação de visibilidade)
            // Por enquanto, retornar erro
            throw new Exception('Acesso negado');
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar trilhas do Moodle
     */
    public function sync_trilhas_moodle() {
        try {
            // Verificar permissões
            if (!current_user_can('manage_options')) {
                throw new Exception('Permissão negada');
            }
            
            // Verificar nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ql_ajax_nonce')) {
                throw new Exception('Nonce inválido');
            }
            
            // Sincronizar com Moodle
            if (!class_exists('QL_Moodle_Integration')) {
                throw new Exception('Integração com Moodle não disponível');
            }
            
            $integration = QL_Moodle_Integration::get_instance();
            $result = $integration->sync_courses_from_moodle();
            
            if (!$result['success']) {
                throw new Exception('Erro na sincronização: ' . implode(', ', $result['errors']));
            }
            
            // Atualizar timestamp da última sincronização
            update_option('quilombo_laboratorio_ultima_sync', time());
            
            $this->send_response(true, [
                'message' => 'Trilhas sincronizadas com sucesso!',
                'stats' => $result
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro: ' . $e->getMessage());
        }
    }
    
    /**
     * Limpar tarefas de exemplo do quadro
     */
    public function clear_example_tasks() {
        $this->verify_request('admin', 'manage_options');
        
        try {
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            global $wpdb;
            $tasks_table = $wpdb->prefix . 'ql_tasks';
            
            // Verificar se tabela existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tasks_table}'");
            if (!$table_exists) {
                throw new Exception('Tabela de tarefas não existe');
            }
            
            // Limpar tarefas de exemplo (títulos que indicam serem exemplos)
            $example_patterns = [
                '%exemplo%',
                '%test%',
                '%demo%',
                '%sample%',
                '%Criar página inicial%',
                '%Configurar SEO%',
                '%Implementar sistema%',
                '%Testar responsividade%',
                '%Configurar servidor%'
            ];
            
            $deleted_count = 0;
            foreach ($example_patterns as $pattern) {
                $count = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$tasks_table} WHERE title LIKE %s",
                    $pattern
                ));
                $deleted_count += $count;
            }
            
            $this->send_response(true, [
                'deleted_count' => $deleted_count,
                'message' => sprintf('%d tarefas de exemplo removidas com sucesso!', $deleted_count)
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao limpar tarefas: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualizar status da tarefa (usado no modal e drag & drop)
     */
    public function update_task_status() {
        $this->verify_request('admin', 'edit_posts');
        
        try {
            $task_id = intval($_POST['task_id']);
            $new_status = sanitize_text_field($_POST['status']);
            $column_id = !empty($_POST['column_id']) ? intval($_POST['column_id']) : null;
            
            if (!$task_id || !$new_status) {
                throw new Exception('ID da tarefa e status são obrigatórios');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            
            // Preparar dados de atualização
            $update_data = ['status' => $new_status];
            
            // Se for uma mudança de coluna via drag & drop
            if ($column_id) {
                $result = $task_model->move_to_column($task_id, $column_id);
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            }
            
            // Atualizar status
            $result = $task_model->update($task_id, $update_data);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Obter tarefa atualizada
            $updated_task = $task_model->get_by_id($task_id);
            
            $this->send_response(true, [
                'task' => $updated_task,
                'message' => 'Status da tarefa atualizado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao atualizar status: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter dados completos de uma tarefa
     */
    public function get_task_data() {
        $this->verify_request('admin', 'read');
        
        try {
            $task_id = intval($_POST['task_id']);
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task = $task_model->get_by_id($task_id);
            
            if (!$task) {
                throw new Exception('Tarefa não encontrada');
            }
            
            // Verificar permissões (usuário pode ver a tarefa?)
            if (!current_user_can('edit_posts') && $task['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para visualizar esta tarefa');
            }
            
            $this->send_response(true, $task);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao carregar tarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Criar subtarefa
     */
    public function create_subtask() {
        $this->verify_request('create_subtask', 'edit_posts');
        
        try {
            $subtask_data = $_POST['subtask_data'] ?? [];
            
            if (empty($subtask_data['title'])) {
                throw new Exception('Título da subtarefa é obrigatório');
            }
            
            if (empty($subtask_data['parent_task_id'])) {
                throw new Exception('ID da tarefa pai é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            
            // Verificar se a tarefa pai existe
            $parent_task = $task_model->get_by_id($subtask_data['parent_task_id']);
            if (!$parent_task) {
                throw new Exception('Tarefa pai não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('edit_posts') && $parent_task['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para criar subtarefas nesta tarefa');
            }
            
            $result = $task_model->create_subtask($subtask_data['parent_task_id'], $subtask_data);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Obter subtarefa criada
            $subtask = $task_model->get_by_id($result);
            
            $this->send_response(true, [
                'subtask' => $subtask,
                'subtask_id' => $result,
                'message' => 'Subtarefa criada com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao criar subtarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Alternar status de subtarefa (completa/incompleta)
     */
    public function toggle_subtask() {
        $this->verify_request('toggle_subtask', 'edit_posts');
        
        try {
            $subtask_id = intval($_POST['subtask_id']);
            $completed = (bool) $_POST['completed'];
            
            if (!$subtask_id) {
                throw new Exception('ID da subtarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $subtask = $task_model->get_by_id($subtask_id);
            
            if (!$subtask) {
                throw new Exception('Subtarefa não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('edit_posts') && $subtask['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para alterar esta subtarefa');
            }
            
            $new_status = $completed ? 'completed' : 'open';
            $result = $task_model->update($subtask_id, ['status' => $new_status]);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $this->send_response(true, [
                'subtask_id' => $subtask_id,
                'status' => $new_status,
                'completed' => $completed,
                'message' => 'Status da subtarefa atualizado!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao alterar subtarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Excluir subtarefa
     */
    public function delete_subtask() {
        $this->verify_request('delete_subtask', 'delete_posts');
        
        try {
            $subtask_id = intval($_POST['subtask_id']);
            
            if (!$subtask_id) {
                throw new Exception('ID da subtarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $subtask = $task_model->get_by_id($subtask_id);
            
            if (!$subtask) {
                throw new Exception('Subtarefa não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('delete_posts') && $subtask['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para excluir esta subtarefa');
            }
            
            $result = $task_model->delete($subtask_id);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            if (!$result) {
                throw new Exception('Erro ao excluir subtarefa');
            }
            
            $this->send_response(true, [
                'subtask_id' => $subtask_id,
                'message' => 'Subtarefa excluída com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao excluir subtarefa: ' . $e->getMessage());
        }
    }
    
    /**
     * Adicionar comentário à tarefa
     */
    public function add_comment() {
        $this->verify_request('add_comment', 'read');
        
        try {
            $task_id = intval($_POST['task_id']);
            $content = sanitize_textarea_field($_POST['content']);
            $is_private = (bool) ($_POST['is_private'] ?? false);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (empty($content)) {
                throw new Exception('Conteúdo do comentário é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task = $task_model->get_by_id($task_id);
            
            if (!$task) {
                throw new Exception('Tarefa não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('read') && $task['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para comentar nesta tarefa');
            }
            
            $result = $task_model->add_comment($task_id, $content, $is_private);
            
            if (!$result) {
                throw new Exception('Erro ao salvar comentário');
            }
            
            $this->send_response(true, [
                'comment_id' => $result,
                'message' => 'Comentário adicionado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao adicionar comentário: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload de anexo para tarefa
     */
    public function upload_attachment() {
        $this->verify_request('upload_attachment', 'upload_files');
        
        try {
            $task_id = intval($_POST['task_id']);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (empty($_FILES['file'])) {
                throw new Exception('Arquivo é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task = $task_model->get_by_id($task_id);
            
            if (!$task) {
                throw new Exception('Tarefa não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('upload_files') && $task['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para anexar arquivos nesta tarefa');
            }
            
            // Verificar tipo de arquivo permitido
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'];
            $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_types)) {
                throw new Exception('Tipo de arquivo não permitido. Permitidos: ' . implode(', ', $allowed_types));
            }
            
            // Upload do arquivo
            $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);
            
            if (isset($upload['error'])) {
                throw new Exception('Erro no upload: ' . $upload['error']);
            }
            
            global $wpdb;
            
            // Salvar anexo na base de dados
            $attachment_data = [
                'task_id' => $task_id,
                'user_id' => get_current_user_id(),
                'filename' => sanitize_file_name($_FILES['file']['name']),
                'filepath' => $upload['url'],
                'filesize' => $_FILES['file']['size'],
                'created_at' => current_time('mysql')
            ];
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ql_attachments',
                $attachment_data
            );
            
            if (!$result) {
                throw new Exception('Erro ao salvar anexo no banco de dados');
            }
            
            $attachment_id = $wpdb->insert_id;
            
            // Registrar atividade
            $task_model->add_activity($task_id, 'file_upload', "Anexou o arquivo: {$attachment_data['filename']}");
            
            $this->send_response(true, [
                'attachment_id' => $attachment_id,
                'attachment' => $attachment_data,
                'message' => 'Arquivo anexado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao anexar arquivo: ' . $e->getMessage());
        }
    }
    
    /**
     * Excluir anexo
     */
    public function delete_attachment() {
        $this->verify_request('delete_attachment', 'delete_posts');
        
        try {
            $attachment_id = intval($_POST['attachment_id']);
            
            if (!$attachment_id) {
                throw new Exception('ID do anexo é obrigatório');
            }
            
            global $wpdb;
            
            // Obter anexo
            $attachment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ql_attachments WHERE id = %d",
                $attachment_id
            ), ARRAY_A);
            
            if (!$attachment) {
                throw new Exception('Anexo não encontrado');
            }
            
            // Verificar permissões
            if (!current_user_can('delete_posts') && $attachment['user_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para excluir este anexo');
            }
            
            // Excluir arquivo físico
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $attachment['filepath']);
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
            }
            
            // Excluir do banco
            $result = $wpdb->delete(
                $wpdb->prefix . 'ql_attachments',
                ['id' => $attachment_id],
                ['%d']
            );
            
            if (!$result) {
                throw new Exception('Erro ao excluir anexo do banco de dados');
            }
            
            // Registrar atividade
            if (class_exists('QL_Task')) {
                $task_model = QL_Task::get_instance();
                $task_model->add_activity($attachment['task_id'], 'file_delete', "Excluiu o arquivo: {$attachment['filename']}");
            }
            
            $this->send_response(true, [
                'attachment_id' => $attachment_id,
                'message' => 'Anexo excluído com sucesso!'
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao excluir anexo: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter lista de usuários para seleção
     */
    public function get_users() {
        $this->verify_request('get_users', 'read');
        
        try {
            $search = sanitize_text_field($_POST['search'] ?? '');
            
            $args = [
                'number' => 50,
                'orderby' => 'display_name',
                'fields' => ['ID', 'display_name', 'user_email']
            ];
            
            if (!empty($search)) {
                $args['search'] = "*{$search}*";
                $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
            }
            
            $users = get_users($args);
            
            $formatted_users = [];
            foreach ($users as $user) {
                $formatted_users[] = [
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email
                ];
            }
            
            $this->send_response(true, $formatted_users);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao carregar usuários: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter atividades de uma tarefa
     */
    public function get_task_activities() {
        $this->verify_request('get_task_activities', 'read');
        
        try {
            $task_id = intval($_POST['task_id']);
            $limit = intval($_POST['limit'] ?? 20);
            
            if (!$task_id) {
                throw new Exception('ID da tarefa é obrigatório');
            }
            
            if (!class_exists('QL_Task')) {
                throw new Exception('Classe QL_Task não encontrada');
            }
            
            $task_model = QL_Task::get_instance();
            $task = $task_model->get_by_id($task_id);
            
            if (!$task) {
                throw new Exception('Tarefa não encontrada');
            }
            
            // Verificar permissões
            if (!current_user_can('read') && $task['creator_id'] != get_current_user_id()) {
                throw new Exception('Sem permissão para ver atividades desta tarefa');
            }
            
            $activities = $task_model->get_task_activities($task_id, $limit);
            
            $this->send_response(true, $activities);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao carregar atividades: ' . $e->getMessage());
        }
    }

    /**
     * Obter templates de modais
     */
    public function get_modal_templates() {
        $this->verify_request('admin', 'read');
        
        try {
            // Capturar output do template
            ob_start();
            include_once QL_PLUGIN_PATH . 'templates/task-modals.php';
            $html = ob_get_clean();
            
            $this->send_response(true, [
                'html' => $html
            ]);
            
        } catch (Exception $e) {
            $this->send_response(false, [], 'Erro ao carregar templates: ' . $e->getMessage());
        }
    }
}