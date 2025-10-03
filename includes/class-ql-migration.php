<?php
/**
 * Classe para migração de dados do Kanboard para o Quilombo Laboratório
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Migration {
    
    private static $instance = null;
    private $kanboard_db_path;
    private $kanboard_pdo;
    private $migration_log = [];
    
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
        $this->kanboard_db_path = QL_Config::get_kanboard_db_path();
        
        // Hooks para interface administrativa
        add_action('wp_ajax_ql_start_migration', [$this, 'ajax_start_migration']);
        add_action('wp_ajax_ql_check_migration_status', [$this, 'ajax_check_migration_status']);
    }
    
    /**
     * Verificar se Kanboard está acessível
     */
    public function check_kanboard_access() {
        if (!file_exists($this->kanboard_db_path)) {
            return new WP_Error('db_not_found', 'Banco de dados do Kanboard não encontrado em: ' . $this->kanboard_db_path);
        }
        
        if (!is_readable($this->kanboard_db_path)) {
            return new WP_Error('db_not_readable', 'Não foi possível ler o banco de dados do Kanboard');
        }
        
        try {
            $this->connect_kanboard_db();
            return true;
        } catch (Exception $e) {
            return new WP_Error('db_connection_error', 'Erro ao conectar com banco Kanboard: ' . $e->getMessage());
        }
    }
    
    /**
     * Conectar com banco do Kanboard
     */
    private function connect_kanboard_db() {
        if ($this->kanboard_pdo) {
            return $this->kanboard_pdo;
        }
        
        $this->kanboard_pdo = new PDO('sqlite:' . $this->kanboard_db_path);
        $this->kanboard_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $this->kanboard_pdo;
    }
    
    /**
     * Obter estatísticas de dados do Kanboard
     */
    public function get_kanboard_stats() {
        $access_check = $this->check_kanboard_access();
        if (is_wp_error($access_check)) {
            return $access_check;
        }
        
        try {
            $pdo = $this->connect_kanboard_db();
            
            $stats = [];
            
            // Projetos
            $stmt = $pdo->query("SELECT COUNT(*) FROM projects WHERE is_active = 1");
            $stats['projects'] = $stmt->fetchColumn();
            
            // Tarefas
            $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
            $stats['tasks'] = $stmt->fetchColumn();
            
            // Usuários
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
            $stats['users'] = $stmt->fetchColumn();
            
            // Comentários
            $stmt = $pdo->query("SELECT COUNT(*) FROM comments");
            $stats['comments'] = $stmt->fetchColumn();
            
            // Anexos
            $stmt = $pdo->query("SELECT COUNT(*) FROM task_has_files");
            $stats['attachments'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            return new WP_Error('stats_error', 'Erro ao obter estatísticas: ' . $e->getMessage());
        }
    }
    
    /**
     * Executar migração completa
     */
    public function run_migration($options = []) {
        $access_check = $this->check_kanboard_access();
        if (is_wp_error($access_check)) {
            return $access_check;
        }
        
        // Opções padrão
        $default_options = [
            'migrate_projects' => true,
            'migrate_tasks' => true,
            'migrate_users' => false, // WordPress já tem usuários
            'migrate_comments' => true,
            'migrate_attachments' => false, // Requer tratamento especial de arquivos
            'create_default_gc_project' => true,
            'dry_run' => false
        ];
        
        $options = wp_parse_args($options, $default_options);
        
        $this->migration_log = [];
        $this->log('Iniciando migração do Kanboard para Quilombo Laboratório');
        $this->log('Opções: ' . json_encode($options));
        
        try {
            global $wpdb;
            
            // Iniciar transação
            if (!$options['dry_run']) {
                $wpdb->query('START TRANSACTION');
            }
            
            $migration_results = [
                'projects' => 0,
                'boards' => 0,
                'columns' => 0,
                'tasks' => 0,
                'comments' => 0,
                'errors' => []
            ];
            
            // 1. Migrar projetos
            if ($options['migrate_projects']) {
                $project_results = $this->migrate_projects($options);
                if (is_wp_error($project_results)) {
                    throw new Exception($project_results->get_error_message());
                }
                $migration_results['projects'] = $project_results['projects'];
                $migration_results['boards'] = $project_results['boards'];
                $migration_results['columns'] = $project_results['columns'];
            }
            
            // 2. Migrar tarefas
            if ($options['migrate_tasks']) {
                $task_results = $this->migrate_tasks($options);
                if (is_wp_error($task_results)) {
                    throw new Exception($task_results->get_error_message());
                }
                $migration_results['tasks'] = $task_results['tasks'];
            }
            
            // 3. Migrar comentários
            if ($options['migrate_comments']) {
                $comment_results = $this->migrate_comments($options);
                if (is_wp_error($comment_results)) {
                    $this->log('Erro ao migrar comentários: ' . $comment_results->get_error_message());
                    $migration_results['errors'][] = 'Comentários: ' . $comment_results->get_error_message();
                } else {
                    $migration_results['comments'] = $comment_results['comments'];
                }
            }
            
            // Finalizar transação
            if (!$options['dry_run']) {
                $wpdb->query('COMMIT');
                $this->log('Migração concluída com sucesso!');
            } else {
                $wpdb->query('ROLLBACK');
                $this->log('Dry run concluído - nenhuma alteração foi feita no banco');
            }
            
            $migration_results['log'] = $this->migration_log;
            
            return $migration_results;
            
        } catch (Exception $e) {
            if (!$options['dry_run']) {
                $wpdb->query('ROLLBACK');
            }
            
            $this->log('Erro na migração: ' . $e->getMessage());
            return new WP_Error('migration_error', 'Erro na migração: ' . $e->getMessage(), [
                'log' => $this->migration_log
            ]);
        }
    }
    
    /**
     * Migrar projetos do Kanboard
     */
    private function migrate_projects($options) {
        $this->log('Iniciando migração de projetos...');
        
        $pdo = $this->connect_kanboard_db();
        
        // Buscar projetos ativos do Kanboard
        $stmt = $pdo->prepare("
            SELECT p.*, u.username as owner_username 
            FROM projects p 
            LEFT JOIN users u ON p.owner_id = u.id 
            WHERE p.is_active = 1
        ");
        $stmt->execute();
        $kanboard_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated_projects = 0;
        $migrated_boards = 0;
        $migrated_columns = 0;
        
        foreach ($kanboard_projects as $kb_project) {
            $this->log("Migrando projeto: {$kb_project['name']} (ID: {$kb_project['id']})");
            
            // 1. Criar projeto no Gestão Coletiva (se habilitado)
            $gc_projeto_id = null;
            if ($options['create_default_gc_project'] && class_exists('GC_Projeto')) {
                $gc_projeto_id = $this->create_gc_project($kb_project);
            }
            
            // 2. Criar projeto no QL
            if (!$options['dry_run'] && class_exists('QL_Project')) {
                $project_model = QL_Project::get_instance();
                
                $project_data = [
                    'name' => $kb_project['name'],
                    'description' => $kb_project['description'] ?: 'Projeto migrado do Kanboard',
                    'gc_projeto_id' => $gc_projeto_id,
                    'status' => 'active',
                    'visibility' => $this->map_project_visibility($kb_project),
                    'kanboard_project_id' => $kb_project['id'],
                    'start_date' => !empty($kb_project['start_date']) ? $kb_project['start_date'] : null,
                    'end_date' => !empty($kb_project['end_date']) ? $kb_project['end_date'] : null
                ];
                
                $ql_project_id = $project_model->create($project_data);
                
                if (is_wp_error($ql_project_id)) {
                    $this->log("Erro ao criar projeto QL: " . $ql_project_id->get_error_message());
                    continue;
                }
                
                $migrated_projects++;
                
                // 3. Criar board padrão
                $board_result = $this->migrate_project_board($kb_project, $ql_project_id, $options);
                if (!is_wp_error($board_result)) {
                    $migrated_boards += $board_result['boards'];
                    $migrated_columns += $board_result['columns'];
                }
            }
        }
        
        $this->log("Projetos migrados: {$migrated_projects}");
        
        return [
            'projects' => $migrated_projects,
            'boards' => $migrated_boards,
            'columns' => $migrated_columns
        ];
    }
    
    /**
     * Criar projeto no Gestão Coletiva
     */
    private function create_gc_project($kb_project) {
        if (!class_exists('GC_Projeto')) {
            return null;
        }
        
        try {
            $gc_data = [
                'nome' => $kb_project['name'],
                'descricao' => $kb_project['description'] ?: 'Projeto migrado do Kanboard',
                'data_inicio' => $kb_project['start_date'] ?: current_time('Y-m-d'),
                'data_fim' => $kb_project['end_date'] ?: null,
                'orcamento_estimado' => 0,
                'status' => 'ativo',
                'tipo_trilha' => 'criacao', // Padrão para projetos migrados
                'origem' => 'kanboard_migration'
            ];
            
            $gc_projeto = new GC_Projeto();
            $projeto_id = $gc_projeto->criar($gc_data);
            
            $this->log("Projeto GC criado: ID {$projeto_id}");
            
            return $projeto_id;
            
        } catch (Exception $e) {
            $this->log("Erro ao criar projeto GC: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Migrar board do projeto
     */
    private function migrate_project_board($kb_project, $ql_project_id, $options) {
        if (!class_exists('QL_Board') || !class_exists('QL_Column')) {
            return new WP_Error('missing_classes', 'Classes QL_Board ou QL_Column não encontradas');
        }
        
        $board_model = QL_Board::get_instance();
        $column_model = QL_Column::get_instance();
        
        // Criar board principal
        $board_data = [
            'name' => $kb_project['name'] . ' - Board Principal',
            'description' => 'Board migrado do Kanboard',
            'project_id' => $ql_project_id,
            'status' => 'active'
        ];
        
        if ($options['dry_run']) {
            $board_id = 'dry_run_board_' . $kb_project['id'];
        } else {
            $board_id = $board_model->create($board_data);
            if (is_wp_error($board_id)) {
                return $board_id;
            }
        }
        
        // Migrar colunas do Kanboard
        $pdo = $this->connect_kanboard_db();
        $stmt = $pdo->prepare("SELECT * FROM columns WHERE project_id = ? ORDER BY position ASC");
        $stmt->execute([$kb_project['id']]);
        $kb_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated_columns = 0;
        
        foreach ($kb_columns as $kb_column) {
            $column_data = [
                'name' => $kb_column['title'],
                'description' => $kb_column['description'] ?: '',
                'board_id' => $board_id,
                'position' => $kb_column['position'],
                'task_limit' => $kb_column['task_limit'] ?: 0,
                'color' => '#' . ($kb_column['color'] ?: '34495e')
            ];
            
            if (!$options['dry_run']) {
                $column_id = $column_model->create($column_data);
                if (!is_wp_error($column_id)) {
                    $migrated_columns++;
                }
            } else {
                $migrated_columns++;
            }
        }
        
        return [
            'boards' => 1,
            'columns' => $migrated_columns
        ];
    }
    
    /**
     * Migrar tarefas
     */
    private function migrate_tasks($options) {
        $this->log('Iniciando migração de tarefas...');
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('missing_class', 'Classe QL_Task não encontrada');
        }
        
        $pdo = $this->connect_kanboard_db();
        $task_model = QL_Task::get_instance();
        
        // Buscar tarefas do Kanboard
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   p.name as project_name,
                   c.title as column_title, c.position as column_position,
                   u1.username as creator_username,
                   u2.username as assignee_username
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN columns c ON t.column_id = c.id
            LEFT JOIN users u1 ON t.creator_id = u1.id
            LEFT JOIN users u2 ON t.owner_id = u2.id
            WHERE p.is_active = 1
            ORDER BY t.project_id, t.column_id, t.position
        ");
        $stmt->execute();
        $kanboard_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated_tasks = 0;
        
        foreach ($kanboard_tasks as $kb_task) {
            // Encontrar projeto QL correspondente
            $ql_project = $this->find_ql_project_by_kanboard_id($kb_task['project_id']);
            if (!$ql_project) {
                $this->log("Projeto QL não encontrado para task {$kb_task['id']}");
                continue;
            }
            
            // Encontrar board e coluna correspondentes
            $board_column = $this->find_ql_board_column($ql_project['id'], $kb_task['column_position']);
            if (!$board_column) {
                $this->log("Board/coluna não encontrada para task {$kb_task['id']}");
                continue;
            }
            
            // Mapear usuário assignado
            $assigned_user_id = null;
            if (!empty($kb_task['assignee_username'])) {
                $assigned_user = get_user_by('login', $kb_task['assignee_username']);
                if ($assigned_user) {
                    $assigned_user_id = $assigned_user->ID;
                }
            }
            
            // Preparar dados da tarefa
            $task_data = [
                'title' => $kb_task['title'],
                'description' => $kb_task['description'] ?: '',
                'column_id' => $board_column['column_id'],
                'board_id' => $board_column['board_id'],
                'project_id' => $ql_project['id'],
                'assigned_user_id' => $assigned_user_id,
                'status' => $this->map_task_status($kb_task['is_active']),
                'priority' => $this->map_task_priority($kb_task['priority']),
                'color' => !empty($kb_task['color_id']) ? $this->map_task_color($kb_task['color_id']) : null,
                'score' => $kb_task['score'] ?: 0,
                'position' => $kb_task['position'],
                'start_date' => !empty($kb_task['date_started']) ? date('Y-m-d H:i:s', $kb_task['date_started']) : null,
                'due_date' => !empty($kb_task['date_due']) ? date('Y-m-d H:i:s', $kb_task['date_due']) : null,
                'reference' => 'KB-' . $kb_task['id']
            ];
            
            if (!$options['dry_run']) {
                $task_id = $task_model->create($task_data);
                if (!is_wp_error($task_id)) {
                    $migrated_tasks++;
                    
                    // Salvar ID original para referência
                    global $wpdb;
                    $wpdb->update(
                        $wpdb->prefix . 'ql_tasks',
                        ['kanboard_task_id' => $kb_task['id']],
                        ['id' => $task_id],
                        ['%d'],
                        ['%d']
                    );
                } else {
                    $this->log("Erro ao criar tarefa: " . $task_id->get_error_message());
                }
            } else {
                $migrated_tasks++;
            }
        }
        
        $this->log("Tarefas migradas: {$migrated_tasks}");
        
        return ['tasks' => $migrated_tasks];
    }
    
    /**
     * Migrar comentários
     */
    private function migrate_comments($options) {
        $this->log('Iniciando migração de comentários...');
        
        $pdo = $this->connect_kanboard_db();
        
        // Buscar comentários do Kanboard
        $stmt = $pdo->prepare("
            SELECT c.*, u.username, t.title as task_title
            FROM comments c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN tasks t ON c.task_id = t.id
            ORDER BY c.date_creation ASC
        ");
        $stmt->execute();
        $kanboard_comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated_comments = 0;
        
        foreach ($kanboard_comments as $kb_comment) {
            // Encontrar tarefa QL correspondente
            $ql_task = $this->find_ql_task_by_kanboard_id($kb_comment['task_id']);
            if (!$ql_task) {
                continue;
            }
            
            // Encontrar usuário WordPress
            $wp_user = null;
            if (!empty($kb_comment['username'])) {
                $wp_user = get_user_by('login', $kb_comment['username']);
            }
            
            if (!$options['dry_run']) {
                global $wpdb;
                
                $comment_data = [
                    'task_id' => $ql_task['id'],
                    'user_id' => $wp_user ? $wp_user->ID : QL_Config::get_default_admin_id(), // Admin como fallback
                    'activity_type' => 'comment',
                    'content' => $kb_comment['comment'],
                    'created_at' => date('Y-m-d H:i:s', $kb_comment['date_creation'])
                ];
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ql_task_activities',
                    $comment_data
                );
                
                if ($result !== false) {
                    $migrated_comments++;
                }
            } else {
                $migrated_comments++;
            }
        }
        
        $this->log("Comentários migrados: {$migrated_comments}");
        
        return ['comments' => $migrated_comments];
    }
    
    // ========== MÉTODOS AUXILIARES ==========
    
    private function map_project_visibility($kb_project) {
        // Kanboard: 0 = private, 1 = public
        return $kb_project['is_public'] ? 'public' : 'private';
    }
    
    private function map_task_status($is_active) {
        return $is_active ? 'open' : 'completed';
    }
    
    private function map_task_priority($priority) {
        // Mapear prioridades do Kanboard (0=default, 1=low, 2=normal, 3=high, 4=urgent)
        $priority_map = [0 => 2, 1 => 1, 2 => 2, 3 => 3, 4 => 4];
        return $priority_map[$priority] ?? 2;
    }
    
    private function map_task_color($color_id) {
        // Mapear cores do Kanboard para hex
        $color_map = [
            'yellow' => '#f1c40f',
            'blue' => '#3498db',
            'green' => '#27ae60',
            'purple' => '#9b59b6',
            'red' => '#e74c3c',
            'orange' => '#e67e22',
            'grey' => '#95a5a6'
        ];
        
        return $color_map[$color_id] ?? null;
    }
    
    private function find_ql_project_by_kanboard_id($kanboard_project_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE kanboard_project_id = %d",
            $kanboard_project_id
        ), ARRAY_A);
    }
    
    private function find_ql_board_column($project_id, $column_position) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT c.id as column_id, b.id as board_id 
             FROM {$wpdb->prefix}ql_columns c
             JOIN {$wpdb->prefix}ql_boards b ON c.board_id = b.id
             WHERE b.project_id = %d AND c.position = %d
             LIMIT 1",
            $project_id, $column_position
        ), ARRAY_A);
    }
    
    private function find_ql_task_by_kanboard_id($kanboard_task_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_tasks WHERE kanboard_task_id = %d",
            $kanboard_task_id
        ), ARRAY_A);
    }
    
    private function log($message) {
        $timestamp = current_time('Y-m-d H:i:s');
        $this->migration_log[] = "[{$timestamp}] {$message}";
        error_log("QL Migration: {$message}");
    }
    
    // ========== MÉTODOS AJAX ==========
    
    public function ajax_start_migration() {
        check_ajax_referer('ql_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $options = [
            'migrate_projects' => !empty($_POST['migrate_projects']),
            'migrate_tasks' => !empty($_POST['migrate_tasks']),
            'migrate_comments' => !empty($_POST['migrate_comments']),
            'create_default_gc_project' => !empty($_POST['create_gc_project']),
            'dry_run' => !empty($_POST['dry_run'])
        ];
        
        $result = $this->run_migration($options);
        
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'log' => $result->get_error_data()['log'] ?? []
            ]);
        } else {
            wp_send_json_success($result);
        }
    }
    
    public function ajax_check_migration_status() {
        check_ajax_referer('ql_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $stats = $this->get_kanboard_stats();
        
        if (is_wp_error($stats)) {
            wp_send_json_error([
                'message' => $stats->get_error_message()
            ]);
        } else {
            wp_send_json_success($stats);
        }
    }
}