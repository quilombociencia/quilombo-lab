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
        
        // Cron job para sincronização automática
        add_action('ql_moodle_sync', [$this, 'scheduled_sync']);
        
        // Hook para sincronização de membros com delay
        add_action('ql_sync_project_members_delayed', [$this, 'sync_project_members_delayed_handler'], 10, 2);
        
        // Hook para quando configurações do Moodle são atualizadas
        add_action('update_option_quilombo_laboratorio_settings', [$this, 'on_settings_updated'], 10, 2);
    }
    
    public function init() {
        // Carregar configurações
        $this->load_settings();
        
        // Registrar cron job se não existir
        if (!wp_next_scheduled('ql_moodle_sync')) {
            wp_schedule_event(time(), 'daily', 'ql_moodle_sync');
        }
        
        // Adicionar menu admin se necessário
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
    }
    
    /**
     * Carregar configurações do Moodle
     */
    private function load_settings() {
        $moodle_settings = QL_Config::get_moodle_settings();
        
        $this->moodle_url = rtrim($moodle_settings['url'], '/');
        $this->moodle_token = $moodle_settings['token'];
        $this->api_endpoint = $this->moodle_url . '/webservice/rest/server.php';
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
                                    <?php echo esc_html(ucfirst($course->project_status)); ?>
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
            return [
                'success' => false,
                'message' => 'Erro ao conectar: ' . $e->getMessage()
            ];
        }
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
            $response = $this->call_moodle_api('core_course_get_courses');
            
            if (!$response || !is_array($response)) {
                return [];
            }
            
            // Incluir todos os cursos (incluindo o curso do site ID 1)
            return $response;
            
        } catch (Exception $e) {
            error_log('Quilombo Laboratório: Erro ao buscar cursos do Moodle: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obter cursos do Moodle (método interno)
     */
    private function get_moodle_courses() {
        $response = $this->call_moodle_api('core_course_get_courses');
        
        if (!$response || !is_array($response)) {
            throw new Exception('Erro ao buscar cursos do Moodle');
        }
        
        // Verificar se conseguimos acessar o curso principal (ID 1)
        $site_course_accessible = $this->check_site_course_access();
        
        if (!$site_course_accessible) {
            // Se não consegue acessar o curso principal, filtrar apenas os acessíveis
            $courses = array_filter($response, function($course) {
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
        return $response;
    }
    
    /**
     * Processar um curso específico do Moodle
     */
    private function process_moodle_course($course) {
        error_log("QL Moodle: Processando curso ID {$course['id']}: {$course['fullname']}");
        
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
        $name = strtolower($course['fullname'] . ' ' . $course['shortname']);
        
        // Buscar por palavras-chave
        if (preg_match('/pesquisa|research|investigação/', $name)) {
            return 'pesquisa';
        }
        
        if (preg_match('/criação|creation|design|arte|música/', $name)) {
            return 'criacao';
        }
        
        // Padrão é aprendizagem
        return 'aprendizagem';
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
            'settings' => json_encode([
                'trilha_type' => $trilha_type,
                'moodle_course_id' => $course['id'],
                'auto_sync' => true,
                'is_collective_project' => $is_site_course,
                'is_virtual_course' => $is_virtual,
                'limited_access' => $is_virtual
            ]),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
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
        
        $update_data = [
            'name' => $course['fullname'],
            'description' => $course['summary'] ?? '',
            'start_date' => !empty($course['startdate']) ? date('Y-m-d', $course['startdate']) : null,
            'end_date' => !empty($course['enddate']) ? date('Y-m-d', $course['enddate']) : null,
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            $update_data,
            ['id' => $project_id],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            throw new Exception('Erro ao atualizar projeto: ' . $wpdb->last_error);
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
                'name' => 'Processo Criativo',
                'columns' => [
                    ['name' => 'Ideação', 'color' => '#f1c40f'],
                    ['name' => 'Prototipagem', 'color' => '#e67e22'],
                    ['name' => 'Desenvolvimento', 'color' => '#3498db'],
                    ['name' => 'Teste', 'color' => '#9b59b6'],
                    ['name' => 'Finalização', 'color' => '#27ae60']
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
            $response = $this->call_moodle_api('core_enrol_get_enrolled_users', [
                'courseid' => $course_id
            ]);
            
            if (!$response || !is_array($response)) {
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
        
        return [
            'success' => true,
            'projects_processed' => $stats['projects_processed'],
            'total_members_synced' => $stats['total_members_synced'],
            'errors' => $stats['errors']
        ];
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
            $result = $this->sync_courses_from_moodle();
            error_log('QL Moodle Sync: Sincronização automática executada com sucesso');
        } catch (Exception $e) {
            error_log('QL Moodle Sync: Erro na sincronização automática: ' . $e->getMessage());
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
                'updated_at' => current_time('mysql')
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
    
}