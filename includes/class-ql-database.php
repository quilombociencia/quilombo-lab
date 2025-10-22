<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciamento do banco de dados do Quilombo Laboratório
 * Integrada com o Plugin Gestão Coletiva
 */
class QL_Database {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Verificar se tabelas precisam ser atualizadas
        add_action('plugins_loaded', [$this, 'maybe_upgrade_database']);
    }
    
    /**
     * Criar todas as tabelas necessárias
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];
        
        // Tabela de projetos do laboratório (estende GC_Projetos)
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_projects (
            id int(11) NOT NULL AUTO_INCREMENT,
            gc_projeto_id int(11) NULL COMMENT 'FK para wp_gc_projetos (opcional)',
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL UNIQUE,
            description text,
            status enum('active', 'archived', 'completed', 'on_hold') DEFAULT 'active',
            visibility enum('public', 'private', 'team') DEFAULT 'team',
            owner_id int(11) NOT NULL COMMENT 'FK para wp_users.ID',
            start_date date NULL,
            end_date date NULL,
            estimated_hours int(11) DEFAULT 0,
            actual_hours int(11) DEFAULT 0,
            progress_percentage int(3) DEFAULT 0,
            priority enum('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            color varchar(7) DEFAULT '#3498db',
            settings json COMMENT 'Configurações específicas do projeto',
            moodle_course_id int(11) NULL COMMENT 'ID do curso no Moodle',
            kanboard_project_id int(11) NULL COMMENT 'ID no Kanboard original (para migração)',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY gc_projeto_id (gc_projeto_id),
            KEY owner_id (owner_id),
            KEY status (status),
            KEY moodle_course_id (moodle_course_id)
        ) $charset_collate;";
        
        // Tabela de boards (múltiplos boards por projeto)
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_boards (
            id int(11) NOT NULL AUTO_INCREMENT,
            project_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            board_type enum('kanban', 'scrum', 'calendar', 'timeline') DEFAULT 'kanban',
            is_default boolean DEFAULT FALSE,
            settings json COMMENT 'Configurações do board',
            position int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            CONSTRAINT fk_ql_boards_project FOREIGN KEY (project_id) REFERENCES {$wpdb->prefix}ql_projects(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de colunas do board
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_columns (
            id int(11) NOT NULL AUTO_INCREMENT,
            board_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            position int(11) NOT NULL DEFAULT 0,
            task_limit int(11) DEFAULT 0 COMMENT 'WIP limit',
            color varchar(7) DEFAULT '#34495e',
            column_type enum('backlog', 'active', 'done', 'custom') DEFAULT 'custom',
            auto_actions json COMMENT 'Ações automáticas quando tarefa entra/sai',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            CONSTRAINT fk_ql_columns_board FOREIGN KEY (board_id) REFERENCES {$wpdb->prefix}ql_boards(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de swimlanes (raias)
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_swimlanes (
            id int(11) NOT NULL AUTO_INCREMENT,
            board_id int(11) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            position int(11) NOT NULL DEFAULT 0,
            color varchar(7) DEFAULT '#95a5a6',
            is_active boolean DEFAULT TRUE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id),
            CONSTRAINT fk_ql_swimlanes_board FOREIGN KEY (board_id) REFERENCES {$wpdb->prefix}ql_boards(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela principal de tarefas
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_tasks (
            id int(11) NOT NULL AUTO_INCREMENT,
            project_id int(11) NOT NULL,
            board_id int(11) NOT NULL,
            column_id int(11) NOT NULL,
            swimlane_id int(11) NULL,
            title varchar(500) NOT NULL,
            description text,
            task_number varchar(50) NOT NULL COMMENT 'Número único da tarefa (ex: QL-001)',
            status enum('open', 'in_progress', 'review', 'completed', 'cancelled') DEFAULT 'open',
            priority enum('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            task_type enum('feature', 'bug', 'improvement', 'research', 'documentation') DEFAULT 'feature',
            
            -- Usuários
            creator_id int(11) NOT NULL,
            assigned_user_id int(11) NULL,
            owner_id int(11) NULL,
            
            -- Datas
            start_date datetime NULL,
            due_date datetime NULL,
            completed_at datetime NULL,
            
            -- Estimativas e tracking
            estimated_hours decimal(8,2) DEFAULT 0,
            actual_hours decimal(8,2) DEFAULT 0,
            story_points int(11) DEFAULT 0,
            
            -- Financeiro (integração com Gestão Coletiva)
            estimated_cost decimal(10,2) DEFAULT 0,
            actual_cost decimal(10,2) DEFAULT 0,
            gc_lancamento_id int(11) NULL COMMENT 'FK para wp_gc_lancamentos',
            
            -- Hierarquia de tarefas
            parent_task_id int(11) NULL COMMENT 'Tarefa pai para subtarefas',
            
            -- Posicionamento
            position int(11) DEFAULT 0,
            
            -- Metadados
            color varchar(7) DEFAULT '#ffffff',
            tags text COMMENT 'Tags separadas por vírgula',
            custom_fields json COMMENT 'Campos customizados',
            
            -- Integração externa
            kanboard_task_id int(11) NULL COMMENT 'ID no Kanboard original',
            moodle_activity_id int(11) NULL COMMENT 'Atividade relacionada no Moodle',
            
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY task_number (task_number),
            KEY project_id (project_id),
            KEY board_id (board_id),
            KEY column_id (column_id),
            KEY swimlane_id (swimlane_id),
            KEY creator_id (creator_id),
            KEY assigned_user_id (assigned_user_id),
            KEY status (status),
            KEY priority (priority),
            KEY due_date (due_date),
            KEY parent_task_id (parent_task_id),
            KEY gc_lancamento_id (gc_lancamento_id),
            
            CONSTRAINT fk_ql_tasks_project FOREIGN KEY (project_id) REFERENCES {$wpdb->prefix}ql_projects(id) ON DELETE CASCADE,
            CONSTRAINT fk_ql_tasks_board FOREIGN KEY (board_id) REFERENCES {$wpdb->prefix}ql_boards(id) ON DELETE CASCADE,
            CONSTRAINT fk_ql_tasks_column FOREIGN KEY (column_id) REFERENCES {$wpdb->prefix}ql_columns(id),
            CONSTRAINT fk_ql_tasks_swimlane FOREIGN KEY (swimlane_id) REFERENCES {$wpdb->prefix}ql_swimlanes(id),
            CONSTRAINT fk_ql_tasks_parent FOREIGN KEY (parent_task_id) REFERENCES {$wpdb->prefix}ql_tasks(id)
        ) $charset_collate;";
        
        // Tabela de comentários/atividades das tarefas
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_task_activities (
            id int(11) NOT NULL AUTO_INCREMENT,
            task_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            activity_type enum('comment', 'status_change', 'assignment', 'file_upload', 'time_log', 'system') DEFAULT 'comment',
            content text,
            old_value varchar(255) NULL COMMENT 'Valor anterior para mudanças',
            new_value varchar(255) NULL COMMENT 'Novo valor para mudanças',
            metadata json COMMENT 'Dados adicionais da atividade',
            is_private boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY activity_type (activity_type),
            CONSTRAINT fk_ql_activities_task FOREIGN KEY (task_id) REFERENCES {$wpdb->prefix}ql_tasks(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de anexos
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_attachments (
            id int(11) NOT NULL AUTO_INCREMENT,
            task_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            filename varchar(255) NOT NULL,
            original_filename varchar(255) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size int(11) NOT NULL,
            mime_type varchar(100) NOT NULL,
            is_image boolean DEFAULT FALSE,
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            CONSTRAINT fk_ql_attachments_task FOREIGN KEY (task_id) REFERENCES {$wpdb->prefix}ql_tasks(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de membros do projeto
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_project_members (
            id int(11) NOT NULL AUTO_INCREMENT,
            project_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            role enum('viewer', 'member', 'coordinator', 'manager') DEFAULT 'member',
            permissions json COMMENT 'Permissões específicas',
            joined_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_active boolean DEFAULT TRUE,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id, user_id),
            KEY project_id (project_id),
            KEY user_id (user_id),
            CONSTRAINT fk_ql_members_project FOREIGN KEY (project_id) REFERENCES {$wpdb->prefix}ql_projects(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de time tracking
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_time_tracking (
            id int(11) NOT NULL AUTO_INCREMENT,
            task_id int(11) NOT NULL,
            user_id int(11) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NULL,
            duration_minutes int(11) NULL COMMENT 'Duração em minutos',
            description text,
            billable boolean DEFAULT TRUE,
            hourly_rate decimal(10,2) DEFAULT 0,
            is_active boolean DEFAULT FALSE COMMENT 'Se está rodando atualmente',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id),
            KEY user_id (user_id),
            KEY start_time (start_time),
            CONSTRAINT fk_ql_time_task FOREIGN KEY (task_id) REFERENCES {$wpdb->prefix}ql_tasks(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Tabela de templates de projeto
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_project_templates (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            template_type enum('trilha_aprendizagem', 'trilha_pesquisa', 'trilha_criacao', 'custom') DEFAULT 'custom',
            template_data json NOT NULL COMMENT 'Estrutura do template (boards, columns, tasks padrão)',
            is_active boolean DEFAULT TRUE,
            created_by int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_type (template_type),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // Tabela de configurações do plugin
        $tables[] = "CREATE TABLE {$wpdb->prefix}ql_settings (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL UNIQUE,
            setting_value longtext,
            setting_type enum('string', 'number', 'boolean', 'json', 'array') DEFAULT 'string',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // Executar criação das tabelas
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
        
        // Inserir dados padrão
        self::insert_default_data();
        
        // Atualizar versão do banco
        update_option('ql_database_version', QL_PLUGIN_VERSION);
        
        error_log('Quilombo Laboratório: Tabelas criadas com sucesso');
    }
    
    /**
     * Inserir dados padrão
     */
    private static function insert_default_data() {
        global $wpdb;
        
        // Templates padrão por tipo de trilha
        $templates = [
            [
                'name' => 'Trilha de Aprendizagem Padrão',
                'description' => 'Template padrão para trilhas de aprendizagem',
                'template_type' => 'trilha_aprendizagem',
                'template_data' => json_encode([
                    'boards' => [
                        ['name' => 'Kanban Principal', 'type' => 'kanban', 'is_default' => true]
                    ],
                    'columns' => [
                        ['name' => 'Planejamento', 'position' => 1, 'color' => '#e74c3c'],
                        ['name' => 'Em Desenvolvimento', 'position' => 2, 'color' => '#f39c12'],
                        ['name' => 'Revisão', 'position' => 3, 'color' => '#3498db'],
                        ['name' => 'Concluído', 'position' => 4, 'color' => '#27ae60']
                    ],
                    'default_tasks' => [
                        ['title' => 'Definir objetivos de aprendizagem', 'column' => 1],
                        ['title' => 'Preparar materiais didáticos', 'column' => 1],
                        ['title' => 'Configurar ambiente de aprendizagem', 'column' => 1]
                    ]
                ]),
                'created_by' => QL_Config::get_default_admin_id()
            ],
            [
                'name' => 'Trilha de Pesquisa Padrão',
                'description' => 'Template padrão para trilhas de pesquisa',
                'template_type' => 'trilha_pesquisa',
                'template_data' => json_encode([
                    'boards' => [
                        ['name' => 'Metodologia Científica', 'type' => 'kanban', 'is_default' => true]
                    ],
                    'columns' => [
                        ['name' => 'Questão de Pesquisa', 'position' => 1, 'color' => '#9b59b6'],
                        ['name' => 'Coleta de Dados', 'position' => 2, 'color' => '#e67e22'],
                        ['name' => 'Análise', 'position' => 3, 'color' => '#2980b9'],
                        ['name' => 'Validação', 'position' => 4, 'color' => '#16a085'],
                        ['name' => 'Publicação', 'position' => 5, 'color' => '#27ae60']
                    ],
                    'default_tasks' => [
                        ['title' => 'Definir problema de pesquisa', 'column' => 1],
                        ['title' => 'Revisão bibliográfica', 'column' => 1],
                        ['title' => 'Elaborar metodologia', 'column' => 1]
                    ]
                ]),
                'created_by' => QL_Config::get_default_admin_id()
            ],
            [
                'name' => 'Trilha de Criação Padrão',
                'description' => 'Template padrão para trilhas de criação',
                'template_type' => 'trilha_criacao',
                'template_data' => json_encode([
                    'boards' => [
                        ['name' => 'Desenvolvimento de Projeto', 'type' => 'kanban', 'is_default' => true]
                    ],
                    'columns' => [
                        ['name' => 'Problema', 'position' => 1, 'color' => '#e74c3c'],
                        ['name' => 'Ideação', 'position' => 2, 'color' => '#f1c40f'],
                        ['name' => 'Planejamento', 'position' => 3, 'color' => '#e67e22'],
                        ['name' => 'Desenvolvimento', 'position' => 4, 'color' => '#3498db'],
                        ['name' => 'Avaliação', 'position' => 5, 'color' => '#9b59b6'],
                        ['name' => 'Concluída', 'position' => 6, 'color' => '#27ae60']
                    ],
                    'default_tasks' => [
                        ['title' => 'Identificar o problema central', 'column' => 1],
                        ['title' => 'Definir o contexto e stakeholders', 'column' => 1],
                        ['title' => 'Documentar requisitos iniciais', 'column' => 1]
                    ]
                ]),
                'created_by' => QL_Config::get_default_admin_id()
            ]
        ];
        
        foreach ($templates as $template) {
            $wpdb->insert(
                $wpdb->prefix . 'ql_project_templates',
                $template
            );
        }
        
        // Configurações padrão (migrando para novo sistema de configurações)
        $default_settings = [
            'task_number_prefix' => QL_Config::get_task_number_prefix(),
            'default_board_type' => QL_Config::get_default_board_type(),
            'enable_time_tracking' => true,
            'enable_financial_integration' => QL_Config::is_gc_integration_enabled(),
            'auto_create_gc_expenses' => QL_Config::should_auto_create_gc_expenses(),
            'default_task_status' => QL_Config::get_default_task_status(),
            'enable_notifications' => QL_Config::get_notification_settings()['enabled'],
            'notification_email' => QL_Config::get_notification_settings()['email'],
            'max_file_upload_size' => QL_Config::get_upload_settings()['max_size'],
            'allowed_file_types' => QL_Config::get_upload_settings()['allowed_types'],
            'enable_public_boards' => QL_Config::are_public_boards_enabled(),
            'default_project_visibility' => QL_Config::get_default_project_visibility()
        ];
        
        foreach ($default_settings as $key => $value) {
            $wpdb->insert(
                $wpdb->prefix . 'ql_settings',
                [
                    'setting_key' => $key,
                    'setting_value' => is_array($value) ? json_encode($value) : $value,
                    'setting_type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string')
                ]
            );
        }
        
        error_log('Quilombo Laboratório: Dados padrão inseridos');
    }
    
    /**
     * Verificar se precisa atualizar banco
     */
    public function maybe_upgrade_database() {
        $current_version = get_option('ql_database_version');
        
        if ($current_version !== QL_PLUGIN_VERSION) {
            self::create_tables();
        }
    }
    
    /**
     * Limpeza de logs antigos
     */
    public static function cleanup_old_logs() {
        global $wpdb;
        
        // Remover atividades antigas (mais de 1 ano)
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}ql_task_activities 
            WHERE activity_type = 'system' 
            AND created_at < %s
        ", date('Y-m-d H:i:s', strtotime('-1 year'))));
        
        // Remover sessões de time tracking muito antigas sem fim
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}ql_time_tracking 
            SET is_active = FALSE, end_time = start_time 
            WHERE is_active = TRUE 
            AND start_time < %s
        ", date('Y-m-d H:i:s', strtotime('-24 hours'))));
        
        error_log('Quilombo Laboratório: Limpeza de dados executada');
    }
    
    /**
     * Obter configuração
     */
    public static function get_setting($key, $default = null) {
        global $wpdb;
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}ql_settings WHERE setting_key = %s",
            $key
        ));
        
        return $result !== null ? $result : $default;
    }
    
    /**
     * Salvar configuração
     */
    public static function update_setting($key, $value, $type = 'string') {
        global $wpdb;
        
        $value_to_store = is_array($value) || is_object($value) ? json_encode($value) : $value;
        
        $result = $wpdb->replace(
            $wpdb->prefix . 'ql_settings',
            [
                'setting_key' => $key,
                'setting_value' => $value_to_store,
                'setting_type' => $type
            ]
        );
        
        return $result !== false;
    }
}