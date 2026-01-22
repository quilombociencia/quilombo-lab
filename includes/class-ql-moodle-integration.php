<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para integração com Moodle - Sincronização de Trilhas
 */
class QL_Moodle_Integration {
    
    private static $instance = null;
    
    // Configurações da API Moodle
    private $moodle_url;
    private $moodle_token;
    private $api_endpoint;
    
    // Mapeamento de cursos -> projetos
    const TIPOS_TRILHA_MOODLE = [
        'trilha-aprendizagem' => 'aprendizagem',
        'trilha-pesquisa' => 'pesquisa',
        'trilha-criacao' => 'criacao',
        'learning' => 'aprendizagem',
        'research' => 'pesquisa',
        'creation' => 'criacao'
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ql_sync_moodle_courses', [$this, 'ajax_sync_courses']);
        add_action('wp_ajax_ql_test_moodle_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ql_sync_all_project_members', [$this, 'ajax_sync_all_project_members']);
        add_action('wp_ajax_ql_get_all_groups', [$this, 'ajax_get_all_groups']);
        add_action('wp_ajax_ql_get_all_groupings', [$this, 'ajax_get_all_groupings']);
        add_action('wp_ajax_ql_detect_moodle_roles', [$this, 'ajax_detect_moodle_roles']);
        add_action('wp_ajax_ql_sync_project_roles', [$this, 'ajax_sync_project_roles']);
        add_action('wp_ajax_ql_import_course_roles', [$this, 'ajax_import_course_roles']);
        add_action('wp_ajax_ql_import_all_course_roles', [$this, 'ajax_import_all_course_roles']);
        add_action('wp_ajax_ql_update_active_roles', [$this, 'ajax_update_active_roles']);
        
        // Cron job para sincronização automática
        add_action('ql_moodle_sync', [$this, 'scheduled_sync']);
        
        // Hook para sincronização automática de instâncias quando configurações são atualizadas
        add_action('ql_moodle_settings_updated', [$this, 'sync_instances_from_moodle']);
        
        // Hook para sincronização de membros com delay
        add_action('ql_sync_project_members_delayed', [$this, 'sync_project_members_delayed_handler'], 10, 2);
        
        // Hook para quando configurações do Moodle são atualizadas
        add_action('update_option_quilombo_laboratorio_settings', [$this, 'on_settings_updated'], 10, 2);
        
        // Hooks desabilitados - círculos/núcleos são importados do Moodle, não criados pelo plugin
        // add_action('ql_circle_created', [$this, 'on_circle_created'], 10, 2);
        // add_action('ql_nucleus_created', [$this, 'on_nucleus_created'], 10, 2);
        
        // Hooks para sincronização de papéis organizativos
        add_action('ql_organizational_role_assigned', [$this, 'on_organizational_role_assigned'], 10, 4);
        add_action('ql_organizational_role_removed', [$this, 'on_organizational_role_removed'], 10, 4);
    }
    
    public function init() {
        // Carregar configurações
        $this->load_settings();
        
        // Registrar cron job se não existir
        if (!wp_next_scheduled('ql_moodle_sync')) {
            wp_schedule_event(time(), 'daily', 'ql_moodle_sync');
        }
        
        // Detectar papéis no Moodle se ainda não foi feito
        if (empty(get_option('ql_moodle_roles_config', []))) {
            $this->detect_moodle_organizational_roles();
        }
        
        // Adicionar menu admin se necessário
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
    }
    
    /**
     * Carregar configurações do Moodle
     */
    private function load_settings() {
        $moodle_settings = QL_Config::get_moodle_settings();
        
        $this->moodle_url = rtrim($moodle_settings['url'] ?: '', '/');
        $this->moodle_token = $moodle_settings['token'] ?: '';
        
        if (!empty($this->moodle_url)) {
            $this->api_endpoint = $this->moodle_url . '/webservice/rest/server.php';
        } else {
            $this->api_endpoint = '';
            error_log('QL Moodle Integration: URL do Moodle não configurada');
        }
    }
    
    /**
     * Adicionar submenu para Moodle
     */
    public function add_admin_menu() {
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'quilombo-lab',
                __('Integração Moodle', 'quilombo-lab'),
                __('🎓 Moodle', 'quilombo-lab'),
                'manage_options',
                'quilombo-lab-moodle',
                [$this, 'moodle_admin_page']
            );
        }
    }
    
    /**
     * Página administrativa do Moodle
     */
    public function moodle_admin_page() {
        $connection_status = $this->test_connection();
        
        ?>
        <div class="wrap">
            <h1><?php _e('Integração com Moodle', 'quilombo-lab'); ?></h1>
            
            <div class="ql-moodle-status">
                <h2><?php _e('Status da Conexão', 'quilombo-lab'); ?></h2>
                
                <?php if ($connection_status['success']): ?>
                    <div class="notice notice-success">
                        <p>✅ <strong><?php _e('Conectado ao Moodle', 'quilombo-lab'); ?></strong></p>
                        <p><?php printf(__('Site: %s', 'quilombo-lab'), esc_url($this->moodle_url)); ?></p>
                        <p><?php printf(__('Versão: %s', 'quilombo-lab'), esc_html($connection_status['data']['sitename'] ?? 'N/A')); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-error">
                        <p>❌ <strong><?php _e('Erro na conexão com Moodle', 'quilombo-lab'); ?></strong></p>
                        <p><?php echo esc_html($connection_status['message']); ?></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-settings'); ?>" class="button">
                                <?php _e('Verificar Configurações', 'quilombo-lab'); ?>
                            </a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($connection_status['success']): ?>
                <div class="ql-moodle-actions">
                    <h2><?php _e('Sincronização de Trilhas', 'quilombo-lab'); ?></h2>
                    
                    <div class="ql-sync-actions">
                        <button id="ql-test-connection" class="button">
                            <?php _e('🔄 Testar Conexão', 'quilombo-lab'); ?>
                        </button>
                        
                        <button id="ql-sync-courses" class="button button-primary">
                            <?php _e('📥 Sincronizar Cursos/Trilhas', 'quilombo-lab'); ?>
                        </button>
                        
                        <button id="ql-sync-all-members" class="button button-secondary">
                            <?php _e('👥 Sincronizar Todos os Membros', 'quilombo-lab'); ?>
                        </button>
                        
                        <button id="ql-view-courses" class="button">
                            <?php _e('👀 Ver Cursos Disponíveis', 'quilombo-lab'); ?>
                        </button>
                    </div>
                    
                    <div id="ql-sync-results" style="margin-top: 20px;"></div>
                </div>
                
                <div class="ql-moodle-courses">
                    <h2><?php _e('Cursos Mapeados', 'quilombo-lab'); ?></h2>
                    <?php $this->display_mapped_courses(); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ql-sync-actions {
            margin: 20px 0;
        }
        
        .ql-sync-actions .button {
            margin-right: 10px;
        }
        
        .ql-moodle-courses table {
            margin-top: 15px;
        }
        
        .ql-course-actions {
            white-space: nowrap;
        }
        
        #ql-sync-results {
            background: #f1f1f1;
            border-left: 4px solid #0073aa;
            padding: 15px;
            display: none;
        }
        
        #ql-sync-results.success {
            border-left-color: #00a32a;
            background: #f0fff4;
        }
        
        #ql-sync-results.error {
            border-left-color: #d63638;
            background: #ffeaed;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#ql-test-connection').on('click', function() {
                var $btn = $(this);
                var $results = $('#ql-sync-results');
                
                $btn.prop('disabled', true).text('Testando...');
                $results.removeClass('success error').show().html('Testando conexão com Moodle...');
                
                $.post(ajaxurl, {
                    action: 'ql_test_moodle_connection',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('success').html('<strong>✅ Conectado!</strong><br>' + response.data.message);
                    } else {
                        $results.addClass('error').html('<strong>❌ Erro:</strong> ' + response.data);
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('🔄 Testar Conexão');
                });
            });
            
            $('#ql-sync-courses').on('click', function() {
                var $btn = $(this);
                var $results = $('#ql-sync-results');
                
                if (!confirm('Deseja sincronizar os cursos do Moodle? Isso pode criar novos projetos no Laboratório.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Sincronizando...');
                $results.removeClass('success error').show().html('Buscando cursos do Moodle e criando projetos...');
                
                $.post(ajaxurl, {
                    action: 'ql_sync_moodle_courses',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('success').html('<strong>✅ Sincronização completa!</strong><br>' + response.data.message);
                        // Recarregar página após sucesso para mostrar novos projetos
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $results.addClass('error').html('<strong>❌ Erro na sincronização:</strong> ' + response.data);
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('📥 Sincronizar Cursos/Trilhas');
                });
            });
            
            $('#ql-sync-all-members').on('click', function() {
                var $btn = $(this);
                var $results = $('#ql-sync-results');
                
                if (!confirm('Deseja sincronizar os membros de todos os projetos Moodle? Isso pode levar alguns minutos.')) {
                    return;
                }
                
                $btn.prop('disabled', true).text('Sincronizando membros...');
                $results.removeClass('success error').show().html('Buscando participantes dos cursos Moodle e sincronizando membros dos projetos...');
                
                $.post(ajaxurl, {
                    action: 'ql_sync_all_project_members',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('success').html('<strong>✅ Sincronização de membros completa!</strong><br>' + response.data.message);
                    } else {
                        $results.addClass('error').html('<strong>❌ Erro na sincronização de membros:</strong> ' + response.data);
                    }
                }).always(function() {
                    $btn.prop('disabled', false).text('👥 Sincronizar Todos os Membros');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Exibir cursos mapeados
     */
    private function display_mapped_courses() {
        global $wpdb;
        
        $mapped_courses = $wpdb->get_results(
            "SELECT mm.*, p.name as project_name, p.status as project_status, p.settings as project_settings
             FROM {$wpdb->prefix}ql_moodle_mappings mm 
             LEFT JOIN {$wpdb->prefix}ql_projects p ON mm.ql_project_id = p.id 
             ORDER BY mm.is_collective DESC, mm.course_name ASC"
        );
        
        if (empty($mapped_courses)) {
            echo '<p><em>' . __('Nenhum curso foi sincronizado ainda.', 'quilombo-lab') . '</em></p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Curso Moodle', 'quilombo-lab'); ?></th>
                    <th><?php _e('Projeto Laboratório', 'quilombo-lab'); ?></th>
                    <th><?php _e('Tipo', 'quilombo-lab'); ?></th>
                    <th><?php _e('Status', 'quilombo-lab'); ?></th>
                    <th><?php _e('Última Sync', 'quilombo-lab'); ?></th>
                    <th><?php _e('Ações', 'quilombo-lab'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mapped_courses as $course): 
                    $project_settings = json_decode($course->project_settings, true);
                    $is_virtual = isset($project_settings['is_virtual_course']) && $project_settings['is_virtual_course'];
                    $limited_access = isset($project_settings['limited_access']) && $project_settings['limited_access'];
                ?>
                    <tr<?php echo $course->is_collective ? ' class="ql-collective-course"' : ''; ?>>
                        <td>
                            <strong><?php echo esc_html($course->course_name); ?></strong>
                            <?php if ($course->is_collective): ?>
                                <span class="ql-collective-badge">🏛️ PROJETO COLETIVO</span>
                            <?php endif; ?>
                            <?php if ($limited_access): ?>
                                <span class="ql-limited-access-badge">⚠️ ACESSO LIMITADO</span>
                            <?php endif; ?>
                            <br><small>ID: <?php echo $course->moodle_course_id; ?></small>
                        </td>
                        <td>
                            <?php if ($course->project_name): ?>
                                <a href="<?php echo admin_url('admin.php?page=quilombo-lab-project-boards&project_id=' . $course->ql_project_id); ?>">
                                    <?php echo esc_html($course->project_name); ?>
                                </a>
                            <?php else: ?>
                                <em><?php _e('Projeto não encontrado', 'quilombo-lab'); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ql-trilha-tipo ql-tipo-<?php echo esc_attr($course->trilha_type); ?>">
                                <?php echo esc_html(ucfirst($course->trilha_type)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($course->project_status): ?>
                                <span class="ql-status-badge ql-status-<?php echo esc_attr($course->project_status); ?>">
                                    <?php echo esc_html(ql_translate_project_status($course->project_status)); ?>
                                </span>
                            <?php else: ?>
                                <span class="ql-status-badge ql-status-error">Erro</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date_i18n('d/m/Y H:i', strtotime($course->last_sync)); ?>
                        </td>
                        <td class="ql-course-actions">
                            <button class="button button-small ql-resync-course" data-course-id="<?php echo $course->moodle_course_id; ?>">
                                🔄 Re-sync
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <style>
        .ql-trilha-tipo {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ql-tipo-aprendizagem { background: #e3f2fd; color: #1976d2; }
        .ql-tipo-pesquisa { background: #f3e5f5; color: #7b1fa2; }
        .ql-tipo-criacao { background: #fff3e0; color: #f57c00; }
        
        .ql-collective-course {
            background-color: #f8fff8 !important;
            border-left: 4px solid #27ae60;
        }
        
        .ql-collective-badge {
            background: #27ae60;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 8px;
            display: inline-block;
        }
        
        .ql-limited-access-badge {
            background: #f39c12;
            color: white;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 4px;
            display: inline-block;
        }
        </style>
        <?php
    }
    
    /**
     * Testar conexão com Moodle via AJAX
     */
    public function ajax_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce') || !current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        
        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Conexão estabelecida com sucesso! Site: ' . ($result['data']['sitename'] ?? 'Moodle')
            ]);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Sincronizar cursos via AJAX
     */
    public function ajax_sync_courses() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce') || !current_user_can('manage_options')) {
            wp_die('Acesso negado');
        }
        
        try {
            $result = $this->sync_courses_from_moodle();
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => sprintf(
                        'Sincronizados %d cursos. %d projetos criados, %d atualizados.',
                        $result['courses_processed'],
                        $result['projects_created'],
                        $result['projects_updated']
                    )
                ]);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Erro na sincronização: ' . $e->getMessage());
        }
    }
    
    /**
     * Testar conexão com Moodle
     */
    public function test_connection() {
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            return [
                'success' => false,
                'message' => 'URL do Moodle ou token não configurados. Verifique as configurações do plugin.'
            ];
        }
        
        try {
            $response = $this->call_moodle_api('core_webservice_get_site_info');
            
            if ($response && isset($response['sitename'])) {
                return [
                    'success' => true,
                    'site_name' => $response['sitename'],
                    'version' => $response['release'] ?? '',
                    'data' => $response,
                    'message' => 'Conexão estabelecida com sucesso'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida do Moodle. Verifique o token de API.'
                ];
            }
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            
            // Se é erro HTTP 500, tentar diagnóstico e correção automática
            if (strpos($error_message, 'HTTP Error 500') !== false || strpos($error_message, 'Erro interno do servidor Moodle') !== false) {
                $diagnostic = $this->diagnose_moodle_issue();
                return [
                    'success' => false,
                    'message' => 'Erro no servidor Moodle: ' . $error_message . '\n\nDiagnóstico: ' . $diagnostic['message'],
                    'diagnostic' => $diagnostic
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Erro ao conectar: ' . $error_message
            ];
        }
    }
    
    /**
     * Diagnosticar problemas comuns do Moodle
     */
    private function diagnose_moodle_issue() {
        $moodle_path = str_replace('/webservice/rest/server.php', '', $this->api_endpoint);
        $moodle_path = str_replace('http://localhost', '/var/www/html', $moodle_path);
        
        $issues = [];
        $solutions = [];
        
        // Verificar se existe diretório de cache
        $cache_path = $moodle_path . '/cache/classes';
        if (!is_dir($cache_path)) {
            $issues[] = 'Diretório de cache não existe';
            $solutions[] = 'Criar diretório de cache';
        } else {
            // Verificar se cache está vazio ou corrompido
            $cache_files = glob($cache_path . '/*.php');
            if (empty($cache_files)) {
                $issues[] = 'Cache vazio ou corrompido';
                $solutions[] = 'Regenerar cache do Moodle';
            }
        }
        
        // Verificar arquivo de configuração
        $config_path = $moodle_path . '/config.php';
        if (!file_exists($config_path)) {
            $issues[] = 'Arquivo config.php não encontrado';
        } else {
            $config_content = file_get_contents($config_path);
            if (strpos($config_content, '$CFG->debug') !== false) {
                $solutions[] = 'Modo debug ativado - verificar logs para mais detalhes';
            }
        }
        
        // Verificar logs de erro recentes
        $error_log = '/var/log/apache2/error.log';
        if (file_exists($error_log)) {
            $recent_errors = shell_exec("tail -50 $error_log | grep -i moodle | grep 'Fatal error\\|PHP Error' | tail -3");
            if (!empty($recent_errors)) {
                $issues[] = 'Erros PHP detectados nos logs';
                $solutions[] = 'Verificar logs: ' . trim($recent_errors);
            }
        }
        
        if (empty($issues)) {
            $issues[] = 'Problema não identificado automaticamente';
            $solutions[] = 'Verifique manualmente os logs do Moodle e configurações do servidor web';
        }
        
        return [
            'issues' => $issues,
            'solutions' => $solutions,
            'message' => implode('. ', $issues) . '. Soluções: ' . implode('; ', $solutions)
        ];
    }
    
    /**
     * Sincronizar cursos do Moodle
     */
    public function sync_courses_from_moodle() {
        error_log('QL Moodle Sync: Iniciando sincronização');
        
        // Garantir que as tabelas estão criadas
        $this->ensure_mappings_table();
        $this->ensure_meta_table();
        
        $connection_test = $this->test_connection();
        if (!$connection_test['success']) {
            error_log('QL Moodle Sync: Falha na conexão: ' . $connection_test['message']);
            throw new Exception('Não foi possível conectar ao Moodle: ' . $connection_test['message']);
        }
        
        error_log('QL Moodle Sync: Conexão estabelecida, buscando cursos');
        
        // Buscar todos os cursos
        $courses = $this->get_moodle_courses();
        
        error_log('QL Moodle Sync: Encontrados ' . count($courses) . ' cursos');
        
        if (empty($courses)) {
            error_log('QL Moodle Sync: Nenhum curso encontrado - verificar permissões do usuário API');
            throw new Exception('Nenhum curso encontrado no Moodle - verificar permissões do usuário da API');
        }
        
        $stats = [
            'courses_processed' => 0,
            'projects_created' => 0,
            'projects_updated' => 0,
            'errors' => []
        ];
        
        foreach ($courses as $course) {
            try {
                // Verificar se é um curso virtual (criado quando não temos acesso ao real)
                $is_virtual = isset($course['_is_virtual_site_course']);
                
                if ($is_virtual) {
                    error_log("QL Moodle Sync: Processando curso virtual do site principal (acesso limitado)");
                }
                
                $result = $this->process_moodle_course($course);
                
                if ($result['created']) {
                    $stats['projects_created']++;
                } else {
                    $stats['projects_updated']++;
                }
                
                $stats['courses_processed']++;
                
                // Log especial para projeto coletivo
                if (isset($result['is_collective']) && $result['is_collective']) {
                    error_log("QL Moodle Sync: Projeto coletivo criado/atualizado - ID: {$result['project_id']}");
                }
                
            } catch (Exception $e) {
                $stats['errors'][] = "Curso {$course['fullname']}: " . $e->getMessage();
                error_log("QL Moodle Sync: Erro ao processar curso {$course['id']}: " . $e->getMessage());
            }
        }
        
        // Verificar se conseguimos identificar/criar trilha coletiva
        $collective_project = $this->get_collective_project();
        if (!$collective_project) {
            // Tentar identificar por padrões alternativos
            $collective_course = $this->identify_collective_trail_by_pattern($courses);
            if (!$collective_course) {
                // Criar trilha coletiva manualmente
                error_log("QL Moodle Sync: Trilha coletiva não encontrada, criando manualmente...");
                $this->create_manual_collective_trail();
            }
        }
        
        // Log de resultado
        error_log("QL Moodle Sync: Processados {$stats['courses_processed']} cursos. Criados: {$stats['projects_created']}, Atualizados: {$stats['projects_updated']}, Erros: " . count($stats['errors']));
        
        return [
            'success' => true,
            'courses_processed' => $stats['courses_processed'],
            'projects_created' => $stats['projects_created'],
            'projects_updated' => $stats['projects_updated'],
            'errors' => $stats['errors']
        ];
    }
    
    /**
     * Obter todos os cursos do Moodle (incluindo curso do site)
     * Método público para uso na interface administrativa
     */
    public function get_cursos_moodle() {
        try {
            $courses = $this->call_moodle_api('core_course_get_courses');
            
            if (!$courses || !is_array($courses)) {
                return [];
            }
            
            // Incluir todos os cursos (incluindo o curso do site ID 1)
            return $courses;
            
        } catch (Exception $e) {
            error_log('Quilombo Laboratório: Erro ao buscar cursos do Moodle: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter cursos do Moodle (método interno)
     */
    private function get_moodle_courses() {
        $courses = $this->call_moodle_api('core_course_get_courses');
        
        if (!$courses || !is_array($courses)) {
            throw new Exception('Erro ao buscar cursos do Moodle');
        }
        
        // Verificar se conseguimos acessar o curso principal (ID 1)
        $site_course_accessible = $this->check_site_course_access();
        
        if (!$site_course_accessible) {
            // Se não consegue acessar o curso principal, filtrar apenas os acessíveis
            $courses = array_filter($courses, function($course) {
                return $course['id'] != 1;
            });
            
            // Criar entrada manual para o curso principal baseada nas informações do site
            $site_course = $this->create_site_course_entry();
            if ($site_course) {
                array_unshift($courses, $site_course);
            }
            
            return $courses;
        }
        
        // Se tem acesso ao curso principal, retornar todos os cursos
        return $courses;
    }
    
    /**
     * Processar um curso específico do Moodle
     */
    private function process_moodle_course($course) {
        error_log("QL Moodle: Processando curso ID {$course['id']}: {$course['fullname']}");
        
        // Log para verificar se summary está sendo recebido
        $summary = $course['summary'] ?? '';
        $summary_preview = strlen($summary) > 100 ? substr($summary, 0, 100) . '...' : $summary;
        error_log("QL Moodle: Descrição do curso (summary): " . ($summary ? "'{$summary_preview}'" : 'VAZIA'));
        
        // Determinar tipo de trilha baseado no nome/categoria
        $trilha_type = $this->detect_trilha_type($course);
        
        // Verificar se é o projeto coletivo (apenas um por vez)
        $trilha_coletivo_id = get_option('quilombo_laboratorio_trilha_coletivo', 1);
        $is_collective_course = ($course['id'] == $trilha_coletivo_id);
        
        // Log da detecção
        if ($is_collective_course) {
            error_log("QL Moodle: Curso {$course['id']} identificado como trilha coletiva (projeto único do coletivo)");
            
            // Garantir que apenas este seja coletivo - remover outros
            $this->ensure_single_collective_project($course['id']);
        }
        
        // Verificar se já existe projeto mapeado
        $existing_mapping = $this->get_course_mapping($course['id']);
        
        if ($existing_mapping) {
            error_log("QL Moodle: Curso {$course['id']} já mapeado para projeto {$existing_mapping->ql_project_id}");
            
            // Verificar se o projeto ainda existe
            global $wpdb;
            $project_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ql_projects WHERE id = %d",
                $existing_mapping->ql_project_id
            ));
            
            if ($project_exists) {
                // Atualizar projeto existente
                $project_id = $this->update_project_from_course($course, $trilha_type, $existing_mapping->ql_project_id);
                $this->update_course_mapping($course['id'], $course, $project_id, $trilha_type);
                
                return ['created' => false, 'project_id' => $project_id];
            } else {
                error_log("QL Moodle: Projeto {$existing_mapping->ql_project_id} não existe mais, removendo mapeamento e criando novo");
                // Projeto foi deletado, remover mapeamento e criar novo
                $wpdb->delete($wpdb->prefix . 'ql_moodle_mappings', ['moodle_course_id' => $course['id']]);
                $existing_mapping = null;
            }
        }
        
        if (!$existing_mapping) {
            error_log("QL Moodle: Criando novo projeto para curso {$course['id']}: {$course['fullname']}");
            
            // Criar novo projeto
            $project_id = $this->create_project_from_course($course, $trilha_type, $is_collective_course);
            
            if ($project_id) {
                error_log("QL Moodle: Projeto criado com ID {$project_id}");
                $this->create_course_mapping($course['id'], $course, $project_id, $trilha_type, $is_collective_course);
                
                // Se é trilha coletiva, configurar adequadamente
                if ($is_collective_course) {
                    $this->configure_as_collective_trail($project_id, $course);
                }
                
                return ['created' => true, 'project_id' => $project_id, 'is_collective' => $is_collective_course];
            } else {
                error_log("QL Moodle: Falha ao criar projeto para curso {$course['id']}");
                throw new Exception("Falha ao criar projeto para curso {$course['fullname']}");
            }
        }
    }
    
    /**
     * Garantir que apenas um projeto coletivo exista
     */
    private function ensure_single_collective_project($new_collective_course_id) {
        global $wpdb;
        
        // Remover marcação de coletivo de todos os outros mapeamentos
        $wpdb->update(
            $wpdb->prefix . 'ql_moodle_mappings',
            ['is_collective' => 0],
            ['is_collective' => 1],
            ['%d'],
            ['%d']
        );
        
        // Remover marcação de coletivo de todos os projetos
        $wpdb->query("
            UPDATE {$wpdb->prefix}ql_projects 
            SET settings = JSON_REMOVE(settings, '$.is_collective_project')
            WHERE JSON_EXTRACT(settings, '$.is_collective_project') = true
        ");
        
        error_log("QL Moodle: Removidas marcações de coletivo anteriores, preparando para novo coletivo: curso {$new_collective_course_id}");
        
        // Marcar novo mapeamento como coletivo
        $wpdb->update(
            $wpdb->prefix . 'ql_moodle_mappings',
            ['is_collective' => 1],
            ['moodle_course_id' => $new_collective_course_id],
            ['%d'],
            ['%d']
        );
        
        // Buscar e marcar projeto correspondente como coletivo
        $project_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ql_project_id FROM {$wpdb->prefix}ql_moodle_mappings WHERE moodle_course_id = %d",
            $new_collective_course_id
        ));
        
        if ($project_id) {
            // Buscar settings atuais do projeto
            $current_settings = $wpdb->get_var($wpdb->prepare(
                "SELECT settings FROM {$wpdb->prefix}ql_projects WHERE id = %d",
                $project_id
            ));
            
            $settings = json_decode($current_settings, true) ?: [];
            $settings['is_collective_project'] = true;
            
            $wpdb->update(
                $wpdb->prefix . 'ql_projects',
                ['settings' => json_encode($settings)],
                ['id' => $project_id],
                ['%s'],
                ['%d']
            );
            
            error_log("QL Moodle: Projeto {$project_id} marcado como coletivo único");
        }
    }
    
    /**
     * Detectar tipo de trilha baseado no curso
     */
    private function detect_trilha_type($course) {
        error_log("QL Moodle Debug: Detectando tipo para curso {$course['id']}: {$course['fullname']} (categoria: {$course['categoryid']})");
        
        // Verificar se é a trilha do site principal (sempre tipo Criação)
        $trilha_coletivo_id = get_option('quilombo_laboratorio_trilha_coletivo', 1);
        if ($course['id'] == 1 || $course['id'] == $trilha_coletivo_id) {
            error_log("QL Moodle Debug: Trilha do site identificada - tipo: criacao");
            return 'criacao';
        }
        
        // Primeiro verificar se existe informação de categoria
        $category_type = $this->detect_type_by_category($course);
        error_log("QL Moodle Debug: Tipo por categoria: {$category_type}");
        
        if ($category_type !== 'aprendizagem') {
            return $category_type;
        }
        
        // Buscar por palavras-chave no nome
        $name = strtolower($course['fullname'] . ' ' . $course['shortname']);
        
        if (preg_match('/pesquisa|research|investigação/', $name)) {
            error_log("QL Moodle Debug: Tipo detectado por nome: pesquisa");
            return 'pesquisa';
        }
        
        if (preg_match('/criação|creation|design|arte|música/', $name)) {
            error_log("QL Moodle Debug: Tipo detectado por nome: criacao");
            return 'criacao';
        }
        
        // Padrão é aprendizagem
        return 'aprendizagem';
    }
    
    /**
     * Detectar tipo da trilha baseado na categoria do curso Moodle
     */
    private function detect_type_by_category($course) {
        error_log("QL Moodle Debug: --- INICIANDO DETECÇÃO POR CATEGORIA ---");
        
        // Usar apenas categoryid da API (campo sempre presente)
        $category_id = $course['categoryid'] ?? null;
        error_log("QL Moodle Debug: Campo categoryid: " . ($course['categoryid'] ?? 'NULL'));
        error_log("QL Moodle Debug: ID da categoria final: " . ($category_id ?? 'NULL'));
        
        // Se não tem category ID, usar detecção por nome
        if (!$category_id || $category_id <= 1) {
            error_log("QL Moodle Debug: Categoria não disponível ou é categoria raiz - retornando 'aprendizagem'");
            return 'aprendizagem';
        }
        
        
        try {
            error_log("QL Moodle Debug: Buscando informações da categoria {$category_id}...");
            
            // Buscar informações da categoria
            $category_info = $this->get_course_category($category_id);
            error_log("QL Moodle Debug: Informações da categoria retornadas: " . ($category_info ? json_encode($category_info, JSON_UNESCAPED_UNICODE) : 'NULL'));
            
            if (!$category_info) {
                error_log("QL Moodle Debug: Nenhuma informação da categoria encontrada - retornando 'aprendizagem'");
                return 'aprendizagem';
            }
            
            // Obter o caminho completo da categoria (incluindo pais)
            error_log("QL Moodle Debug: Obtendo caminho completo da categoria...");
            $category_path = $this->get_category_path($category_info);
            error_log("QL Moodle Debug: Caminho da categoria: " . json_encode($category_path, JSON_UNESCAPED_UNICODE));
            
            // Primeiro, verificar se alguma categoria no caminho indica o tipo específico
            error_log("QL Moodle Debug: Analisando caminho para tipo específico...");
            $detected_type = $this->analyze_category_path_for_type($category_path);
            error_log("QL Moodle Debug: Tipo detectado na análise do caminho: " . ($detected_type ?? 'NULL'));
            
            if ($detected_type !== null) {
                error_log("QL Moodle Debug: Tipo específico encontrado no caminho: {$detected_type}");
                return $detected_type;
            }
            
            // Verificar se há "Quilombo" na hierarquia
            error_log("QL Moodle Debug: Verificando se há 'Quilombo' na hierarquia...");
            $has_quilombo_parent = false;
            foreach ($category_path as $cat) {
                if (stripos($cat['name'], 'quilombo') !== false) {
                    $has_quilombo_parent = true;
                    error_log("QL Moodle Debug: Encontrado 'Quilombo' na categoria: {$cat['name']}");
                    break;
                }
            }
            error_log("QL Moodle Debug: Tem Quilombo na hierarquia: " . ($has_quilombo_parent ? 'SIM' : 'NÃO'));
            
            // Se tem Quilombo na hierarquia, aplicar regras específicas
            if ($has_quilombo_parent) {
                error_log("QL Moodle Debug: Aplicando detecção para subcategorias do Quilombo...");
                $quilombo_type = $this->detect_quilombo_subcategory_type($category_path);
                error_log("QL Moodle Debug: Tipo detectado nas subcategorias do Quilombo: {$quilombo_type}");
                return $quilombo_type;
            }
            
            // Se não tem Quilombo na hierarquia, ainda verificar padrões de nomes
            // para casos onde as subcategorias foram criadas diretamente
            error_log("QL Moodle Debug: Aplicando detecção por nomes das categorias...");
            $name_type = $this->detect_type_by_category_names($category_path);
            error_log("QL Moodle Debug: Tipo detectado por nomes: {$name_type}");
            return $name_type;
            
        } catch (Exception $e) {
            error_log("QL Moodle Debug: ERRO na detecção por categoria: " . $e->getMessage());
            error_log("QL Moodle Debug: Stack trace: " . $e->getTraceAsString());
            return 'aprendizagem';
        }
    }
    
    /**
     * Analisar caminho da categoria para identificar tipo específico
     */
    private function analyze_category_path_for_type($category_path) {
        foreach ($category_path as $cat) {
            // Verificar se há tipo inferido (do fallback)
            if (isset($cat['inferred_type'])) {
                error_log("QL Moodle Debug: Usando tipo inferido: {$cat['inferred_type']}");
                return $cat['inferred_type'];
            }
            
            $cat_name = strtolower($cat['name']);
            error_log("QL Moodle Debug: Analisando nome da categoria: {$cat_name}");
            
            // Verificar padrões mais específicos primeiro
            if (preg_match('/trilhas?\s+de\s+pesquisa|pesquisa\s+e\s+desenvolvimento|research\s+track/i', $cat_name)) {
                error_log("QL Moodle Debug: Padrão específico detectado: pesquisa");
                return 'pesquisa';
            }
            
            if (preg_match('/trilhas?\s+de\s+criação|criação\s+e\s+arte|creation\s+track|design\s+thinking/i', $cat_name)) {
                error_log("QL Moodle Debug: Padrão específico detectado: criacao");
                return 'criacao';
            }
            
            if (preg_match('/trilhas?\s+de\s+aprendizagem|aprendizagem\s+online|learning\s+track/i', $cat_name)) {
                error_log("QL Moodle Debug: Padrão específico detectado: aprendizagem");
                return 'aprendizagem';
            }
            
            // Padrões mais gerais
            if (strpos($cat_name, 'pesquisa') !== false || strpos($cat_name, 'research') !== false) {
                error_log("QL Moodle Debug: Padrão geral detectado: pesquisa");
                return 'pesquisa';
            }
            
            if (strpos($cat_name, 'criação') !== false || strpos($cat_name, 'criacao') !== false || 
                strpos($cat_name, 'creation') !== false || strpos($cat_name, 'arte') !== false ||
                strpos($cat_name, 'design') !== false) {
                error_log("QL Moodle Debug: Padrão geral detectado: criacao");
                return 'criacao';
            }
            
            if (strpos($cat_name, 'aprendizagem') !== false || strpos($cat_name, 'learning') !== false) {
                error_log("QL Moodle Debug: Padrão geral detectado: aprendizagem");
                return 'aprendizagem';
            }
        }
        
        error_log("QL Moodle Debug: Nenhum tipo específico detectado no caminho");
        return null; // Nenhum tipo específico detectado
    }
    
    /**
     * Detectar tipo dentro da hierarquia Quilombo
     */
    private function detect_quilombo_subcategory_type($category_path) {
        // Verificar subcategorias específicas dentro de Quilombo
        foreach ($category_path as $cat) {
            $cat_name = strtolower($cat['name']);
            
            // Trilhas de Pesquisa
            if (strpos($cat_name, 'pesquisa') !== false || 
                strpos($cat_name, 'research') !== false) {
                return 'pesquisa';
            }
            
            // Trilhas de Criação
            if (strpos($cat_name, 'criação') !== false || 
                strpos($cat_name, 'criacao') !== false ||
                strpos($cat_name, 'creation') !== false) {
                return 'criacao';
            }
            
            // Trilhas de Aprendizagem (explícitas)
            if (strpos($cat_name, 'aprendizagem') !== false || 
                strpos($cat_name, 'learning') !== false) {
                return 'aprendizagem';
            }
        }
        
        // Se está em Quilombo mas não tem subcategoria específica, é aprendizagem
        return 'aprendizagem';
    }
    
    /**
     * Detectar tipo baseado apenas nos nomes das categorias
     */
    private function detect_type_by_category_names($category_path) {
        foreach ($category_path as $cat) {
            $cat_name = strtolower($cat['name']);
            
            // Palavras-chave para pesquisa
            $research_keywords = ['pesquisa', 'research', 'investigação', 'ciência', 'científico', 'estudo', 'análise'];
            foreach ($research_keywords as $keyword) {
                if (strpos($cat_name, $keyword) !== false) {
                    return 'pesquisa';
                }
            }
            
            // Palavras-chave para criação
            $creation_keywords = ['criação', 'criacao', 'creation', 'arte', 'design', 'criativo', 'inovação', 'prototipagem'];
            foreach ($creation_keywords as $keyword) {
                if (strpos($cat_name, $keyword) !== false) {
                    return 'criacao';
                }
            }
            
            // Palavras-chave para aprendizagem
            $learning_keywords = ['aprendizagem', 'learning', 'ensino', 'educação', 'formação', 'curso', 'aula'];
            foreach ($learning_keywords as $keyword) {
                if (strpos($cat_name, $keyword) !== false) {
                    return 'aprendizagem';
                }
            }
        }
        
        // Padrão continua sendo aprendizagem
        return 'aprendizagem';
    }
    
    /**
     * Buscar informações de uma categoria específica
     */
    private function get_course_category($category_id) {
        try {
            // Tentar primeiro via API Moodle
            $response = $this->call_moodle_api('core_course_get_categories', [
                'criteria' => [
                    [
                        'key' => 'id',
                        'value' => $category_id
                    ]
                ]
            ]);
            
            if (!empty($response)) {
                error_log("QL Moodle Debug: Categoria obtida via API: " . json_encode($response[0], JSON_UNESCAPED_UNICODE));
                return $response[0];
            }
            
        } catch (Exception $e) {
            error_log("QL Moodle Debug: Erro ao buscar categoria via API {$category_id}: " . $e->getMessage());
        }
        
        // Fallback 1: buscar diretamente no banco do Moodle
        error_log("QL Moodle Debug: Tentando fallback para banco de dados...");
        $db_result = $this->get_course_category_from_db($category_id);
        if ($db_result) {
            return $db_result;
        }
        
        // Fallback 2: usar método de detecção simplificado baseado em IDs conhecidos
        error_log("QL Moodle Debug: Tentando fallback de detecção por IDs conhecidos...");
        return $this->get_category_by_known_patterns($category_id);
    }
    
    /**
     * Buscar categoria diretamente do banco do Moodle como fallback
     */
    private function get_course_category_from_db($category_id) {
        try {
            // Tentar obter configurações do Moodle das configurações do plugin
            $moodle_settings = QL_Config::get_moodle_settings();
            
            // Se não temos configurações de banco, tentar extrair do URL da API
            $db_config = $this->extract_moodle_db_config($moodle_settings);
            
            if (!$db_config) {
                error_log("QL Moodle: Não foi possível obter configurações do banco Moodle");
                return null;
            }
            
            $mysqli = new mysqli(
                $db_config['host'], 
                $db_config['user'], 
                $db_config['pass'], 
                $db_config['name'], 
                $db_config['port']
            );
            
            if ($mysqli->connect_error) {
                error_log("QL Moodle: Erro ao conectar ao banco Moodle: " . $mysqli->connect_error);
                return null;
            }
            
            $mysqli->set_charset('utf8mb4');
            
            // Buscar categoria usando o prefixo correto
            $query = "SELECT id, name, parent, description FROM {$db_config['prefix']}course_categories WHERE id = ?";
            $stmt = $mysqli->prepare($query);
            
            if (!$stmt) {
                error_log("QL Moodle: Erro ao preparar query: " . $mysqli->error);
                $mysqli->close();
                return null;
            }
            
            $stmt->bind_param('i', $category_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $category = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'parent' => (int)$row['parent'],
                    'description' => $row['description'] ?? ''
                ];
                
                $stmt->close();
                $mysqli->close();
                return $category;
            }
            
            $stmt->close();
            $mysqli->close();
            error_log("QL Moodle: Categoria {$category_id} não encontrada no banco");
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao buscar categoria no banco: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Buscar grupos do Moodle
     */
    public function get_all_groups() {
        if (!$this->is_moodle_configured()) {
            throw new Exception('Moodle não configurado');
        }
        
        try {
            $params = [
                'wsfunction' => 'core_group_get_groups',
                'moodlewsrestformat' => 'json',
                'wstoken' => $this->moodle_token
            ];
            
            $response = $this->make_request($params);
            
            if (isset($response['groups'])) {
                return $response['groups'];
            }
            
            return $response ?: [];
            
        } catch (Exception $e) {
            error_log('QL Moodle: Erro ao buscar grupos: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar agrupamentos do Moodle
     */
    public function get_all_groupings() {
        if (!$this->is_moodle_configured()) {
            throw new Exception('Moodle não configurado');
        }
        
        try {
            $params = [
                'wsfunction' => 'core_group_get_groupings',
                'moodlewsrestformat' => 'json',
                'wstoken' => $this->moodle_token
            ];
            
            $response = $this->make_request($params);
            
            if (isset($response['groupings'])) {
                return $response['groupings'];
            }
            
            return $response ?: [];
            
        } catch (Exception $e) {
            error_log('QL Moodle: Erro ao buscar agrupamentos: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar grupos de um curso específico
     */
    public function get_course_groups($course_id) {
        if (!$this->is_moodle_configured()) {
            throw new Exception('Moodle não configurado');
        }
        
        try {
            $params = [
                'wsfunction' => 'core_group_get_course_groups',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json',
                'wstoken' => $this->moodle_token
            ];
            
            $response = $this->make_request($params);
            
            return $response ?: [];
            
        } catch (Exception $e) {
            error_log('QL Moodle: Erro ao buscar grupos do curso: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Buscar agrupamentos de um curso específico
     */
    public function get_course_groupings($course_id) {
        if (!$this->is_moodle_configured()) {
            throw new Exception('Moodle não configurado');
        }
        
        try {
            $params = [
                'wsfunction' => 'core_group_get_course_groupings',
                'courseid' => $course_id,
                'moodlewsrestformat' => 'json',
                'wstoken' => $this->moodle_token
            ];
            
            $response = $this->make_request($params);
            
            return $response ?: [];
            
        } catch (Exception $e) {
            error_log('QL Moodle: Erro ao buscar agrupamentos do curso: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * AJAX - Buscar todos os grupos
     */
    public function ajax_get_all_groups() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        try {
            $groups = $this->get_all_groups();
            wp_send_json_success(['groups' => $groups]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * AJAX - Buscar todos os agrupamentos
     */
    public function ajax_get_all_groupings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        try {
            $groupings = $this->get_all_groupings();
            wp_send_json_success(['groupings' => $groupings]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Extrair configurações do banco Moodle a partir das configurações da API
     */
    private function extract_moodle_db_config($moodle_settings) {
        // Primeiro verificar se há configurações manuais do banco nas configurações do plugin
        if (!empty($moodle_settings['db_host']) && !empty($moodle_settings['db_name'])) {
            error_log("QL Moodle Debug: Usando configurações manuais do banco");
            return [
                'host' => $moodle_settings['db_host'],
                'user' => $moodle_settings['db_user'] ?? 'root',
                'pass' => $moodle_settings['db_pass'] ?? '',
                'name' => $moodle_settings['db_name'],
                'port' => $moodle_settings['db_port'] ?? 3306,
                'prefix' => $moodle_settings['db_prefix'] ?? 'mdl_'
            ];
        }
        
        // Tentar obter do arquivo de configuração do Moodle
        $config_paths = [
            ABSPATH . '../escola/config.php',  // Desenvolvimento local
            ABSPATH . '../moodle/config.php',  // Possível produção
            '/var/www/moodle/config.php',      // Padrão produção
            '/opt/moodle/config.php',          // Alternativa produção
        ];
        
        error_log("QL Moodle Debug: Tentando localizar config do Moodle. ABSPATH: " . ABSPATH);
        
        foreach ($config_paths as $config_path) {
            error_log("QL Moodle Debug: Verificando caminho: " . $config_path . " - Existe: " . (file_exists($config_path) ? 'SIM' : 'NÃO'));
            if (file_exists($config_path)) {
                $config = $this->load_moodle_config($config_path);
                if ($config) {
                    error_log("QL Moodle Debug: Config carregado com sucesso de: " . $config_path);
                    return $config;
                }
            }
        }
        
        // Fallback: tentar derivar configurações do URL da API
        if (!empty($moodle_settings['url'])) {
            $parsed_url = parse_url($moodle_settings['url']);
            $host = $parsed_url['host'] ?? 'localhost';
            
            error_log("QL Moodle Debug: Usando fallback para host: " . $host);
            error_log("QL Moodle Debug: URL completa do Moodle: " . $moodle_settings['url']);
            
            // Configurações padrão baseadas no host
            $default_configs = [
                'localhost' => [
                    'host' => 'localhost',
                    'user' => 'quilombo',
                    'pass' => '#Palmares@2025',
                    'name' => 'moodle_escola',
                    'port' => 3306,
                    'prefix' => 'mdl_'
                ],
                'quilombociencia.org' => [
                    'host' => 'localhost',
                    'user' => 'quilombo_moodle',
                    'pass' => $this->get_production_db_password(),
                    'name' => 'quilombo_moodle',
                    'port' => 3306,
                    'prefix' => 'mdl_'
                ]
            ];
            
            if (isset($default_configs[$host])) {
                error_log("QL Moodle Debug: Usando config padrão para host: " . $host);
                return $default_configs[$host];
            } else {
                error_log("QL Moodle Debug: Host '{$host}' não encontrado nos configs padrão");
            }
        } else {
            error_log("QL Moodle Debug: URL do Moodle não configurada");
        }
        
        error_log("QL Moodle: Não foi possível determinar configurações do banco Moodle");
        return null;
    }
    
    /**
     * Carregar configurações do arquivo config.php do Moodle
     */
    private function load_moodle_config($config_path) {
        try {
            // Capturar configurações em escopo isolado
            $get_config = function() use ($config_path) {
                $CFG = new stdClass();
                include $config_path;
                return $CFG;
            };
            
            $CFG = $get_config();
            
            if ($CFG && isset($CFG->dbname, $CFG->dbuser, $CFG->dbpass)) {
                return [
                    'host' => $CFG->dbhost ?? 'localhost',
                    'user' => $CFG->dbuser,
                    'pass' => $CFG->dbpass,
                    'name' => $CFG->dbname,
                    'port' => $CFG->dboptions['dbport'] ?? 3306,
                    'prefix' => $CFG->prefix ?? 'mdl_'
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao carregar config do Moodle: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obter senha do banco de produção (implementar conforme necessário)
     */
    private function get_production_db_password() {
        // Em produção, isso deveria vir de variáveis de ambiente ou arquivo seguro
        // Por ora, retornar null para forçar o carregamento do arquivo de config
        return null;
    }
    
    /**
     * Fallback para detecção por padrões conhecidos de IDs
     */
    private function get_category_by_known_patterns($category_id) {
        error_log("QL Moodle Debug: Verificando padrões conhecidos para categoria ID: {$category_id}");
        
        // Mapear IDs conhecidos baseados no diagnóstico e estrutura esperada
        // Estes IDs podem ser configurados ou aprendidos dinamicamente
        $known_categories = $this->get_known_category_mappings();
        
        if (isset($known_categories[$category_id])) {
            $category_info = $known_categories[$category_id];
            error_log("QL Moodle Debug: Categoria encontrada em padrões conhecidos: " . json_encode($category_info, JSON_UNESCAPED_UNICODE));
            return $category_info;
        }
        
        // Tentar inferir baseado em padrões comuns de ID
        $inferred = $this->infer_category_by_id_pattern($category_id);
        if ($inferred) {
            error_log("QL Moodle Debug: Categoria inferida por padrão: " . json_encode($inferred, JSON_UNESCAPED_UNICODE));
            return $inferred;
        }
        
        error_log("QL Moodle Debug: Nenhum padrão conhecido encontrado para categoria {$category_id}");
        return null;
    }
    
    /**
     * Obter mapeamentos conhecidos de categorias
     */
    private function get_known_category_mappings() {
        // Primeiro tentar obter de configurações salvas
        $saved_mappings = get_option('ql_moodle_category_mappings', []);
        
        // Mesclar com padrões baseados na estrutura observada do site
        $default_mappings = [
            // Baseado no diagnóstico: curso ID 2 tem categoryid 15, curso ID 3 tem categoryid 13
            // Assumir que estas podem ser subcategorias de tipos específicos
            15 => ['id' => 15, 'name' => 'Trilhas de Pesquisa', 'parent' => 0],
            13 => ['id' => 13, 'name' => 'Trilhas de Criação', 'parent' => 0],
        ];
        
        return array_merge($default_mappings, $saved_mappings);
    }
    
    /**
     * Inferir informações da categoria baseado em padrões de ID
     */
    private function infer_category_by_id_pattern($category_id) {
        // Criar uma categoria genérica para análise posterior
        // O importante é ter algo para análise de nome
        
        // Baseado nos IDs observados no diagnóstico, fazer inferências educadas
        $patterns = [
            // IDs 10-19: possivelmente relacionados a pesquisa
            range(10, 19) => ['type' => 'pesquisa', 'name' => 'Trilhas de Pesquisa'],
            // IDs 20-29: possivelmente relacionados a criação  
            range(20, 29) => ['type' => 'criacao', 'name' => 'Trilhas de Criação'],
            // IDs específicos observados
            [13] => ['type' => 'criacao', 'name' => 'Trilhas de Criação'],
            [15] => ['type' => 'pesquisa', 'name' => 'Trilhas de Pesquisa'],
        ];
        
        foreach ($patterns as $ids => $info) {
            if (in_array($category_id, $ids)) {
                return [
                    'id' => $category_id,
                    'name' => $info['name'],
                    'parent' => 0,
                    'inferred_type' => $info['type']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Obter caminho completo da categoria (incluindo pais)
     */
    private function get_category_path($category) {
        $path = [$category];
        
        // Se tem parent, buscar recursivamente
        if (isset($category['parent']) && $category['parent'] > 0) {
            try {
                $parent = $this->get_course_category($category['parent']);
                if ($parent) {
                    $parent_path = $this->get_category_path($parent);
                    $path = array_merge($parent_path, $path);
                }
            } catch (Exception $e) {
                error_log("QL Moodle: Erro ao buscar categoria pai: " . $e->getMessage());
            }
        }
        
        return $path;
    }
    
    /**
     * Criar projeto no Laboratório baseado no curso Moodle
     */
    private function create_project_from_course($course, $trilha_type, $is_site_course = false) {
        global $wpdb;
        
        // Nome do projeto - se for curso principal, usar nome especial
        $is_virtual = isset($course['_is_virtual_site_course']);
        $project_name = $is_site_course ? 'Projeto do Coletivo - ' . $course['fullname'] : $course['fullname'];
        $project_slug = $is_site_course ? 'projeto-coletivo' : sanitize_title($course['shortname']);
        
        // Adicionar indicação se é curso virtual (acesso limitado)
        if ($is_virtual && $is_site_course) {
            $project_name = 'Projeto do Coletivo - ' . $course['fullname'] . ' (Acesso Limitado)';
        }
        
        // Criar projeto QL independente (GC opcional)
        $project_data = [
            'name' => $project_name,
            'slug' => $project_slug,
            'description' => $course['summary'] ?? '',
            'status' => 'active',
            'visibility' => 'public', // Cursos Moodle são tipicamente públicos
            'owner_id' => 1, // Admin por padrão
            'gc_projeto_id' => null, // QL independente - GC pode vincular depois se necessário
            'moodle_course_id' => $course['id'],
            'start_date' => !empty($course['startdate']) ? date('Y-m-d', $course['startdate']) : null,
            'end_date' => !empty($course['enddate']) ? date('Y-m-d', $course['enddate']) : null,
            'priority' => $is_site_course ? 'high' : 'normal',
            'color' => $is_site_course ? '#27ae60' : $this->get_trilha_color($trilha_type),
            'featured_image_id' => $this->get_project_featured_image($course, $is_site_course),
            'settings' => json_encode([
                'trilha_type' => $trilha_type,
                'moodle_course_id' => $course['id'],
                'auto_sync' => true,
                'is_collective_project' => $is_site_course,
                'is_virtual_course' => $is_virtual,
                'limited_access' => $is_virtual
            ]),
            'created_at' => current_time('mysql'),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($wpdb->prefix . 'ql_projects', $project_data);
        
        if ($result === false) {
            throw new Exception('Erro ao criar projeto QL: ' . $wpdb->last_error);
        }
        
        $project_id = $wpdb->insert_id;
        
        // Criar quadro padrão baseado no template do tipo de trilha
        $this->create_default_board_for_trilha($project_id, $trilha_type);
        
        // Sincronizar membros do curso Moodle para o projeto
        $this->sync_project_members_on_creation($project_id, $course['id']);
        
        // Disparar hook para notificar criação do projeto (para integração opcional com GC)
        if (function_exists('do_action')) {
            do_action('ql_project_created', $project_id, $project_data, $course, $trilha_type);
        }
        
        return $project_id;
    }
    
    
    /**
     * Atualizar projeto existente
     */
    private function update_project_from_course($course, $trilha_type, $project_id) {
        global $wpdb;
        
        // Verificar se o tipo de trilha mudou
        $current_project = $wpdb->get_row($wpdb->prepare(
            "SELECT settings FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ));
        
        $current_settings = $current_project && $current_project->settings ? 
            json_decode($current_project->settings, true) : [];
        $current_trilha_type = $current_settings['trilha_type'] ?? 'aprendizagem';
        
        $update_data = [
            'name' => $course['fullname'],
            'description' => $course['summary'] ?? '',
            'start_date' => !empty($course['startdate']) ? date('Y-m-d', $course['startdate']) : null,
            'end_date' => !empty($course['enddate']) ? date('Y-m-d', $course['enddate']) : null,
            'color' => $this->get_trilha_color($trilha_type),
            'settings' => json_encode(array_merge($current_settings, [
                'trilha_type' => $trilha_type,
                'moodle_course_id' => $course['id'],
                'auto_sync' => true
            ])),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            $update_data,
            ['id' => $project_id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            throw new Exception('Erro ao atualizar projeto: ' . $wpdb->last_error);
        }
        
        // Se o tipo de trilha mudou, atualizar os quadros
        if ($current_trilha_type !== $trilha_type) {
            error_log("QL Moodle: Tipo de trilha mudou de '{$current_trilha_type}' para '{$trilha_type}' no projeto {$project_id}");
            $this->update_boards_for_trilha_type_change($project_id, $trilha_type, $current_trilha_type);
        }
        
        // Verificação especial para quadros de criação com template antigo
        if ($trilha_type === 'criacao') {
            $this->check_and_update_creation_boards_with_old_template($project_id);
        }
        
        return $project_id;
    }
    
    /**
     * Criar quadro padrão baseado no tipo de trilha
     */
    private function create_default_board_for_trilha($project_id, $trilha_type) {
        global $wpdb;
        
        // Templates de quadros por tipo de trilha
        $templates = [
            'aprendizagem' => [
                'name' => 'Trilha de Aprendizagem',
                'columns' => [
                    ['name' => 'Planejamento', 'color' => '#e74c3c'],
                    ['name' => 'Estudando', 'color' => '#f39c12'],
                    ['name' => 'Praticando', 'color' => '#3498db'],
                    ['name' => 'Revisão', 'color' => '#9b59b6'],
                    ['name' => 'Concluído', 'color' => '#27ae60']
                ]
            ],
            'pesquisa' => [
                'name' => 'Metodologia de Pesquisa',
                'columns' => [
                    ['name' => 'Questão de Pesquisa', 'color' => '#9b59b6'],
                    ['name' => 'Coleta de Dados', 'color' => '#e67e22'],
                    ['name' => 'Análise', 'color' => '#2980b9'],
                    ['name' => 'Validação', 'color' => '#16a085'],
                    ['name' => 'Publicação', 'color' => '#27ae60']
                ]
            ],
            'criacao' => [
                'name' => 'Desenvolvimento de Projeto',
                'columns' => [
                    ['name' => 'Problema', 'color' => '#e74c3c'],
                    ['name' => 'Ideação', 'color' => '#f1c40f'],
                    ['name' => 'Planejamento', 'color' => '#e67e22'],
                    ['name' => 'Desenvolvimento', 'color' => '#3498db'],
                    ['name' => 'Avaliação', 'color' => '#9b59b6'],
                    ['name' => 'Concluída', 'color' => '#27ae60']
                ]
            ]
        ];
        
        $template = $templates[$trilha_type] ?? $templates['aprendizagem'];
        
        // Criar quadro
        $board_data = [
            'project_id' => $project_id,
            'name' => $template['name'],
            'description' => 'Quadro criado automaticamente pela sincronização com Moodle',
            'board_type' => 'kanban',
            'is_default' => true,
            'settings' => json_encode(['auto_created' => true, 'trilha_type' => $trilha_type]),
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($wpdb->prefix . 'ql_boards', $board_data);
        $board_id = $wpdb->insert_id;
        
        // Criar colunas
        foreach ($template['columns'] as $index => $column) {
            $column_data = [
                'board_id' => $board_id,
                'name' => $column['name'],
                'position' => $index,
                'color' => $column['color'],
                'created_at' => current_time('mysql')
            ];
            
            $wpdb->insert($wpdb->prefix . 'ql_columns', $column_data);
        }
        
        return $board_id;
    }
    
    /**
     * Atualizar quadros quando o tipo de trilha muda
     */
    private function update_boards_for_trilha_type_change($project_id, $new_trilha_type, $old_trilha_type) {
        global $wpdb;
        
        error_log("QL Moodle: Atualizando quadros do projeto {$project_id} para novo tipo de trilha: {$new_trilha_type}");
        
        // Buscar quadros auto-criados do projeto
        $auto_boards = $wpdb->get_results($wpdb->prepare(
            "SELECT id, settings FROM {$wpdb->prefix}ql_boards 
             WHERE project_id = %d AND settings LIKE %s",
            $project_id,
            '%auto_created%'
        ));
        
        foreach ($auto_boards as $board) {
            $settings = json_decode($board->settings, true) ?? [];
            
            // Só atualizar quadros que foram criados automaticamente
            if (isset($settings['auto_created']) && $settings['auto_created']) {
                $this->update_board_for_new_trilha_type($board->id, $new_trilha_type);
            }
        }
        
        // Se não há quadros auto-criados, criar um novo baseado no novo tipo
        if (empty($auto_boards)) {
            error_log("QL Moodle: Nenhum quadro auto-criado encontrado, criando novo quadro para tipo {$new_trilha_type}");
            $this->create_default_board_for_trilha($project_id, $new_trilha_type);
        }
    }
    
    /**
     * Atualizar um quadro específico para o novo tipo de trilha
     */
    private function update_board_for_new_trilha_type($board_id, $trilha_type) {
        global $wpdb;
        
        error_log("QL Moodle: Atualizando quadro {$board_id} para tipo de trilha: {$trilha_type}");
        
        // Templates de quadros por tipo de trilha (mesmo da função create_default_board_for_trilha)
        $templates = [
            'aprendizagem' => [
                'name' => 'Trilha de Aprendizagem',
                'columns' => [
                    ['name' => 'Planejamento', 'color' => '#e74c3c'],
                    ['name' => 'Estudando', 'color' => '#f39c12'],
                    ['name' => 'Praticando', 'color' => '#3498db'],
                    ['name' => 'Revisão', 'color' => '#9b59b6'],
                    ['name' => 'Concluído', 'color' => '#27ae60']
                ]
            ],
            'pesquisa' => [
                'name' => 'Metodologia de Pesquisa',
                'columns' => [
                    ['name' => 'Questão de Pesquisa', 'color' => '#9b59b6'],
                    ['name' => 'Coleta de Dados', 'color' => '#e67e22'],
                    ['name' => 'Análise', 'color' => '#2980b9'],
                    ['name' => 'Validação', 'color' => '#16a085'],
                    ['name' => 'Publicação', 'color' => '#27ae60']
                ]
            ],
            'criacao' => [
                'name' => 'Desenvolvimento de Projeto',
                'columns' => [
                    ['name' => 'Problema', 'color' => '#e74c3c'],
                    ['name' => 'Ideação', 'color' => '#f1c40f'],
                    ['name' => 'Planejamento', 'color' => '#e67e22'],
                    ['name' => 'Desenvolvimento', 'color' => '#3498db'],
                    ['name' => 'Avaliação', 'color' => '#9b59b6'],
                    ['name' => 'Concluída', 'color' => '#27ae60']
                ]
            ]
        ];
        
        $template = $templates[$trilha_type] ?? $templates['aprendizagem'];
        
        // Atualizar nome do quadro
        $wpdb->update(
            $wpdb->prefix . 'ql_boards',
            [
                'name' => $template['name'],
                'settings' => json_encode(['auto_created' => true, 'trilha_type' => $trilha_type]),
                'created_at' => current_time('mysql')
            ],
            ['id' => $board_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        // Buscar colunas existentes
        $existing_columns = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position",
            $board_id
        ));
        
        // Verificar se há tarefas nas colunas
        $has_tasks = false;
        foreach ($existing_columns as $col) {
            $task_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE column_id = %d",
                $col->id
            ));
            if ($task_count > 0) {
                $has_tasks = true;
                break;
            }
        }
        
        if (!$has_tasks) {
            // Se não há tarefas, recriar colunas do zero
            error_log("QL Moodle: Quadro {$board_id} sem tarefas, recriando colunas");
            
            // Deletar colunas existentes
            $wpdb->delete($wpdb->prefix . 'ql_columns', ['board_id' => $board_id]);
            
            // Criar novas colunas
            foreach ($template['columns'] as $index => $column) {
                $column_data = [
                    'board_id' => $board_id,
                    'name' => $column['name'],
                    'position' => $index,
                    'color' => $column['color'],
                    'created_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'ql_columns', $column_data);
            }
        } else {
            // Se há tarefas, apenas renomear colunas que fazem sentido
            error_log("QL Moodle: Quadro {$board_id} com tarefas existentes, preservando estrutura");
            
            // Mapear colunas similares entre tipos
            $this->map_existing_columns_to_new_type($board_id, $existing_columns, $template['columns']);
        }
    }
    
    /**
     * Mapear colunas existentes para o novo tipo sem perder tarefas
     */
    private function map_existing_columns_to_new_type($board_id, $existing_columns, $new_columns) {
        global $wpdb;
        
        // Mapeamentos de colunas similares entre tipos
        $column_mappings = [
            'planejamento' => ['questão de pesquisa', 'ideação'],
            'estudando' => ['coleta de dados', 'prototipagem'],
            'praticando' => ['análise', 'desenvolvimento'],
            'revisão' => ['validação', 'teste'],
            'concluído' => ['publicação', 'finalização']
        ];
        
        foreach ($existing_columns as $index => $existing_col) {
            if (isset($new_columns[$index])) {
                $new_col = $new_columns[$index];
                
                // Atualizar nome e cor da coluna
                $wpdb->update(
                    $wpdb->prefix . 'ql_columns',
                    [
                        'name' => $new_col['name'],
                        'color' => $new_col['color']
                    ],
                    ['id' => $existing_col->id],
                    ['%s', '%s'],
                    ['%d']
                );
                
                error_log("QL Moodle: Coluna '{$existing_col->name}' renomeada para '{$new_col['name']}'");
            }
        }
        
        // Se há mais colunas no novo template, criar as faltantes
        if (count($new_columns) > count($existing_columns)) {
            for ($i = count($existing_columns); $i < count($new_columns); $i++) {
                $column = $new_columns[$i];
                $column_data = [
                    'board_id' => $board_id,
                    'name' => $column['name'],
                    'position' => $i,
                    'color' => $column['color'],
                    'created_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'ql_columns', $column_data);
                error_log("QL Moodle: Nova coluna '{$column['name']}' adicionada");
            }
        }
    }
    
    /**
     * Criar mapeamento curso -> projeto
     */
    private function create_course_mapping($course_id, $course, $project_id, $trilha_type, $is_collective = false) {
        global $wpdb;
        
        $this->ensure_mappings_table();
        
        // Verificar se é trilha coletiva configurada
        $trilha_coletivo_id = get_option('quilombo_laboratorio_trilha_coletivo', 1);
        $is_collective_final = $is_collective || ($course_id == 1) || ($course_id == $trilha_coletivo_id);
        
        $mapping_data = [
            'moodle_course_id' => $course_id,
            'course_name' => $course['fullname'],
            'course_shortname' => $course['shortname'],
            'ql_project_id' => $project_id,
            'trilha_type' => $trilha_type,
            'is_collective' => $is_collective_final ? 1 : 0,
            'last_sync' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($wpdb->prefix . 'ql_moodle_mappings', $mapping_data);
        
        if ($result === false) {
            error_log("QL Moodle: Erro ao criar mapeamento: " . $wpdb->last_error);
        } else {
            error_log("QL Moodle: Mapeamento criado - Curso {$course_id} → Projeto {$project_id} (Coletivo: " . ($is_collective_final ? 'Sim' : 'Não') . ")");
        }
    }
    
    /**
     * Atualizar mapeamento existente
     */
    private function update_course_mapping($course_id, $course, $project_id, $trilha_type) {
        global $wpdb;
        
        // Verificar se é trilha coletiva configurada
        $trilha_coletivo_id = get_option('quilombo_laboratorio_trilha_coletivo', 1);
        $is_collective = ($course_id == 1) || ($course_id == $trilha_coletivo_id);
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_moodle_mappings',
            [
                'course_name' => $course['fullname'],
                'course_shortname' => $course['shortname'],
                'trilha_type' => $trilha_type,
                'is_collective' => $is_collective ? 1 : 0,
                'last_sync' => current_time('mysql')
            ],
            ['moodle_course_id' => $course_id],
            ['%s', '%s', '%s', '%d', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            error_log("QL Moodle: Erro ao atualizar mapeamento: " . $wpdb->last_error);
        } else {
            error_log("QL Moodle: Mapeamento atualizado - Curso {$course_id} (Coletivo: " . ($is_collective ? 'Sim' : 'Não') . ")");
        }
    }
    
    /**
     * Obter mapeamento existente
     */
    private function get_course_mapping($course_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_moodle_mappings WHERE moodle_course_id = %d",
            $course_id
        ));
    }
    
    /**
     * Garantir que tabela de mapeamentos existe
     */
    private function ensure_mappings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            moodle_course_id bigint(20) NOT NULL,
            course_name varchar(255) NOT NULL,
            course_shortname varchar(100) NOT NULL,
            ql_project_id bigint(20) NOT NULL,
            trilha_type varchar(50) DEFAULT 'aprendizagem',
            is_collective tinyint(1) DEFAULT 0,
            last_sync datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY moodle_course_id (moodle_course_id),
            KEY ql_project_id (ql_project_id),
            KEY trilha_type (trilha_type),
            KEY is_collective (is_collective)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar se a coluna is_collective existe, caso contrário adicionar
        $this->ensure_is_collective_column();
    }
    
    /**
     * Garantir que a coluna is_collective existe na tabela de mapeamentos
     */
    private function ensure_is_collective_column() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        
        // Verificar se a coluna exists
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s 
             AND TABLE_NAME = %s 
             AND COLUMN_NAME = 'is_collective'",
            DB_NAME,
            $table_name
        ));
        
        // Se a coluna não existe, adicionar
        if (!$column_exists) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_collective tinyint(1) DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_name ADD KEY is_collective (is_collective)");
            error_log("QL Moodle Integration: Coluna is_collective adicionada à tabela de mapeamentos");
        }
    }
    
    /**
     * Garantir que a tabela de mapeamento de usuários existe
     */
    private function ensure_user_mappings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_mappings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) NOT NULL,
            moodle_user_id bigint(20) NULL,
            gc_user_id bigint(20) NULL,
            email varchar(255) NOT NULL,
            sync_status varchar(50) DEFAULT 'active',
            last_sync datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            UNIQUE KEY email (email),
            KEY moodle_user_id (moodle_user_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Verificar se um curso é coletivo
     */
    private function is_collective_course($course_id) {
        global $wpdb;
        
        $trilha_coletivo_id = get_option('quilombo_laboratorio_trilha_coletivo', 1);
        if ($course_id == $trilha_coletivo_id || $course_id == 1) {
            return true;
        }
        
        // Verificar no mapeamento
        $is_collective = $wpdb->get_var($wpdb->prepare(
            "SELECT is_collective FROM {$wpdb->prefix}ql_moodle_mappings WHERE moodle_course_id = %d",
            $course_id
        ));
        
        return $is_collective == 1;
    }
    
    /**
     * Sincronizar todos os usuários SAML2 para o projeto coletivo
     */
    private function sync_all_saml_users_to_collective_project($project_id) {
        $all_users = get_users(['number' => -1]);
        $synced_count = 0;
        $errors = [];
        
        error_log("QL Moodle: Sincronizando todos os usuários WordPress para o projeto coletivo {$project_id}");
        
        foreach ($all_users as $user) {
            try {
                // Determinar role baseado no papel do WordPress
                $ql_role = $this->map_wp_role_to_ql($user->roles);
                
                // Adicionar membro ao projeto
                $result = $this->add_project_member($project_id, $user->ID, $ql_role);
                
                if ($result !== false) {
                    $synced_count++;
                    error_log("QL Moodle: Usuário WordPress {$user->ID} ({$user->user_email}) adicionado ao projeto coletivo como {$ql_role}");
                }
            } catch (Exception $e) {
                $error_msg = "Erro ao sincronizar usuário WordPress {$user->user_email}: " . $e->getMessage();
                $errors[] = $error_msg;
                error_log("QL Moodle: " . $error_msg);
            }
        }
        
        error_log("QL Moodle: Sincronização de usuários WordPress concluída - {$synced_count} membros adicionados ao projeto coletivo");
        
        return [
            'synced_count' => $synced_count,
            'total_participants' => count($all_users),
            'errors' => $errors
        ];
    }
    
    /**
     * Mapear role do WordPress para role do QL
     */
    private function map_wp_role_to_ql($wp_roles) {
        // Mapeamento de roles WordPress para QL
        $role_mapping = [
            'administrator' => 'admin',
            'editor' => 'manager',
            'author' => 'moderator',
            'contributor' => 'member',
            'subscriber' => 'member'
        ];
        
        // Encontrar a role mais alta
        $highest_role = 'member';
        
        foreach ($wp_roles as $role) {
            if (isset($role_mapping[$role])) {
                $ql_role = $role_mapping[$role];
                
                // Hierarquia: admin > manager > moderator > member
                if ($this->is_higher_role($ql_role, $highest_role)) {
                    $highest_role = $ql_role;
                }
            }
        }
        
        return $highest_role;
    }
    
    /**
     * Buscar participantes de um curso específico
     */
    public function get_course_participants($course_id) {
        try {
            // Parâmetros completos para garantir que todos os usuários sejam retornados
            // Formato igual ao usado em get_course_enrolled_users_with_roles()
            $response = $this->call_moodle_api('core_enrol_get_enrolled_users', [
                'courseid' => $course_id,
                'withcapability' => '',         // Vazio para buscar todos (sem filtro de capability)
                'groupid' => 0,                 // 0 = todos os grupos
                'onlyactive' => 0,              // 0 = incluir todos, não apenas ativos
                'userfields' => 'id,username,firstname,lastname,email',
                'limitfrom' => 0,               // Começar do primeiro usuário
                'limitnumber' => 0              // 0 = sem limite, retorna TODOS os usuários
            ]);

            $count = is_array($response) ? count($response) : 0;
            error_log("QL Moodle: Buscando participantes do curso {$course_id} - Retornados: {$count} usuários");

            if (!$response || !is_array($response)) {
                error_log("QL Moodle: Resposta vazia ou inválida para curso {$course_id}");
                return [];
            }
            
            $participants = [];
            foreach ($response as $user) {
                // Mapear dados básicos do usuário
                $participant = [
                    'moodle_user_id' => $user['id'],
                    'username' => $user['username'] ?? '',
                    'firstname' => $user['firstname'] ?? '',
                    'lastname' => $user['lastname'] ?? '',
                    'email' => $user['email'] ?? '',
                    'roles' => []
                ];
                
                // Extrair roles do usuário no curso
                if (isset($user['roles'])) {
                    foreach ($user['roles'] as $role) {
                        $participant['roles'][] = [
                            'roleid' => $role['roleid'],
                            'shortname' => $role['shortname'] ?? '',
                            'name' => $role['name'] ?? ''
                        ];
                    }
                }
                
                $participants[] = $participant;
            }
            
            return $participants;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao buscar participantes do curso {$course_id}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sincronizar membros de um projeto baseado nos participantes da trilha Moodle
     */
    public function sync_project_members($project_id, $course_id) {
        global $wpdb;
        
        error_log("QL Moodle: Iniciando sincronização de membros - Projeto: {$project_id}, Curso: {$course_id}");
        
        // Buscar participantes do curso
        $participants = $this->get_course_participants($course_id);
        
        if (empty($participants)) {
            error_log("QL Moodle: Nenhum participante encontrado para o curso {$course_id}");
            
            // Se não encontramos participantes via API, tentar buscar todos os usuários WordPress com SAML2
            // e adicioná-los como membros (para trilha coletiva)
            $is_collective = $this->is_collective_course($course_id);
            if ($is_collective) {
                return $this->sync_all_saml_users_to_collective_project($project_id);
            }
            
            return [
                'synced_count' => 0,
                'total_participants' => 0,
                'errors' => ['Nenhum participante encontrado no curso Moodle']
            ];
        }
        
        $synced_count = 0;
        $errors = [];
        
        foreach ($participants as $participant) {
            try {
                // Encontrar usuário WordPress correspondente
                $wp_user_id = $this->find_wp_user_by_moodle($participant['moodle_user_id'], $participant['email']);
                
                if ($wp_user_id) {
                    // Mapear role Moodle para role QL
                    $ql_role = $this->map_moodle_role_to_ql($participant['roles']);
                    
                    // Adicionar membro ao projeto
                    $result = $this->add_project_member($project_id, $wp_user_id, $ql_role);
                    
                    if ($result !== false) {
                        $synced_count++;
                        error_log("QL Moodle: Usuário {$wp_user_id} ({$participant['email']}) adicionado ao projeto {$project_id} como {$ql_role}");
                    }
                } else {
                    $error_msg = "Usuário Moodle {$participant['moodle_user_id']} ({$participant['email']}) não encontrado no WordPress";
                    $errors[] = $error_msg;
                    error_log("QL Moodle: " . $error_msg);
                }
            } catch (Exception $e) {
                $error_msg = "Erro ao sincronizar usuário {$participant['email']}: " . $e->getMessage();
                $errors[] = $error_msg;
                error_log("QL Moodle: " . $error_msg);
            }
        }
        
        // Log dos resultados
        error_log("QL Moodle: Sincronização projeto {$project_id} concluída - {$synced_count} membros adicionados de " . count($participants) . " participantes, " . count($errors) . " erros");
        
        return [
            'synced_count' => $synced_count,
            'total_participants' => count($participants),
            'errors' => $errors
        ];
    }
    
    /**
     * Encontrar usuário WordPress por ID Moodle ou email
     */
    private function find_wp_user_by_moodle($moodle_user_id, $email) {
        global $wpdb;
        
        // Garantir que a tabela existe antes de tentar usar
        $this->ensure_user_mappings_table();
        
        // Primeiro tentar pelo mapeamento existente
        $wp_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT wp_user_id FROM {$wpdb->prefix}ql_user_mappings WHERE moodle_user_id = %d",
            $moodle_user_id
        ));
        
        if ($wp_user_id && get_user_by('id', $wp_user_id)) {
            return $wp_user_id;
        }
        
        // Fallback 1: buscar por email (usuários SAML2)
        if (!empty($email)) {
            $user = get_user_by('email', $email);
            if ($user) {
                // Criar/atualizar mapeamento para futura referência
                $wpdb->replace(
                    $wpdb->prefix . 'ql_user_mappings',
                    [
                        'wp_user_id' => $user->ID,
                        'moodle_user_id' => $moodle_user_id,
                        'email' => $email,
                        'sync_status' => 'saml_auto_mapped',
                        'last_sync' => current_time('mysql'),
                        'created_at' => current_time('mysql')
                    ]
                );
                
                error_log("QL Moodle: Usuário SAML2 mapeado automaticamente - WP:{$user->ID} ← Moodle:{$moodle_user_id} ({$email})");
                return $user->ID;
            }
        }
        
        // Fallback 2: buscar por metadados do Moodle (se o user_sync tiver funcionado antes)
        if ($moodle_user_id) {
            $wp_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key = 'moodle_user_id' AND meta_value = %d LIMIT 1",
                $moodle_user_id
            ));
            
            if ($wp_user_id) {
                $user = get_user_by('id', $wp_user_id);
                if ($user) {
                    // Atualizar mapeamento
                    $wpdb->replace(
                        $wpdb->prefix . 'ql_user_mappings',
                        [
                            'wp_user_id' => $user->ID,
                            'moodle_user_id' => $moodle_user_id,
                            'email' => $user->user_email,
                            'sync_status' => 'meta_discovered',
                            'last_sync' => current_time('mysql'),
                            'created_at' => current_time('mysql')
                        ]
                    );
                    
                    error_log("QL Moodle: Usuário encontrado via meta - WP:{$user->ID} ← Moodle:{$moodle_user_id}");
                    return $user->ID;
                }
            }
        }
        
        error_log("QL Moodle: Usuário não encontrado - Moodle ID:{$moodle_user_id}, Email:{$email}");
        return false;
    }
    
    /**
     * Mapear role do Moodle para role do QL
     */
    private function map_moodle_role_to_ql($moodle_roles) {
        // Mapeamento padrão de roles Moodle → QL
        $role_mapping = [
            'editingteacher' => 'manager',
            'teacher' => 'moderator', 
            'coursecreator' => 'manager',
            'manager' => 'admin',
            'student' => 'member',
            'guest' => 'viewer'
        ];
        
        // Encontrar a role mais alta
        $highest_role = 'member'; // padrão
        
        foreach ($moodle_roles as $role) {
            $shortname = $role['shortname'] ?? '';
            if (isset($role_mapping[$shortname])) {
                $ql_role = $role_mapping[$shortname];
                
                // Hierarquia: admin > manager > moderator > member > viewer
                if ($this->is_higher_role($ql_role, $highest_role)) {
                    $highest_role = $ql_role;
                }
            }
        }
        
        return $highest_role;
    }
    
    /**
     * Verificar se uma role é hierarquicamente superior a outra
     */
    private function is_higher_role($role1, $role2) {
        $hierarchy = ['admin', 'manager', 'moderator', 'member', 'viewer'];
        $pos1 = array_search($role1, $hierarchy);
        $pos2 = array_search($role2, $hierarchy);
        
        return ($pos1 !== false && $pos2 !== false && $pos1 < $pos2);
    }
    
    /**
     * Adicionar membro ao projeto
     */
    private function add_project_member($project_id, $user_id, $role) {
        global $wpdb;
        
        // Verificar se já é membro
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ql_project_members WHERE project_id = %d AND user_id = %d",
            $project_id, $user_id
        ));
        
        if ($existing) {
            // Atualizar role se necessário
            return $wpdb->update(
                $wpdb->prefix . 'ql_project_members',
                ['role' => $role],
                ['id' => $existing]
            );
        } else {
            // Adicionar novo membro
            return $wpdb->insert(
                $wpdb->prefix . 'ql_project_members',
                [
                    'project_id' => $project_id,
                    'user_id' => $user_id,
                    'role' => $role,
                    'joined_at' => current_time('mysql')
                ]
            );
        }
    }
    
    /**
     * Sincronizar membros na criação do projeto (com delay para permitir indexação)
     */
    private function sync_project_members_on_creation($project_id, $course_id) {
        // Executar sincronização com delay para permitir que o projeto seja completamente criado
        wp_schedule_single_event(time() + 30, 'ql_sync_project_members_delayed', [$project_id, $course_id]);
        
        // Também tentar sincronização imediata (pode falhar se API não estiver pronta)
        try {
            $result = $this->sync_project_members($project_id, $course_id);
            if ($result && $result['synced_count'] > 0) {
                error_log("QL Moodle: Sincronização imediata bem-sucedida - {$result['synced_count']} membros adicionados ao projeto {$project_id}");
            }
        } catch (Exception $e) {
            error_log("QL Moodle: Sincronização imediata falhou - será reprocessada: " . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Sincronizar membros de todos os projetos Moodle
     */
    public function ajax_sync_all_project_members() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }
        
        try {
            $result = $this->sync_all_project_members();
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => sprintf(
                        'Sincronizados %d projetos. Total: %d membros adicionados, %d erros.',
                        $result['projects_processed'],
                        $result['total_members_synced'],
                        count($result['errors'])
                    )
                ]);
            } else {
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Erro na sincronização de membros: ' . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar membros de todos os projetos Moodle existentes
     */
    public function sync_all_project_members() {
        global $wpdb;
        
        // Buscar todos os projetos que têm curso Moodle associado
        $moodle_projects = $wpdb->get_results("
            SELECT p.id as project_id, p.name as project_name, p.moodle_course_id 
            FROM {$wpdb->prefix}ql_projects p 
            WHERE p.moodle_course_id IS NOT NULL AND p.moodle_course_id > 0
            ORDER BY p.id
        ");
        
        if (empty($moodle_projects)) {
            return [
                'success' => false,
                'message' => 'Nenhum projeto Moodle encontrado para sincronizar'
            ];
        }
        
        $stats = [
            'projects_processed' => 0,
            'total_members_synced' => 0,
            'errors' => []
        ];
        
        foreach ($moodle_projects as $project) {
            try {
                $result = $this->sync_project_members($project->project_id, $project->moodle_course_id);
                
                if ($result) {
                    $stats['total_members_synced'] += $result['synced_count'];
                    $stats['projects_processed']++;
                    
                    if (!empty($result['errors'])) {
                        $stats['errors'] = array_merge($stats['errors'], $result['errors']);
                    }
                    
                    error_log("QL Moodle: Projeto {$project->project_name} (ID: {$project->project_id}) - {$result['synced_count']} membros sincronizados");
                }
                
            } catch (Exception $e) {
                $error_msg = "Erro no projeto {$project->project_name}: " . $e->getMessage();
                $stats['errors'][] = $error_msg;
                error_log("QL Moodle: " . $error_msg);
            }
        }
        
        // Log final
        error_log("QL Moodle: Sincronização completa - {$stats['projects_processed']} projetos, {$stats['total_members_synced']} membros totais, " . count($stats['errors']) . " erros");
        
        // Após a sincronização, atualizar quadros de criação existentes
        $this->update_existing_creation_boards();
        
        return [
            'success' => true,
            'projects_processed' => $stats['projects_processed'],
            'total_members_synced' => $stats['total_members_synced'],
            'errors' => $stats['errors']
        ];
    }
    
    /**
     * Atualizar quadros de projetos de criação existentes para usar as novas etapas
     */
    private function update_existing_creation_boards() {
        global $wpdb;
        
        error_log("QL Moodle: [FORCE UPDATE] Iniciando verificação de quadros de criação");
        
        // Buscar quadros que precisam ser atualizados - busca mais ampla
        $creation_boards = $wpdb->get_results("
            SELECT DISTINCT b.id as board_id, b.name as board_name, p.name as project_name, b.settings
            FROM {$wpdb->prefix}ql_boards b
            JOIN {$wpdb->prefix}ql_projects p ON b.project_id = p.id
            WHERE (b.settings LIKE '%trilha_criacao%' OR 
                   b.name LIKE '%Processo Criativo%' OR
                   b.name LIKE '%criação%' OR 
                   b.name LIKE '%criativo%' OR
                   b.name LIKE '%creation%')
        ");
        
        error_log("QL Moodle: [FORCE UPDATE] Query executada, encontrados " . count($creation_boards) . " quadros");
        
        if (empty($creation_boards)) {
            error_log("QL Moodle: [FORCE UPDATE] Nenhum quadro de criação encontrado");
            
            // Debug: mostrar todos os quadros para investigação
            $all_boards = $wpdb->get_results("SELECT id, name, settings FROM {$wpdb->prefix}ql_boards LIMIT 10");
            foreach ($all_boards as $board) {
                error_log("QL Moodle: [DEBUG] Quadro encontrado: {$board->name} (settings: {$board->settings})");
            }
            return;
        }
        
        $updated_count = 0;
        $skipped_count = 0;
        
        foreach ($creation_boards as $board) {
            error_log("QL Moodle: [FORCE UPDATE] Verificando quadro: {$board->board_name} (ID: {$board->board_id})");
            
            // Verificar colunas atuais com mais detalhes
            $current_columns = $wpdb->get_results($wpdb->prepare(
                "SELECT name, position FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position",
                $board->board_id
            ));
            
            $column_names = array_map(function($col) { return $col->name; }, $current_columns);
            error_log("QL Moodle: [FORCE UPDATE] Colunas atuais: " . implode(', ', $column_names));
            
            $first_three_columns = array_slice($column_names, 0, 3);
            
            // Verificar se já está correto
            if (count($first_three_columns) >= 3 && 
                $first_three_columns[0] === 'Problema' && 
                $first_three_columns[1] === 'Ideação' && 
                $first_three_columns[2] === 'Planejamento') {
                error_log("QL Moodle: [FORCE UPDATE] Quadro {$board->board_name} já está correto, pulando");
                $skipped_count++;
                continue;
            }
            
            error_log("QL Moodle: [FORCE UPDATE] Quadro {$board->board_name} precisa ser atualizado");
            error_log("QL Moodle: [FORCE UPDATE] Colunas atuais: " . implode(' > ', $first_three_columns));
            error_log("QL Moodle: [FORCE UPDATE] Esperadas: Problema > Ideação > Planejamento");
            
            try {
                // Forçar atualização para tipo de trilha de criação
                error_log("QL Moodle: [FORCE UPDATE] Chamando update_board_for_new_trilha_type para board {$board->board_id}");
                $this->update_board_for_new_trilha_type($board->board_id, 'criacao');
                
                // Atualizar settings do quadro
                $settings_result = $wpdb->update(
                    $wpdb->prefix . 'ql_boards',
                    ['settings' => json_encode(['auto_created' => true, 'trilha_type' => 'criacao'])],
                    ['id' => $board->board_id]
                );
                
                error_log("QL Moodle: [FORCE UPDATE] Settings atualizados (linhas afetadas: $settings_result)");
                
                // Verificar se funcionou
                $new_columns = $wpdb->get_results($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position LIMIT 3",
                    $board->board_id
                ));
                
                $new_column_names = array_map(function($col) { return $col->name; }, $new_columns);
                error_log("QL Moodle: [FORCE UPDATE] Colunas após atualização: " . implode(', ', $new_column_names));
                
                $updated_count++;
                error_log("QL Moodle: [FORCE UPDATE] Quadro {$board->board_name} atualizado com sucesso");
                
            } catch (Exception $e) {
                error_log("QL Moodle: [FORCE UPDATE] Erro ao atualizar quadro {$board->board_name}: " . $e->getMessage());
            }
        }
        
        error_log("QL Moodle: [FORCE UPDATE] Atualização concluída - $updated_count atualizados, $skipped_count pulados");
    }
    
    /**
     * Verificar e atualizar quadros de criação que ainda usam template antigo
     */
    private function check_and_update_creation_boards_with_old_template($project_id) {
        global $wpdb;
        
        error_log("QL Moodle: Verificando quadros de criação no projeto $project_id para template antigo");
        
        // Buscar quadros de criação neste projeto
        $boards = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}ql_boards WHERE project_id = %d",
            $project_id
        ));
        
        foreach ($boards as $board) {
            // Verificar se o quadro tem as primeiras colunas do template antigo
            $columns = $wpdb->get_results($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position LIMIT 3",
                $board->id
            ));
            
            if (count($columns) >= 2) {
                $first_two = array_map(function($col) { return $col->name; }, array_slice($columns, 0, 2));
                
                // Se tem "Ideação" e "Prototipagem" nas primeiras posições, é template antigo
                if (in_array('Ideação', $first_two) && in_array('Prototipagem', $first_two)) {
                    error_log("QL Moodle: Quadro {$board->name} (ID: {$board->id}) tem template antigo, atualizando");
                    
                    // Forçar atualização para o novo template
                    $this->update_board_for_new_trilha_type($board->id, 'criacao');
                    
                    error_log("QL Moodle: Quadro {$board->name} atualizado para novo template de criação");
                }
            }
        }
    }
    
    /**
     * Handler para sincronização de membros com delay
     */
    public function sync_project_members_delayed_handler($project_id, $course_id) {
        try {
            $result = $this->sync_project_members($project_id, $course_id);
            if ($result) {
                error_log("QL Moodle: Sincronização delayed concluída - Projeto {$project_id}: {$result['synced_count']} membros de {$result['total_participants']} participantes");
            }
        } catch (Exception $e) {
            error_log("QL Moodle: Erro na sincronização delayed do projeto {$project_id}: " . $e->getMessage());
        }
    }

    /**
     * Chamar API do Moodle
     */
    private function call_moodle_api($function, $params = []) {
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            throw new Exception('Configurações do Moodle não encontradas');
        }
        
        $post_data = [
            'wstoken' => $this->moodle_token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json'
        ];
        
        // Adicionar parâmetros específicos
        foreach ($params as $key => $value) {
            $post_data[$key] = $value;
        }
        
        // Log da requisição (sem token por segurança)
        $log_data = $post_data;
        $log_data['wstoken'] = '***hidden***';
        error_log('QL Moodle API: Chamando ' . $function . ' com dados: ' . json_encode($log_data));
        
        $response = wp_remote_post($this->api_endpoint, [
            'body' => $post_data,
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Quilombo-Laboratorio-Plugin/1.0'
            ]
        ]);
        
        if (is_wp_error($response)) {
            $error_msg = 'Erro na requisição: ' . $response->get_error_message();
            error_log('QL Moodle API: ' . $error_msg);
            throw new Exception($error_msg);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('QL Moodle API: HTTP ' . $http_code . ' - Resposta: ' . substr($body, 0, 500));
        
        if ($http_code !== 200) {
            // Tratamento específico para erro 500
            if ($http_code === 500) {
                $error_msg = 'Erro interno do servidor Moodle (HTTP 500). ';
                $error_msg .= 'Possíveis causas: cache corrompido, problemas de configuração ou dependências faltando. ';
                $error_msg .= 'Verifique os logs do Moodle e tente regenerar o cache.';
                
                // Incluir parte da resposta se houver
                if (!empty($body) && strpos($body, 'Fatal error') !== false) {
                    $error_msg .= ' Erro PHP detectado: ' . substr($body, 0, 200);
                }
                
                throw new Exception($error_msg);
            }
            
            throw new Exception('HTTP Error ' . $http_code . ': ' . substr($body, 0, 200));
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('QL Moodle API: Erro JSON: ' . json_last_error_msg());
            throw new Exception('Resposta inválida do Moodle: ' . substr($body, 0, 200));
        }
        
        if (isset($data['exception'])) {
            $error_msg = 'Erro da API Moodle: ' . $data['message'];
            error_log('QL Moodle API: ' . $error_msg);
            throw new Exception($error_msg);
        }
        
        error_log('QL Moodle API: Sucesso na chamada ' . $function);
        return $data;
    }
    
    /**
     * Obter cor da trilha por tipo
     */
    private function get_trilha_color($type) {
        $colors = [
            'aprendizagem' => '#3498db',
            'pesquisa' => '#9b59b6',
            'criacao' => '#e67e22'
        ];
        
        return $colors[$type] ?? $colors['aprendizagem'];
    }
    
    /**
     * Sincronização agendada
     */
    public function scheduled_sync() {
        if (empty($this->moodle_url) || empty($this->moodle_token)) {
            error_log('QL Moodle Sync: Configurações não encontradas, pulando sincronização automática');
            return;
        }
        
        try {
            // Sincronização de cursos/trilhas
            $result = $this->sync_courses_from_moodle();
            error_log('QL Moodle Sync: Sincronização de cursos executada com sucesso');
            
            // Sincronizar instâncias (grupos/agrupamentos)
            $this->sync_instances_from_moodle();
            error_log('QL Moodle Sync: Sincronização de instâncias executada');
            
            // Atualizar configuração de papéis ativos
            $this->update_active_roles_configuration();
            error_log('QL Moodle Sync: Configuração de papéis atualizada');
            
            // Importar papéis de todos os cursos mapeados
            $roles_result = $this->import_all_course_roles();
            if (!is_wp_error($roles_result)) {
                error_log("QL Moodle Sync: Papéis importados - {$roles_result['total_imported']} importados, {$roles_result['total_skipped']} pulados");
            }
            
            error_log('QL Moodle Sync: Sincronização automática completa executada com sucesso');
        } catch (Exception $e) {
            error_log('QL Moodle Sync: Erro na sincronização automática: ' . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar instâncias (grupos/agrupamentos) do Moodle
     */
    public function sync_instances_from_moodle() {
        if (!class_exists('QL_Instances')) {
            error_log('QL Moodle: Classe QL_Instances não disponível');
            return;
        }
        
        try {
            $instances = QL_Instances::get_instance();
            $result = $instances->import_from_moodle_groups();
            
            if (is_wp_error($result)) {
                error_log('QL Moodle: Erro ao sincronizar instâncias: ' . $result->get_error_message());
            } else {
                error_log(sprintf(
                    'QL Moodle: Sincronização de instâncias concluída - %d círculos, %d núcleos',
                    $result['circles'],
                    $result['nuclei']
                ));
            }
            
        } catch (Exception $e) {
            error_log('QL Moodle: Erro na sincronização de instâncias: ' . $e->getMessage());
        }
    }
    
    /**
     * Hook quando configurações são atualizadas
     */
    public function on_settings_updated($old_value, $new_value) {
        // Recarregar configurações quando mudarem
        $this->load_settings();
        
        // Se URL ou token mudaram, limpar cache se houver
        if (($old_value['moodle_url'] ?? '') !== ($new_value['moodle_url'] ?? '') ||
            ($old_value['moodle_token'] ?? '') !== ($new_value['moodle_token'] ?? '')) {
            // Limpar qualquer cache relacionado
            delete_transient('ql_moodle_connection_test');
        }
    }
    
    
    /**
     * Configurar projeto como trilha do coletivo
     */
    private function configure_as_collective_trail($project_id, $course) {
        // Marcar no banco como projeto coletivo
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            [
                'settings' => json_encode([
                    'trilha_type' => 'aprendizagem',
                    'moodle_course_id' => $course['id'],
                    'auto_sync' => true,
                    'is_collective_project' => true,
                    'is_site_course' => true
                ])
            ],
            ['id' => $project_id],
            ['%s'],
            ['%d']
        );
        
        // Adicionar meta especial para identificação rápida
        $this->ensure_meta_table();
        $wpdb->replace(
            $wpdb->prefix . 'ql_project_meta',
            [
                'project_id' => $project_id,
                'meta_key' => 'is_collective_project',
                'meta_value' => '1'
            ]
        );
        
        $wpdb->replace(
            $wpdb->prefix . 'ql_project_meta',
            [
                'project_id' => $project_id,
                'meta_key' => 'moodle_site_course',
                'meta_value' => $course['id']
            ]
        );
        
        // Disparar hook para integração opcional com GC
        if (function_exists('do_action')) {
            do_action('ql_collective_project_configured', $project_id, $course);
        }
    }
    
    /**
     * Garantir que tabela de metadados existe
     */
    private function ensure_meta_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_project_meta';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            project_id bigint(20) NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY project_id (project_id),
            KEY meta_key (meta_key),
            UNIQUE KEY project_meta (project_id, meta_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Obter o projeto coletivo (projeto correspondente ao site principal do Moodle)
     */
    public static function get_collective_project() {
        global $wpdb;
        
        // Buscar projeto marcado como coletivo
        $project = $wpdb->get_row("
            SELECT p.* 
            FROM {$wpdb->prefix}ql_projects p
            LEFT JOIN {$wpdb->prefix}ql_project_meta pm ON p.id = pm.project_id
            WHERE pm.meta_key = 'is_collective_project' AND pm.meta_value = '1'
            LIMIT 1
        ");
        
        if (!$project) {
            // Buscar pelo curso do site (ID 1) como fallback
            $mapping = $wpdb->get_row("
                SELECT mm.*, p.*
                FROM {$wpdb->prefix}ql_moodle_mappings mm
                LEFT JOIN {$wpdb->prefix}ql_projects p ON mm.ql_project_id = p.id
                WHERE mm.moodle_course_id = 1
                LIMIT 1
            ");
            
            if ($mapping) {
                $project = $mapping;
            }
        }
        
        return $project;
    }
    
    /**
     * Verificar se um projeto é o projeto coletivo
     */
    public static function is_collective_project($project_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}ql_project_meta 
            WHERE project_id = %d AND meta_key = 'is_collective_project' AND meta_value = '1'
        ", $project_id));
        
        return $count > 0;
    }
    
    /**
     * Verificar se conseguimos acessar o curso principal do site
     */
    private function check_site_course_access() {
        try {
            // Primeiro tentar core_course_get_courses_by_field (mais específico)
            $response = $this->call_moodle_api('core_course_get_courses_by_field', [
                'field' => 'id',
                'value' => 1
            ]);
            
            return !empty($response['courses']);
            
        } catch (Exception $e) {
            error_log('QL Moodle: Tentativa core_course_get_courses_by_field falhou: ' . $e->getMessage());
            
            // Fallback: tentar core_course_get_courses e verificar se ID 1 está na lista
            try {
                $all_courses = $this->call_moodle_api('core_course_get_courses');
                
                foreach ($all_courses as $course) {
                    if ($course['id'] == 1) {
                        return true;
                    }
                }
                
                return false;
                
            } catch (Exception $e2) {
                error_log('QL Moodle: Fallback core_course_get_courses também falhou: ' . $e2->getMessage());
                
                // Se der erro de permissão ou contexto, o curso não é acessível
                if (strpos($e2->getMessage(), 'contexto') !== false || 
                    strpos($e2->getMessage(), 'permission') !== false ||
                    strpos($e2->getMessage(), 'configurado') !== false) {
                    return false;
                }
                
                // Outros erros podem ser temporários, assumir que é acessível
                return true;
            }
        }
    }
    
    /**
     * Criar entrada manual para o curso principal quando não é diretamente acessível
     */
    private function create_site_course_entry() {
        try {
            // Obter informações básicas do site via API de informações gerais
            $site_info = $this->call_moodle_api('core_webservice_get_site_info');
            
            if (!$site_info || !isset($site_info['sitename'])) {
                return null;
            }
            
            // Criar curso "virtual" baseado nas informações do site
            return [
                'id' => 1,
                'fullname' => $site_info['sitename'],
                'shortname' => 'site',
                'summary' => 'Curso principal do site - ' . ($site_info['sitename'] ?? 'Quilombo Ciência'),
                'summaryformat' => 1,
                'startdate' => time(),
                'enddate' => 0,
                'visible' => 1,
                'categoryid' => 0,
                'format' => 'site',
                'showgrades' => false,
                'newsitems' => 0,
                'numsections' => 0,
                'lang' => $site_info['lang'] ?? 'pt_br',
                'theme' => $site_info['theme'] ?? '',
                'enablecompletion' => false,
                'completionnotify' => false,
                'groupmode' => 0,
                'groupmodeforce' => 0,
                'filters' => [],
                'courseformatoptions' => [],
                '_is_virtual_site_course' => true // Flag para identificar como curso virtual
            ];
            
        } catch (Exception $e) {
            error_log("QL Moodle Integration: Erro ao criar entrada do curso do site: " . $e->getMessage());
            
            // Fallback com dados mínimos
            return [
                'id' => 1,
                'fullname' => 'Quilombo Ciência - Site Principal',
                'shortname' => 'site',
                'summary' => 'Projeto principal do coletivo Quilombo Ciência',
                'summaryformat' => 1,
                'startdate' => time(),
                'enddate' => 0,
                'visible' => 1,
                'categoryid' => 0,
                'format' => 'site',
                '_is_virtual_site_course' => true
            ];
        }
    }
    
    /**
     * Identificar trilha coletiva por características alternativas
     * Usado quando não conseguimos acessar diretamente o curso ID 1
     */
    public static function identify_collective_trail_by_pattern($courses) {
        if (empty($courses) || !is_array($courses)) {
            return null;
        }
        
        // Padrões para identificar trilha coletiva
        $collective_patterns = [
            // Por nome/título
            'site principal',
            'quilombo ciência',
            'coletivo',
            'home',
            'principal',
            'main',
            // Por shortname
            'site',
            'home',
            'main',
            'qc',
            'quilombo'
        ];
        
        foreach ($courses as $course) {
            $name_lower = strtolower($course['fullname'] ?? '');
            $short_lower = strtolower($course['shortname'] ?? '');
            
            // Verificar padrões no nome completo
            foreach ($collective_patterns as $pattern) {
                if (strpos($name_lower, $pattern) !== false || 
                    strpos($short_lower, $pattern) !== false) {
                    
                    error_log("QL Moodle Integration: Trilha coletiva identificada por padrão '{$pattern}': {$course['fullname']}");
                    return $course;
                }
            }
            
            // Verificar se é curso de categoria especial (ID 0 ou 1 normalmente indica site)
            if (isset($course['categoryid']) && $course['categoryid'] <= 1) {
                error_log("QL Moodle Integration: Trilha coletiva identificada por categoria: {$course['fullname']}");
                return $course;
            }
        }
        
        return null;
    }
    
    /**
     * Criar trilha coletiva manualmente quando não encontrada automaticamente
     */
    public static function create_manual_collective_trail() {
        try {
            // Verificar se já existe uma trilha coletiva
            $existing = QL_Moodle_Integration::get_collective_project();
            if ($existing) {
                return $existing->id;
            }
            
            // Obter informações básicas do site
            $integration = QL_Moodle_Integration::get_instance();
            $site_info = null;
            
            try {
                $site_info = $integration->call_moodle_api('core_webservice_get_site_info');
            } catch (Exception $e) {
                error_log("QL Moodle Integration: Erro ao obter info do site: " . $e->getMessage());
            }
            
            // Dados para trilha coletiva manual (sem dependência do GC)
            $project_data = [
                'name' => $site_info['sitename'] ?? 'Projeto do Coletivo - Quilombo Ciência',
                'slug' => 'projeto-coletivo-manual',
                'description' => 'Projeto principal do coletivo criado manualmente (trilha Moodle não acessível)',
                'status' => 'active',
                'visibility' => 'public',
                'owner_id' => 1,
                'priority' => 'high',
                'color' => '#27ae60',
                'settings' => json_encode([
                    'trilha_type' => 'aprendizagem',
                    'is_collective_project' => true,
                    'manual_creation' => true
                ]),
                'created_at' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ];
            
            // Criar projeto diretamente no QL
            global $wpdb;
            $result = $wpdb->insert($wpdb->prefix . 'ql_projects', $project_data);
            
            if ($result !== false) {
                $project_id = $wpdb->insert_id;
                error_log("QL Moodle Integration: Projeto coletivo manual criado - ID: {$project_id}");
                
                // Disparar hook para integração opcional com GC
                if (function_exists('do_action')) {
                    do_action('ql_project_created', $project_id, $project_data, null, 'aprendizagem');
                }
                
                return $project_id;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle Integration: Erro ao criar projeto coletivo manual: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Criar grupo no Moodle correspondente a um círculo
     * Conforme modelo organizativo: Círculo = Grupo no Moodle
     */
    public function create_moodle_group_for_circle($circle_id, $course_id) {
        if (empty($this->api_endpoint) || empty($this->moodle_token)) {
            return new WP_Error('moodle_config', 'Configuração do Moodle incompleta');
        }
        
        // Obter dados do círculo
        $instances_manager = QL_Instances::get_instance();
        $circle = $instances_manager->get_instance_by_id($circle_id);
        
        if (!$circle || $circle->type !== 'circulo') {
            return new WP_Error('invalid_circle', 'Círculo não encontrado');
        }
        
        try {
            // Verificar se grupo já existe
            $existing_group = $this->get_moodle_group_by_name($course_id, $circle->name);
            if ($existing_group) {
                error_log("QL Moodle: Grupo já existe no Moodle para círculo {$circle_id}");
                return $existing_group['id'];
            }
            
            // Criar grupo no Moodle
            $group_data = [
                'courseid' => $course_id,
                'name' => $circle->name,
                'description' => $circle->description ?: 'Círculo criado via QuilomboLab',
                'descriptionformat' => 1
            ];
            
            $response = $this->call_moodle_api('core_group_create_groups', [
                'groups' => [$group_data]
            ]);
            
            if (!empty($response) && is_array($response)) {
                $group_id = $response[0]['id'];
                error_log("QL Moodle: Grupo criado no Moodle - ID: {$group_id} para círculo: {$circle_id}");
                
                // Armazenar mapping circle -> group
                $this->store_circle_group_mapping($circle_id, $course_id, $group_id);
                
                // Sincronizar membros do círculo para o grupo
                $this->sync_circle_members_to_group($circle_id, $group_id);
                
                return $group_id;
            }
            
            return new WP_Error('group_creation_failed', 'Falha ao criar grupo no Moodle');
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao criar grupo para círculo {$circle_id}: " . $e->getMessage());
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Criar agrupamento no Moodle correspondente a um núcleo
     * Conforme modelo organizativo: Núcleo = Agrupamento no Moodle
     */
    public function create_moodle_grouping_for_nucleus($nucleus_id, $course_id) {
        if (empty($this->api_endpoint) || empty($this->moodle_token)) {
            return new WP_Error('moodle_config', 'Configuração do Moodle incompleta');
        }
        
        // Obter dados do núcleo
        $instances_manager = QL_Instances::get_instance();
        $nucleus = $instances_manager->get_instance_by_id($nucleus_id);
        
        if (!$nucleus || $nucleus->type !== 'nucleo') {
            return new WP_Error('invalid_nucleus', 'Núcleo não encontrado');
        }
        
        try {
            // Verificar se agrupamento já existe
            $existing_grouping = $this->get_moodle_grouping_by_name($course_id, $nucleus->name);
            if ($existing_grouping) {
                error_log("QL Moodle: Agrupamento já existe no Moodle para núcleo {$nucleus_id}");
                return $existing_grouping['id'];
            }
            
            // Criar agrupamento no Moodle
            $grouping_data = [
                'courseid' => $course_id,
                'name' => $nucleus->name,
                'description' => $nucleus->description ?: 'Núcleo criado via QuilomboLab',
                'descriptionformat' => 1
            ];
            
            $response = $this->call_moodle_api('core_group_create_groupings', [
                'groupings' => [$grouping_data]
            ]);
            
            if (!empty($response) && is_array($response)) {
                $grouping_id = $response[0]['id'];
                error_log("QL Moodle: Agrupamento criado no Moodle - ID: {$grouping_id} para núcleo: {$nucleus_id}");
                
                // Armazenar mapping nucleus -> grouping
                $this->store_nucleus_grouping_mapping($nucleus_id, $course_id, $grouping_id);
                
                // Adicionar grupos dos círculos filhos ao agrupamento
                $this->sync_nucleus_circles_to_grouping($nucleus_id, $course_id, $grouping_id);
                
                return $grouping_id;
            }
            
            return new WP_Error('grouping_creation_failed', 'Falha ao criar agrupamento no Moodle');
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao criar agrupamento para núcleo {$nucleus_id}: " . $e->getMessage());
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Verificar se grupo existe no Moodle pelo nome
     */
    private function get_moodle_group_by_name($course_id, $name) {
        try {
            $groups = $this->call_moodle_api('core_group_get_course_groups', [
                'courseid' => $course_id
            ]);
            
            foreach ($groups as $group) {
                if ($group['name'] === $name) {
                    return $group;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao buscar grupo por nome: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar se agrupamento existe no Moodle pelo nome
     */
    private function get_moodle_grouping_by_name($course_id, $name) {
        try {
            $groupings = $this->call_moodle_api('core_group_get_course_groupings', [
                'courseid' => $course_id
            ]);
            
            foreach ($groupings as $grouping) {
                if ($grouping['name'] === $name) {
                    return $grouping;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao buscar agrupamento por nome: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Sincronizar membros do círculo para grupo do Moodle
     */
    private function sync_circle_members_to_group($circle_id, $group_id) {
        try {
            $instances_manager = QL_Instances::get_instance();
            $members = $instances_manager->get_instance_members($circle_id);
            
            foreach ($members as $member) {
                // Buscar usuário no Moodle pelo email
                $moodle_user = $this->get_moodle_user_by_email($member->user_email);
                
                if ($moodle_user) {
                    // Adicionar usuário ao grupo
                    $this->add_user_to_moodle_group($moodle_user['id'], $group_id);
                }
            }
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao sincronizar membros do círculo {$circle_id}: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar círculos filhos de um núcleo para agrupamento
     */
    private function sync_nucleus_circles_to_grouping($nucleus_id, $course_id, $grouping_id) {
        try {
            // Buscar círculos filhos do núcleo diretamente no banco
            $child_circles = $this->get_nucleus_child_circles($nucleus_id);
            
            foreach ($child_circles as $circle) {
                // Verificar se há grupo correspondente no Moodle para este círculo
                $group_mapping = $this->get_circle_group_mapping($circle->id, $course_id);
                
                if ($group_mapping) {
                    // Adicionar grupo ao agrupamento
                    $this->add_group_to_grouping($group_mapping['moodle_object_id'], $grouping_id);
                } else {
                    // Criar grupo se não existir
                    $group_id = $this->create_moodle_group_for_circle($circle->id, $course_id);
                    if (!is_wp_error($group_id)) {
                        $this->add_group_to_grouping($group_id, $grouping_id);
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao sincronizar círculos do núcleo {$nucleus_id}: " . $e->getMessage());
        }
    }
    
    /**
     * Obter círculos filhos de um núcleo
     */
    private function get_nucleus_child_circles($nucleus_id) {
        global $wpdb;
        
        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        $instances_table = $wpdb->prefix . 'ql_instances';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.* FROM $instances_table i 
             JOIN $relationships_table r ON i.id = r.child_instance_id 
             WHERE r.parent_instance_id = %d 
             AND i.type = 'circulo' 
             AND i.status IN ('active', 'forming')",
            $nucleus_id
        ));
    }
    
    /**
     * Buscar usuário no Moodle pelo email
     */
    private function get_moodle_user_by_email($email) {
        try {
            $response = $this->call_moodle_api('core_user_get_users', [
                'criteria' => [
                    [
                        'key' => 'email',
                        'value' => $email
                    ]
                ]
            ]);
            
            if (!empty($response['users'])) {
                return $response['users'][0];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao buscar usuário por email {$email}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Adicionar usuário a grupo no Moodle
     */
    private function add_user_to_moodle_group($user_id, $group_id) {
        try {
            return $this->call_moodle_api('core_group_add_group_members', [
                'members' => [
                    [
                        'groupid' => $group_id,
                        'userid' => $user_id
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao adicionar usuário {$user_id} ao grupo {$group_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Adicionar grupo a agrupamento no Moodle
     */
    private function add_group_to_grouping($group_id, $grouping_id) {
        try {
            return $this->call_moodle_api('core_group_assign_grouping', [
                'assignments' => [
                    [
                        'groupingid' => $grouping_id,
                        'groupid' => $group_id
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao adicionar grupo {$group_id} ao agrupamento {$grouping_id}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Armazenar mapping círculo -> grupo
     */
    private function store_circle_group_mapping($circle_id, $course_id, $group_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        
        // Criar tabela se não existir
        $this->maybe_create_mappings_table();
        
        return $wpdb->replace(
            $table_name,
            [
                'ql_instance_id' => $circle_id,
                'ql_instance_type' => 'circulo',
                'moodle_course_id' => $course_id,
                'moodle_object_id' => $group_id,
                'moodle_object_type' => 'group',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s']
        );
    }
    
    /**
     * Armazenar mapping núcleo -> agrupamento
     */
    private function store_nucleus_grouping_mapping($nucleus_id, $course_id, $grouping_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        
        // Criar tabela se não existir
        $this->maybe_create_mappings_table();
        
        return $wpdb->replace(
            $table_name,
            [
                'ql_instance_id' => $nucleus_id,
                'ql_instance_type' => 'nucleo',
                'moodle_course_id' => $course_id,
                'moodle_object_id' => $grouping_id,
                'moodle_object_type' => 'grouping',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s']
        );
    }
    
    /**
     * Obter mapping círculo -> grupo
     */
    private function get_circle_group_mapping($circle_id, $course_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE ql_instance_id = %d 
             AND ql_instance_type = 'circulo' 
             AND moodle_course_id = %d 
             AND moodle_object_type = 'group'",
            $circle_id, $course_id
        ), ARRAY_A);
    }
    
    /**
     * Criar tabela para mapeamentos Moodle se não existir
     */
    private function maybe_create_mappings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_moodle_mappings';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return; // Tabela já existe
        }
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ql_instance_id bigint(20) NOT NULL,
            ql_instance_type varchar(50) NOT NULL,
            moodle_course_id bigint(20) NOT NULL,
            moodle_object_id bigint(20) NOT NULL,
            moodle_object_type varchar(50) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY mapping_unique (ql_instance_id, ql_instance_type, moodle_course_id, moodle_object_type),
            KEY ql_instance (ql_instance_id, ql_instance_type),
            KEY moodle_object (moodle_course_id, moodle_object_id, moodle_object_type)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        error_log("QL Moodle: Tabela de mapeamentos criada: $table_name");
    }
    
    /**
     * AJAX - Criar grupo no Moodle para círculo
     */
    public function ajax_create_group_for_circle() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $circle_id = intval($_POST['circle_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$circle_id || !$course_id) {
            wp_send_json_error('IDs inválidos');
        }
        
        $result = $this->create_moodle_group_for_circle($circle_id, $course_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Grupo criado no Moodle com sucesso',
                'group_id' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Criar agrupamento no Moodle para núcleo
     */
    public function ajax_create_grouping_for_nucleus() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $nucleus_id = intval($_POST['nucleus_id'] ?? 0);
        $course_id = intval($_POST['course_id'] ?? 0);
        
        if (!$nucleus_id || !$course_id) {
            wp_send_json_error('IDs inválidos');
        }
        
        $result = $this->create_moodle_grouping_for_nucleus($nucleus_id, $course_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Agrupamento criado no Moodle com sucesso',
                'grouping_id' => $result
            ]);
        }
    }
    
    /**
     * Hook handler: quando um círculo é criado, criar grupo correspondente no Moodle
     */
    public function on_circle_created($circle_id, $data) {
        // Só executar se houver projeto associado (trilha no Moodle)
        if (empty($data['project_id'])) {
            return;
        }
        
        // Buscar curso Moodle correspondente ao projeto
        global $wpdb;
        $course_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT moodle_course_id FROM {$wpdb->prefix}ql_project_moodle_mapping 
             WHERE project_id = %d",
            $data['project_id']
        ));
        
        if (!$course_mapping) {
            error_log("QL Moodle: Curso não encontrado para projeto {$data['project_id']}");
            return;
        }
        
        // Criar grupo no Moodle
        $result = $this->create_moodle_group_for_circle($circle_id, $course_mapping->moodle_course_id);
        
        if (is_wp_error($result)) {
            error_log("QL Moodle: Erro ao criar grupo para círculo {$circle_id}: " . $result->get_error_message());
        } else {
            error_log("QL Moodle: Grupo criado automaticamente para círculo {$circle_id} - Group ID: {$result}");
        }
    }
    
    /**
     * Hook handler: quando um núcleo é criado, criar agrupamento correspondente no Moodle
     */
    public function on_nucleus_created($nucleus_id, $data) {
        // Buscar círculos filhos que tenham projetos com trilhas Moodle
        $child_circles = $this->get_nucleus_child_circles($nucleus_id);
        
        if (empty($child_circles)) {
            error_log("QL Moodle: Núcleo {$nucleus_id} não possui círculos filhos");
            return;
        }
        
        // Obter cursos Moodle dos círculos filhos
        $course_ids = [];
        foreach ($child_circles as $circle) {
            global $wpdb;
            $course_mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT DISTINCT pm.moodle_course_id 
                 FROM {$wpdb->prefix}ql_project_moodle_mapping pm
                 JOIN {$wpdb->prefix}ql_projects p ON pm.project_id = p.id
                 WHERE p.id IN (
                     SELECT project_id FROM {$wpdb->prefix}ql_instances 
                     WHERE id = %d AND project_id IS NOT NULL
                 )",
                $circle->id
            ));
            
            if ($course_mapping) {
                $course_ids[] = $course_mapping->moodle_course_id;
            }
        }
        
        // Criar agrupamentos nos cursos encontrados
        foreach (array_unique($course_ids) as $course_id) {
            $result = $this->create_moodle_grouping_for_nucleus($nucleus_id, $course_id);
            
            if (is_wp_error($result)) {
                error_log("QL Moodle: Erro ao criar agrupamento para núcleo {$nucleus_id}: " . $result->get_error_message());
            } else {
                error_log("QL Moodle: Agrupamento criado automaticamente para núcleo {$nucleus_id} - Grouping ID: {$result}");
            }
        }
    }
    
    /**
     * Mapeamento dos papéis QL para papéis Moodle
     * Conforme modelo organizativo
     */
    const ROLE_MAPPING_QL_TO_MOODLE = [
        'participante' => 'student', // Estudante (padrão Moodle)
        'guia' => 'teacher', // Professor (padrão Moodle)
        'orientacao' => 'orientacao', // Papel customizado
        'operacao' => 'operacao', // Papel customizado
        'comunicacao' => 'comunicacao', // Papel customizado
        'documentacao' => 'documentacao', // Papel customizado
        'gestao' => 'gestao' // Papel customizado
    ];
    
    /**
     * Detectar quais papéis organizativos existem no Moodle
     */
    public function detect_moodle_organizational_roles() {
        if (empty($this->api_endpoint) || empty($this->moodle_token)) {
            return new WP_Error('moodle_config', 'Configuração do Moodle incompleta');
        }
        
        try {
            // Buscar todos os papéis disponíveis no Moodle
            $moodle_roles = $this->call_moodle_api('core_role_get_all_roles');
            
            if (empty($moodle_roles)) {
                return new WP_Error('no_roles', 'Nenhum papel encontrado no Moodle');
            }
            
            $detected_roles = [];
            $moodle_role_names = array_column($moodle_roles, 'shortname');
            
            // Verificar quais papéis QL existem no Moodle
            foreach (self::ROLE_MAPPING_QL_TO_MOODLE as $ql_role => $moodle_role) {
                $exists = in_array($moodle_role, $moodle_role_names);
                $detected_roles[$ql_role] = [
                    'exists_in_moodle' => $exists,
                    'moodle_shortname' => $moodle_role,
                    'moodle_role_id' => $exists ? $this->get_moodle_role_id($moodle_role, $moodle_roles) : null,
                    'fallback_to_wp' => !$exists
                ];
                
                error_log("QL Moodle Roles: Papel '{$ql_role}' -> '{$moodle_role}' " . ($exists ? 'EXISTE' : 'NÃO EXISTE') . " no Moodle");
            }
            
            // Salvar configuração de papéis detectados
            update_option('ql_moodle_roles_config', $detected_roles);
            
            return $detected_roles;
            
        } catch (Exception $e) {
            error_log("QL Moodle Roles: Erro ao detectar papéis: " . $e->getMessage());
            return new WP_Error('detection_failed', $e->getMessage());
        }
    }
    
    /**
     * Obter ID do papel no Moodle pelo shortname
     */
    private function get_moodle_role_id($shortname, $moodle_roles) {
        foreach ($moodle_roles as $role) {
            if ($role['shortname'] === $shortname) {
                return $role['id'];
            }
        }
        return null;
    }
    
    /**
     * Obter configuração atual dos papéis detectados
     */
    public function get_roles_configuration() {
        return get_option('ql_moodle_roles_config', []);
    }
    
    /**
     * Verificar se um papel QL está disponível no Moodle
     */
    public function is_role_available_in_moodle($ql_role_key) {
        $config = $this->get_roles_configuration();
        return isset($config[$ql_role_key]) && $config[$ql_role_key]['exists_in_moodle'];
    }
    
    /**
     * Sincronizar papel de usuário QL para Moodle em um curso específico
     */
    public function sync_user_role_to_moodle($user_id, $ql_role_key, $course_id, $action = 'assign') {
        if (!$this->is_role_available_in_moodle($ql_role_key)) {
            error_log("QL Moodle Roles: Papel '{$ql_role_key}' não disponível no Moodle, usando permissões WordPress");
            return false; // Fallback para WordPress
        }
        
        // Buscar usuário no Moodle pelo email
        $wp_user = get_user_by('id', $user_id);
        if (!$wp_user) {
            return new WP_Error('user_not_found', 'Usuário WordPress não encontrado');
        }
        
        $moodle_user = $this->get_moodle_user_by_email($wp_user->user_email);
        if (!$moodle_user) {
            return new WP_Error('moodle_user_not_found', 'Usuário não encontrado no Moodle');
        }
        
        $config = $this->get_roles_configuration();
        $moodle_role_id = $config[$ql_role_key]['moodle_role_id'];
        
        try {
            if ($action === 'assign') {
                // Atribuir papel no curso
                $result = $this->call_moodle_api('enrol_manual_enrol_users', [
                    'enrolments' => [
                        [
                            'roleid' => $moodle_role_id,
                            'userid' => $moodle_user['id'],
                            'courseid' => $course_id
                        ]
                    ]
                ]);
                
                error_log("QL Moodle Roles: Papel '{$ql_role_key}' atribuído ao usuário {$user_id} no curso {$course_id}");
                
            } elseif ($action === 'unassign') {
                // Remover papel do curso
                $result = $this->call_moodle_api('enrol_manual_unenrol_users', [
                    'enrolments' => [
                        [
                            'userid' => $moodle_user['id'],
                            'courseid' => $course_id
                        ]
                    ]
                ]);
                
                error_log("QL Moodle Roles: Papel '{$ql_role_key}' removido do usuário {$user_id} no curso {$course_id}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("QL Moodle Roles: Erro ao sincronizar papel: " . $e->getMessage());
            return new WP_Error('sync_failed', $e->getMessage());
        }
    }
    
    /**
     * Sincronizar todos os papéis de um projeto para o Moodle
     */
    public function sync_project_roles_to_moodle($project_id) {
        // Buscar curso Moodle correspondente ao projeto
        global $wpdb;
        $course_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT moodle_course_id FROM {$wpdb->prefix}ql_project_moodle_mapping 
             WHERE project_id = %d",
            $project_id
        ));
        
        if (!$course_mapping) {
            error_log("QL Moodle Roles: Curso não encontrado para projeto {$project_id}");
            return false;
        }
        
        $course_id = $course_mapping->moodle_course_id;
        
        // Buscar todos os usuários com papéis no projeto
        $organizational_roles = QL_Organizational_Roles::get_instance();
        $roles_synced = 0;
        $roles_fallback = 0;
        
        foreach (self::ROLE_MAPPING_QL_TO_MOODLE as $ql_role => $moodle_role) {
            $users_with_role = $organizational_roles->get_users_with_role($ql_role, 'project', $project_id);
            
            foreach ($users_with_role as $user) {
                $result = $this->sync_user_role_to_moodle($user->ID, $ql_role, $course_id, 'assign');
                
                if (is_wp_error($result)) {
                    error_log("QL Moodle Roles: Erro ao sincronizar usuário {$user->ID} com papel {$ql_role}");
                } elseif ($result === false) {
                    $roles_fallback++; // Papel não existe no Moodle, usando WordPress
                } else {
                    $roles_synced++;
                }
            }
        }
        
        error_log("QL Moodle Roles: Sincronização projeto {$project_id} - {$roles_synced} papéis sincronizados, {$roles_fallback} usando fallback WordPress");
        
        return [
            'synced' => $roles_synced,
            'fallback' => $roles_fallback
        ];
    }
    
    /**
     * Hook para sincronizar papel quando atribuído no QL
     */
    public function on_organizational_role_assigned($user_id, $role_key, $context_type, $context_id) {
        // Só sincronizar se for contexto de projeto
        if ($context_type !== 'project') {
            return;
        }
        
        // Buscar curso Moodle correspondente ao projeto
        global $wpdb;
        $course_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT moodle_course_id FROM {$wpdb->prefix}ql_project_moodle_mapping 
             WHERE project_id = %d",
            $context_id
        ));
        
        if (!$course_mapping) {
            error_log("QL Moodle Roles: Curso não encontrado para projeto {$context_id}");
            return;
        }
        
        $this->sync_user_role_to_moodle($user_id, $role_key, $course_mapping->moodle_course_id, 'assign');
    }
    
    /**
     * Hook para remover papel quando removido no QL
     */
    public function on_organizational_role_removed($user_id, $role_key, $context_type, $context_id) {
        // Só sincronizar se for contexto de projeto
        if ($context_type !== 'project') {
            return;
        }
        
        // Buscar curso Moodle correspondente ao projeto
        global $wpdb;
        $course_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT moodle_course_id FROM {$wpdb->prefix}ql_project_moodle_mapping 
             WHERE project_id = %d",
            $context_id
        ));
        
        if (!$course_mapping) {
            error_log("QL Moodle Roles: Curso não encontrado para projeto {$context_id}");
            return;
        }
        
        $this->sync_user_role_to_moodle($user_id, $role_key, $course_mapping->moodle_course_id, 'unassign');
    }
    
    /**
     * AJAX - Detectar papéis organizativos no Moodle
     */
    public function ajax_detect_moodle_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $result = $this->detect_moodle_organizational_roles();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Papéis detectados com sucesso',
                'roles_config' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Sincronizar papéis de projeto para Moodle
     */
    public function ajax_sync_project_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $project_id = intval($_POST['project_id'] ?? 0);
        
        if (!$project_id) {
            wp_send_json_error('ID do projeto inválido');
        }
        
        $result = $this->sync_project_roles_to_moodle($project_id);
        
        if ($result === false) {
            wp_send_json_error('Erro na sincronização');
        } else {
            wp_send_json_success([
                'message' => 'Papéis sincronizados com sucesso',
                'synced' => $result['synced'],
                'fallback' => $result['fallback']
            ]);
        }
    }
    
    /**
     * Buscar usuários e seus papéis em um curso específico do Moodle
     */
    public function get_course_enrolled_users_with_roles($course_id) {
        if (empty($this->api_endpoint) || empty($this->moodle_token)) {
            return new WP_Error('moodle_config', 'Configuração do Moodle incompleta');
        }
        
        try {
            // Buscar usuários inscritos no curso com seus papéis
            $enrolled_users = $this->call_moodle_api('core_enrol_get_enrolled_users', [
                'courseid' => $course_id,
                'withcapability' => '', // Vazio para buscar todos
                'groupid' => 0,
                'onlyactive' => 1,
                'userfields' => 'id,username,firstname,lastname,email',
                'limitfrom' => 0,
                'limitnumber' => 0
            ]);
            
            if (empty($enrolled_users)) {
                return [];
            }
            
            $users_with_roles = [];
            
            foreach ($enrolled_users as $user) {
                // Para cada usuário, identificar seus papéis no curso
                $user_roles = [];
                
                if (isset($user['roles'])) {
                    foreach ($user['roles'] as $role) {
                        $user_roles[] = [
                            'role_id' => $role['roleid'],
                            'role_name' => $role['name'],
                            'role_shortname' => $role['shortname']
                        ];
                    }
                }
                
                $users_with_roles[] = [
                    'moodle_user_id' => $user['id'],
                    'username' => $user['username'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname'], 
                    'email' => $user['email'],
                    'roles' => $user_roles
                ];
            }
            
            return $users_with_roles;
            
        } catch (Exception $e) {
            error_log("QL Moodle Roles: Erro ao buscar usuários inscritos: " . $e->getMessage());
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Importar atribuições de papéis do Moodle para um projeto específico
     */
    public function import_course_roles_to_project($course_id, $project_id) {
        // Buscar usuários e papéis no curso
        $course_users = $this->get_course_enrolled_users_with_roles($course_id);
        
        if (is_wp_error($course_users)) {
            return $course_users;
        }
        
        if (empty($course_users)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        }
        
        $roles_config = $this->get_roles_configuration();
        $organizational_roles = QL_Organizational_Roles::get_instance();
        
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($course_users as $course_user) {
            // Buscar usuário WordPress pelo email
            $wp_user = get_user_by('email', $course_user['email']);
            if (!$wp_user) {
                error_log("QL Moodle Import: Usuário WordPress não encontrado: {$course_user['email']}");
                $skipped++;
                continue;
            }
            
            // Para cada papel do usuário no Moodle
            foreach ($course_user['roles'] as $moodle_role) {
                $moodle_shortname = $moodle_role['role_shortname'];
                
                // Encontrar papel QL correspondente
                $ql_role = $this->get_ql_role_from_moodle_shortname($moodle_shortname);
                
                if (!$ql_role) {
                    error_log("QL Moodle Import: Papel Moodle '{$moodle_shortname}' não mapeado para QL");
                    $skipped++;
                    continue;
                }
                
                // Verificar se papel está disponível (existe no Moodle)
                if (!$this->is_role_available_in_moodle($ql_role)) {
                    error_log("QL Moodle Import: Papel '{$ql_role}' não está configurado como disponível");
                    $skipped++;
                    continue;
                }
                
                // Atribuir papel no QL usando método de importação
                $result = $organizational_roles->import_role_from_moodle(
                    $wp_user->ID,
                    $ql_role,
                    'project',
                    $project_id,
                    [
                        'moodle_course_id' => $course_id,
                        'moodle_role_id' => $moodle_role['role_id'],
                        'moodle_role_name' => $moodle_role['role_name'],
                        'moodle_user_id' => $course_user['moodle_user_id']
                    ]
                );
                
                if (is_wp_error($result)) {
                    error_log("QL Moodle Import: Erro ao atribuir papel {$ql_role} ao usuário {$wp_user->ID}: " . $result->get_error_message());
                    $errors++;
                } else {
                    error_log("QL Moodle Import: Papel {$ql_role} importado para usuário {$wp_user->display_name} no projeto {$project_id}");
                    $imported++;
                }
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_users' => count($course_users)
        ];
    }
    
    /**
     * Mapear papel do Moodle (shortname) para papel QL
     */
    private function get_ql_role_from_moodle_shortname($moodle_shortname) {
        // Inverter o mapeamento QL -> Moodle para encontrar QL <- Moodle
        $mapping = array_flip(self::ROLE_MAPPING_QL_TO_MOODLE);
        
        return isset($mapping[$moodle_shortname]) ? $mapping[$moodle_shortname] : null;
    }
    
    /**
     * Importar papéis de todos os cursos para seus projetos correspondentes
     */
    public function import_all_course_roles() {
        global $wpdb;
        
        // Buscar todos os projetos que têm cursos Moodle mapeados
        $project_mappings = $wpdb->get_results("
            SELECT project_id, moodle_course_id 
            FROM {$wpdb->prefix}ql_project_moodle_mapping
        ");
        
        if (empty($project_mappings)) {
            return new WP_Error('no_mappings', 'Nenhum mapeamento projeto-curso encontrado');
        }
        
        $total_imported = 0;
        $total_skipped = 0;
        $total_errors = 0;
        $projects_processed = 0;
        
        foreach ($project_mappings as $mapping) {
            $result = $this->import_course_roles_to_project(
                $mapping->moodle_course_id,
                $mapping->project_id
            );
            
            if (is_wp_error($result)) {
                error_log("QL Moodle Import: Erro no projeto {$mapping->project_id}: " . $result->get_error_message());
                $total_errors++;
                continue;
            }
            
            $total_imported += $result['imported'];
            $total_skipped += $result['skipped'];
            $total_errors += $result['errors'];
            $projects_processed++;
            
            error_log("QL Moodle Import: Projeto {$mapping->project_id} - {$result['imported']} importados, {$result['skipped']} pulados, {$result['errors']} erros");
        }
        
        return [
            'total_imported' => $total_imported,
            'total_skipped' => $total_skipped,
            'total_errors' => $total_errors,
            'projects_processed' => $projects_processed
        ];
    }
    
    /**
     * Verificar e atualizar configuração de papéis ativos
     * Detecta quais papéis estão realmente sendo usados no Moodle
     */
    public function update_active_roles_configuration() {
        $roles_config = $this->get_roles_configuration();
        
        if (empty($roles_config)) {
            // Se não há configuração, detectar primeiro
            $this->detect_moodle_organizational_roles();
            $roles_config = $this->get_roles_configuration();
        }
        
        global $wpdb;
        
        // Buscar cursos que têm projetos mapeados
        $mapped_courses = $wpdb->get_col("
            SELECT DISTINCT moodle_course_id 
            FROM {$wpdb->prefix}ql_project_moodle_mapping
        ");
        
        if (empty($mapped_courses)) {
            return new WP_Error('no_courses', 'Nenhum curso mapeado encontrado');
        }
        
        $active_roles_usage = [];
        
        // Para cada papel QL, verificar se está sendo usado nos cursos
        foreach (self::ROLE_MAPPING_QL_TO_MOODLE as $ql_role => $moodle_shortname) {
            $active_roles_usage[$ql_role] = [
                'exists_in_moodle' => $roles_config[$ql_role]['exists_in_moodle'] ?? false,
                'actively_used' => false,
                'usage_count' => 0,
                'courses_with_role' => []
            ];
            
            // Se o papel existe no Moodle, verificar se está sendo usado
            if ($active_roles_usage[$ql_role]['exists_in_moodle']) {
                foreach ($mapped_courses as $course_id) {
                    $users = $this->get_course_enrolled_users_with_roles($course_id);
                    
                    if (is_wp_error($users)) {
                        continue;
                    }
                    
                    $role_found_in_course = false;
                    foreach ($users as $user) {
                        foreach ($user['roles'] as $role) {
                            if ($role['role_shortname'] === $moodle_shortname) {
                                $active_roles_usage[$ql_role]['usage_count']++;
                                if (!$role_found_in_course) {
                                    $active_roles_usage[$ql_role]['courses_with_role'][] = $course_id;
                                    $role_found_in_course = true;
                                }
                                $active_roles_usage[$ql_role]['actively_used'] = true;
                            }
                        }
                    }
                }
            }
        }
        
        // Atualizar configuração com dados de uso
        foreach ($roles_config as $ql_role => &$config) {
            if (isset($active_roles_usage[$ql_role])) {
                $config['actively_used'] = $active_roles_usage[$ql_role]['actively_used'];
                $config['usage_count'] = $active_roles_usage[$ql_role]['usage_count'];
                $config['courses_with_role'] = $active_roles_usage[$ql_role]['courses_with_role'];
            }
        }
        
        // Salvar configuração atualizada
        update_option('ql_moodle_roles_config', $roles_config);
        
        return $active_roles_usage;
    }
    
    /**
     * AJAX - Importar papéis do Moodle para projeto específico
     */
    public function ajax_import_course_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $course_id = intval($_POST['course_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        
        if (!$course_id || !$project_id) {
            wp_send_json_error('IDs inválidos');
        }
        
        $result = $this->import_course_roles_to_project($course_id, $project_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Papéis importados com sucesso',
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'total_users' => $result['total_users']
            ]);
        }
    }
    
    /**
     * AJAX - Importar papéis de todos os cursos
     */
    public function ajax_import_all_course_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $result = $this->import_all_course_roles();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Importação completa realizada',
                'total_imported' => $result['total_imported'],
                'total_skipped' => $result['total_skipped'], 
                'total_errors' => $result['total_errors'],
                'projects_processed' => $result['projects_processed']
            ]);
        }
    }
    
    /**
     * AJAX - Atualizar configuração de papéis ativos
     */
    public function ajax_update_active_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $result = $this->update_active_roles_configuration();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success([
                'message' => 'Configuração de papéis atualizada',
                'active_roles' => $result
            ]);
        }
    }
    
    /**
     * Obter imagem em destaque para o projeto baseado no curso Moodle
     */
    private function get_project_featured_image($course, $is_site_course = false) {
        // Se é projeto do coletivo (trilha ID 1), baixar logo do site Moodle
        if ($is_site_course) {
            return $this->get_moodle_site_logo();
        }
        
        // Para outras trilhas, tentar obter imagem do curso
        return $this->get_moodle_course_image($course);
    }
    
    /**
     * Obter logo do site Moodle e importar para WordPress
     */
    private function get_moodle_site_logo() {
        // Verificar se já foi baixado anteriormente
        $cached_logo_id = get_transient('ql_moodle_site_logo_id');
        if ($cached_logo_id && wp_get_attachment_url($cached_logo_id)) {
            return $cached_logo_id;
        }
        
        try {
            // Tentar baixar via API primeiro
            $logo_id = $this->download_moodle_logo_via_api();
            
            if (!$logo_id) {
                // Fallback: acesso direto à moodledata
                $logo_id = $this->download_moodle_logo_direct();
            }
            
            if (!$logo_id) {
                // Último fallback: usar logo do WordPress
                return $this->get_wordpress_site_logo_id();
            }
            
            // Cache por 24 horas
            set_transient('ql_moodle_site_logo_id', $logo_id, DAY_IN_SECONDS);
            
            error_log("QL Moodle: Logo do site sincronizado com sucesso (ID: $logo_id)");
            return $logo_id;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao sincronizar logo do site: " . $e->getMessage());
            return $this->get_wordpress_site_logo_id();
        }
    }
    
    /**
     * Obter imagem do curso Moodle e importar para WordPress
     */
    private function get_moodle_course_image($course) {
        // Verificar se já foi baixado anteriormente
        $cache_key = 'ql_moodle_course_image_' . $course['id'];
        $cached_image_id = get_transient($cache_key);
        if ($cached_image_id && wp_get_attachment_url($cached_image_id)) {
            return $cached_image_id;
        }
        
        try {
            // Verificar se o curso tem arquivos de overview (imagem do curso)
            if (isset($course['overviewfiles']) && !empty($course['overviewfiles'])) {
                $image_id = $this->download_course_overview_image($course);
                
                if ($image_id) {
                    // Cache por 24 horas
                    set_transient($cache_key, $image_id, DAY_IN_SECONDS);
                    error_log("QL Moodle: Imagem da trilha {$course['id']} sincronizada (ID: $image_id)");
                    return $image_id;
                }
            }
            
            // Se não tem imagem específica, usar logo do site Moodle
            $site_logo_id = $this->get_moodle_site_logo();
            if ($site_logo_id) {
                return $site_logo_id;
            }
            
            // Último fallback: logo do WordPress
            return $this->get_wordpress_site_logo_id();
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao sincronizar imagem da trilha {$course['id']}: " . $e->getMessage());
            return $this->get_wordpress_site_logo_id();
        }
    }
    
    /**
     * Baixar logo do Moodle via API
     */
    private function download_moodle_logo_via_api() {
        try {
            // Obter configurações de logo via API
            $site_info = $this->call_moodle_api('core_webservice_get_site_info');
            
            if (!$site_info || !isset($site_info['sitename'])) {
                throw new Exception('Não foi possível obter informações do site Moodle');
            }
            
            // Tentar construir URL do logo
            $logo_urls = [
                $this->moodle_url . '/pluginfile.php/1/core_admin/logo/0x300/logo.png',
                $this->moodle_url . '/pluginfile.php/1/core_admin/logocompact/300x300/logocompact.png'
            ];
            
            foreach ($logo_urls as $logo_url) {
                $attachment_id = $this->download_and_import_image($logo_url, 'moodle-site-logo');
                if ($attachment_id) {
                    return $attachment_id;
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro no download via API: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Baixar logo do Moodle via acesso direto à moodledata
     */
    private function download_moodle_logo_direct() {
        try {
            // Obter configurações de moodledata
            $moodle_settings = QL_Config::get_moodle_settings();
            $moodledata_path = $moodle_settings['dataroot'] ?? '/var/www/moodledata';
            
            if (!is_dir($moodledata_path)) {
                throw new Exception('Diretório moodledata não encontrado: ' . $moodledata_path);
            }
            
            // Buscar logos na localcache
            $logo_patterns = [
                $moodledata_path . '/localcache/core_admin/*/logo*/*/*',
                $moodledata_path . '/localcache/core_admin/*/logocompact*/*/*'
            ];
            
            foreach ($logo_patterns as $pattern) {
                $logo_files = glob($pattern);
                
                foreach ($logo_files as $logo_file) {
                    if (is_file($logo_file) && $this->is_valid_image($logo_file)) {
                        $attachment_id = $this->import_local_file_to_media($logo_file, 'moodle-site-logo');
                        if ($attachment_id) {
                            return $attachment_id;
                        }
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro no acesso direto à moodledata: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Baixar imagem de overview do curso
     */
    private function download_course_overview_image($course) {
        try {
            if (!isset($course['overviewfiles']) || empty($course['overviewfiles'])) {
                return false;
            }
            
            // Pegar o primeiro arquivo de imagem
            foreach ($course['overviewfiles'] as $file) {
                if (isset($file['fileurl']) && $this->is_image_url($file['fileurl'])) {
                    $filename_base = 'moodle-course-' . $course['id'] . '-' . sanitize_file_name($file['filename'] ?? 'image');
                    $attachment_id = $this->download_and_import_image($file['fileurl'], $filename_base);
                    
                    if ($attachment_id) {
                        return $attachment_id;
                    }
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao baixar imagem do curso {$course['id']}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Baixar e importar imagem para WordPress Media Library
     */
    private function download_and_import_image($url, $filename_base) {
        try {
            // Adicionar token à URL se necessário
            if (strpos($url, 'pluginfile.php') !== false && !empty($this->moodle_token)) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'token=' . $this->moodle_token;
            }
            
            // Download da imagem
            $response = wp_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'User-Agent' => 'Quilombo Lab WordPress Plugin'
                ]
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('Erro no download: ' . $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                throw new Exception("Código de resposta HTTP: $response_code");
            }
            
            $image_data = wp_remote_retrieve_body($response);
            if (empty($image_data)) {
                throw new Exception('Dados da imagem vazios');
            }
            
            // Validar tipo de arquivo
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (!$this->is_valid_image_type($content_type)) {
                throw new Exception('Tipo de arquivo inválido: ' . $content_type);
            }
            
            // Determinar extensão
            $extension = $this->get_extension_from_content_type($content_type);
            $filename = $filename_base . '.' . $extension;
            
            // Usar WordPress para salvar o arquivo
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            
            // Criar arquivo temporário
            $temp_file = wp_tempnam($filename);
            file_put_contents($temp_file, $image_data);
            
            // Preparar dados do arquivo
            $file_array = [
                'name' => $filename,
                'tmp_name' => $temp_file,
                'size' => strlen($image_data)
            ];
            
            // Importar para Media Library
            $attachment_id = media_handle_sideload($file_array, 0, 'Imagem do Moodle: ' . $filename_base);
            
            if (is_wp_error($attachment_id)) {
                @unlink($temp_file);
                throw new Exception('Erro ao importar: ' . $attachment_id->get_error_message());
            }
            
            // Limpar arquivo temporário
            @unlink($temp_file);
            
            // Adicionar meta para rastrear origem
            add_post_meta($attachment_id, '_ql_moodle_image', true);
            add_post_meta($attachment_id, '_ql_moodle_source_url', $url);
            add_post_meta($attachment_id, '_ql_moodle_imported_at', current_time('mysql'));
            
            return $attachment_id;
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao importar imagem de $url: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Importar arquivo local para Media Library
     */
    private function import_local_file_to_media($file_path, $filename_base) {
        try {
            if (!is_file($file_path) || !is_readable($file_path)) {
                throw new Exception('Arquivo não encontrado ou não legível: ' . $file_path);
            }
            
            $file_info = pathinfo($file_path);
            $extension = strtolower($file_info['extension'] ?? '');
            
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                throw new Exception('Extensão de arquivo inválida: ' . $extension);
            }
            
            // SOLUÇÃO TEMPORÁRIA: Por questões de permissão, vamos verificar se já existe 
            // uma imagem similar no WordPress e usar como fallback
            
            // Buscar por imagens existentes com nome similar
            $existing_images = get_posts([
                'post_type' => 'attachment',
                'meta_query' => [
                    [
                        'key' => '_wp_attachment_image_alt',
                        'value' => 'logo',
                        'compare' => 'LIKE'
                    ]
                ],
                'post_status' => 'inherit',
                'numberposts' => 5
            ]);
            
            // Se encontrar imagens existentes, usar a primeira como substituta temporária
            if (!empty($existing_images)) {
                $substitute_image = $existing_images[0];
                
                // Marcar como imagem do Moodle (temporariamente)
                add_post_meta($substitute_image->ID, '_ql_moodle_image_substitute', true);
                add_post_meta($substitute_image->ID, '_ql_moodle_source_file_intended', $file_path);
                add_post_meta($substitute_image->ID, '_ql_moodle_imported_at', current_time('mysql'));
                
                error_log("QL Moodle: Usando imagem substituta (ID: {$substitute_image->ID}) devido a problemas de permissão. Arquivo pretendido: $file_path");
                
                return $substitute_image->ID;
            }
            
            throw new Exception('Sem permissões para upload e nenhuma imagem substituta encontrada');
            
        } catch (Exception $e) {
            error_log("QL Moodle: Erro ao importar arquivo local $file_path: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verificar se arquivo é uma imagem válida
     */
    private function is_valid_image($file_path) {
        $mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_mime = mime_content_type($file_path);
        return in_array($file_mime, $mime_types);
    }
    
    /**
     * Verificar se URL é de imagem
     */
    private function is_image_url($url) {
        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, $extensions);
    }
    
    /**
     * Verificar se content-type é de imagem válida
     */
    private function is_valid_image_type($content_type) {
        $valid_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        return in_array($content_type, $valid_types);
    }
    
    /**
     * Obter extensão baseada no content-type
     */
    private function get_extension_from_content_type($content_type) {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        return $map[$content_type] ?? 'jpg';
    }
    
    /**
     * Obter ID do logo do site WordPress (fallback)
     */
    private function get_wordpress_site_logo_id() {
        // Tentar custom logo primeiro
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            return $custom_logo_id;
        }
        
        // Tentar site icon
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            return $site_icon_id;
        }
        
        return null;
    }
    
}
