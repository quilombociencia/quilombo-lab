<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciamento das configurações do Quilombo Laboratório
 * Sistema organizado em submenus intuitivos
 */
class QL_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_menu'], 11);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_ql_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_ql_reset_settings', [$this, 'ajax_reset_settings']);
    }
    
    /**
     * Adicionar menu de configurações organizado
     */
    public function add_settings_menu() {
        // Menu principal de configurações
        add_submenu_page(
            'quilombo-lab',
            __('Configurações', 'quilombo-lab'),
            __('⚙️ Configurações', 'quilombo-lab'),
            'manage_options',
            'quilombo-lab-settings',
            [$this, 'settings_main_page']
        );
        
        // Submenu: Configurações Gerais
        add_submenu_page(
            null, // Oculto do menu
            __('Configurações Gerais', 'quilombo-lab'),
            __('Gerais', 'quilombo-lab'),
            'manage_options',
            'ql-settings-general',
            [$this, 'settings_general_page']
        );
        
        // Submenu: Integrações
        add_submenu_page(
            null, // Oculto do menu
            __('Integrações', 'quilombo-lab'),
            __('Integrações', 'quilombo-lab'),
            'manage_options',
            'ql-settings-integrations',
            [$this, 'settings_integrations_page']
        );
        
        // Submenu: Sistema
        add_submenu_page(
            null, // Oculto do menu
            __('Configurações do Sistema', 'quilombo-lab'),
            __('Sistema', 'quilombo-lab'),
            'manage_options',
            'ql-settings-system',
            [$this, 'settings_system_page']
        );
        
        // Submenu: Avançado
        add_submenu_page(
            null, // Oculto do menu
            __('Configurações Avançadas', 'quilombo-lab'),
            __('Avançado', 'quilombo-lab'),
            'manage_options',
            'ql-settings-advanced',
            [$this, 'settings_advanced_page']
        );
    }
    
    /**
     * Registrar configurações
     */
    public function register_settings() {
        // Configurações Gerais
        register_setting('ql_settings_general', 'ql_general_settings', [
            'sanitize_callback' => [$this, 'sanitize_general_settings']
        ]);
        
        // Configurações de Integração
        register_setting('ql_settings_integrations', 'ql_integration_settings', [
            'sanitize_callback' => [$this, 'sanitize_integration_settings']
        ]);
        
        // Configurações do Sistema
        register_setting('ql_settings_system', 'ql_system_settings', [
            'sanitize_callback' => [$this, 'sanitize_system_settings']
        ]);
        
        // Configurações Avançadas
        register_setting('ql_settings_advanced', 'ql_advanced_settings', [
            'sanitize_callback' => [$this, 'sanitize_advanced_settings']
        ]);
    }
    
    /**
     * Página principal de configurações (navegação)
     */
    public function settings_main_page() {
        ?>
        <div class="wrap ql-settings-wrap">
            <h1><?php _e('Configurações do Quilombo Laboratório', 'quilombo-lab'); ?></h1>
            
            <div class="ql-settings-navigation">
                <div class="ql-settings-cards">
                    
                    <!-- Configurações Gerais -->
                    <div class="ql-settings-card">
                        <div class="ql-settings-card-icon">🎯</div>
                        <h3><?php _e('Configurações Gerais', 'quilombo-lab'); ?></h3>
                        <p><?php _e('Configurações básicas do sistema, padrões de projetos e tarefas.', 'quilombo-lab'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=ql-settings-general'); ?>" class="button button-primary">
                            <?php _e('Configurar', 'quilombo-lab'); ?>
                        </a>
                    </div>
                    
                    <!-- Integrações -->
                    <div class="ql-settings-card">
                        <div class="ql-settings-card-icon">🔗</div>
                        <h3><?php _e('Integrações', 'quilombo-lab'); ?></h3>
                        <p><?php _e('Moodle, Gestão Coletiva, bancos de dados externos e APIs.', 'quilombo-lab'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=ql-settings-integrations'); ?>" class="button button-primary">
                            <?php _e('Configurar', 'quilombo-lab'); ?>
                        </a>
                    </div>
                    
                    <!-- Sistema -->
                    <div class="ql-settings-card">
                        <div class="ql-settings-card-icon">⚙️</div>
                        <h3><?php _e('Sistema', 'quilombo-lab'); ?></h3>
                        <p><?php _e('Paths, uploads, permissões e configurações de ambiente.', 'quilombo-lab'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=ql-settings-system'); ?>" class="button button-primary">
                            <?php _e('Configurar', 'quilombo-lab'); ?>
                        </a>
                    </div>
                    
                    <!-- Avançado -->
                    <div class="ql-settings-card">
                        <div class="ql-settings-card-icon">🛠️</div>
                        <h3><?php _e('Avançado', 'quilombo-lab'); ?></h3>
                        <p><?php _e('Debug, migrações, logs e configurações técnicas avançadas.', 'quilombo-lab'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=ql-settings-advanced'); ?>" class="button button-primary">
                            <?php _e('Configurar', 'quilombo-lab'); ?>
                        </a>
                    </div>
                    
                </div>
            </div>
            
            <div class="ql-settings-info">
                <h3><?php _e('Status do Sistema', 'quilombo-lab'); ?></h3>
                <?php $this->display_system_status(); ?>
            </div>
        </div>
        
        <style>
        .ql-settings-wrap {
            max-width: 1200px;
        }
        .ql-settings-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .ql-settings-card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: box-shadow 0.2s ease;
        }
        .ql-settings-card:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .ql-settings-card-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .ql-settings-card h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        .ql-settings-card p {
            color: #646970;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .ql-settings-info {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        </style>
        <?php
    }
    
    /**
     * Página de configurações gerais
     */
    public function settings_general_page() {
        if (isset($_POST['submit'])) {
            $this->save_general_settings();
        }
        
        $settings = $this->get_general_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Configurações Gerais', 'quilombo-lab'); ?></h1>
            
            <?php $this->render_settings_navigation('general'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_general_settings', 'ql_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    
                    <!-- Configurações de Projeto -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Configurações de Projetos', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Visibilidade Padrão', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="default_project_visibility">
                                <option value="private" <?php selected($settings['default_project_visibility'], 'private'); ?>><?php _e('Privado', 'quilombo-lab'); ?></option>
                                <option value="team" <?php selected($settings['default_project_visibility'], 'team'); ?>><?php _e('Equipe', 'quilombo-lab'); ?></option>
                                <option value="public" <?php selected($settings['default_project_visibility'], 'public'); ?>><?php _e('Público', 'quilombo-lab'); ?></option>
                            </select>
                            <p class="description"><?php _e('Visibilidade padrão para novos projetos.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Usuário Administrador Padrão', 'quilombo-lab'); ?></th>
                        <td>
                            <?php
                            $users = get_users(['role' => 'administrator', 'number' => 50]);
                            ?>
                            <select name="default_admin_user_id">
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>" <?php selected($settings['default_admin_user_id'], $user->ID); ?>>
                                        <?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Usuário usado como fallback quando o criador atual não está disponível.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Configurações de Tarefas -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Configurações de Tarefas', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Prefixo de Numeração', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="task_number_prefix" value="<?php echo esc_attr($settings['task_number_prefix']); ?>" class="regular-text" maxlength="10" />
                            <p class="description"><?php _e('Prefixo para numeração automática das tarefas (ex: QL-001, PROJ-001).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Status Padrão de Tarefas', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="default_task_status">
                                <option value="open" <?php selected($settings['default_task_status'], 'open'); ?>><?php _e('Aberta', 'quilombo-lab'); ?></option>
                                <option value="in_progress" <?php selected($settings['default_task_status'], 'in_progress'); ?>><?php _e('Em Progresso', 'quilombo-lab'); ?></option>
                                <option value="review" <?php selected($settings['default_task_status'], 'review'); ?>><?php _e('Em Revisão', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Prioridade Padrão', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="default_task_priority">
                                <option value="low" <?php selected($settings['default_task_priority'], 'low'); ?>><?php _e('Baixa', 'quilombo-lab'); ?></option>
                                <option value="normal" <?php selected($settings['default_task_priority'], 'normal'); ?>><?php _e('Normal', 'quilombo-lab'); ?></option>
                                <option value="high" <?php selected($settings['default_task_priority'], 'high'); ?>><?php _e('Alta', 'quilombo-lab'); ?></option>
                                <option value="urgent" <?php selected($settings['default_task_priority'], 'urgent'); ?>><?php _e('Urgente', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Configurações de Board -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Configurações de Quadros', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tipo de Board Padrão', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="default_board_type">
                                <option value="kanban" <?php selected($settings['default_board_type'], 'kanban'); ?>><?php _e('Kanban', 'quilombo-lab'); ?></option>
                                <option value="scrum" <?php selected($settings['default_board_type'], 'scrum'); ?>><?php _e('Scrum', 'quilombo-lab'); ?></option>
                                <option value="calendar" <?php selected($settings['default_board_type'], 'calendar'); ?>><?php _e('Calendário', 'quilombo-lab'); ?></option>
                                <option value="timeline" <?php selected($settings['default_board_type'], 'timeline'); ?>><?php _e('Timeline', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Notificações -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Notificações', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Habilitar Notificações', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_notifications" value="1" <?php checked($settings['enable_notifications'], 1); ?> />
                                <?php _e('Ativar sistema de notificações', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Email de Notificações', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="email" name="notification_email" value="<?php echo esc_attr($settings['notification_email']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Email usado para envio de notificações administrativas.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Página de configurações de integrações
     */
    public function settings_integrations_page() {
        if (isset($_POST['submit'])) {
            $this->save_integration_settings();
        }
        
        $settings = $this->get_integration_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Configurações de Integrações', 'quilombo-lab'); ?></h1>
            
            <?php $this->render_settings_navigation('integrations'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_integration_settings', 'ql_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    
                    <!-- Integração Moodle -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Integração com Moodle', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('URL do Moodle', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="url" name="moodle_url" value="<?php echo esc_attr($settings['moodle_url']); ?>" class="regular-text" placeholder="https://escola.exemplo.org" />
                            <p class="description"><?php _e('URL completa da instalação do Moodle.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Token da API', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="password" name="moodle_token" value="<?php echo esc_attr($settings['moodle_token']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Token de autenticação para API do Moodle.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Curso Padrão (ID)', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="number" name="moodle_default_course_id" value="<?php echo esc_attr($settings['moodle_default_course_id']); ?>" class="small-text" min="1" />
                            <p class="description"><?php _e('ID do curso padrão no Moodle para projetos coletivos.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Testar Conexão', 'quilombo-lab'); ?></th>
                        <td>
                            <button type="button" class="button" onclick="qlTestMoodleConnection()">
                                <?php _e('Testar Conexão com Moodle', 'quilombo-lab'); ?>
                            </button>
                            <div id="moodle-test-result"></div>
                        </td>
                    </tr>
                    
                    <!-- Bancos de Dados Externos -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Bancos de Dados Externos', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Nome do Banco Moodle', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="moodle_database_name" value="<?php echo esc_attr($settings['moodle_database_name']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Nome do banco de dados do Moodle (padrão: moodle_escola).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    
                    <!-- Integração Gestão Coletiva -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Integração com Gestão Coletiva', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Habilitar Integração GC', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="gc_integration_enabled" value="1" <?php checked($settings['gc_integration_enabled'], 1); ?> />
                                <?php _e('Integrar com Plugin Gestão Coletiva', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-criar Lançamentos', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_create_gc_expenses" value="1" <?php checked($settings['auto_create_gc_expenses'], 1); ?> />
                                <?php _e('Criar automaticamente lançamentos financeiros para custos de tarefas', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        function qlTestMoodleConnection() {
            const resultDiv = document.getElementById('moodle-test-result');
            resultDiv.innerHTML = '<p>🔄 Testando conexão...</p>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ql_test_connection',
                    type: 'moodle',
                    nonce: '<?php echo wp_create_nonce("ql_test_connection"); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p style="color: green;">✅ ' + data.data.message + '</p>';
                } else {
                    resultDiv.innerHTML = '<p style="color: red;">❌ ' + data.data + '</p>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p style="color: red;">❌ Erro na conexão: ' + error + '</p>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Página de configurações do sistema
     */
    public function settings_system_page() {
        if (isset($_POST['submit'])) {
            $this->save_system_settings();
        }
        
        $settings = $this->get_system_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Configurações do Sistema', 'quilombo-lab'); ?></h1>
            
            <?php $this->render_settings_navigation('system'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_system_settings', 'ql_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    
                    <!-- Configurações de Upload -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Uploads e Anexos', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tamanho Máximo de Upload', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="number" name="max_file_upload_size" value="<?php echo esc_attr($settings['max_file_upload_size']); ?>" class="small-text" min="1" max="100" /> MB
                            <p class="description"><?php _e('Tamanho máximo permitido para upload de arquivos (em MB).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tipos de Arquivo Permitidos', 'quilombo-lab'); ?></th>
                        <td>
                            <textarea name="allowed_file_types" rows="3" class="large-text" placeholder="jpg,jpeg,png,gif,pdf,doc,docx,txt,zip"><?php echo esc_textarea($settings['allowed_file_types']); ?></textarea>
                            <p class="description"><?php _e('Extensões de arquivo permitidas, separadas por vírgula.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Diretório de Upload', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="upload_directory" value="<?php echo esc_attr($settings['upload_directory']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Diretório personalizado para uploads (relativo ao wp-content/uploads).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Paths do Sistema -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Paths do Sistema', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    
                    <tr>
                        <th scope="row"><?php _e('Diretório de Logs', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="logs_directory" value="<?php echo esc_attr($settings['logs_directory']); ?>" class="regular-text" />
                            <p class="description"><?php _e('Diretório para armazenamento de logs do sistema.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Permissões -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Permissões', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Habilitar Boards Públicos', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_public_boards" value="1" <?php checked($settings['enable_public_boards'], 1); ?> />
                                <?php _e('Permitir criação de quadros públicos visíveis a todos', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Requer Login para Visualização', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_login_for_view" value="1" <?php checked($settings['require_login_for_view'], 1); ?> />
                                <?php _e('Exigir login para visualizar projetos e quadros', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Página de configurações avançadas
     */
    public function settings_advanced_page() {
        if (isset($_POST['submit'])) {
            $this->save_advanced_settings();
        }
        
        $settings = $this->get_advanced_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Configurações Avançadas', 'quilombo-lab'); ?></h1>
            
            <?php $this->render_settings_navigation('advanced'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_advanced_settings', 'ql_nonce'); ?>
                
                <table class="form-table" role="presentation">
                    
                    <!-- Debug e Desenvolvimento -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Debug e Desenvolvimento', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Modo Debug', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="1" <?php checked($settings['debug_mode'], 1); ?> />
                                <?php _e('Ativar logs detalhados e informações de debug', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Log Level', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="log_level">
                                <option value="error" <?php selected($settings['log_level'], 'error'); ?>><?php _e('Somente Erros', 'quilombo-lab'); ?></option>
                                <option value="warning" <?php selected($settings['log_level'], 'warning'); ?>><?php _e('Avisos e Erros', 'quilombo-lab'); ?></option>
                                <option value="info" <?php selected($settings['log_level'], 'info'); ?>><?php _e('Informativo', 'quilombo-lab'); ?></option>
                                <option value="debug" <?php selected($settings['log_level'], 'debug'); ?>><?php _e('Debug Completo', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Otimização -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Otimização', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cache de Dados', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_data_cache" value="1" <?php checked($settings['enable_data_cache'], 1); ?> />
                                <?php _e('Ativar cache de dados para melhor performance', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Tempo de Cache (minutos)', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="number" name="cache_timeout" value="<?php echo esc_attr($settings['cache_timeout']); ?>" class="small-text" min="1" max="1440" />
                            <p class="description"><?php _e('Tempo de expiração do cache em minutos.', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- CDNs e Recursos Externos -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Recursos Externos', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('CDN FullCalendar', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="url" name="fullcalendar_cdn" value="<?php echo esc_attr($settings['fullcalendar_cdn']); ?>" class="large-text" />
                            <p class="description"><?php _e('URL do CDN para FullCalendar (deixe vazio para usar padrão).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('CDN Font Awesome', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="url" name="fontawesome_cdn" value="<?php echo esc_attr($settings['fontawesome_cdn']); ?>" class="large-text" />
                            <p class="description"><?php _e('URL do CDN para Font Awesome (deixe vazio para usar padrão).', 'quilombo-lab'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Migração -->
                    <tr>
                        <th colspan="2">
                            <h3><?php _e('Migração e Backup', 'quilombo-lab'); ?></h3>
                        </th>
                    </tr>
                    
                    <tr>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-backup Antes de Migração', 'quilombo-lab'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_backup_before_migration" value="1" <?php checked($settings['auto_backup_before_migration'], 1); ?> />
                                <?php _e('Criar backup automático antes de executar migrações', 'quilombo-lab'); ?>
                            </label>
                        </td>
                    </tr>
                    
                </table>
                
                <p class="submit">
                    <?php submit_button(__('Salvar Configurações', 'quilombo-lab'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" onclick="qlResetAdvancedSettings()" style="margin-left: 10px;">
                        <?php _e('Restaurar Padrões', 'quilombo-lab'); ?>
                    </button>
                </p>
            </form>
        </div>
        
        <script>
        function qlResetAdvancedSettings() {
            if (confirm('<?php _e("Tem certeza que deseja restaurar as configurações avançadas para os valores padrão?", "quilombo-lab"); ?>')) {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'ql_reset_settings',
                        type: 'advanced',
                        nonce: '<?php echo wp_create_nonce("ql_reset_settings"); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro: ' + data.data);
                    }
                });
            }
        }
        </script>
        <?php
    }
    
    /**
     * Renderizar navegação entre configurações
     */
    private function render_settings_navigation($current_page) {
        $pages = [
            'general' => ['title' => 'Gerais', 'url' => admin_url('admin.php?page=ql-settings-general')],
            'integrations' => ['title' => 'Integrações', 'url' => admin_url('admin.php?page=ql-settings-integrations')],
            'system' => ['title' => 'Sistema', 'url' => admin_url('admin.php?page=ql-settings-system')],
            'advanced' => ['title' => 'Avançado', 'url' => admin_url('admin.php?page=ql-settings-advanced')]
        ];
        
        echo '<div class="ql-settings-nav" style="margin: 20px 0; padding: 15px; background: #f6f7f7; border-radius: 6px;">';
        echo '<nav style="text-align: center;">';
        
        foreach ($pages as $key => $page) {
            $class = ($key === $current_page) ? 'button-primary' : 'button-secondary';
            echo '<a href="' . $page['url'] . '" class="button ' . $class . '" style="margin: 0 5px;">' . $page['title'] . '</a>';
        }
        
        echo '</nav>';
        echo '</div>';
    }
    
    /**
     * Obter configurações gerais com padrões
     */
    public function get_general_settings() {
        $defaults = [
            'default_project_visibility' => 'team',
            'default_admin_user_id' => 1,
            'task_number_prefix' => 'QL',
            'default_task_status' => 'open',
            'default_task_priority' => 'normal',
            'default_board_type' => 'kanban',
            'enable_notifications' => true,
            'notification_email' => get_option('admin_email', '')
        ];
        
        return array_merge($defaults, get_option('ql_general_settings', []));
    }
    
    /**
     * Obter configurações de integração com padrões
     */
    public function get_integration_settings() {
        $defaults = [
            'moodle_url' => '',
            'moodle_token' => '',
            'moodle_default_course_id' => 1,
            'moodle_database_name' => 'moodle_escola',
            'gc_integration_enabled' => false,
            'auto_create_gc_expenses' => false
        ];
        
        return array_merge($defaults, get_option('ql_integration_settings', []));
    }
    
    /**
     * Obter configurações do sistema com padrões
     */
    public function get_system_settings() {
        $defaults = [
            'max_file_upload_size' => 10,
            'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip',
            'upload_directory' => 'quilombo-lab',
            'logs_directory' => WP_CONTENT_DIR . '/logs/quilombo-lab',
            'enable_public_boards' => false,
            'require_login_for_view' => true
        ];
        
        return array_merge($defaults, get_option('ql_system_settings', []));
    }
    
    /**
     * Obter configurações avançadas com padrões
     */
    public function get_advanced_settings() {
        $defaults = [
            'debug_mode' => false,
            'log_level' => 'error',
            'enable_data_cache' => true,
            'cache_timeout' => 60,
            'fullcalendar_cdn' => 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            'fontawesome_cdn' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
            'auto_backup_before_migration' => true
        ];
        
        return array_merge($defaults, get_option('ql_advanced_settings', []));
    }
    
    /**
     * Salvar configurações gerais
     */
    private function save_general_settings() {
        if (!wp_verify_nonce($_POST['ql_nonce'], 'ql_general_settings')) {
            return;
        }
        
        $settings = [
            'default_project_visibility' => sanitize_text_field($_POST['default_project_visibility']),
            'default_admin_user_id' => intval($_POST['default_admin_user_id']),
            'task_number_prefix' => sanitize_text_field($_POST['task_number_prefix']),
            'default_task_status' => sanitize_text_field($_POST['default_task_status']),
            'default_task_priority' => sanitize_text_field($_POST['default_task_priority']),
            'default_board_type' => sanitize_text_field($_POST['default_board_type']),
            'enable_notifications' => isset($_POST['enable_notifications']),
            'notification_email' => sanitize_email($_POST['notification_email'])
        ];
        
        update_option('ql_general_settings', $settings);
        add_settings_error('ql_general_settings', 'settings_updated', __('Configurações gerais salvas com sucesso!', 'quilombo-lab'), 'updated');
    }
    
    /**
     * Salvar configurações de integração
     */
    private function save_integration_settings() {
        if (!wp_verify_nonce($_POST['ql_nonce'], 'ql_integration_settings')) {
            return;
        }
        
        $settings = [
            'moodle_url' => esc_url_raw($_POST['moodle_url']),
            'moodle_token' => sanitize_text_field($_POST['moodle_token']),
            'moodle_default_course_id' => intval($_POST['moodle_default_course_id']),
            'moodle_database_name' => sanitize_text_field($_POST['moodle_database_name']),
            'gc_integration_enabled' => isset($_POST['gc_integration_enabled']),
            'auto_create_gc_expenses' => isset($_POST['auto_create_gc_expenses'])
        ];
        
        update_option('ql_integration_settings', $settings);
        add_settings_error('ql_integration_settings', 'settings_updated', __('Configurações de integração salvas com sucesso!', 'quilombo-lab'), 'updated');
    }
    
    /**
     * Salvar configurações do sistema
     */
    private function save_system_settings() {
        if (!wp_verify_nonce($_POST['ql_nonce'], 'ql_system_settings')) {
            return;
        }
        
        $settings = [
            'max_file_upload_size' => intval($_POST['max_file_upload_size']),
            'allowed_file_types' => sanitize_text_field($_POST['allowed_file_types']),
            'upload_directory' => sanitize_text_field($_POST['upload_directory']),
            'logs_directory' => sanitize_text_field($_POST['logs_directory']),
            'enable_public_boards' => isset($_POST['enable_public_boards']),
            'require_login_for_view' => isset($_POST['require_login_for_view'])
        ];
        
        update_option('ql_system_settings', $settings);
        add_settings_error('ql_system_settings', 'settings_updated', __('Configurações do sistema salvas com sucesso!', 'quilombo-lab'), 'updated');
    }
    
    /**
     * Salvar configurações avançadas
     */
    private function save_advanced_settings() {
        if (!wp_verify_nonce($_POST['ql_nonce'], 'ql_advanced_settings')) {
            return;
        }
        
        $settings = [
            'debug_mode' => isset($_POST['debug_mode']),
            'log_level' => sanitize_text_field($_POST['log_level']),
            'enable_data_cache' => isset($_POST['enable_data_cache']),
            'cache_timeout' => intval($_POST['cache_timeout']),
            'fullcalendar_cdn' => esc_url_raw($_POST['fullcalendar_cdn']),
            'fontawesome_cdn' => esc_url_raw($_POST['fontawesome_cdn']),
            'auto_backup_before_migration' => isset($_POST['auto_backup_before_migration'])
        ];
        
        update_option('ql_advanced_settings', $settings);
        add_settings_error('ql_advanced_settings', 'settings_updated', __('Configurações avançadas salvas com sucesso!', 'quilombo-lab'), 'updated');
    }
    
    /**
     * Exibir status do sistema
     */
    private function display_system_status() {
        $general = $this->get_general_settings();
        $integration = $this->get_integration_settings();
        $system = $this->get_system_settings();
        
        echo '<div class="ql-status-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        
        // Status Moodle
        $moodle_status = !empty($integration['moodle_url']) && !empty($integration['moodle_token']) ? '✅' : '⚠️';
        echo '<div class="ql-status-item" style="padding: 10px; background: white; border-radius: 4px; text-align: center;">';
        echo '<strong>' . $moodle_status . ' Moodle</strong><br>';
        echo !empty($integration['moodle_url']) ? 'Configurado' : 'Não configurado';
        echo '</div>';
        
        // Status GC
        $gc_status = $integration['gc_integration_enabled'] ? '✅' : '⚠️';
        echo '<div class="ql-status-item" style="padding: 10px; background: white; border-radius: 4px; text-align: center;">';
        echo '<strong>' . $gc_status . ' Gestão Coletiva</strong><br>';
        echo $integration['gc_integration_enabled'] ? 'Integração ativa' : 'Desabilitado';
        echo '</div>';
        
        // Status Uploads
        $upload_dir = wp_upload_dir();
        $upload_status = is_writable($upload_dir['basedir']) ? '✅' : '❌';
        echo '<div class="ql-status-item" style="padding: 10px; background: white; border-radius: 4px; text-align: center;">';
        echo '<strong>' . $upload_status . ' Uploads</strong><br>';
        echo is_writable($upload_dir['basedir']) ? 'Diretório gravável' : 'Sem permissão';
        echo '</div>';
        
        // Status Debug
        $debug_status = $this->get_advanced_settings()['debug_mode'] ? '⚠️' : '✅';
        echo '<div class="ql-status-item" style="padding: 10px; background: white; border-radius: 4px; text-align: center;">';
        echo '<strong>' . $debug_status . ' Debug</strong><br>';
        echo $this->get_advanced_settings()['debug_mode'] ? 'Modo debug ativo' : 'Produção';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * AJAX: Testar conexão
     */
    public function ajax_test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_test_connection')) {
            wp_die('Unauthorized');
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        if ($type === 'moodle') {
            $settings = $this->get_integration_settings();
            
            if (empty($settings['moodle_url']) || empty($settings['moodle_token'])) {
                wp_send_json_error('URL e token do Moodle devem estar configurados.');
                return;
            }
            
            // Testar conexão básica
            $response = wp_remote_get($settings['moodle_url'] . '/webservice/rest/server.php?wstoken=' . $settings['moodle_token'] . '&wsfunction=core_webservice_get_site_info&moodlewsrestformat=json');
            
            if (is_wp_error($response)) {
                wp_send_json_error('Erro na conexão: ' . $response->get_error_message());
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['exception'])) {
                wp_send_json_error('Erro do Moodle: ' . $data['message']);
                return;
            }
            
            if (isset($data['sitename'])) {
                wp_send_json_success(['message' => 'Conexão bem-sucedida com: ' . $data['sitename']]);
            } else {
                wp_send_json_error('Resposta inesperada do Moodle.');
            }
        }
        
        wp_send_json_error('Tipo de teste não reconhecido.');
    }
    
    /**
     * AJAX: Resetar configurações
     */
    public function ajax_reset_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_reset_settings')) {
            wp_die('Unauthorized');
        }
        
        $type = sanitize_text_field($_POST['type']);
        
        switch ($type) {
            case 'advanced':
                delete_option('ql_advanced_settings');
                wp_send_json_success('Configurações avançadas resetadas para os valores padrão.');
                break;
            
            default:
                wp_send_json_error('Tipo de reset não reconhecido.');
        }
    }
    
    /**
     * Métodos de sanitização
     */
    public function sanitize_general_settings($input) {
        // Implementar sanitização específica se necessário
        return $input;
    }
    
    public function sanitize_integration_settings($input) {
        // Implementar sanitização específica se necessário
        return $input;
    }
    
    public function sanitize_system_settings($input) {
        // Implementar sanitização específica se necessário
        return $input;
    }
    
    public function sanitize_advanced_settings($input) {
        // Implementar sanitização específica se necessário
        return $input;
    }
}