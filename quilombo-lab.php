<?php
/**
 * Plugin Name: Quilombo Laboratório de Projetos
 * Plugin URI: https://quilombociencia.org
 * Description: Sistema completo de gestão de projetos integrado ao Plugin Gestão Coletiva. Gestão de projetos nativa do WordPress baseada em trilhas do Moodle.
 * Version: 1.1.0-beta-stable
 * Author: Quilombo Ciência
 * Author URI: https://quilombociencia.org
 * License: GPL v3 or later
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Text Domain: quilombo-lab
 * Domain Path: /languages
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes do plugin
define('QL_PLUGIN_FILE', __FILE__);
define('QL_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('QL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('QL_PLUGIN_VERSION', '1.0.4-fix-' . time());
define('QL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('QL_DB_VERSION', '1.0.3');

/**
 * Classe principal do plugin
 */
class QuilomboLaboratorio {
    
    private static $instance = null;
    
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
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        // Verificar integridade do banco na inicialização
        add_action('init', [$this, 'check_database_integrity'], 1);
        add_action('admin_init', [$this, 'admin_init']);
        
        // Hooks de atualização
        add_action('upgrader_process_complete', [$this, 'on_plugin_update'], 10, 2);
        
        // Admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar dependências
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Carregar arquivos principais
        $this->load_includes();
        
        // Inicializar componentes
        $this->init_components();
        
        // Hooks WordPress
        $this->setup_hooks();
        
        // Carregar idiomas
        load_plugin_textdomain('quilombo-lab', false, dirname(QL_PLUGIN_BASENAME) . '/languages');
        
        // Log de inicialização
        error_log('Quilombo Laboratório: Plugin inicializado com sucesso');
    }
    
    /**
     * Verificar dependências
     */
    private function check_dependencies() {
        // Verificar versão PHP primeiro
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo __('Quilombo Laboratório requer PHP 7.4 ou superior. Versão atual: ' . PHP_VERSION, 'quilombo-lab');
                echo '</p></div>';
            });
            return false;
        }
        
        // Verificar se Plugin Gestão Coletiva está ativo (totalmente opcional)
        if (!function_exists('gestao_coletiva_version') && !class_exists('GestaoColetiva') && !class_exists('GC_Projeto')) {
            // Mostrar aviso apenas para administradores e apenas no painel admin
            if (current_user_can('manage_options') && is_admin()) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-info is-dismissible"><p>';
                    echo __('Quilombo Laboratório funcionando de forma independente. Para funcionalidades avançadas de gestão financeira, ative o Plugin Gestão Coletiva.', 'quilombo-lab');
                    echo '</p></div>';
                });
            }
            // Plugin funciona completamente sem GC
        }
        
        return true;
    }
    
    /**
     * Carregar arquivos de classes
     */
    private function load_includes() {
        // Core classes - carregar apenas os que existem
        $core_files = [
            'class-ql-config.php',           // Sistema de configurações centralizadas
            'class-ql-settings.php',         // Interface de configurações
            'class-ql-status.php',
            'class-ql-database.php',
            'class-ql-project.php',
            'class-ql-gc-integration.php'
        ];
        
        foreach ($core_files as $file) {
            $file_path = QL_PLUGIN_PATH . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("Quilombo Laboratório: Arquivo não encontrado: {$file}");
            }
        }
        
        // Outros arquivos opcionais - carregar apenas se existirem
        $optional_files = [
            'class-ql-board.php',
            'class-ql-task.php',
            'class-ql-column.php',
            'class-ql-user.php',
            'class-ql-timeline.php',
            'class-ql-calendar.php',
            'class-ql-reports.php',
            'class-ql-moodle-integration.php',
            'class-ql-migration.php',
            'class-ql-team-formation.php',
            'class-ql-user-sync.php',
            'class-ql-unified-permissions.php',
            'class-ql-sso.php',
            'class-ql-admin.php',
            'class-ql-api.php',
            'class-ql-ajax.php',
            'class-ql-public.php',
            'class-ql-shortcodes.php',
            'class-ql-trilha-sync.php'
        ];
        
        foreach ($optional_files as $file) {
            $file_path = QL_PLUGIN_PATH . 'includes/' . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
        
        // Incluir página de teste para diagnóstico (remover em produção)
        // if (file_exists(QL_PLUGIN_PATH . 'teste-admin-page.php')) {
        //     require_once QL_PLUGIN_PATH . 'teste-admin-page.php';
        // }
        
        // Incluir página de teste do sistema Kanban (comentado para evitar conflitos no frontend)
        // if (file_exists(QL_PLUGIN_PATH . 'admin-test-page.php')) {
        //     require_once QL_PLUGIN_PATH . 'admin-test-page.php';
        // }
    }
    
    /**
     * Inicializar componentes
     */
    private function init_components() {
        // Sistema de configurações - sempre necessário primeiro
        if (class_exists('QL_Config')) {
            QL_Config::get_instance();
        }
        
        // Interface de configurações - apenas para admin
        if (is_admin() && class_exists('QL_Settings')) {
            QL_Settings::get_instance();
        }
        
        // Database setup - sempre necessário
        if (class_exists('QL_Database')) {
            QL_Database::get_instance();
        }
        
        // Integração GC - sempre necessário
        if (class_exists('QL_GC_Integration')) {
            QL_GC_Integration::get_instance();
        }
        
        // Admin interface - apenas se existir
        if (is_admin() && class_exists('QL_Admin')) {
            QL_Admin::get_instance();
        }
        
        // AJAX handlers - apenas se existir
        if (class_exists('QL_Ajax')) {
            QL_Ajax::get_instance();
        }
        
        // REST API - apenas se existir
        if (class_exists('QL_API')) {
            QL_API::get_instance();
        }
        
        // Public interface - apenas se existir
        if (class_exists('QL_Public')) {
            QL_Public::get_instance();
        }
        
        // Shortcodes - apenas se existir
        if (class_exists('QL_Shortcodes')) {
            QL_Shortcodes::get_instance();
        }
        
        // Outras integrações - apenas se existirem
        if (class_exists('QL_Moodle_Integration')) {
            QL_Moodle_Integration::get_instance();
        }
        
        // Formação de equipes - apenas se existir
        if (class_exists('QL_Team_Formation')) {
            QL_Team_Formation::get_instance();
        }
        
        // Sincronização de usuários - sempre importante
        if (class_exists('QL_User_Sync')) {
            QL_User_Sync::get_instance();
        }
        
        // Sistema de permissões unificado - sempre importante
        if (class_exists('QL_Unified_Permissions')) {
            QL_Unified_Permissions::get_instance();
        }
        
        // Sistema SSO - sempre importante
        if (class_exists('QL_SSO')) {
            QL_SSO::get_instance();
        }
        
        // Sincronização de trilhas - sempre importante
        if (class_exists('QL_Trilha_Sync')) {
            QL_Trilha_Sync::get_instance();
        }
        
        // Reports - apenas se existir
        if (class_exists('QL_Reports')) {
            QL_Reports::get_instance();
        }
        
        // Calendar - apenas se existir
        if (class_exists('QL_Calendar')) {
            QL_Calendar::get_instance();
        }
    }
    
    /**
     * Setup hooks WordPress
     */
    private function setup_hooks() {
        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // REST API
        add_action('rest_api_init', [$this, 'register_api_routes']);
        
        // Cron jobs
        add_action('ql_daily_cleanup', [$this, 'daily_cleanup']);
        if (!wp_next_scheduled('ql_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'ql_daily_cleanup');
        }
        
        // Integration hooks
        add_action('gc_projeto_criado', [$this, 'on_gc_project_created'], 10, 2);
        add_action('gc_projeto_atualizado', [$this, 'on_gc_project_updated'], 10, 2);
    }
    
    /**
     * Enqueue assets públicos
     */
    public function enqueue_public_assets() {
        wp_enqueue_style(
            'quilombo-lab-public',
            QL_PLUGIN_URL . 'assets/css/public.css',
            [],
            QL_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'quilombo-lab-public',
            QL_PLUGIN_URL . 'assets/js/public.js',
            ['jquery'],
            QL_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('quilombo-lab-public', 'ql_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ql_public_nonce'),
            'strings' => [
                'loading' => __('Carregando...', 'quilombo-lab'),
                'error' => __('Erro ao carregar dados.', 'quilombo-lab'),
            ]
        ]);
    }
    
    /**
     * Enqueue assets administrativos
     */
    public function enqueue_admin_assets($hook) {
        // Carregar em todas as páginas administrativas para desenvolvimento
        // Em produção, isso deve ser mais restritivo
        if (!is_admin()) {
            return;
        }
        
        // Log para debug
        error_log("QL Debug: Carregando scripts na página: " . $hook);
        
        // Verificar se é uma página do plugin
        $is_ql_page = strpos($hook, 'quilombo-lab') !== false;
        
        wp_enqueue_style(
            'quilombo-lab-admin',
            QL_PLUGIN_URL . 'assets/css/admin.css',
            [],
            QL_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'quilombo-lab-task-modals',
            QL_PLUGIN_URL . 'assets/css/task-modals.css',
            ['quilombo-lab-admin'],
            QL_PLUGIN_VERSION
        );
        
        // CSS de correção para problemas críticos
        wp_enqueue_style(
            'quilombo-lab-fix',
            QL_PLUGIN_URL . 'assets/css/kanban-fix.css',
            ['quilombo-lab-admin'],
            QL_PLUGIN_VERSION
        );
        
        // Garantir que jQuery e jQuery UI estão carregados PRIMEIRO
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-widget');
        wp_enqueue_script('jquery-ui-mouse');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
        
        // Scripts principais reativados para funcionalidade completa
        wp_enqueue_script(
            'quilombo-lab-kanban',
            QL_PLUGIN_URL . 'assets/js/kanban.js',
            ['jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-mouse', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'],
            QL_PLUGIN_VERSION,
            true
        );
        
        // DESABILITADO: Conflita com sistema final de modais
        // wp_enqueue_script(
        //     'quilombo-lab-task-modals',
        //     QL_PLUGIN_URL . 'assets/js/task-modals.js',
        //     ['jquery', 'quilombo-lab-kanban'],
        //     QL_PLUGIN_VERSION,
        //     true
        // );
        
        wp_enqueue_script(
            'quilombo-lab-admin',
            QL_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'quilombo-lab-kanban'],
            QL_PLUGIN_VERSION,
            true
        );
        
        // SISTEMA DE DEBUG: Desabilitado - usando sistema final
        // wp_enqueue_script(
        //     'quilombo-lab-debug',
        //     QL_PLUGIN_URL . 'assets/js/debug-modal-system.js',
        //     ['jquery'],
        //     QL_PLUGIN_VERSION . '-debug',
        //     true
        // );
        
        // MODAIS FINAIS: Sistema completo com integração backend
        wp_enqueue_script(
            'quilombo-lab-final-modals',
            QL_PLUGIN_URL . 'assets/js/final-working-modals.js',
            ['jquery', 'quilombo-lab-kanban', 'quilombo-lab-admin'],
            QL_PLUGIN_VERSION . '-final',
            true
        );
        
        // TESTE TEMPORÁRIO: Verificar se sistema final está funcionando
        wp_enqueue_script(
            'quilombo-lab-test-final',
            QL_PLUGIN_URL . 'assets/js/test-final-modals.js',
            ['jquery', 'quilombo-lab-final-modals'],
            QL_PLUGIN_VERSION . '-test',
            true
        );
        
        // Script de correção crítica para funcionalidade Kanban - apenas nas páginas do plugin
        if ($is_ql_page || strpos($hook, 'project') !== false) {
            // Scripts de fix removidos - causavam conflitos e não existem
            // wp_enqueue_script(
            //     'quilombo-lab-fix',
            //     QL_PLUGIN_URL . 'assets/js/kanban-fix.js',
            //     ['jquery', 'jquery-ui-sortable'],
            //     QL_PLUGIN_VERSION,
            //     true
            // );
            
            // wp_enqueue_script(
            //     'quilombo-lab-jquery-fix',
            //     QL_PLUGIN_URL . 'assets/js/jquery-fix-override.js',
            //     ['jquery'],
            //     QL_PLUGIN_VERSION,
            //     true
            // );
        }
        
        // Script de teste removido - funcionalidade confirmada
        
        // Obter board_id padrão das configurações
        $board_id = 1; // Valor padrão
        if (class_exists('QL_Config')) {
            $config = QL_Config::get_instance();
            $board_id = $config->get('default_board_id', 1);
        }
        
        // Localizar variáveis para múltiplos scripts
        $localize_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('quilombo-lab/v1/'),
            'nonce' => wp_create_nonce('ql_admin_nonce'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
            'board_id' => $board_id,
            'default_board_id' => $board_id,
            'strings' => [
                'confirm_delete' => __('Tem certeza que deseja excluir?', 'quilombo-lab'),
                'task_moved' => __('Tarefa movida com sucesso!', 'quilombo-lab'),
                'error_moving' => __('Erro ao mover tarefa.', 'quilombo-lab'),
            ]
        ];
        
        // Localizar para todos os scripts que precisam
        wp_localize_script('quilombo-lab-task-modals', 'ql_admin', $localize_data);
        wp_localize_script('quilombo-lab-kanban', 'ql_admin', $localize_data);
        wp_localize_script('quilombo-lab-admin', 'ql_admin', $localize_data);
        wp_localize_script('quilombo-lab-final-modals', 'ql_admin', $localize_data);
        
        // Incluir modais em páginas do plugin via hook
        if ($is_ql_page) {
            add_action('admin_footer', [$this, 'include_admin_modals']);
        }
        
        // CORREÇÃO CRÍTICA: Usar output buffering para garantir execução
        add_action('admin_head', [$this, 'include_critical_js_fix']);
        add_action('admin_footer', [$this, 'include_critical_js_fix']);
        add_action('wp_footer', [$this, 'include_critical_js_fix']);
        
        // CORREÇÃO DIRETA: Executar imediatamente se estivermos numa página do plugin
        if (isset($_GET['page']) && strpos($_GET['page'], 'quilombo-lab') !== false) {
            add_action('admin_init', function() {
                ob_start();
                ?>
                <script>
                console.log('🚨 CORREÇÃO DIRETA EXECUTADA - admin_init');
                window.ql_admin = {
                    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                };
                </script>
                <?php
                $script = ob_get_clean();
                echo $script;
            });
        }
    }
    
    /**
     * Incluir modais nas páginas administrativas
     */
    public function include_admin_modals() {
        // Verificar se já foram incluídos para evitar duplicação
        static $modals_included = false;
        
        if ($modals_included) {
            return;
        }
        
        $modals_included = true;
        
        // Incluir templates de modais
        if (file_exists(QL_PLUGIN_PATH . 'templates/task-modals.php')) {
            include_once QL_PLUGIN_PATH . 'templates/task-modals.php';
        }
    }
    
    /**
     * Incluir JavaScript crítico inline para correção de problemas
     */
    public function include_critical_js_fix() {
        // Só executar em páginas do Kanban
        if (!isset($_GET['page']) || strpos($_GET['page'], 'quilombo-lab') === false) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        console.log('🔧 CORREÇÃO CRÍTICA INLINE CARREGADA - SUCESSO!');
        
        // Garantir que ql_admin está disponível IMEDIATAMENTE
        window.ql_admin = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>',
            board_id: <?php echo get_option('ql_default_board_id', 1); ?>
        };
        console.log('✅ window.ql_admin FORÇADO:', window.ql_admin);
        
        // Sobrescrever função de fechamento IMEDIATAMENTE
        window.closeTaskModal = function() {
            console.log('🗙 Fechando modal (INLINE FIX)...');
            if (typeof jQuery !== 'undefined') {
                jQuery('#ql-task-creation-modal, #ql-task-view-modal').remove();
            }
        };
        console.log('✅ closeTaskModal REDEFINIDA');
        
        // Aguardar jQuery e aplicar eventos
        if (typeof jQuery !== 'undefined') {
            jQuery(document).ready(function($) {
                console.log('🔧 jQuery PRONTO - aplicando eventos...');
                
                // Evento ESC
                $(document).off('keydown.qlfix').on('keydown.qlfix', function(e) {
                    if (e.keyCode === 27 && $('.ql-task-creation-modal, .ql-task-view-modal, #ql-task-creation-modal, #ql-task-view-modal').length > 0) {
                        console.log('🔑 ESC DETECTADO - fechando modal');
                        window.closeTaskModal();
                    }
                });
                
                // Evento click fora
                $(document).off('click.qlfix').on('click.qlfix', '#ql-task-creation-modal, #ql-task-view-modal', function(e) {
                    if (e.target === this) {
                        console.log('🖱️ CLICK FORA - fechando modal');
                        window.closeTaskModal();
                    }
                });
                
                console.log('✅ EVENTOS APLICADOS COM SUCESSO!');
            });
        } else {
            console.log('⚠️ jQuery não disponível ainda');
        }
        </script>
        <?php
    }
    
    /**
     * Registrar rotas da API
     */
    public function register_api_routes() {
        if (class_exists('QL_API')) {
            QL_API::register_api_routes();
        }
    }
    
    /**
     * Limpeza diária
     */
    public function daily_cleanup() {
        // Limpar logs antigos
        if (class_exists('QL_Database')) {
            QL_Database::cleanup_old_logs();
        }
        
        // Atualizar estatísticas
        if (class_exists('QL_Reports')) {
            QL_Reports::update_daily_stats();
        }
        
        error_log('Quilombo Laboratório: Limpeza diária executada');
    }
    
    /**
     * Hook quando projeto GC é criado
     */
    public function on_gc_project_created($projeto_id, $projeto_data) {
        // Criar estrutura do laboratório automaticamente
        if (class_exists('QL_Project')) {
            QL_Project::create_from_gc_project($projeto_id, $projeto_data);
            error_log("Quilombo Laboratório: Estrutura criada para projeto GC {$projeto_id}");
        }
    }
    
    /**
     * Hook quando projeto GC é atualizado
     */
    public function on_gc_project_updated($projeto_id, $projeto_data) {
        // Sincronizar dados com estrutura do laboratório
        if (class_exists('QL_Project')) {
            QL_Project::sync_from_gc_project($projeto_id, $projeto_data);
        }
    }
    
    /**
     * Verificar integridade do banco
     */
    public function check_database_integrity() {
        $installed_version = get_option('ql_database_version', '0.0.0');
        
        if (version_compare($installed_version, QL_DB_VERSION, '<') || !$this->tables_exist()) {
            $this->install_or_update_database();
        }
        
        // Verificar e corrigir problemas conhecidos
        $this->auto_fix_common_issues();
    }
    
    /**
     * Corrigir problemas comuns automaticamente
     */
    private function auto_fix_common_issues() {
        global $wpdb;
        
        // 1. Corrigir status de projetos (problema identificado nos scripts)
        $updated = $wpdb->query("
            UPDATE {$wpdb->prefix}ql_projects 
            SET status = 'active' 
            WHERE (status IS NULL OR status = '') AND gc_projeto_id IS NOT NULL
        ");
        
        if ($updated > 0) {
            error_log("QL: Corrigido status de $updated projetos automaticamente");
        }
        
        // 2. Limpar cache problemático
        $this->clear_problematic_cache();
        
        // 3. Verificar permissões do usuário atual (se estiver no admin)
        if (is_admin() && current_user_can('administrator')) {
            $this->ensure_admin_capabilities();
        }
    }
    
    /**
     * Limpar cache que pode estar causando problemas
     */
    private function clear_problematic_cache() {
        global $wpdb;
        
        // Limpar transients relacionados a projetos que podem estar outdated
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ql_projects%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ql_projects%'");
        
        // Limpar object cache
        wp_cache_delete('ql_projects_all', 'quilombo_laboratorio');
        wp_cache_delete('ql_user_projects_' . get_current_user_id(), 'quilombo_laboratorio');
    }
    
    /**
     * Garantir que administradores tenham todas as capabilities necessárias
     */
    private function ensure_admin_capabilities() {
        $user = wp_get_current_user();
        
        if ($user && in_array('administrator', $user->roles)) {
            $required_caps = [
                'manage_laboratory',
                'manage_projects', 
                'view_all_projects',
                'edit_projects',
                'delete_projects',
                'manage_boards',
                'manage_tasks',
                'view_reports'
            ];
            
            $added = false;
            foreach ($required_caps as $cap) {
                if (!current_user_can($cap)) {
                    $user->add_cap($cap);
                    $added = true;
                }
            }
            
            if ($added) {
                error_log("QL: Capabilities adicionadas automaticamente para usuário " . $user->user_login);
            }
        }
    }
    
    /**
     * Verificar se tabelas principais existem
     */
    private function tables_exist() {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'ql_projects',
            $wpdb->prefix . 'ql_boards',
            $wpdb->prefix . 'ql_moodle_mappings'
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Instalar ou atualizar banco de dados
     */
    private function install_or_update_database() {
        try {
            // Incluir classe de database se não existir
            if (!class_exists('QL_Database')) {
                require_once QL_PLUGIN_PATH . 'includes/class-ql-database.php';
            }
            
            // Criar tabelas principais do QL
            $this->create_ql_tables();
            
            $installed_version = get_option('ql_database_version', '0.0.0');
            if ($installed_version === '0.0.0') {
                $this->setup_initial_data();
            }
            
            update_option('ql_database_version', QL_DB_VERSION);
            set_transient('ql_database_updated', true, 60);
            
            error_log('QL: Banco atualizado para versão ' . QL_DB_VERSION);
            
        } catch (Exception $e) {
            set_transient('ql_database_error', $e->getMessage(), 300);
            error_log('QL: Erro ao atualizar banco - ' . $e->getMessage());
        }
    }
    
    /**
     * Criar tabelas essenciais do QL (independente do GC)
     */
    private function create_ql_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela principal de projetos (independente)
        $sql1 = "CREATE TABLE {$wpdb->prefix}ql_projects (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            gc_projeto_id bigint(20) NULL,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description longtext,
            status varchar(50) DEFAULT 'active',
            visibility varchar(50) DEFAULT 'public',
            owner_id bigint(20) DEFAULT 1,
            start_date date NULL,
            end_date date NULL,
            estimated_hours int(11) DEFAULT 0,
            actual_hours int(11) DEFAULT 0,
            progress_percentage int(11) DEFAULT 0,
            priority varchar(50) DEFAULT 'normal',
            color varchar(7) DEFAULT '#3498db',
            settings longtext,
            moodle_course_id bigint(20) NULL,
            kanboard_project_id bigint(20) NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY slug (slug),
            KEY status (status),
            KEY moodle_course_id (moodle_course_id)
        ) $charset_collate;";
        
        // Mapeamentos Moodle
        $sql2 = "CREATE TABLE {$wpdb->prefix}ql_moodle_mappings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            moodle_course_id bigint(20) NOT NULL,
            course_name varchar(255) NOT NULL,
            course_shortname varchar(100) NOT NULL,
            ql_project_id bigint(20) NOT NULL,
            trilha_type varchar(50) DEFAULT 'aprendizagem',
            is_collective tinyint(1) DEFAULT 0,
            last_sync datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY moodle_course_id (moodle_course_id)
        ) $charset_collate;";
        
        // Quadros
        $sql3 = "CREATE TABLE {$wpdb->prefix}ql_boards (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description longtext,
            board_type varchar(50) DEFAULT 'kanban',
            is_default tinyint(1) DEFAULT 0,
            settings longtext,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id)
        ) $charset_collate;";
        
        // Colunas
        $sql4 = "CREATE TABLE {$wpdb->prefix}ql_columns (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            board_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            position int(11) DEFAULT 0,
            color varchar(7) DEFAULT '#3498db',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY board_id (board_id)
        ) $charset_collate;";
        
        // Tarefas
        $sql5 = "CREATE TABLE {$wpdb->prefix}ql_tasks (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            column_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description longtext,
            assigned_user_id bigint(20) NULL,
            position int(11) DEFAULT 0,
            priority varchar(50) DEFAULT 'normal',
            due_date date NULL,
            status varchar(50) DEFAULT 'open',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY column_id (column_id)
        ) $charset_collate;";
        
        // Anexos
        $sql6 = "CREATE TABLE {$wpdb->prefix}ql_attachments (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_id bigint(20) NOT NULL,
            filename varchar(255) NOT NULL,
            filepath varchar(500) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY task_id (task_id)
        ) $charset_collate;";
        
        // Membros do projeto
        $sql7 = "CREATE TABLE {$wpdb->prefix}ql_project_members (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            role varchar(50) DEFAULT 'member',
            permissions longtext,
            joined_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY project_user (project_id, user_id)
        ) $charset_collate;";
        
        // Metadados
        $sql8 = "CREATE TABLE {$wpdb->prefix}ql_project_meta (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            UNIQUE KEY project_meta (project_id, meta_key)
        ) $charset_collate;";
        
        // Trilha mappings
        $sql9 = "CREATE TABLE {$wpdb->prefix}ql_trilha_mappings (
            gc_projeto_id bigint(20) NOT NULL,
            trilha_term_id bigint(20) DEFAULT 0,
            wp_category_id bigint(20) DEFAULT 0,
            wp_page_id bigint(20) DEFAULT 0,
            ql_project_id bigint(20) DEFAULT 0,
            data_sync datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (gc_projeto_id)
        ) $charset_collate;";
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
        dbDelta($sql6);
        dbDelta($sql7);
        dbDelta($sql8);
        dbDelta($sql9);
    }
    
    /**
     * Configurar dados iniciais - NOVA LÓGICA UNIFICADA
     */
    private function setup_initial_data() {
        global $wpdb;
        
        // NOVA LÓGICA: Detectar automaticamente trilha ID 1 e criar projeto coletivo correspondente
        // Manter compatibilidade com configuração legacy
        if (!get_option('quilombo_laboratorio_trilha_coletivo')) {
            update_option('quilombo_laboratorio_trilha_coletivo', 1);
            error_log('QL: Auto-detecção - Trilha ID 1 (Site Principal) configurada como trilha do coletivo');
        }
        
        // CONCEITO CORRETO: Criar projeto coletivo inicial (correspondente à trilha ID 1)
        $existing = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}ql_projects 
             WHERE slug = 'quilombo-ciencia-coletivo' LIMIT 1"
        );
        
        if ($existing) {
            // Se projeto já existe, garantir que está marcado como coletivo
            $settings = $wpdb->get_var($wpdb->prepare(
                "SELECT settings FROM {$wpdb->prefix}ql_projects WHERE id = %d",
                $existing
            ));
            
            $settings_array = json_decode($settings, true) ?: [];
            if (!isset($settings_array['is_collective_project'])) {
                $settings_array['is_collective_project'] = true;
                $wpdb->update(
                    $wpdb->prefix . 'ql_projects',
                    ['settings' => json_encode($settings_array)],
                    ['id' => $existing],
                    ['%s'],
                    ['%d']
                );
                error_log('QL: Projeto coletivo existente marcado corretamente');
            }
            
            // Configurar este projeto como coletivo nas configurações unificadas
            $current_settings = get_option('quilombo_laboratorio_settings', []);
            if (!isset($current_settings['projeto_coletivo_id']) || $current_settings['projeto_coletivo_id'] == 0) {
                $current_settings['projeto_coletivo_id'] = $existing;
                update_option('quilombo_laboratorio_settings', $current_settings);
                error_log("QL: Projeto existente ID {$existing} configurado como projeto do coletivo");
            }
            
            return;
        }
        
        // Criar projeto coletivo inicial (correspondente à trilha ID 1 quando sincronizada)
        $wpdb->insert(
            $wpdb->prefix . 'ql_projects',
            [
                'name' => 'Quilombo Ciência - Projeto do Coletivo',
                'slug' => 'quilombo-ciencia-coletivo',
                'description' => 'Projeto principal do coletivo Quilombo Ciência (correspondente à trilha do site principal)',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => QL_Config::get_default_admin_id(),
                'priority' => 'high',
                'color' => '#27ae60',
                'moodle_course_id' => null, // Será preenchido na sincronização
                'settings' => json_encode(['is_collective_project' => true])
            ]
        );
        
        $project_id = $wpdb->insert_id;
        
        // Configurar automaticamente nas configurações unificadas
        $current_settings = get_option('quilombo_laboratorio_settings', []);
        $current_settings['projeto_coletivo_id'] = $project_id;
        update_option('quilombo_laboratorio_settings', $current_settings);
        
        error_log("QL: Projeto coletivo inicial criado - ID: {$project_id} - Auto-configurado como projeto do coletivo");
        
        // Criar quadro padrão
        $wpdb->insert(
            $wpdb->prefix . 'ql_boards',
            [
                'project_id' => $project_id,
                'name' => 'Organização do Coletivo',
                'description' => 'Quadro principal do coletivo',
                'board_type' => 'kanban',
                'is_default' => 1
            ]
        );
        
        $board_id = $wpdb->insert_id;
        
        // Criar colunas
        $colunas = [
            ['name' => 'Planejamento', 'color' => '#e74c3c'],
            ['name' => 'Em Andamento', 'color' => '#f39c12'],
            ['name' => 'Revisão', 'color' => '#3498db'],
            ['name' => 'Concluído', 'color' => '#27ae60']
        ];
        
        foreach ($colunas as $i => $coluna) {
            $wpdb->insert(
                $wpdb->prefix . 'ql_columns',
                [
                    'board_id' => $board_id,
                    'name' => $coluna['name'],
                    'position' => $i,
                    'color' => $coluna['color']
                ]
            );
        }
    }
    
    /**
     * Inicialização do admin
     */
    public function admin_init() {
        // Verificar se precisa executar sincronização inicial
        if (get_option('ql_needs_initial_sync', false)) {
            $this->trigger_initial_sync();
            delete_option('ql_needs_initial_sync');
        }
    }
    
    /**
     * Disparar sincronização inicial
     */
    private function trigger_initial_sync() {
        if (class_exists('QL_Moodle_Integration')) {
            try {
                $integration = QL_Moodle_Integration::get_instance();
                $integration->sync_courses_from_moodle();
                set_transient('ql_initial_sync_success', true, 300);
            } catch (Exception $e) {
                error_log('QL: Erro na sincronização inicial: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Avisos administrativos
     */
    public function admin_notices() {
        if (get_transient('ql_database_updated')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Quilombo Laboratório:</strong> Banco de dados atualizado com sucesso!</p>';
            echo '</div>';
            delete_transient('ql_database_updated');
        }
        
        if (get_transient('ql_initial_sync_success')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>Quilombo Laboratório:</strong> Sincronização inicial com Moodle concluída!</p>';
            echo '</div>';
            delete_transient('ql_initial_sync_success');
        }
        
        if ($error = get_transient('ql_database_error')) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Quilombo Laboratório:</strong> Erro no banco: ' . esc_html($error) . '</p>';
            echo '</div>';
            delete_transient('ql_database_error');
        }
    }
    
    /**
     * Hook de atualização do plugin
     */
    public function on_plugin_update($upgrader_object, $options) {
        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, 'quilombo-lab') !== false) {
                    $this->install_or_update_database();
                    update_option('ql_needs_initial_sync', true);
                    break;
                }
            }
        }
    }
    
    /**
     * Ativação do plugin
     */
    public function activate() {
        // Instalar/atualizar banco
        $this->install_or_update_database();
        
        // Criar páginas padrão se necessário
        $this->create_default_pages();
        
        // Configurar permissões
        $this->setup_capabilities();
        
        // Agendar sincronização inicial
        update_option('ql_needs_initial_sync', true);
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('Quilombo Laboratório: Plugin ativado com sucesso');
    }
    
    /**
     * Desativação do plugin
     */
    public function deactivate() {
        // Remover cron jobs
        wp_clear_scheduled_hook('ql_daily_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        error_log('Quilombo Laboratório: Plugin desativado');
    }
    
    /**
     * Criar páginas padrão
     */
    private function create_default_pages() {
        // Página do laboratório principal
        $laboratorio_page = get_page_by_path('laboratorio');
        if (!$laboratorio_page) {
            wp_insert_post([
                'post_title' => 'Laboratório de Projetos',
                'post_name' => 'laboratorio',
                'post_content' => '[ql_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            ]);
        }
    }
    
    /**
     * Configurar permissões
     */
    private function setup_capabilities() {
        $admin_role = get_role('administrator');
        $editor_role = get_role('editor');
        $author_role = get_role('author');
        
        // Lista completa de capabilities necessárias
        $all_capabilities = [
            'manage_laboratory',
            'manage_projects', 
            'view_all_projects',
            'edit_projects',
            'delete_projects',
            'manage_boards',
            'manage_tasks',
            'view_reports',
            'ql_manage_all_projects',
            'ql_manage_settings',
            'ql_view_reports',
            'ql_manage_users'
        ];
        
        // Capabilities para administradores (todas)
        if ($admin_role) {
            foreach ($all_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Capabilities para editores
        if ($editor_role) {
            $editor_caps = [
                'manage_projects',
                'view_all_projects',
                'edit_projects',
                'manage_boards',
                'manage_tasks',
                'view_reports',
                'ql_manage_projects',
                'ql_view_reports'
            ];
            foreach ($editor_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
        
        // Capabilities para autores
        if ($author_role) {
            $author_caps = [
                'view_all_projects',
                'manage_tasks',
                'ql_create_tasks',
                'ql_edit_own_tasks'
            ];
            foreach ($author_caps as $cap) {
                $author_role->add_cap($cap);
            }
        }
    }
}

// Inicializar plugin
QuilomboLaboratorio::get_instance();

// Funções utilitárias globais
function ql_get_project($project_id) {
    if (class_exists('QL_Project')) {
        return QL_Project::get_by_id($project_id);
    }
    return null;
}

function ql_get_current_user_projects() {
    if (class_exists('QL_Project')) {
        return QL_Project::get_user_projects(get_current_user_id());
    }
    return [];
}

function ql_create_task($project_id, $task_data) {
    if (class_exists('QL_Task')) {
        return QL_Task::create($project_id, $task_data);
    }
    return null;
}

function ql_get_project_board($project_id) {
    if (class_exists('QL_Board')) {
        return QL_Board::get_by_project($project_id);
    }
    return null;
}