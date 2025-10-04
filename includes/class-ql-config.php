<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe helper para acessar configurações de forma centralizada
 * Substitui valores hardcoded por configurações dinâmicas
 */
class QL_Config {
    
    private static $instance = null;
    private static $cache = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Cache das configurações para evitar múltiplas consultas
        $this->load_all_settings();
    }
    
    /**
     * Carregar todas as configurações no cache
     */
    private function load_all_settings() {
        if (empty(self::$cache)) {
            self::$cache = [
                'general' => get_option('ql_general_settings', []),
                'integration' => get_option('ql_integration_settings', []),
                'system' => get_option('ql_system_settings', []),
                'advanced' => get_option('ql_advanced_settings', [])
            ];
        }
    }
    
    /**
     * Obter valor de configuração com fallback
     */
    public static function get($section, $key, $default = null) {
        $instance = self::get_instance();
        
        if (isset(self::$cache[$section][$key])) {
            return self::$cache[$section][$key];
        }
        
        return $default;
    }
    
    /**
     * Atualizar valor no cache
     */
    public static function set_cache($section, $key, $value) {
        self::$cache[$section][$key] = $value;
    }
    
    /**
     * Limpar cache
     */
    public static function clear_cache() {
        self::$cache = [];
    }
    
    // ===============================================
    // MÉTODOS ESPECÍFICOS PARA VALORES HARDCODED
    // ===============================================
    
    /**
     * Obter ID do usuário administrador padrão
     * Substitui: 'owner_id' => 1
     */
    public static function get_default_admin_id() {
        $admin_id = self::get('general', 'default_admin_user_id', 1);
        
        // Verificar se o usuário existe e é admin
        $user = get_user_by('id', $admin_id);
        if (!$user || !user_can($user, 'manage_options')) {
            // Fallback: pegar o primeiro admin disponível
            $admins = get_users(['role' => 'administrator', 'number' => 1]);
            return !empty($admins) ? $admins[0]->ID : 1;
        }
        
        return $admin_id;
    }
    
    /**
     * Obter usuário criador atual ou fallback
     * Substitui: get_current_user_id() ?: 1
     */
    public static function get_current_or_default_user_id() {
        $current_user_id = get_current_user_id();
        return $current_user_id ?: self::get_default_admin_id();
    }
    
    /**
     * Obter prefixo de numeração de tarefas
     * Substitui: 'task_number_prefix' => 'QL'
     */
    public static function get_task_number_prefix() {
        return self::get('general', 'task_number_prefix', 'QL');
    }
    
    /**
     * Obter status padrão de tarefas
     * Substitui: 'default_task_status' => 'open'
     */
    public static function get_default_task_status() {
        return self::get('general', 'default_task_status', 'open');
    }
    
    /**
     * Obter prioridade padrão de tarefas
     * Substitui: 'priority' => 'normal'
     */
    public static function get_default_task_priority() {
        return self::get('general', 'default_task_priority', 'normal');
    }
    
    /**
     * Obter visibilidade padrão de projetos
     * Substitui: 'default_project_visibility' => 'team'
     */
    public static function get_default_project_visibility() {
        return self::get('general', 'default_project_visibility', 'team');
    }
    
    /**
     * Obter tipo padrão de board
     * Substitui: 'default_board_type' => 'kanban'
     */
    public static function get_default_board_type() {
        return self::get('general', 'default_board_type', 'kanban');
    }
    
    /**
     * Obter cor padrão de projetos
     * Substitui: 'color' => '#3498db'
     */
    public static function get_default_project_color() {
        return '#3498db'; // Mantém como padrão visual
    }
    
    /**
     * Obter cor padrão de tarefas
     * Substitui: 'color' => '#ffffff'
     */
    public static function get_default_task_color() {
        return '#ffffff'; // Mantém como padrão visual
    }
    
    /**
     * Obter configurações de upload
     * Substitui: max_file_upload_size, allowed_file_types
     */
    public static function get_upload_settings() {
        return [
            'max_size' => self::get('system', 'max_file_upload_size', 10),
            'allowed_types' => self::get('system', 'allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'),
            'directory' => self::get('system', 'upload_directory', 'quilombo-lab')
        ];
    }
    
    /**
     * Obter path do banco Kanboard para migração
     * Substitui: '/var/www/html/lab/data/db.sqlite'
     */
    public static function get_kanboard_db_path() {
        return self::get('system', 'kanboard_db_path', WP_CONTENT_DIR . '/kanboard/db.sqlite');
    }
    
    /**
     * Obter configurações do Moodle
     * Substitui: URLs hardcoded e course ID = 1
     */
    public static function get_moodle_settings() {
        return [
            'url' => self::get('integration', 'moodle_url', ''),
            'token' => self::get('integration', 'moodle_token', ''),
            'default_course_id' => self::get('integration', 'moodle_default_course_id', 1),
            'database_name' => self::get('integration', 'moodle_database_name', 'moodle_escola')
        ];
    }
    
    /**
     * Obter nome do banco Kanboard
     * Substitui: 'dbname=kanboard'
     */
    public static function get_kanboard_database_name() {
        return self::get('integration', 'kanboard_database_name', 'kanboard');
    }
    
    /**
     * Obter nome do banco Moodle
     * Substitui: 'dbname=moodle_escola'
     */
    public static function get_moodle_database_name() {
        return self::get('integration', 'moodle_database_name', 'moodle_escola');
    }
    
    /**
     * Obter configurações de notificação
     * Substitui: notification_email hardcoded
     */
    public static function get_notification_settings() {
        return [
            'enabled' => self::get('general', 'enable_notifications', true),
            'email' => self::get('general', 'notification_email', get_option('admin_email', ''))
        ];
    }
    
    /**
     * Obter configurações de CDN
     * Substitui: URLs hardcoded de CDNs
     */
    public static function get_cdn_settings() {
        return [
            'fullcalendar' => self::get('advanced', 'fullcalendar_cdn', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'),
            'fontawesome' => self::get('advanced', 'fontawesome_cdn', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css')
        ];
    }
    
    /**
     * Verificar se debug está ativo
     * Substitui: verificações hardcoded de debug
     */
    public static function is_debug_mode() {
        return self::get('advanced', 'debug_mode', false);
    }
    
    /**
     * Obter level de log
     */
    public static function get_log_level() {
        return self::get('advanced', 'log_level', 'error');
    }
    
    /**
     * Verificar se integração GC está ativa
     */
    public static function is_gc_integration_enabled() {
        return self::get('integration', 'gc_integration_enabled', false);
    }
    
    /**
     * Verificar se deve auto-criar lançamentos GC
     */
    public static function should_auto_create_gc_expenses() {
        return self::get('integration', 'auto_create_gc_expenses', false);
    }
    
    /**
     * Obter configurações de cache
     */
    public static function get_cache_settings() {
        return [
            'enabled' => self::get('advanced', 'enable_data_cache', true),
            'timeout' => self::get('advanced', 'cache_timeout', 60) * MINUTE_IN_SECONDS
        ];
    }
    
    /**
     * Verificar se boards públicos estão habilitados
     */
    public static function are_public_boards_enabled() {
        return self::get('system', 'enable_public_boards', false);
    }
    
    /**
     * Verificar se migração Kanboard está habilitada
     */
    public static function is_kanboard_migration_enabled() {
        return self::get('advanced', 'enable_kanboard_migration', true);
    }
    
    /**
     * Obter diretório de logs
     */
    public static function get_logs_directory() {
        $dir = self::get('system', 'logs_directory', WP_CONTENT_DIR . '/logs/quilombo-lab');
        
        // Criar diretório se não existir
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        return $dir;
    }
    
    /**
     * Construir string de conexão PDO para banco externo
     */
    public static function get_external_db_dsn($database_name) {
        return sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8mb4",
            DB_HOST,
            $database_name
        );
    }
    
    /**
     * Obter credenciais para bancos externos
     * (usa as mesmas credenciais do WordPress por padrão)
     */
    public static function get_external_db_credentials() {
        return [
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        ];
    }
    
    /**
     * Validar e normalizar IP
     * Substitui: $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
     */
    public static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Validar IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '127.0.0.1';
        }
        
        return $ip;
    }
    
    /**
     * Obter configurações completas para debugging
     */
    public static function get_all_settings_for_debug() {
        if (!self::is_debug_mode()) {
            return ['debug_mode' => false];
        }
        
        return [
            'general' => self::$cache['general'] ?? [],
            'integration' => self::$cache['integration'] ?? [],
            'system' => self::$cache['system'] ?? [],
            'advanced' => self::$cache['advanced'] ?? []
        ];
    }
    
    /**
     * Método para migração: mapear valores antigos para novos
     */
    public static function migrate_hardcoded_values() {
        $migrated = [];
        
        // Verificar se há configurações antigas no banco de dados antigo
        $old_settings = get_option('quilombo_laboratorio_settings', []);
        
        if (!empty($old_settings)) {
            // Mapear configurações antigas para nova estrutura
            $general_settings = [];
            $integration_settings = [];
            
            // Mapear campos conhecidos
            if (isset($old_settings['moodle_url'])) {
                $integration_settings['moodle_url'] = $old_settings['moodle_url'];
            }
            if (isset($old_settings['moodle_token'])) {
                $integration_settings['moodle_token'] = $old_settings['moodle_token'];
            }
            if (isset($old_settings['default_visibility'])) {
                $general_settings['default_project_visibility'] = $old_settings['default_visibility'];
            }
            
            // Salvar novas configurações
            if (!empty($general_settings)) {
                update_option('ql_general_settings', array_merge(
                    get_option('ql_general_settings', []), 
                    $general_settings
                ));
                $migrated[] = 'general';
            }
            
            if (!empty($integration_settings)) {
                update_option('ql_integration_settings', array_merge(
                    get_option('ql_integration_settings', []), 
                    $integration_settings
                ));
                $migrated[] = 'integration';
            }
            
            // Limpar cache após migração
            self::clear_cache();
        }
        
        return $migrated;
    }
}