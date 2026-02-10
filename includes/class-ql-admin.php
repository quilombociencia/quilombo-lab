<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para interface administrativa do Quilombo Laboratório
 */
class QL_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_filter('plugin_action_links_' . QL_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
        add_action('admin_init', [$this, 'admin_init']);
    }
    
    /**
     * Adicionar menu administrativo
     * Estrutura reformulada conforme modelo organizativo
     */
    public function add_admin_menu() {
        // Menu principal - Laboratório
        add_menu_page(
            __('Laboratório de Projetos', 'quilombo-lab'),
            __('Laboratório', 'quilombo-lab'),
            'read',
            'quilombo-lab',
            [$this, 'dashboard_page'],
            'dashicons-clipboard',
            30
        );
        
        // Submenu Painel (Dashboard principal)
        add_submenu_page(
            'quilombo-lab',
            __('Painel', 'quilombo-lab'),
            __('🏠 Painel', 'quilombo-lab'),
            'read',
            'quilombo-lab',
            [$this, 'dashboard_page']
        );
        
        // Submenu Projetos
        add_submenu_page(
            'quilombo-lab',
            __('Projetos', 'quilombo-lab'),
            __('📋 Projetos', 'quilombo-lab'),
            'read',
            'quilombo-lab-projetos',
            [$this, 'projects_page']
        );
        
        // Menu Organização - NOVO MENU PRINCIPAL
        add_menu_page(
            __('Organização', 'quilombo-lab'),
            __('Organização', 'quilombo-lab'),
            'read',
            'quilombo-lab-organizacao',
            [$this, 'organization_dashboard_page'],
            'dashicons-groups',
            31
        );
        
        // Submenu Instâncias sob Organização
        add_submenu_page(
            'quilombo-lab-organizacao',
            __('Instâncias Organizacionais', 'quilombo-lab'),
            __('🌐 Instâncias', 'quilombo-lab'),
            'read',
            'quilombo-lab-instances',
            [$this, 'instances_page_wrapper']
        );
        
        // Submenu Papéis sob Organização
        add_submenu_page(
            'quilombo-lab-organizacao',
            __('Papéis Organizativos', 'quilombo-lab'),
            __('🎭 Papéis', 'quilombo-lab'),
            'read',
            'quilombo-lab-roles',
            [$this, 'roles_page_wrapper']
        );
        
        // Submenu Responsabilidades sob Organização
        add_submenu_page(
            'quilombo-lab-organizacao',
            __('Responsabilidades', 'quilombo-lab'),
            __('⚙️ Responsabilidades', 'quilombo-lab'),
            'read',
            'quilombo-lab-responsibilities',
            [$this, 'responsibilities_page']
        );
        
        // Submenu Consultas e Consenso sob Organização
        add_submenu_page(
            'quilombo-lab-organizacao',
            __('Participação (Consultas e Contestações)', 'quilombo-lab'),
            __('🗳️ Participação', 'quilombo-lab'),
            'read',
            'quilombo-lab-consultations',
            [$this, 'consultations_page']
        );
        
        // Submenu Territórios sob Organização - PRIORIDADE 0
        add_submenu_page(
            'quilombo-lab-organizacao',
            __('Territórios', 'quilombo-lab'),
            __('🗺️ Territórios', 'quilombo-lab'),
            'read',
            'quilombo-lab-territories',
            [$this, 'territories_page']
        );
        
        // Páginas ocultas (acessadas via parâmetros)
        add_submenu_page(
            null,
            __('Quadros do Projeto', 'quilombo-lab'),
            __('Quadros', 'quilombo-lab'),
            'read',
            'quilombo-lab-project-boards',
            [$this, 'project_boards_page']
        );
        
        add_submenu_page(
            null,
            __('Kanban Board', 'quilombo-lab'),
            __('Kanban', 'quilombo-lab'),
            'read',
            'quilombo-lab-kanban',
            [$this, 'kanban_page']
        );
        
        // Submenu Status mantido no menu principal
        add_submenu_page(
            'quilombo-lab',
            __('Status do Sistema', 'quilombo-lab'),
            __('🔍 Status', 'quilombo-lab'),
            'read',
            'quilombo-lab-status',
            [$this, 'status_page']
        );
    }
    
    /**
     * Adicionar links de ação na lista de plugins
     */
    public function add_plugin_action_links($links) {
        $plugin_links = [];
        
        // Link para dashboard
        $plugin_links[] = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=quilombo-lab'),
            __('Painel', 'quilombo-lab')
        );
        
        // Link para configurações (apenas admins)
        if (current_user_can('manage_options')) {
            $plugin_links[] = sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=quilombo-lab-settings'),
                __('Configurações', 'quilombo-lab')
            );
        }
        
        // Link para status
        $plugin_links[] = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=quilombo-lab-status'),
            __('Status', 'quilombo-lab')
        );
        
        return array_merge($plugin_links, $links);
    }
    
    /**
     * Inicialização admin
     */
    public function admin_init() {
        // Registrar configurações
        register_setting('ql_settings', 'ql_settings_group');
    }
    
    /**
     * Página Painel
     */
    public function dashboard_page() {
        $user_id = get_current_user_id();
        
        // Buscar projetos do usuário com método simples
        $user_projects = [];
        $total_projects = 0;
        
        if (class_exists('QL_Project')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ql_projects';
            
            // Projetos do usuário
            $user_projects = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE owner_id = %d ORDER BY updated_at DESC LIMIT 5",
                $user_id
            ));
            
            // Total de projetos
            $total_projects = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        }
        
        // Status da integração GC
        $gc_integration_status = class_exists('GC_Projeto') ? 'active' : 'inactive';
        
        ?>
        <div class="wrap">
            <h1><?php _e('Laboratório de Projetos - Painel', 'quilombo-lab'); ?></h1>
            
            <div class="ql-painel-grid">
                <!-- Cards de Estatísticas -->
                <div class="ql-stats-cards">
                    <div class="ql-card">
                        <h3><?php _e('Meus Projetos', 'quilombo-lab'); ?></h3>
                        <div class="ql-stat-number"><?php echo count($user_projects); ?></div>
                        <p><?php _e('Projetos em que você participa', 'quilombo-lab'); ?></p>
                    </div>
                    
                    <div class="ql-card">
                        <h3><?php _e('Total de Projetos', 'quilombo-lab'); ?></h3>
                        <div class="ql-stat-number"><?php echo $total_projects; ?></div>
                        <p><?php _e('Projetos no sistema', 'quilombo-lab'); ?></p>
                    </div>
                    
                    <div class="ql-card">
                        <h3><?php _e('Integração GC', 'quilombo-lab'); ?></h3>
                        <div class="ql-stat-indicator <?php echo $gc_integration_status; ?>">
                            <?php echo $gc_integration_status === 'active' ? '✅ Ativa' : '⚠️ Inativa'; ?>
                        </div>
                        <p><?php _e('Status da integração financeira', 'quilombo-lab'); ?></p>
                    </div>
                </div>
                
                <!-- Lista de Projetos Recentes -->
                <div class="ql-recent-projects">
                    <h2><?php _e('Seus Projetos Recentes', 'quilombo-lab'); ?></h2>
                    
                    <?php if (!empty($user_projects)): ?>
                        <div class="ql-projects-list">
                            <?php foreach (array_slice($user_projects, 0, 5) as $project): ?>
                                <div class="ql-project-item">
                                    <div class="ql-project-info">
                                        <h4><?php echo esc_html($project->name); ?></h4>
                                        <p><?php echo esc_html($project->description ?: 'Sem descrição'); ?></p>
                                        <small><?php printf(__('Criado em %s', 'quilombo-lab'), date('d/m/Y', strtotime($project->created_at))); ?></small>
                                    </div>
                                    <div class="ql-project-status">
                                        <span class="ql-status-badge ql-status-<?php echo $project->status; ?>">
                                            <?php echo ucfirst($project->status); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <p class="ql-view-all">
                            <a href="<?php echo admin_url('admin.php?page=ql-projects'); ?>" class="button">
                                <?php _e('Ver Todos os Projetos', 'quilombo-lab'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <div class="ql-empty-state">
                            <p><?php _e('Você ainda não participa de nenhum projeto.', 'quilombo-lab'); ?></p>
                            <?php if (current_user_can('ql_create_tasks')): ?>
                                <p>
                                    <a href="<?php echo admin_url('admin.php?page=ql-projects'); ?>" class="button button-primary">
                                        <?php _e('Explorar Projetos', 'quilombo-lab'); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Ações Rápidas -->
                <div class="ql-quick-actions">
                    <h2><?php _e('Ações Rápidas', 'quilombo-lab'); ?></h2>
                    
                    <div class="ql-action-buttons">
                        <a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>" class="button button-primary">
                            📋 <?php _e('Ver Projetos', 'quilombo-lab'); ?>
                        </a>
                        
                        <?php if (current_user_can('manage_options')): ?>
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-status'); ?>" class="button">
                                ⚙️ <?php _e('Verificar Status', 'quilombo-lab'); ?>
                            </a>
                            
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-settings'); ?>" class="button">
                                🔧 <?php _e('Configurações', 'quilombo-lab'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .ql-painel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .ql-stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            grid-column: 1 / -1;
        }
        
        .ql-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
        }
        
        .ql-card h3 {
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        
        .ql-stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2271b1;
            margin: 10px 0;
        }
        
        .ql-stat-indicator {
            font-size: 1.2em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .ql-stat-indicator.active {
            color: #00a32a;
        }
        
        .ql-stat-indicator.inactive {
            color: #dba617;
        }
        
        .ql-recent-projects, .ql-quick-actions {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
        }
        
        .ql-project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .ql-project-item:last-child {
            border-bottom: none;
        }
        
        .ql-project-info h4 {
            margin: 0 0 5px 0;
            color: #1d2327;
        }
        
        .ql-project-info p {
            margin: 0 0 5px 0;
            color: #646970;
            font-size: 14px;
        }
        
        .ql-project-info small {
            color: #8c8f94;
        }
        
        .ql-status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ql-status-active {
            background: #e7f3ff;
            color: #0073aa;
        }
        
        .ql-status-completed {
            background: #f0fff4;
            color: #00a32a;
        }
        
        .ql-status-on_hold {
            background: #fff2e7;
            color: #d63638;
        }
        
        .ql-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #646970;
        }
        
        .ql-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .ql-painel-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Página de Projetos - VERSÃO MELHORADA COM EDIÇÃO/EXCLUSÃO
     */
    public function projects_page() {
        // Processar ações de formulário
        $this->handle_project_actions();
        
        // Verificar se é para criar um projeto de demonstração (apenas para ambiente de desenvolvimento)
        if (isset($_GET['create_demo']) && current_user_can('manage_options') && WP_DEBUG) {
            $this->create_demo_project();
        }
        
        // Verificar se é para criar um board
        if (isset($_GET['create_board']) && isset($_GET['project_id'])) {
            $this->handle_create_default_board(intval($_GET['project_id']));
        }
        
        // Verificar se é para editar um projeto
        if (isset($_GET['edit_project']) && isset($_GET['project_id'])) {
            $this->show_edit_project_form(intval($_GET['project_id']));
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Projetos do Laboratório', 'quilombo-lab'); ?>
                <?php if (current_user_can('manage_options')): ?>
                    <a href="<?php echo add_query_arg('create_demo', '1'); ?>" class="page-title-action">
                        <?php _e('+ Criar Projeto Demo', 'quilombo-lab'); ?>
                    </a>
                <?php endif; ?>
            </h1>
            
            <?php 
            // Mostrar mensagens de sucesso/erro
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
                switch ($message) {
                    case 'demo_created':
                        echo '<div class="notice notice-success"><p>Projeto de demonstração criado com sucesso!</p></div>';
                        break;
                    case 'project_updated':
                        echo '<div class="notice notice-success"><p>Projeto atualizado com sucesso!</p></div>';
                        break;
                    case 'project_deleted':
                        echo '<div class="notice notice-success"><p>Projeto excluído permanentemente!</p></div>';
                        break;
                }
            }
            ?>
            
            <?php if (class_exists('QL_Project')): ?>
                <?php 
                // Buscar projetos do usuário atual
                $project_model = QL_Project::get_instance();
                $user_projects = $project_model->get_user_projects(get_current_user_id());
                
                // Se for admin, buscar todos os projetos
                if (current_user_can('manage_options')) {
                    global $wpdb;
                    $all_projects = $wpdb->get_results("
                        SELECT p.*, gc.nome as gc_nome 
                        FROM {$wpdb->prefix}ql_projects p 
                        LEFT JOIN {$wpdb->prefix}gc_projetos gc ON p.gc_projeto_id = gc.id 
                        ORDER BY p.created_at DESC 
                        LIMIT 20
                    ");
                    $projects = $all_projects ?: $user_projects;
                } else {
                    $projects = $user_projects;
                }
                ?>
                
                <?php if (!empty($projects)): ?>
                    <div class="ql-projects-grid">
                        <?php foreach ($projects as $project): ?>
                            <?php 
                            // Obter estatísticas do projeto
                            $stats = $this->get_project_stats($project->id);
                            ?>
                            <div class="ql-project-card">
                                <?php 
                                // Obter imagem do projeto
                                $image_url = null;
                                if (!empty($project->featured_image_id)) {
                                    $image_url = wp_get_attachment_image_url($project->featured_image_id, 'medium');
                                }
                                ?>
                                
                                <?php if ($image_url): ?>
                                    <div class="ql-project-image">
                                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($project->name); ?>" />
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ql-project-header">
                                    <h3><?php echo esc_html($project->name); ?></h3>
                                    <span class="ql-status-badge ql-status-<?php echo $project->status; ?>">
                                        <?php echo ucfirst($project->status); ?>
                                    </span>
                                </div>
                                
                                <div class="ql-project-content">
                                    <p class="ql-project-description"><?php 
                                        $description = $project->description ?: 'Sem descrição disponível';
                                        $clean_description = wp_strip_all_tags($description);
                                        $truncated = strlen($clean_description) > 120 ? substr($clean_description, 0, 120) . '...' : $clean_description;
                                        echo esc_html($truncated);
                                    ?></p>
                                    
                                    <div class="ql-project-stats">
                                        <div class="ql-stat-row">
                                            <span>📋 Quadros: <?php echo $stats['quadros']; ?></span>
                                            <span>📝 Tarefas: <?php echo $stats['tasks']; ?></span>
                                        </div>
                                        <?php if (isset($project->gc_nome) && $project->gc_nome): ?>
                                            <div class="ql-gc-link">
                                                <small>🔗 Vinculado ao GC: <?php echo esc_html($project->gc_nome); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ql-project-meta">
                                        <small>
                                            <?php printf(__('Criado em %s', 'quilombo-lab'), date('d/m/Y', strtotime($project->created_at))); ?>
                                        </small>
                                        <?php if (isset($project->progress_percentage) && $project->progress_percentage > 0): ?>
                                            <div class="ql-progress-bar">
                                                <div class="ql-progress-fill" style="width: <?php echo $project->progress_percentage; ?>%"></div>
                                                <span class="ql-progress-text"><?php echo $project->progress_percentage; ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="ql-project-actions">
                                    <?php if ($stats['quadros'] > 0): ?>
                                        <a href="<?php echo admin_url('admin.php?page=quilombo-lab-project-boards&project_id=' . $project->id); ?>" 
                                           class="button button-primary">
                                            <?php _e('Ver Quadros', 'quilombo-lab'); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo add_query_arg(['create_board' => '1', 'project_id' => $project->id]); ?>" 
                                           class="button button-primary">
                                            <?php _e('Criar Quadro', 'quilombo-lab'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="<?php echo add_query_arg(['edit_project' => '1', 'project_id' => $project->id]); ?>" 
                                           class="button">
                                            <?php _e('✏️ Editar', 'quilombo-lab'); ?>
                                        </a>
                                        
                                        <?php 
                                        $is_moodle_project = !empty($project->moodle_course_id);
                                        $settings = json_decode($project->settings, true) ?: [];
                                        $is_collective = $settings['is_collective_project'] ?? false;
                                        ?>
                                        
                                        <?php if (!$is_moodle_project && !$is_collective): ?>
                                            <button type="button" class="button button-link-delete" 
                                                    onclick="qlConfirmDeleteProject(<?php echo $project->id; ?>, '<?php echo esc_js($project->name); ?>')">
                                                <?php _e('🗑️ Excluir', 'quilombo-lab'); ?>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $project_page_url = $this->get_project_page_url($project);
                                    if ($project_page_url): ?>
                                        <a href="<?php echo esc_url($project_page_url); ?>" class="button" target="_blank">
                                            <?php _e('👁️ Ver Página', 'quilombo-lab'); ?>
                                        </a>
                                    <?php else: ?>
                                        <button class="button" onclick="qlViewProjectDetails(<?php echo $project->id; ?>)">
                                            <?php _e('👁️ Detalhes', 'quilombo-lab'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($is_moodle_project): ?>
                                    <div class="ql-project-origin">
                                        <small>🎓 <em>Sincronizado do Moodle (Trilha <?php echo $project->moodle_course_id; ?>)</em></small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($is_collective): ?>
                                    <div class="ql-collective-badge">
                                        <small>🏛️ <strong>PROJETO DO COLETIVO</strong></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="ql-empty-state">
                        <h2><?php _e('Nenhum projeto encontrado', 'quilombo-lab'); ?></h2>
                        <p><?php _e('Parece que ainda não há projetos criados no sistema.', 'quilombo-lab'); ?></p>
                        <?php if (current_user_can('manage_options')): ?>
                            <p>
                                <a href="<?php echo add_query_arg('create_demo', '1'); ?>" class="button button-primary">
                                    <?php _e('Criar Projeto de Demonstração', 'quilombo-lab'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <p><em><?php _e('Projetos são criados automaticamente quando trilhas são sincronizadas do Moodle ou através do Plugin Gestão Coletiva.', 'quilombo-lab'); ?></em></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><?php _e('A classe QL_Project não está disponível. Verifique se o plugin foi instalado corretamente.', 'quilombo-lab'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ql-projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .ql-project-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 400px;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        
        .ql-project-image {
            width: 100%;
            height: 150px;
            overflow: hidden;
            background: #f6f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ql-project-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }
        
        .ql-project-card:hover .ql-project-image img {
            transform: scale(1.05);
        }
        
        .ql-project-header {
            padding: 20px 20px 0 20px;
        }
        
        .ql-project-content {
            padding: 0 20px 20px 20px;
            flex: 1;
        }
        
        .ql-project-actions {
            padding: 0 20px 20px 20px;
            margin-top: auto;
        }
        
        .ql-project-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .ql-project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .ql-project-header h3 {
            margin: 0;
            color: #1d2327;
        }
        
        .ql-project-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .ql-project-description {
            color: #646970;
            margin-bottom: 15px;
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .ql-project-stats {
            margin-top: auto;
        }
        
        .ql-stat-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .ql-stat-row span {
            font-size: 0.9em;
            color: #646970;
        }
        
        .ql-project-meta {
            margin-bottom: 15px;
        }
        
        .ql-project-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f1;
        }
        
        .ql-gc-link {
            margin-top: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 3px;
        }
        
        .ql-status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .ql-status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .ql-status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .ql-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #646970;
        }
        
        .ql-empty-state h2 {
            color: #1d2327;
            margin-bottom: 15px;
        }
        
        .ql-progress-bar {
            background: #f0f0f1;
            border-radius: 10px;
            height: 20px;
            position: relative;
            margin-top: 10px;
        }
        
        .ql-progress-fill {
            background: #2271b1;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .ql-progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            font-weight: bold;
            color: #1d2327;
        }
        
        .ql-project-origin {
            margin-top: 10px;
            padding: 8px;
            background: #e8f4fd;
            border-radius: 3px;
        }
        
        .ql-collective-badge {
            margin-top: 10px;
            padding: 8px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 3px;
        }
        </style>
        
        <script>
        function qlConfirmDeleteProject(projectId, projectName) {
            if (confirm('ATENÇÃO: Tem certeza que deseja excluir permanentemente o projeto "' + projectName + '"?\n\nEsta ação:\n• Removerá todos os quadros e tarefas\n• Excluirá todos os anexos\n• Não pode ser desfeita\n\nClique OK para continuar ou Cancelar para voltar.')) {
                var confirmation = prompt('Para confirmar a exclusão, digite "EXCLUIR" (em maiúsculas):');
                if (confirmation === 'EXCLUIR') {
                    // Criar e submeter formulário dinamicamente
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<?php echo wp_nonce_field("delete_project", "_wpnonce", true, false); ?>' +
                                   '<input type="hidden" name="project_id" value="' + projectId + '">' +
                                   '<input type="hidden" name="delete_project" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                } else {
                    alert('Exclusão cancelada. Texto de confirmação incorreto.');
                }
            }
        }
        
        function qlViewProjectDetails(projectId) {
            alert('Detalhes do projeto em desenvolvimento.\n\nProjeto ID: ' + projectId + '\n\nEm breve você poderá:\n• Ver informações completas\n• Gerenciar membros\n• Ver relatórios detalhados');
        }
        </script>
        <?php
    }
    
    /**
     * Página de Status
     */
    public function status_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Status do Sistema', 'quilombo-lab'); ?></h1>
            
            <?php if (class_exists('QL_Status')): ?>
                <?php 
                $can_activate = QL_Status::display_report();
                ?>
                
                <div class="ql-status-actions" style="margin-top: 20px;">
                    <button class="button" onclick="location.reload()">
                        <?php _e('🔄 Atualizar Status', 'quilombo-lab'); ?>
                    </button>
                    
                    <?php if ($can_activate): ?>
                        <span style="color: green; margin-left: 15px;">
                            <strong><?php _e('✅ Sistema funcionando corretamente!', 'quilombo-lab'); ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><?php _e('Classe QL_Status não disponível. Verifique a instalação do plugin.', 'quilombo-lab'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ql-status-report {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .ql-status-success {
            color: #00a32a;
            padding: 5px 0;
        }
        
        .ql-status-warning {
            color: #dba617;
            padding: 5px 0;
        }
        
        .ql-status-error {
            color: #d63638;
            padding: 5px 0;
        }
        
        .ql-status-summary {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f1;
            font-size: 16px;
        }
        </style>
        <?php
    }
    
    
    /**
     * Buscar trilhas disponíveis no Moodle (já sincronizadas)
     */
    private function get_trilhas_moodle() {
        global $wpdb;
        
        // Primeiro, buscar trilhas já sincronizadas (mais confiável)
        $trilhas_sincronizadas = $wpdb->get_results("
            SELECT 
                mm.moodle_course_id as id,
                mm.course_name as nome,
                mm.course_shortname as shortname,
                mm.trilha_type as categoria,
                mm.is_collective,
                mm.last_sync,
                p.name as projeto_nome
            FROM {$wpdb->prefix}ql_moodle_mappings mm
            LEFT JOIN {$wpdb->prefix}ql_projects p ON mm.ql_project_id = p.id
            ORDER BY mm.moodle_course_id
        ");
        
        $trilhas = [];
        
        // Converter trilhas sincronizadas para formato esperado
        foreach ($trilhas_sincronizadas as $trilha) {
            $trilhas[] = [
                'id' => $trilha->id,
                'nome' => $trilha->nome,
                'categoria' => $trilha->categoria,
                'is_site_course' => ($trilha->id == 1),
                'is_collective' => ($trilha->is_collective == 1),
                'projeto_nome' => $trilha->projeto_nome,
                'last_sync' => $trilha->last_sync,
                'descricao' => "Trilha sincronizada - Projeto: {$trilha->projeto_nome}"
            ];
        }
        
        // Se não há trilhas sincronizadas, tentar buscar diretamente do Moodle
        if (empty($trilhas) && class_exists('QL_Moodle_Integration')) {
            error_log('QL Admin: Nenhuma trilha sincronizada encontrada, tentando buscar do Moodle...');
            
            $integration = QL_Moodle_Integration::get_instance();
            $cursos_moodle = $integration->get_cursos_moodle();
            
            foreach ($cursos_moodle as $curso) {
                $trilhas[] = [
                    'id' => $curso['id'],
                    'nome' => $curso['fullname'],
                    'categoria' => 'aprendizagem', // padrão
                    'is_site_course' => ($curso['id'] == 1),
                    'is_collective' => false,
                    'projeto_nome' => 'Não sincronizado',
                    'last_sync' => null,
                    'descricao' => $curso['summary'] ?? 'Curso disponível no Moodle'
                ];
            }
        }
        
        // Garantir que sempre há pelo menos o curso padrão (ID 1)
        if (empty($trilhas)) {
            $trilhas[] = [
                'id' => 1,
                'nome' => 'Curso Principal do Site (Padrão)',
                'categoria' => 'coletivo',
                'is_site_course' => true,
                'is_collective' => false,
                'projeto_nome' => 'A criar',
                'last_sync' => null,
                'descricao' => 'Curso padrão do site Moodle - usado como fallback'
            ];
        }
        
        return $trilhas;
    }
    
    /**
     * Detectar categoria de uma trilha baseado no nome/categoria
     */
    private function detectar_categoria_trilha($trilha) {
        $nome = strtolower($trilha['fullname'] ?? $trilha['nome'] ?? '');
        $categoria = strtolower($trilha['category_name'] ?? '');
        
        // Palavras-chave para cada categoria
        $categorias = [
            'aprendizagem' => ['aprendizagem', 'curso', 'ensino', 'educação', 'learning', 'education'],
            'pesquisa' => ['pesquisa', 'research', 'estudo', 'investigação', 'analysis'],
            'criacao' => ['criação', 'desenvolvimento', 'projeto', 'creation', 'development', 'build']
        ];
        
        foreach ($categorias as $cat => $palavras) {
            foreach ($palavras as $palavra) {
                if (strpos($nome, $palavra) !== false || strpos($categoria, $palavra) !== false) {
                    return $cat;
                }
            }
        }
        
        return 'aprendizagem'; // padrão
    }
    
    /**
     * Verificar se é o curso principal do site Moodle
     */
    private function is_curso_site_moodle($trilha) {
        $id = $trilha['id'] ?? 0;
        $nome = strtolower($trilha['fullname'] ?? $trilha['nome'] ?? '');
        
        // ID 1 = curso padrão do Moodle
        if ($id == 1) {
            return true;
        }
        
        // Verificar por palavras-chave
        $indicadores = ['site', 'principal', 'home', 'geral', 'quilombo', 'ciência'];
        foreach ($indicadores as $indicador) {
            if (strpos($nome, $indicador) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Processar mudança do projeto responsável pelo coletivo (NOVA LÓGICA UNIFICADA)
     */
    private function processar_mudanca_projeto_coletivo($projeto_anterior, $novo_projeto_id) {
        global $wpdb;
        
        // Log da mudança
        error_log("QL Admin: Projeto coletivo alterado de {$projeto_anterior} para {$novo_projeto_id}");
        
        try {
            // 1. Remover marcação coletiva de TODOS os projetos (apenas UM por vez)
            $wpdb->query("
                UPDATE {$wpdb->prefix}ql_projects 
                SET settings = JSON_REMOVE(settings, '$.is_collective_project')
                WHERE JSON_EXTRACT(settings, '$.is_collective_project') = true
            ");
            
            // 2. Remover marcação coletiva de TODOS os mapeamentos Moodle
            $wpdb->update(
                $wpdb->prefix . 'ql_moodle_mappings',
                ['is_collective' => 0],
                ['is_collective' => 1],
                ['%d'],
                ['%d']
            );
            
            // 3. Marcar o NOVO projeto como coletivo
            $projeto = $wpdb->get_row($wpdb->prepare(
                "SELECT id, name, moodle_course_id, settings FROM {$wpdb->prefix}ql_projects WHERE id = %d",
                $novo_projeto_id
            ));
            
            if (!$projeto) {
                throw new Exception("Projeto ID {$novo_projeto_id} não encontrado");
            }
            
            // Atualizar settings do projeto
            $settings = json_decode($projeto->settings, true) ?: [];
            $settings['is_collective_project'] = true;
            
            $wpdb->update(
                $wpdb->prefix . 'ql_projects',
                ['settings' => json_encode($settings)],
                ['id' => $novo_projeto_id],
                ['%s'],
                ['%d']
            );
            
            // 4. Se o projeto tem trilha Moodle correspondente, marcar mapeamento como coletivo
            if ($projeto->moodle_course_id) {
                $wpdb->update(
                    $wpdb->prefix . 'ql_moodle_mappings',
                    ['is_collective' => 1],
                    ['moodle_course_id' => $projeto->moodle_course_id],
                    ['%d'],
                    ['%d']
                );
                
                // Atualizar também a configuração de trilha coletivo para manter compatibilidade
                update_option('quilombo_laboratorio_trilha_coletivo', $projeto->moodle_course_id);
                
                error_log("QL Admin: Trilha Moodle {$projeto->moodle_course_id} automaticamente definida como trilha do coletivo");
            }
            
            // 5. Integração com Gestão Coletiva (se disponível)
            if (class_exists('GC_Trilha') && $projeto->moodle_course_id) {
                try {
                    $trilha_data = [
                        'course_id' => $projeto->moodle_course_id,
                        'name' => $projeto->name,
                        'is_collective' => true
                    ];
                    
                    // Sincronizar com GC como fundo do coletivo
                    $fundo_id = GC_Trilha::sincronizar_trilha_coletivo($trilha_data);
                    if ($fundo_id) {
                        error_log("QL Admin: Projeto coletivo sincronizado com GC - Fundo ID: {$fundo_id}");
                    }
                } catch (Exception $e) {
                    error_log("QL Admin: Erro na sincronização GC: " . $e->getMessage());
                }
            }
            
            add_settings_error(
                'quilombo_laboratorio_settings',
                'projeto_coletivo_updated',
                sprintf(
                    __('Projeto "%s" agora é responsável pela gestão do coletivo! %s', 'quilombo-lab'), 
                    $projeto->name,
                    $projeto->moodle_course_id ? "Trilha Moodle ID {$projeto->moodle_course_id} automaticamente configurada." : "Projeto independente (sem trilha Moodle)."
                ),
                'updated'
            );
            
            error_log("QL Admin: Projeto coletivo unificado configurado - Projeto: {$novo_projeto_id}, Trilha: " . ($projeto->moodle_course_id ?: 'N/A'));
            
        } catch (Exception $e) {
            error_log("QL Admin: Erro ao processar mudança de projeto coletivo: " . $e->getMessage());
            
            add_settings_error(
                'quilombo_laboratorio_settings',
                'projeto_coletivo_error',
                __('Erro ao configurar projeto do coletivo: ', 'quilombo-lab') . $e->getMessage(),
                'error'
            );
        }
    }
    
    /**
     * Processar mudança da trilha do coletivo (FUNÇÃO LEGACY - MANTER COMPATIBILIDADE)
     */
    private function processar_mudanca_trilha_coletivo($trilha_anterior, $nova_trilha) {
        global $wpdb;
        
        // Salvar separadamente para consulta rápida
        update_option('quilombo_laboratorio_trilha_coletivo', $nova_trilha);
        
        // Log da mudança
        error_log("QL Admin: Trilha do coletivo alterada de {$trilha_anterior} para {$nova_trilha}");
        
        // GARANTIR QUE APENAS UM PROJETO COLETIVO EXISTE
        try {
            // 1. Remover marcação de coletivo de todos os mapeamentos
            $wpdb->update(
                $wpdb->prefix . 'ql_moodle_mappings',
                ['is_collective' => 0],
                ['is_collective' => 1],
                ['%d'],
                ['%d']
            );
            error_log("QL Admin: Removidas marcações coletivas de mapeamentos anteriores");
            
            // 2. Remover marcação de coletivo de todos os projetos
            $wpdb->query("
                UPDATE {$wpdb->prefix}ql_projects 
                SET settings = JSON_REMOVE(settings, '$.is_collective_project')
                WHERE JSON_EXTRACT(settings, '$.is_collective_project') = true
            ");
            error_log("QL Admin: Removidas marcações coletivas de projetos anteriores");
            
            // 3. Marcar novo mapeamento como coletivo
            $updated_mapping = $wpdb->update(
                $wpdb->prefix . 'ql_moodle_mappings',
                ['is_collective' => 1],
                ['moodle_course_id' => $nova_trilha],
                ['%d'],
                ['%d']
            );
            
            if ($updated_mapping) {
                error_log("QL Admin: Mapeamento da trilha {$nova_trilha} marcado como coletivo");
                
                // 4. Buscar e marcar projeto correspondente como coletivo
                $project_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ql_project_id FROM {$wpdb->prefix}ql_moodle_mappings WHERE moodle_course_id = %d",
                    $nova_trilha
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
                    
                    error_log("QL Admin: Projeto {$project_id} marcado como coletivo");
                    
                    add_settings_error(
                        'quilombo_laboratorio_settings',
                        'trilha_coletivo_updated',
                        sprintf(__('Trilha do coletivo alterada com sucesso! Projeto #%d agora é o projeto coletivo único.', 'quilombo-lab'), $project_id),
                        'updated'
                    );
                } else {
                    error_log("QL Admin: Trilha {$nova_trilha} não possui projeto correspondente ainda");
                    
                    add_settings_error(
                        'quilombo_laboratorio_settings',
                        'trilha_coletivo_no_project',
                        __('Trilha coletiva configurada, mas ainda não há projeto correspondente. Execute a sincronização Moodle.', 'quilombo-lab'),
                        'notice-warning'
                    );
                }
            } else {
                error_log("QL Admin: Trilha {$nova_trilha} não encontrada nos mapeamentos");
                
                add_settings_error(
                    'quilombo_laboratorio_settings',
                    'trilha_coletivo_not_found',
                    __('Trilha selecionada não foi encontrada. Execute a sincronização Moodle primeiro.', 'quilombo-lab'),
                    'error'
                );
            }
            
        } catch (Exception $e) {
            error_log("QL Admin: Erro ao processar mudança de trilha coletiva: " . $e->getMessage());
            
            add_settings_error(
                'quilombo_laboratorio_settings',
                'trilha_coletivo_error',
                __('Erro ao alterar trilha coletiva. Verifique os logs.', 'quilombo-lab'),
                'error'
            );
        }
        
        // Notificar Gestão Coletiva sobre a mudança (se integração estiver ativa)
        $this->notificar_gestao_coletiva_mudanca_trilha($nova_trilha);
    }
    
    /**
     * Notificar Gestão Coletiva sobre mudança de trilha
     */
    private function notificar_gestao_coletiva_mudanca_trilha($trilha_id) {
        // Integração com plugin Gestão Coletiva
        if (class_exists('GC_Trilha')) {
            try {
                // Buscar dados da trilha
                $trilha_data = $this->get_dados_trilha($trilha_id);
                
                // Criar/atualizar trilha na Gestão Coletiva
                $fundo_id = GC_Trilha::sincronizar_trilha_coletivo($trilha_data);
                
                error_log("Quilombo Laboratório: Trilha {$trilha_id} sincronizada como fundo {$fundo_id} na Gestão Coletiva");
                
            } catch (Exception $e) {
                error_log("Quilombo Laboratório: Erro ao sincronizar trilha {$trilha_id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Obter dados de uma trilha específica
     */
    private function get_dados_trilha($trilha_id) {
        $trilhas = $this->get_trilhas_moodle();
        $trilha = array_filter($trilhas, function($t) use ($trilha_id) {
            return $t['id'] == $trilha_id;
        });
        
        if (empty($trilha)) {
            throw new Exception("Trilha {$trilha_id} não encontrada");
        }
        
        return array_values($trilha)[0];
    }
    
    /**
     * Mostrar status da sincronização
     */
    private function mostrar_status_sincronizacao() {
        $trilha_coletivo = get_option('quilombo_laboratorio_trilha_coletivo', 0);
        $ultima_sync = get_option('quilombo_laboratorio_ultima_sync', 0);
        
        if ($trilha_coletivo) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Configurado:</strong> Trilha ID ' . $trilha_coletivo . ' definida como principal do coletivo.</p>';
            if ($ultima_sync) {
                echo '<p><small>Última sincronização: ' . date('d/m/Y H:i:s', $ultima_sync) . '</small></p>';
            }
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>⚠️ Não configurado:</strong> Selecione uma trilha para ser a principal do coletivo.</p>';
            echo '</div>';
        }
    }
    
    /**
     * Criar projeto de demonstração
     */
    private function create_demo_project() {
        if (!class_exists('QL_Project')) {
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos&message=error'));
            exit;
        }
        
        try {
            global $wpdb;
            
            // Verificar se já existe projeto demo
            $existing = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}ql_projects WHERE name LIKE 'Projeto Demo%'");
            if ($existing) {
                wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos&message=demo_exists'));
                exit;
            }
            
            $wpdb->query('START TRANSACTION');
            
            // Criar projeto demo
            $project_data = [
                'name' => 'Projeto Demo - ' . date('d/m/Y H:i'),
                'description' => 'Projeto de demonstração criado para testar as funcionalidades do Quilombo Laboratório. Contém boards e tarefas de exemplo.',
                'status' => 'active',
                'visibility' => 'team',
                'owner_id' => get_current_user_id(),
                'start_date' => current_time('Y-m-d'),
                'priority' => 'normal'
            ];
            
            $wpdb->insert($wpdb->prefix . 'ql_projects', $project_data);
            $project_id = $wpdb->insert_id;
            
            if (!$project_id) {
                throw new Exception('Erro ao criar projeto');
            }
            
            // Criar board principal
            $board_data = [
                'name' => 'Quadro Principal',
                'description' => 'Quadro principal do projeto de demonstração',
                'project_id' => $project_id,
                'user_id' => get_current_user_id(),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $wpdb->insert($wpdb->prefix . 'ql_boards', $board_data);
            $board_id = $wpdb->insert_id;
            
            // Criar colunas padrão
            $columns = [
                ['name' => 'Backlog', 'position' => 0, 'color' => '#6c757d'],
                ['name' => 'Para Fazer', 'position' => 1, 'color' => '#ffc107'],
                ['name' => 'Em Progresso', 'position' => 2, 'color' => '#17a2b8'],
                ['name' => 'Revisão', 'position' => 3, 'color' => '#fd7e14'],
                ['name' => 'Concluído', 'position' => 4, 'color' => '#28a745']
            ];
            
            $column_ids = [];
            foreach ($columns as $column) {
                $column['board_id'] = $board_id;
                $column['created_at'] = current_time('mysql');
                $column['updated_at'] = current_time('mysql');
                
                $wpdb->insert($wpdb->prefix . 'ql_columns', $column);
                $column_ids[] = $wpdb->insert_id;
            }
            
            // Criar tarefas de exemplo
            $tasks = [
                [
                    'title' => 'Configurar ambiente de desenvolvimento',
                    'description' => 'Instalar e configurar todas as ferramentas necessárias para o desenvolvimento do projeto.',
                    'column_id' => $column_ids[4], // Concluído
                    'priority' => 2,
                    'status' => 'completed'
                ],
                [
                    'title' => 'Definir arquitetura do sistema',
                    'description' => 'Documentar a arquitetura técnica e definir padrões de desenvolvimento.',
                    'column_id' => $column_ids[4], // Concluído
                    'priority' => 3,
                    'status' => 'completed'
                ],
                [
                    'title' => 'Implementar autenticação de usuários',
                    'description' => 'Desenvolver sistema de login e controle de acesso.',
                    'column_id' => $column_ids[2], // Em Progresso
                    'priority' => 3,
                    'status' => 'in_progress'
                ],
                [
                    'title' => 'Criar dashboard administrativo',
                    'description' => 'Interface para administração do sistema.',
                    'column_id' => $column_ids[1], // Para Fazer
                    'priority' => 2,
                    'status' => 'open'
                ],
                [
                    'title' => 'Implementar relatórios',
                    'description' => 'Sistema de relatórios e estatísticas.',
                    'column_id' => $column_ids[0], // Backlog
                    'priority' => 1,
                    'status' => 'open'
                ],
                [
                    'title' => 'Testes automatizados',
                    'description' => 'Criar suite de testes para garantir qualidade.',
                    'column_id' => $column_ids[0], // Backlog
                    'priority' => 2,
                    'status' => 'open'
                ]
            ];
            
            foreach ($tasks as $index => $task) {
                $task['project_id'] = $project_id;
                $task['board_id'] = $board_id;
                $task['creator_id'] = get_current_user_id();
                $task['position'] = $index;
                $task['reference'] = 'DEMO-' . sprintf('%03d', $index + 1);
                $task['created_at'] = current_time('mysql');
                $task['updated_at'] = current_time('mysql');
                
                $wpdb->insert($wpdb->prefix . 'ql_tasks', $task);
            }
            
            $wpdb->query('COMMIT');
            
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos&message=demo_created'));
            exit;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos&message=error'));
            exit;
        }
    }
    
    /**
     * Obter estatísticas do projeto
     */
    private function get_project_stats($project_id) {
        global $wpdb;
        
        // Contar quadros
        $boards_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_boards WHERE project_id = %d",
            $project_id
        ));
        
        // Contar tarefas
        $tasks_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE project_id = %d",
            $project_id
        ));
        
        return [
            'quadros' => intval($boards_count),
            'tasks' => intval($tasks_count)
        ];
    }
    
    /**
     * Página de boards do projeto
     */
    public function project_boards_page() {
        $project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        
        if (!$project_id) {
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos'));
            exit;
        }
        
        global $wpdb;
        
        // Buscar projeto
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos'));
            exit;
        }
        
        // Buscar quadros do projeto
        $quadros = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_boards WHERE project_id = %d ORDER BY created_at ASC",
            $project_id
        ));
        
        ?>
        <div class="wrap">
            <h1>
                <?php printf(__('Quadros do Projeto: %s', 'quilombo-lab'), esc_html($project->name)); ?>
                <a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>" class="page-title-action">
                    ← <?php _e('Voltar aos Projetos', 'quilombo-lab'); ?>
                </a>
            </h1>
            
            <?php if (!empty($quadros)): ?>
                <div class="ql-boards-list">
                    <?php foreach ($quadros as $quadro): ?>
                        <?php
                        // Buscar colunas e tarefas do quadro
                        $columns = $wpdb->get_results($wpdb->prepare(
                            "SELECT c.*, COUNT(t.id) as tasks_count 
                             FROM {$wpdb->prefix}ql_columns c 
                             LEFT JOIN {$wpdb->prefix}ql_tasks t ON c.id = t.column_id 
                             WHERE c.board_id = %d 
                             GROUP BY c.id 
                             ORDER BY c.position ASC",
                            $quadro->id
                        ));
                        ?>
                        
                        <div class="ql-board-card">
                            <div class="ql-board-header">
                                <h2><?php echo esc_html($quadro->name); ?></h2>
                                <div class="ql-board-actions">
                                    <button class="button button-primary" onclick="qlOpenQuadro(<?php echo $quadro->id; ?>)">
                                        📋 <?php _e('Abrir Quadro', 'quilombo-lab'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <?php if ($quadro->description): ?>
                                <p class="ql-board-description"><?php echo esc_html($quadro->description); ?></p>
                            <?php endif; ?>
                            
                            <div class="ql-board-preview">
                                <?php if (!empty($columns)): ?>
                                    <div class="ql-columns-preview">
                                        <?php foreach ($columns as $column): ?>
                                            <div class="ql-column-preview" style="border-left: 3px solid <?php echo esc_attr($column->color ?: '#ccc'); ?>">
                                                <strong><?php echo esc_html($column->name); ?></strong>
                                                <span class="ql-tasks-count"><?php echo $column->tasks_count; ?> tarefas</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="ql-no-columns"><?php _e('Nenhuma coluna criada ainda.', 'quilombo-lab'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="ql-empty-state">
                    <h2><?php _e('Nenhum board encontrado', 'quilombo-lab'); ?></h2>
                    <p><?php _e('Este projeto ainda não possui boards criados.', 'quilombo-lab'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ql-boards-list {
            margin-top: 20px;
        }
        
        .ql-board-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .ql-board-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .ql-board-header h2 {
            margin: 0;
            color: #1d2327;
        }
        
        .ql-board-description {
            color: #646970;
            margin-bottom: 15px;
        }
        
        .ql-columns-preview {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .ql-column-preview {
            background: #f6f7f7;
            padding: 10px 15px;
            border-radius: 4px;
            min-width: 120px;
            text-align: center;
        }
        
        .ql-column-preview strong {
            display: block;
            margin-bottom: 5px;
            color: #1d2327;
        }
        
        .ql-tasks-count {
            font-size: 12px;
            color: #646970;
        }
        
        .ql-no-columns {
            color: #646970;
            font-style: italic;
        }
        
        .ql-project-stats .ql-stat-row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .ql-project-stats .ql-stat-row span {
            font-size: 14px;
            color: #646970;
        }
        
        .ql-gc-link {
            margin-top: 10px;
        }
        
        .ql-gc-link small {
            color: #0073aa;
        }
        </style>
        
        <script>
        function qlOpenQuadro(quadroId) {
            console.log('qlOpenQuadro chamada com ID:', quadroId);
            // Redirecionar para página do Kanban
            var url = '<?php echo admin_url('admin.php?page=quilombo-lab-kanban&board_id='); ?>' + quadroId;
            console.log('Redirecionando para:', url);
            window.location.href = url;
        }
        
        function qlViewProjectDetails(projectId) {
            // Mostrar detalhes do projeto
            alert('Detalhes do projeto em desenvolvimento.\n\nProjeto ID: ' + projectId + '\n\nEm breve você poderá:\n• Ver informações completas\n• Editar configurações\n• Gerenciar membros\n• Ver relatórios');
        }
        </script>
        <?php
    }
    
    /**
     * Obter URL da página pública do projeto
     */
    private function get_project_page_url($project) {
        global $wpdb;
        
        // 1. Verificar se existe página com meta ql_projeto_id (método primário)
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = 'ql_projeto_id' AND meta_value = %s 
             AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish')",
            $project->id
        ));
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // 2. Para projetos do Moodle, verificar se existe página mapeada via trilha_sync
        if (!empty($project->moodle_course_id)) {
            // Verificar se tabela existe
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ql_trilha_mappings'");
            if ($table_exists) {
                $page_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT wp_page_id FROM {$wpdb->prefix}ql_trilha_mappings 
                     WHERE ql_project_id = %d AND wp_page_id > 0",
                    $project->id
                ));
                
                if ($page_id) {
                    return get_permalink($page_id);
                }
            }
        }
        
        // 3. Verificar pelo ID do projeto GC se existir (para compatibilidade)
        if (!empty($project->gc_projeto_id)) {
            $page_id_gc = get_option('ql_projeto_page_' . $project->gc_projeto_id);
            if ($page_id_gc && get_post_status($page_id_gc) === 'publish') {
                return get_permalink($page_id_gc);
            }
        }
        
        // 4. Buscar página por slug/nome do projeto (fallback)
        $page_slug = sanitize_title($project->name);
        $page_by_slug = get_page_by_path($page_slug);
        
        if ($page_by_slug && $page_by_slug->post_status === 'publish') {
            // Verificar se realmente é uma página de projeto
            $is_project_page = get_post_meta($page_by_slug->ID, 'ql_is_projeto_page', true);
            if ($is_project_page) {
                return get_permalink($page_by_slug->ID);
            }
        }
        
        return null;
    }
    
    /**
     * Obter Board ID com fallback para configuração
     */
    private function get_board_id_with_fallback() {
        // 1. Board ID da URL tem prioridade
        $board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;
        
        if ($board_id > 0) {
            return $board_id;
        }
        
        // 2. Board ID das configurações
        $default_board_id = intval(QL_Config::get('general', 'default_board_id', 0));
        
        if ($default_board_id > 0) {
            return $default_board_id;
        }
        
        // 3. Tentar obter o primeiro board disponível
        global $wpdb;
        $first_board = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}ql_boards ORDER BY created_at ASC LIMIT 1");
        
        return $first_board ? intval($first_board) : 0;
    }

    /**
     * Página do Kanban Board
     */
    public function kanban_page() {
        $board_id = $this->get_board_id_with_fallback();
        
        // Debug
        error_log("Kanban page chamada com board_id: $board_id");
        
        if (!$board_id) {
            error_log("Board ID não fornecido, redirecionando para projetos");
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos'));
            exit;
        }
        
        global $wpdb;
        
        // Buscar dados do board
        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, p.name as project_name 
             FROM {$wpdb->prefix}ql_boards b 
             LEFT JOIN {$wpdb->prefix}ql_projects p ON b.project_id = p.id 
             WHERE b.id = %d",
            $board_id
        ));
        
        if (!$board) {
            wp_redirect(admin_url('admin.php?page=quilombo-lab-projetos'));
            exit;
        }
        
        // Buscar colunas do board
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position ASC",
            $board_id
        ));
        
        // Buscar tarefas agrupadas por coluna
        $tasks_query = $wpdb->prepare(
            "SELECT t.*, 
                    u.display_name as assigned_name,
                    c.display_name as creator_name
             FROM {$wpdb->prefix}ql_tasks t 
             LEFT JOIN {$wpdb->prefix}users u ON t.assigned_user_id = u.ID 
             LEFT JOIN {$wpdb->prefix}users c ON t.creator_id = c.ID
             INNER JOIN {$wpdb->prefix}ql_columns col ON t.column_id = col.id
             WHERE col.board_id = %d 
             ORDER BY col.position ASC, t.position ASC",
            $board_id
        );
        $all_tasks = $wpdb->get_results($tasks_query);
        
        // Agrupar tarefas por coluna
        $tasks_by_column = [];
        foreach ($all_tasks as $task) {
            $tasks_by_column[$task->column_id][] = $task;
        }
        
        // Buscar usuários do projeto para dropdown
        $project_users = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT u.ID, u.display_name 
             FROM {$wpdb->prefix}users u 
             INNER JOIN {$wpdb->prefix}ql_project_members pm ON u.ID = pm.user_id 
             WHERE pm.project_id = %d 
             ORDER BY u.display_name ASC",
            $board->project_id
        ));
        
        ?>
        <div class="wrap">
            <h1>
                <?php printf(__('%s - %s', 'quilombo-lab'), esc_html($board->project_name), esc_html($board->name)); ?>
                <a href="<?php echo admin_url('admin.php?page=quilombo-lab-project-boards&project_id=' . $board->project_id); ?>" class="page-title-action">
                    ← <?php _e('Voltar aos Quadros', 'quilombo-lab'); ?>
                </a>
            </h1>
            
            <div class="ql-kanban-container">
                <div class="ql-kanban-board" data-board-id="<?php echo $board_id; ?>">
                    <?php foreach ($columns as $column): ?>
                        <div class="ql-kanban-column" data-column-id="<?php echo $column->id; ?>">
                            <div class="ql-column-header" style="border-left: 4px solid <?php echo esc_attr($column->color ?: '#ccc'); ?>">
                                <h3 class="ql-column-title"><?php echo esc_html($column->name); ?></h3>
                                <span class="ql-column-count">
                                    <?php echo count($tasks_by_column[$column->id] ?? []); ?>
                                </span>
                                <button class="ql-add-task-btn" data-column-id="<?php echo $column->id; ?>" title="Adicionar Tarefa">
                                    +
                                </button>
                            </div>
                            
                            <div class="ql-tasks-list">
                                <?php if (isset($tasks_by_column[$column->id])): ?>
                                    <?php foreach ($tasks_by_column[$column->id] as $task): ?>
                                        <div class="ql-kanban-task ql-color-<?php echo esc_attr($task->color_id ?: 'blue'); ?>" 
                                             data-task-id="<?php echo $task->id; ?>" 
                                             data-priority="<?php echo esc_attr($task->priority); ?>"
                                             style="cursor: pointer;">
                                             
                                            <!-- Header da tarefa com referência e prioridade -->
                                            <div class="ql-task-header">
                                                <div class="ql-task-ref-container">
                                                    <span class="ql-task-ref"><?php echo esc_html($task->reference ?: $task->task_number ?: '#' . $task->id); ?></span>
                                                    <?php if ($task->score > 0): ?>
                                                        <span class="ql-task-score" title="Story Points">⭐ <?php echo $task->score; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ql-task-priority-container">
                                                    <span class="ql-task-priority ql-priority-<?php echo $task->priority; ?>" title="Prioridade: <?php echo ucfirst($task->priority); ?>">
                                                        <?php 
                                                        $priority_icons = [
                                                            'low' => '🔽', 
                                                            'normal' => '➖', 
                                                            'high' => '🔼', 
                                                            'urgent' => '🚨'
                                                        ];
                                                        echo $priority_icons[$task->priority] ?? '➖';
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Título da tarefa -->
                                            <h4 class="ql-task-title" title="<?php echo esc_attr($task->title); ?>">
                                                <?php echo esc_html(wp_trim_words($task->title, 8, '...')); ?>
                                            </h4>
                                            
                                            <!-- Descrição -->
                                            <?php if ($task->description): ?>
                                                <p class="ql-task-description" title="<?php echo esc_attr(wp_trim_words($task->description, 30)); ?>">
                                                    <?php echo esc_html(wp_trim_words($task->description, 12, '...')); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <!-- Tags se houver -->
                                            <?php if ($task->tags): ?>
                                                <div class="ql-task-tags">
                                                    <?php 
                                                    $tags = explode(',', $task->tags);
                                                    foreach (array_slice($tags, 0, 2) as $tag): 
                                                        $tag = trim($tag);
                                                        if ($tag):
                                                    ?>
                                                        <span class="ql-tag"><?php echo esc_html($tag); ?></span>
                                                    <?php endif; endforeach; ?>
                                                    <?php if (count($tags) > 2): ?>
                                                        <span class="ql-tag-more">+<?php echo count($tags) - 2; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Informações de tempo e data -->
                                            <?php if ($task->time_estimated > 0 || $task->due_date): ?>
                                                <div class="ql-task-meta">
                                                    <?php if ($task->time_estimated > 0): ?>
                                                        <span class="ql-task-time" title="Tempo estimado: <?php echo $task->time_estimated; ?>h">
                                                            ⏱️ <?php echo number_format($task->time_estimated, 1); ?>h
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($task->due_date): ?>
                                                        <?php 
                                                        $due_timestamp = strtotime($task->due_date);
                                                        $is_overdue = $due_timestamp < time();
                                                        $is_today = date('Y-m-d', $due_timestamp) === date('Y-m-d');
                                                        $is_soon = $due_timestamp < (time() + 86400 * 3); // 3 days
                                                        ?>
                                                        <span class="ql-task-due <?php 
                                                            echo $is_overdue ? 'ql-overdue' : '';
                                                            echo $is_today ? 'ql-today' : '';
                                                            echo $is_soon && !$is_overdue && !$is_today ? 'ql-soon' : '';
                                                        ?>" title="Vencimento: <?php echo date_i18n('d/m/Y H:i', $due_timestamp); ?>">
                                                            📅 <?php echo date('d/m', $due_timestamp); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Footer com responsável e progresso -->
                                            <div class="ql-task-footer">
                                                <div class="ql-task-assignee">
                                                    <?php if ($task->assigned_name): ?>
                                                        <div class="ql-avatar" title="Responsável: <?php echo esc_attr($task->assigned_name); ?>">
                                                            <?php echo strtoupper(substr($task->assigned_name, 0, 2)); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="ql-avatar ql-unassigned" title="Não atribuído">
                                                            ?
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="ql-task-actions">
                                                    <?php if ($task->status === 'completed'): ?>
                                                        <span class="ql-task-status-icon" title="Concluído">✅</span>
                                                    <?php elseif ($task->status === 'in_progress'): ?>
                                                        <span class="ql-task-status-icon" title="Em progresso">🔄</span>
                                                    <?php elseif ($task->status === 'review'): ?>
                                                        <span class="ql-task-status-icon" title="Em revisão">👁️</span>
                                                    <?php endif; ?>
                                                    
                                                    <button class="ql-task-menu" title="Mais opções">⋮</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <style>
        .ql-kanban-container {
            margin-top: 20px;
            overflow-x: auto;
            padding-bottom: 20px;
        }
        
        .ql-kanban-board {
            display: flex;
            gap: 20px;
            min-width: max-content;
            padding: 20px;
            background: #f6f7f7;
            border-radius: 8px;
        }
        
        .ql-kanban-column {
            width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .ql-column-header {
            padding: 15px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        
        .ql-column-title {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: #1d2327;
            flex: 1;
        }
        
        .ql-column-count {
            background: #6c757d;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin: 0 10px;
        }
        
        .ql-add-task-btn {
            background: #2271b1;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            transition: background 0.2s;
        }
        
        .ql-add-task-btn:hover {
            background: #135e96;
        }
        
        .ql-tasks-list {
            padding: 15px;
            min-height: 200px;
        }
        
        /* Cards das tarefas - estilo Kanboard aprimorado */
        .ql-kanban-task {
            background: white;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 0;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            overflow: hidden;
            position: relative;
        }
        
        .ql-kanban-task::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #ddd;
        }
        
        /* Cores por tipo de tarefa */
        .ql-color-red::before { background: #e74c3c; }
        .ql-color-blue::before { background: #3498db; }
        .ql-color-green::before { background: #27ae60; }
        .ql-color-purple::before { background: #9b59b6; }
        .ql-color-orange::before { background: #f39c12; }
        .ql-color-yellow::before { background: #f1c40f; }
        .ql-color-grey::before { background: #95a5a6; }
        
        .ql-kanban-task:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
            border-color: #2271b1;
        }
        
        .ql-kanban-task.ql-dragging {
            opacity: 0.9;
            transform: rotate(3deg) scale(1.02);
            z-index: 1000;
        }
        
        /* Header da tarefa */
        .ql-task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 12px 12px 8px 12px;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .ql-task-ref-container {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ql-task-ref {
            font-size: 11px;
            color: #6c757d;
            font-weight: 600;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 12px;
        }
        
        .ql-task-score {
            font-size: 10px;
            color: #f39c12;
            font-weight: 600;
        }
        
        .ql-task-priority-container {
            display: flex;
            align-items: center;
        }
        
        .ql-task-priority {
            font-size: 14px;
            line-height: 1;
        }
        
        /* Prioridades com cores */
        .ql-priority-low { color: #27ae60; }
        .ql-priority-normal { color: #6c757d; }
        .ql-priority-high { color: #f39c12; }
        .ql-priority-urgent { color: #e74c3c; }
        
        /* Título da tarefa */
        .ql-task-title {
            margin: 0;
            padding: 0 12px 8px 12px;
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
            line-height: 1.4;
            min-height: 20px;
        }
        
        /* Descrição */
        .ql-task-description {
            margin: 0;
            padding: 0 12px 8px 12px;
            font-size: 12px;
            color: #646970;
            line-height: 1.4;
            max-height: 40px;
            overflow: hidden;
        }
        
        /* Tags */
        .ql-task-tags {
            padding: 0 12px 8px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .ql-tag {
            font-size: 10px;
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 500;
        }
        
        .ql-tag-more {
            font-size: 10px;
            background: #f5f5f5;
            color: #666;
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        /* Meta informações */
        .ql-task-meta {
            padding: 0 12px 8px 12px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .ql-task-time {
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 8px;
        }
        
        .ql-task-due {
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 8px;
        }
        
        .ql-task-due.ql-overdue {
            background: #ffebee;
            color: #c62828;
            font-weight: 600;
        }
        
        .ql-task-due.ql-today {
            background: #fff3e0;
            color: #ef6c00;
            font-weight: 600;
        }
        
        .ql-task-due.ql-soon {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        /* Footer da tarefa */
        .ql-task-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px 12px 12px;
            background: #fafbfc;
            border-top: 1px solid #f0f0f1;
        }
        
        .ql-task-assignee {
            display: flex;
            align-items: center;
        }
        
        .ql-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #2271b1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .ql-avatar.ql-unassigned {
            background: #ddd;
            color: #666;
        }
        
        .ql-task-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ql-task-status-icon {
            font-size: 14px;
        }
        
        .ql-task-menu {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 4px;
            border-radius: 3px;
            font-size: 14px;
            line-height: 1;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .ql-kanban-task:hover .ql-task-menu {
            opacity: 1;
        }
        
        .ql-task-menu:hover {
            background: #f0f0f1;
            color: #1d2327;
        }
        
        .ql-task-placeholder {
            background: #f0f0f1;
            border: 2px dashed #c3c4c7;
            border-radius: 6px;
            height: 80px;
            margin-bottom: 10px;
        }
        
        /* Modal Styles */
        .ql-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ql-modal.hidden {
            display: none;
        }
        
        .ql-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .ql-modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 1;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .ql-modal-content.ql-modal-large {
            max-width: 900px;
            width: 95%;
        }
        
        .ql-modal-header {
            padding: 20px 20px 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ql-modal-title {
            margin: 0;
            font-size: 18px;
            color: #1d2327;
        }
        
        .ql-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ql-modal-body {
            padding: 20px;
        }
        
        .ql-modal-footer {
            padding: 0 20px 20px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .ql-form-group {
            margin-bottom: 15px;
        }
        
        .ql-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #1d2327;
        }
        
        .ql-form-group input,
        .ql-form-group textarea,
        .ql-form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .ql-form-group textarea {
            resize: vertical;
        }
        
        .ql-form-row {
            display: flex;
            gap: 15px;
        }
        
        .ql-form-row .ql-form-group {
            flex: 1;
        }
        
        .ql-form-group.ql-form-group-large {
            flex: 2;
        }
        
        .ql-form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f1;
        }
        
        .ql-form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .ql-form-section h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #1d2327;
            font-weight: 600;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f1;
        }
        
        .ql-form-group small {
            display: block;
            margin-top: 4px;
            color: #6c757d;
            font-size: 11px;
        }
        
        /* Loading */
        .ql-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .ql-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2271b1;
            border-radius: 50%;
            animation: ql-spin 1s linear infinite;
        }
        
        @keyframes ql-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Notifications */
        .ql-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            color: white;
            font-weight: 600;
            z-index: 999999;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .ql-notification-show {
            transform: translateX(0);
        }
        
        .ql-notification-success {
            background: #00a32a;
        }
        
        .ql-notification-error {
            background: #d63638;
        }
        
        .ql-notification-info {
            background: #2271b1;
        }

        /* ========== DRAG & DROP ENHANCEMENTS ========== */
        
        /* Efeitos durante o drag */
        .ql-kanban-task.ql-dragging {
            opacity: 0.8;
            transform: rotate(2deg) scale(1.02);
            z-index: 1000;
            box-shadow: 0 8px 25px rgba(34, 113, 177, 0.25);
            border-color: #2271b1;
        }

        /* Placeholder para posição de drop */
        .ql-task-placeholder {
            background: rgba(34, 113, 177, 0.1);
            border: 2px dashed #2271b1;
            border-radius: 8px;
            height: 80px;
            margin-bottom: 12px;
            position: relative;
            transition: all 0.2s ease;
        }

        .ql-task-placeholder::before {
            content: "↓ Solte aqui";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #2271b1;
            font-weight: 500;
            font-size: 14px;
        }

        /* Estados das colunas durante drag */
        .ql-kanban-column.ql-column-dragging-from {
            opacity: 0.7;
            background: rgba(219, 166, 23, 0.05);
        }

        .ql-kanban-column.ql-column-dragging-to {
            background: rgba(34, 113, 177, 0.05);
            border: 2px solid rgba(34, 113, 177, 0.3);
            transform: scale(1.02);
        }

        /* Transições suaves para todas as tarefas */
        .ql-kanban-task {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Efeito de "highlight" quando uma tarefa é movida */
        .ql-kanban-task.ql-task-moved {
            animation: ql-task-highlight 0.6s ease-out;
        }

        @keyframes ql-task-highlight {
            0% {
                background: rgba(0, 163, 42, 0.2);
                border-color: #00a32a;
                transform: scale(1.05);
            }
            100% {
                background: white;
                border-color: #e1e5e9;
                transform: scale(1);
            }
        }

        /* Helper visual durante drag */
        .ql-drag-helper {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2271b1;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.3);
            animation: ql-helper-bounce 1s ease-in-out infinite alternate;
        }

        @keyframes ql-helper-bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-5px); }
        }

        /* Cursor personalizado durante drag */
        .ui-sortable-helper {
            cursor: grabbing !important;
        }

        /* Melhorar responsividade das tarefas durante drag */
        .ql-tasks-list.ui-sortable {
            min-height: 100px;
        }

        .ql-tasks-list.ui-sortable-over {
            background: rgba(34, 113, 177, 0.05);
            border-radius: 8px;
        }
        
        /* CORREÇÃO DE EMERGÊNCIA - GARANTIR VISIBILIDADE E FUNCIONALIDADE */
        .ql-add-task-btn {
            cursor: pointer !important;
            pointer-events: auto !important;
            position: relative !important;
            z-index: 10 !important;
        }
        
        .ql-kanban-task {
            cursor: pointer !important;
            pointer-events: auto !important;
            position: relative !important;
            z-index: 5 !important;
        }
        
        .ql-kanban-task.ql-dragging {
            opacity: 0.7 !important;
            transform: rotate(2deg) !important;
            z-index: 1000 !important;
        }
        
        /* Estados de drag-and-drop avançados */
        .ql-drop-target {
            background: rgba(34, 113, 177, 0.1) !important;
            border: 2px dashed #2271b1 !important;
            border-radius: 8px !important;
        }
        
        .ql-drop-active {
            background: rgba(34, 113, 177, 0.2) !important;
            border: 2px solid #2271b1 !important;
        }
        
        .ql-kanban-task.ql-moving {
            opacity: 0.6 !important;
            filter: blur(1px) !important;
        }
        
        .ql-kanban-task.ql-move-success {
            background: rgba(40, 167, 69, 0.1) !important;
            border-color: #28a745 !important;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.3) !important;
        }
        
        .ql-task-placeholder {
            background: rgba(34, 113, 177, 0.1) !important;
            border: 2px dashed #2271b1 !important;
            border-radius: 8px !important;
            height: 80px !important;
            margin-bottom: 12px !important;
            position: relative !important;
            transition: all 0.2s ease !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #2271b1 !important;
            font-weight: 500 !important;
        }
        </style>
        
        <!-- Sistema Kanban limpo e funcional -->
        <script>
        // Configuração essencial do QLKanban
        console.log('🔧 APLICANDO CORREÇÃO DEFINITIVA...');
        
        // Garantir que ql_admin está disponível IMEDIATAMENTE
        window.ql_admin = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>',
            board_id: <?php echo get_option('ql_default_board_id', 1); ?>
        };
        console.log('✅ window.ql_admin DEFINIDO:', window.ql_admin);
        
        // Redefinir função de fechamento IMEDIATAMENTE
        window.closeTaskModal = function() {
            console.log('🗙 FECHANDO MODAL (CORREÇÃO DEFINITIVA)');
            if (typeof jQuery !== 'undefined') {
                jQuery('#ql-task-creation-modal, #ql-task-view-modal').remove();
            }
        };
        console.log('✅ closeTaskModal REDEFINIDA');
        
        // Aguardar jQuery e DOM
        (function() {
            function forceKanbanFix() {
                if (typeof jQuery === 'undefined') {
                    console.log('❌ jQuery não disponível');
                    return;
                }
                
                var $ = jQuery;
                console.log('✅ jQuery disponível, aplicando correção...');
                
                // Debug: verificar elementos no DOM
                var addButtons = $('.ql-add-task-btn');
                var taskCards = $('.ql-kanban-task');
                var taskLists = $('.ql-tasks-list');
                
                console.log('🔍 Debug DOM:');
                console.log('- Botões (+) encontrados:', addButtons.length);
                console.log('- Cards de tarefas encontrados:', taskCards.length);
                console.log('- Listas de tarefas encontradas:', taskLists.length);
                
                if (addButtons.length === 0) {
                    console.log('⚠️ Nenhum botão .ql-add-task-btn encontrado!');
                    console.log('🔍 Procurando por elementos similares...');
                    
                    // Procurar por outros seletores possíveis
                    var altButtons = $('[class*="add"], [class*="btn"], button:contains("+")');
                    console.log('- Elementos alternativos encontrados:', altButtons.length);
                    altButtons.each(function(i, el) {
                        console.log('  -', i + 1, ':', el.className, el.textContent.trim());
                    });
                }
                
                if (taskCards.length === 0) {
                    console.log('⚠️ Nenhum card .ql-kanban-task encontrado!');
                    var altTasks = $('[class*="task"], [class*="card"], [data-task-id]');
                    console.log('- Elementos alternativos encontrados:', altTasks.length);
                    altTasks.each(function(i, el) {
                        console.log('  -', i + 1, ':', el.className);
                    });
                }
                
                // Remover eventos anteriores e adicionar novos
                $(document).off('click', '.ql-add-task-btn');
                $(document).off('click', '.ql-kanban-task');
                
                // Seletores de fallback
                var buttonSelectors = '.ql-add-task-btn, [data-column-id] button:contains("+"), .ql-column-header button, button[title*="Adicionar"], [class*="add"][class*="task"], [class*="add"][class*="btn"]';
                var taskSelectors = '.ql-kanban-task, [data-task-id], [class*="kanban"][class*="task"], [class*="task"][class*="card"]';
                
                // Event para botão + DESABILITADO - delegado ao QLTaskModals  
                // $(document).on('click', buttonSelectors, function(e) {
                //     e.preventDefault();
                //     e.stopPropagation();
                //     console.log('🎯 Botão + clicado!');
                //     
                //     var columnId = $(this).data('column-id') || $(this).closest('.ql-kanban-column').data('column-id');
                //     var columnName = $(this).closest('.ql-kanban-column').find('.ql-column-title').text();
                //     
                //     if (!columnId) {
                //         alert('Erro: Column ID não encontrado');
                //         return;
                //     }
                //     
                //     // Abrir modal de criação de tarefa
                //     openTaskCreationModal(columnId, columnName);
                // });
                
                // Event para cards de tarefas DESABILITADO - delegado ao QLTaskModals
                // $(document).on('click', taskSelectors, function(e) {
                //     e.preventDefault();
                //     e.stopPropagation();
                //     console.log('🎯 Card clicado!');
                    
                //     var taskId = $(this).data('task-id');
                //     var title = $(this).find('.ql-task-title, h4, h3').first().text();
                //     
                //     if (!taskId) {
                //         alert('Erro: Task ID não encontrado');
                //         return;
                //     }
                //     
                //     // Abrir modal de visualização de tarefa
                //     openTaskViewModal(taskId, title);
                // });
                
                // Drag and Drop DESABILITADO - gerenciado pelo kanban.js
                // if (typeof $.fn.sortable !== 'undefined') {
                //     $('.ql-tasks-list').sortable({
//                         connectWith: '.ql-tasks-list',
//                         items: '.ql-kanban-task',
//                         placeholder: 'ql-task-placeholder',
//                         tolerance: 'pointer',
//                         cursor: 'move',
//                         opacity: 0.8,
//                         distance: 5,
//                         delay: 100,
//                         
//                         start: function(event, ui) {
//                             ui.item.addClass('ql-dragging');
//                             ui.placeholder.height(ui.item.height());
//                             ui.placeholder.text('Solte aqui');
//                             
//                             // Destacar áreas de drop
//                             $('.ql-tasks-list').not(ui.item.parent()).addClass('ql-drop-target');
//                             
//                             console.log('🚀 Drag iniciado - Tarefa:', ui.item.data('task-id'));
//                         },
//                         
//                         stop: function(event, ui) {
//                             ui.item.removeClass('ql-dragging');
//                             $('.ql-tasks-list').removeClass('ql-drop-target');
//                             
//                             console.log('🛑 Drag finalizado');
//                         },
//                         
//                         over: function(event, ui) {
//                             $(this).addClass('ql-drop-active');
//                         },
//                         
//                         out: function(event, ui) {
//                             $(this).removeClass('ql-drop-active');
//                         },
//                         
//                         receive: function(event, ui) {
//                             var taskId = ui.item.data('task-id');
//                             var newColumnId = $(this).closest('.ql-kanban-column').data('column-id');
//                             var newPosition = ui.item.index();
//                             var columnName = $(this).closest('.ql-kanban-column').find('.ql-column-title').text();
//                             
//                             $(this).removeClass('ql-drop-active');
//                             
//                             console.log('📦 Movendo tarefa', taskId, 'para coluna', columnName, 'posição', newPosition);
//                             
//                             // Feedback visual imediato
//                             ui.item.addClass('ql-moving');
//                             
//                             $.ajax({
//                                 url: window.ql_admin.ajax_url,
//                                 method: 'POST',
//                                 data: {
//                                     action: 'ql_move_task',
//                                     task_id: taskId,
//                                     new_column_id: newColumnId,
//                                     new_position: newPosition,
//                                     nonce: window.ql_admin.nonce
//                                 },
//                                 success: function(response) {
//                                     ui.item.removeClass('ql-moving');
//                                     
//                                     if (response && response.success) {
//                                         console.log('✅ Tarefa movida com sucesso para', columnName);
//                                         
//                                         // Feedback visual de sucesso
//                                         ui.item.addClass('ql-move-success');
//                                         setTimeout(function() {
//                                             ui.item.removeClass('ql-move-success');
//                                         }, 2000);
//                                         
//                                         // Atualizar contadores das colunas
//                                         updateColumnCounters();
//                                         
//                                     } else {
//                                         console.error('❌ Erro ao mover tarefa:', response.message);
//                                         // Reverter movimento em caso de erro
//                                         $(ui.sender).sortable('cancel');
//                                         alert('Erro ao mover tarefa: ' + (response.message || 'Erro desconhecido'));
//                                     }
//                                 },
//                                 error: function(xhr, status, error) {
//                                     ui.item.removeClass('ql-moving');
//                                     console.error('❌ Erro de conexão ao mover tarefa:', error);
//                                     
//                                     // Reverter movimento
//                                     $(ui.sender).sortable('cancel');
//                                     alert('Erro de conexão ao mover tarefa. Página será recarregada.');
//                                     location.reload();
//                                 }
//                             });
//                         },
//                         
//                         update: function(event, ui) {
//                             // Atualizar posições dentro da mesma coluna
//                             if (!ui.sender) {
//                                 var taskId = ui.item.data('task-id');
//                                 var columnId = $(this).closest('.ql-kanban-column').data('column-id');
//                                 var newPosition = ui.item.index();
//                                 
//                                 console.log('🔄 Reordenando tarefa', taskId, 'na mesma coluna, nova posição:', newPosition);
//                                 
//                                 $.ajax({
//                                     url: window.ql_admin.ajax_url,
//                                     method: 'POST',
//                                     data: {
//                                         action: 'ql_move_task',
//                                         task_id: taskId,
//                                         new_column_id: columnId,
//                                         new_position: newPosition,
//                                         nonce: window.ql_admin.nonce
//                                     },
//                                     success: function(response) {
//                                         if (response && response.success) {
//                                             console.log('✅ Posição atualizada');
//                                         }
//                                     }
//                                 });
//                             }
//                         }
//                     });
//                     
//                     // Função para atualizar contadores
//                     function updateColumnCounters() {
//                         $('.ql-kanban-column').each(function() {
//                             var taskCount = $(this).find('.ql-kanban-task').length;
//                             $(this).find('.ql-column-count').text(taskCount);
//                         });
//                     }
//                     
//                     console.log('✅ Drag & Drop avançado inicializado');
//                 } else {
//                     console.log('❌ jQuery UI Sortable não disponível');
//                 }
                
                // ADICIONAR EVENTOS ESC E CLICK FORA
                console.log('🔧 Adicionando eventos ESC e click fora...');
                
                // Evento ESC para fechar modal
                $(document).off('keydown.qlmodal').on('keydown.qlmodal', function(e) {
                    if (e.keyCode === 27) {
                        var $modals = $('#ql-task-creation-modal, #ql-task-view-modal');
                        if ($modals.length > 0) {
                            console.log('🔑 ESC PRESSIONADO - fechando modal');
                            window.closeTaskModal();
                        }
                    }
                });
                
                // Evento click fora do modal
                $(document).off('click.qlmodal').on('click.qlmodal', '#ql-task-creation-modal, #ql-task-view-modal', function(e) {
                    if (e.target === this) {
                        console.log('🖱️ CLICK FORA DO MODAL - fechando');
                        window.closeTaskModal();
                    }
                });
                
                console.log('✅ EVENTOS ESC E CLICK FORA ADICIONADOS!');
                console.log('✅ Correção inline aplicada com sucesso!');
            }
            
            // Tentar aplicar imediatamente
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function() {
                    setTimeout(forceKanbanFix, 100);
                });
            }
            
            // Backup: tentar múltiplas vezes
            var attempts = 0;
            var retryInterval = setInterval(function() {
                attempts++;
                if (typeof jQuery !== 'undefined') {
                    clearInterval(retryInterval);
                    jQuery(document).ready(function() {
                        setTimeout(forceKanbanFix, 100);
                        // Tentar novamente após 2 segundos caso elementos não estejam prontos
                        setTimeout(forceKanbanFix, 2000);
                        // E mais uma vez após 5 segundos
                        setTimeout(forceKanbanFix, 5000);
                    });
                } else if (attempts > 50) {
                    clearInterval(retryInterval);
                    console.error('❌ jQuery não carregou após 50 tentativas');
                }
            }, 100);
            
            // Força aplicação quando página estiver completamente carregada
            if (typeof jQuery !== 'undefined') {
                jQuery(window).on('load', function() {
                    setTimeout(forceKanbanFix, 500);
                });
            }
        })();
        
        // Funções para modais reais - com proteção jQuery
        window.openTaskCreationModal = function(columnId, columnName) {
            // Garantir que jQuery está disponível
            if (typeof jQuery === 'undefined') {
                console.error('jQuery não está disponível para openTaskCreationModal');
                return;
            }
            var $ = jQuery;
            
            // Remover modal anterior se existir
            $('#ql-task-creation-modal').remove();
            
            var modalHtml = `
                <div id="ql-task-creation-modal" style="
                    position: fixed; 
                    top: 0; left: 0; 
                    width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.7); 
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white; 
                        padding: 0; 
                        border-radius: 8px; 
                        width: 90%; 
                        max-width: 600px;
                        max-height: 90vh;
                        overflow: hidden;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    ">
                        <div style="
                            background: #f8f9fa;
                            padding: 20px;
                            border-bottom: 1px solid #e9ecef;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <h3 style="margin: 0; color: #2c3e50;">Nova Tarefa - ${columnName}</h3>
                            <button onclick="closeTaskModal()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                                color: #6c757d;
                                padding: 0;
                                width: 30px;
                                height: 30px;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            " onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='none'">×</button>
                        </div>
                        
                        <div style="padding: 20px; max-height: 60vh; overflow-y: auto;">
                            <form id="task-creation-form">
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                        Título da Tarefa *
                                    </label>
                                    <input type="text" id="task-title" style="
                                        width: 100%; 
                                        padding: 12px; 
                                        border: 2px solid #e9ecef; 
                                        border-radius: 6px;
                                        font-size: 16px;
                                        transition: border-color 0.3s;
                                        box-sizing: border-box;
                                    " required placeholder="Ex: Implementar funcionalidade X">
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                        Descrição
                                    </label>
                                    <textarea id="task-description" style="
                                        width: 100%; 
                                        padding: 12px; 
                                        border: 2px solid #e9ecef; 
                                        border-radius: 6px;
                                        font-size: 14px;
                                        transition: border-color 0.3s;
                                        box-sizing: border-box;
                                        resize: vertical;
                                        min-height: 100px;
                                    " rows="4" placeholder="Descreva os detalhes da tarefa (opcional)"></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                            Prioridade
                                        </label>
                                        <select id="task-priority" style="
                                            width: 100%; 
                                            padding: 12px; 
                                            border: 2px solid #e9ecef; 
                                            border-radius: 6px;
                                            font-size: 14px;
                                            background: white;
                                            box-sizing: border-box;
                                        ">
                                            <option value="low">🔽 Baixa</option>
                                            <option value="normal" selected>➖ Normal</option>
                                            <option value="high">🔼 Alta</option>
                                            <option value="urgent">🚨 Urgente</option>
                                        </select>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                            Responsável
                                        </label>
                                        <select id="task-assignee" style="
                                            width: 100%; 
                                            padding: 12px; 
                                            border: 2px solid #e9ecef; 
                                            border-radius: 6px;
                                            font-size: 14px;
                                            background: white;
                                            box-sizing: border-box;
                                        ">
                                            <option value="">Não atribuído</option>
                                            <?php foreach ($project_users as $user): ?>
                                            <option value="<?php echo $user->ID; ?>"><?php echo esc_html($user->display_name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 20px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">
                                        Data Limite
                                    </label>
                                    <input type="date" id="task-due-date" style="
                                        width: 100%; 
                                        padding: 12px; 
                                        border: 2px solid #e9ecef; 
                                        border-radius: 6px;
                                        font-size: 14px;
                                        box-sizing: border-box;
                                    ">
                                </div>
                            </form>
                        </div>
                        
                        <div style="
                            background: #f8f9fa;
                            padding: 20px;
                            border-top: 1px solid #e9ecef;
                            display: flex;
                            justify-content: flex-end;
                            gap: 12px;
                        ">
                            <button type="button" onclick="closeTaskModal()" style="
                                padding: 12px 24px; 
                                border: 2px solid #6c757d; 
                                background: white; 
                                color: #6c757d;
                                border-radius: 6px;
                                font-size: 14px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.3s;
                            " onmouseover="this.style.background='#6c757d'; this.style.color='white'" 
                               onmouseout="this.style.background='white'; this.style.color='#6c757d'">
                                Cancelar
                            </button>
                            <button type="button" onclick="createTask(${columnId})" style="
                                padding: 12px 24px; 
                                border: none; 
                                background: #007cba; 
                                color: white; 
                                border-radius: 6px;
                                font-size: 14px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.3s;
                            " onmouseover="this.style.background='#005a87'" 
                               onmouseout="this.style.background='#007cba'">
                                Criar Tarefa
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            setTimeout(function() {
                $('#task-title').focus();
            }, 100);
            
            // Estilo de foco para campos
            $('#task-title, #task-description').on('focus', function() {
                $(this).css('border-color', '#007cba');
            }).on('blur', function() {
                $(this).css('border-color', '#e9ecef');
            });
        };
        
        window.openTaskViewModal = function(taskId, title) {
            // Garantir que jQuery está disponível
            if (typeof jQuery === 'undefined') {
                console.error('jQuery não está disponível para openTaskViewModal');
                return;
            }
            var $ = jQuery;
            
            console.log('📋 Carregando dados da tarefa:', taskId);
            
            // Carregar dados da tarefa via AJAX
            $.ajax({
                url: (typeof ql_admin !== 'undefined' ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php'),
                method: 'POST',
                data: {
                    action: 'ql_get_task_data',
                    task_id: taskId,
                    nonce: (typeof ql_admin !== 'undefined' ? ql_admin.nonce : '')
                },
                success: function(response) {
                    console.log('📋 Dados da tarefa carregados:', response);
                    if (response && response.success && response.data) {
                        showTaskViewModal(response.data);
                    } else {
                        alert('Erro ao carregar dados da tarefa: ' + (response.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro ao carregar tarefa:', error);
                    alert('Erro de conexão ao carregar tarefa: ' + error);
                }
            });
        };
        
        // Função para mostrar modal com dados da tarefa
        function showTaskViewModal(taskData) {
            var $ = jQuery;
            
            // Remover modal anterior se existir
            $('#ql-task-view-modal').remove();
            
            var modalHtml = `
                <div id="ql-task-view-modal" style="
                    position: fixed; 
                    top: 0; left: 0; 
                    width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.7); 
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <div style="
                        background: white; 
                        padding: 0; 
                        border-radius: 8px; 
                        width: 90%; 
                        max-width: 800px;
                        max-height: 90vh;
                        overflow: hidden;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    ">
                        <div style="
                            background: #f8f9fa;
                            padding: 20px;
                            border-bottom: 1px solid #e9ecef;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        ">
                            <div>
                                <h3 style="margin: 0 0 5px 0; color: #2c3e50;">${taskData.title || 'Sem título'}</h3>
                                <span style="color: #6c757d; font-size: 14px;">ID: ${taskData.id} | Status: ${taskData.status || 'open'} | Prioridade: ${taskData.priority || 'normal'}</span>
                            </div>
                            <button onclick="closeTaskModal()" style="
                                background: none;
                                border: none;
                                font-size: 24px;
                                cursor: pointer;
                                color: #6c757d;
                                padding: 0;
                                width: 30px;
                                height: 30px;
                                border-radius: 50%;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            " onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='none'">×</button>
                        </div>
                        
                        <div style="padding: 20px; max-height: 60vh; overflow-y: auto;">
                            <!-- Título editável -->
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Título</label>
                                <input type="text" id="edit-task-title" value="${taskData.title || ''}" style="
                                    width: 100%; 
                                    padding: 10px; 
                                    border: 1px solid #ddd; 
                                    border-radius: 4px;
                                    font-size: 16px;
                                ">
                            </div>
                            
                            <!-- Descrição editável -->
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Descrição</label>
                                <textarea id="edit-task-description" style="
                                    width: 100%; 
                                    padding: 10px; 
                                    border: 1px solid #ddd; 
                                    border-radius: 4px;
                                    min-height: 100px;
                                    resize: vertical;
                                " placeholder="Descreva a tarefa...">${taskData.description || ''}</textarea>
                            </div>
                            
                            <!-- Status e Prioridade -->
                            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                                <div style="flex: 1;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Status</label>
                                    <select id="edit-task-status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="open" ${taskData.status === 'open' ? 'selected' : ''}>Aberta</option>
                                        <option value="in_progress" ${taskData.status === 'in_progress' ? 'selected' : ''}>Em Progresso</option>
                                        <option value="review" ${taskData.status === 'review' ? 'selected' : ''}>Em Revisão</option>
                                        <option value="completed" ${taskData.status === 'completed' ? 'selected' : ''}>Concluída</option>
                                        <option value="cancelled" ${taskData.status === 'cancelled' ? 'selected' : ''}>Cancelada</option>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Prioridade</label>
                                    <select id="edit-task-priority" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="low" ${taskData.priority === 'low' ? 'selected' : ''}>Baixa</option>
                                        <option value="normal" ${taskData.priority === 'normal' ? 'selected' : ''}>Normal</option>
                                        <option value="high" ${taskData.priority === 'high' ? 'selected' : ''}>Alta</option>
                                        <option value="urgent" ${taskData.priority === 'urgent' ? 'selected' : ''}>Urgente</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Informações de sistema -->
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                                <h4 style="margin: 0 0 10px 0; color: #6c757d; font-size: 14px;">Informações do Sistema</h4>
                                <div style="font-size: 13px; color: #6c757d;">
                                    <p style="margin: 5px 0;"><strong>Criada em:</strong> ${taskData.created_at || 'Não informado'}</p>
                                    <p style="margin: 5px 0;"><strong>Atualizada em:</strong> ${taskData.updated_at || 'Não informado'}</p>
                                    <p style="margin: 5px 0;"><strong>Criador:</strong> ${taskData.creator_name || 'Não informado'}</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Rodapé com botões -->
                        <div style="
                            padding: 20px; 
                            border-top: 1px solid #e9ecef; 
                            display: flex; 
                            justify-content: space-between; 
                            align-items: center;
                        ">
                            <button onclick="deleteTask(${taskData.id})" style="
                                padding: 8px 16px; 
                                border: none; 
                                background: #dc3545; 
                                color: white; 
                                border-radius: 4px;
                                cursor: pointer;
                            ">🗑️ Excluir</button>
                            
                            <div>
                                <button onclick="window.closeTaskModal()" style="
                                    padding: 8px 16px; 
                                    border: 1px solid #ddd; 
                                    background: #f8f9fa; 
                                    color: #495057; 
                                    border-radius: 4px;
                                    cursor: pointer;
                                    margin-right: 10px;
                                ">Cancelar</button>
                                <button onclick="saveTaskChanges(${taskData.id})" style="
                                    padding: 8px 16px; 
                                    border: none; 
                                    background: #007cba; 
                                    color: white; 
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">💾 Salvar Alterações</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
        };
        
        window.closeTaskModal = function() {
            // Garantir que jQuery está disponível
            if (typeof jQuery === 'undefined') {
                console.error('jQuery não está disponível para closeTaskModal');
                return;
            }
            var $ = jQuery;
            
            $('#ql-task-creation-modal, #ql-task-view-modal').remove();
        };
        
        window.createTask = function(columnId) {
            // Garantir que jQuery está disponível
            if (typeof jQuery === 'undefined') {
                console.error('jQuery não está disponível para createTask');
                return;
            }
            var $ = jQuery;
            
            var title = $('#task-title').val().trim();
            if (!title) {
                alert('Título é obrigatório');
                $('#task-title').focus();
                return;
            }
            
            var taskData = {
                action: 'ql_create_task',
                title: title,
                description: $('#task-description').val().trim(),
                column_id: columnId,
                priority: $('#task-priority').val(),
                assigned_user_id: $('#task-assignee').val() || null,
                due_date: $('#task-due-date').val() || null,
                nonce: window.ql_admin.nonce
            };
            
            // Desabilitar botão durante criação
            var btn = event.target;
            var originalText = btn.textContent;
            btn.textContent = 'Criando...';
            btn.disabled = true;
            
            $.ajax({
                url: window.ql_admin.ajax_url,
                method: 'POST',
                data: taskData,
                success: function(response) {
                    if (response && response.success) {
                        closeTaskModal();
                        location.reload(); // Recarregar para ver a nova tarefa
                    } else {
                        alert('Erro ao criar tarefa: ' + (response.message || 'Erro desconhecido'));
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                },
                error: function(xhr, status, error) {
                    alert('Erro de conexão: ' + error);
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            });
        };
        
        window.editTask = function(taskId) {
            console.log('🎯 QL DEBUG: editTask chamado para ID:', taskId);
            
            // Simular clique no card da tarefa para abrir o modal
            var $taskCard = $('.ql-kanban-task[data-task-id="' + taskId + '"]');
            if ($taskCard.length > 0) {
                console.log('📋 QL DEBUG: Card da tarefa encontrado, simulando clique...');
                $taskCard.trigger('click');
            } else {
                console.warn('⚠️ QL DEBUG: Card da tarefa não encontrado. Tentando modal direto...');
                // Se o QLTaskModals estiver disponível, tentar usar diretamente
                if (typeof QLTaskModals !== 'undefined') {
                    // Criar evento simulado para o modal
                    var mockEvent = {
                        preventDefault: function() {},
                        stopPropagation: function() {},
                        currentTarget: { dataset: { taskId: taskId } }
                    };
                    if (QLTaskModals.openTaskModal) {
                        QLTaskModals.openTaskModal(mockEvent);
                    }
                } else {
                    alert('Sistema de modais não carregado. Recarregue a página.');
                }
            }
        };
        
        window.deleteTask = function(taskId) {
            console.log('🗑️ QL DEBUG: deleteTask chamado para ID:', taskId);
            
            if (confirm('Tem certeza que deseja excluir esta tarefa?')) {
                // Fazer requisição AJAX para deletar a tarefa
                $.ajax({
                    url: ql_admin.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'ql_delete_task',
                        task_id: taskId,
                        nonce: ql_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remover o card da interface
                            $('.ql-kanban-task[data-task-id="' + taskId + '"]').fadeOut(300, function() {
                                $(this).remove();
                            });
                            console.log('✅ QL DEBUG: Tarefa excluída com sucesso');
                        } else {
                            alert('Erro ao excluir tarefa: ' + (response.data || 'Erro desconhecido'));
                            console.error('❌ QL DEBUG: Erro ao excluir tarefa:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Erro na comunicação com o servidor.');
                        console.error('❌ QL DEBUG: Erro AJAX:', error);
                    }
                });
            }
        };
        
        // Fechar modal com ESC
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) {
                closeTaskModal();
            }
        });
        
        // Fechar modal clicando fora
        $(document).on('click', '#ql-task-creation-modal, #ql-task-view-modal', function(e) {
            if (e.target.id === 'ql-task-creation-modal' || e.target.id === 'ql-task-view-modal') {
                closeTaskModal();
            }
        });
        
        // Funções de debug (manter para desenvolvimento se necessário)
        window.debugKanban = function() {
            console.log('🔍 Debug Kanban:', {
                jQuery: typeof jQuery !== 'undefined',
                jQueryUI: typeof jQuery !== 'undefined' && typeof jQuery.fn.sortable !== 'undefined',
                addButtons: jQuery('.ql-add-task-btn').length,
                tasks: jQuery('.ql-kanban-task').length,
                taskLists: jQuery('.ql-tasks-list').length
            });
        };
        </script>
        
        <script>
        // Configuração global do QLKanban
        window.ql_admin = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>',
            rest_url: '<?php echo rest_url('quilombo-lab/v1/'); ?>',
            rest_nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
            board_id: <?php echo $board_id; ?>,
            default_board_id: <?php echo intval(QL_Config::get('general', 'default_board_id', 0)); ?>,
            strings: {
                task_moved: 'Tarefa movida com sucesso!',
                error_moving: 'Erro ao mover tarefa',
                task_created: 'Tarefa criada com sucesso!',
                task_updated: 'Tarefa atualizada com sucesso!',
                confirm_delete: 'Tem certeza de que deseja excluir esta tarefa?'
            }
        };
        
        // Debug das variáveis
        console.log('📊 Variáveis ql_admin:', window.ql_admin);

        // Carregar usuários no modal quando abrir
        jQuery(document).ready(function($) {
            // Preencher dropdown de usuários
            var userOptions = '<option value="">Não atribuído</option>';
            <?php foreach ($project_users as $user): ?>
                userOptions += '<option value="<?php echo $user->ID; ?>"><?php echo esc_js($user->display_name); ?></option>';
            <?php endforeach; ?>
            
            $(document).on('qlModalReady', function() {
                $('#task-assigned').html(userOptions);
            });
            
            // Inicializar QLKanban se disponível
            if (typeof QLKanban !== 'undefined') {
                QLKanban.init();
                console.log('✅ QLKanban inicializado com drag & drop');
                
                // Event handler para cliques em tarefas removido - agora gerenciado pelo task-modals.js
                // Isso evita conflitos entre múltiplos event handlers
                
            } else {
                console.warn('⚠️ QLKanban não encontrado. Tentando carregar novamente...');
                
                // Tentar carregar QLKanban novamente após um delay
                setTimeout(function() {
                    if (typeof QLKanban !== 'undefined') {
                        console.log('✅ QLKanban carregado com sucesso após delay');
                        QLKanban.init();
                    } else {
                        console.error('❌ QLKanban não pôde ser carregado');
                    }
                }, 1000);
            }
            
            // Trigger evento quando modal for criado
            setTimeout(function() {
                $(document).trigger('qlModalReady');
            }, 500);
        });
        </script>
        
        <?php
        // Incluir templates de modais de tarefas
        include_once QL_PLUGIN_PATH . 'templates/task-modals.php';
        ?>
        
        <!-- Sistema de Debug e Correção Imediata -->
        <script>
        jQuery(document).ready(function($) {
            
            console.log('🚀 QL DEBUG: Sistema iniciando...');
            
            // Função para diagnosticar a página
            function diagnosticarPagina() {
                console.log('🔍 QL DEBUG: Iniciando diagnóstico...');
                
                // Verificar elementos na página
                var addButtons = $('.ql-add-task-btn');
                var taskCards = $('.ql-kanban-task');
                
                console.log('📊 QL DEBUG: Elementos encontrados:');
                console.log('   - Botões Adicionar: ' + addButtons.length);
                console.log('   - Cards de Tarefa: ' + taskCards.length);
                
                if (addButtons.length > 0) {
                    console.log('   - Primeiro botão data-column-id:', addButtons.first().data('column-id'));
                } else {
                    console.error('❌ QL DEBUG: Nenhum botão .ql-add-task-btn encontrado!');
                }
                
                if (taskCards.length > 0) {
                    console.log('   - Primeiro card data-task-id:', taskCards.first().data('task-id'));
                } else {
                    console.warn('⚠️ QL DEBUG: Nenhum card .ql-kanban-task encontrado');
                }
                
                // Verificar scripts
                console.log('📝 QL DEBUG: Status dos scripts:');
                console.log('   - jQuery:', typeof $ !== 'undefined' ? '✅ v' + $.fn.jquery : '❌ Não encontrado');
                console.log('   - QLTaskModals:', typeof QLTaskModals !== 'undefined' ? '✅ Disponível' : '❌ Não encontrado');
                console.log('   - QLKanban:', typeof QLKanban !== 'undefined' ? '✅ Disponível' : '❌ Não encontrado');
                
                // Verificar event handlers existentes
                var events = $._data(document, 'events');
                if (events && events.click) {
                    console.log('📋 QL DEBUG: Event handlers de click encontrados:', events.click.length);
                } else {
                    console.warn('⚠️ QL DEBUG: Nenhum event handler de click no document');
                }
                
                return {
                    addButtons: addButtons.length,
                    taskCards: taskCards.length,
                    hasQLTaskModals: typeof QLTaskModals !== 'undefined',
                    hasJQuery: typeof $ !== 'undefined'
                };
            }
            
            // Executar diagnóstico inicial
            var status = diagnosticarPagina();
            
            // DESABILITADO - Conflitando com QLTaskModals
            function instalarEventHandlersDirectos_DESABILITADO() {
                console.log('🔧 QL DEBUG: Instalando event handlers diretos...');
                
                // Remover todos os handlers existentes primeiro
                $(document).off('click.ql_direct');
                $('.ql-add-task-btn, .ql-kanban-task').off('click.ql_direct');
                
                // Handler DIRETO para botões (não via delegação)
                // $('.ql-add-task-btn').on('click.ql_direct', function(e) {
//                     e.preventDefault();
//                     e.stopImmediatePropagation();
//                     
//                     console.log('🎯 QL DEBUG: CLIQUE DIRETO detectado em botão adicionar!');
//                     console.log('   - Element:', this);
//                     console.log('   - Column ID:', $(this).data('column-id'));
//                     
//                     var columnId = $(this).data('column-id');
//                     var columnName = $(this).closest('.ql-kanban-column').find('.ql-column-title').text() || 'Coluna';
//                     
//                     var title = prompt('✨ NOVA TAREFA ✨\n\nColuna: ' + columnName + '\n\nDigite o título da tarefa:');
//                     
//                     if (title && title.trim()) {
//                         var $button = $(this);
//                         var originalText = $button.text();
//                         
//                         // Mostrar loading
//                         $button.text('Criando...').prop('disabled', true);
//                         
//                         console.log('📡 QL DEBUG: Enviando AJAX para criar tarefa...');
//                         
//                         // Tentar criar via AJAX primeiro
//                         $.ajax({
//                             url: '<?php echo admin_url('admin-ajax.php'); ?>',
//                             method: 'POST',
//                             data: {
//                                 action: 'ql_create_task',
//                                 title: title.trim(),
//                                 column_id: columnId,
//                                 description: 'Tarefa criada via interface',
//                                 priority: 'normal',
//                                 nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
//                             },
//                             success: function(response) {
//                                 console.log('✅ QL DEBUG: Resposta AJAX recebida:', response);
//                                 
//                                 $button.text(originalText).prop('disabled', false);
//                                 
//                                 if (response && response.success && response.data && response.data.task) {
//                                     // Sucesso total - usar dados reais do servidor
//                                     var task = response.data.task;
//                                     var realTaskHtml = '<div class="ql-kanban-task ql-color-blue" data-task-id="' + task.id + '" data-priority="' + (task.priority || 'normal') + '">' +
//                                         '<div class="ql-task-header">' +
//                                         '<div class="ql-task-ref-container">' +
//                                         '<span class="ql-task-ref">#' + task.id + '</span>' +
//                                         '</div>' +
//                                         '<div class="ql-task-priority">' +
//                                         '<span class="ql-priority-badge ql-priority-normal">Normal</span>' +
//                                         '</div>' +
//                                         '<div class="ql-task-menu">⋮</div>' +
//                                         '</div>' +
//                                         '<div class="ql-task-content">' +
//                                         '<h4 class="ql-task-title">' + task.title + '</h4>' +
//                                         '<p class="ql-task-description">' + (task.description || 'Nova tarefa') + '</p>' +
//                                         '</div>' +
//                                         '<div class="ql-task-footer">' +
//                                         '<div class="ql-task-meta">' +
//                                         '<span class="ql-task-date">Agora</span>' +
//                                         '</div>' +
//                                         '</div>' +
//                                         '</div>';
//                                     
//                                     $button.closest('.ql-kanban-column').find('.ql-tasks-list').append(realTaskHtml);
//                                     alert('✅ Tarefa "' + title + '" criada com sucesso!\n\nID: #' + task.id);
//                                     console.log('✅ QL DEBUG: Tarefa real criada e adicionada');
//                                 } else {
//                                     // AJAX funcionou mas resposta não foi sucesso - fallback visual
//                                     console.warn('⚠️ QL DEBUG: AJAX funcionou mas resposta indica erro:', response);
//                                     criarTarefaVisual(title, columnId, $button);
//                                     alert('⚠️ Tarefa criada localmente.\n\nMotivo: ' + (response.message || 'Erro na criação no servidor') + '\n\nRecarregue a página para sincronizar.');
//                                 }
//                             },
//                             error: function(xhr, status, error) {
//                                 console.error('❌ QL DEBUG: Erro AJAX:', {status: status, error: error, responseText: xhr.responseText});
//                                 
//                                 $button.text(originalText).prop('disabled', false);
//                                 
//                                 // Fallback visual em caso de erro total
//                                 criarTarefaVisual(title, columnId, $button);
//                                 alert('🔌 Modo offline!\n\nTarefa "' + title + '" criada localmente.\n\nRecarregue quando a conexão for restaurada.\n\nErro: ' + error);
//                             }
//                         });
//                         
//                         // Função auxiliar para criar tarefa visual
//                         function criarTarefaVisual(title, columnId, $button) {
//                             var taskId = 'temp-' + Date.now();
//                             var visualTaskHtml = '<div class="ql-kanban-task ql-color-blue" data-task-id="' + taskId + '" data-priority="normal" style="border: 2px dashed #ffc107; background: rgba(255, 243, 205, 0.3);">' +
//                                 '<div class="ql-task-header">' +
//                                 '<div class="ql-task-ref-container">' +
//                                 '<span class="ql-task-ref">#TEMP</span>' +
//                                 '</div>' +
//                                 '<div class="ql-task-priority">' +
//                                 '<span class="ql-priority-badge ql-priority-normal">Normal</span>' +
//                                 '</div>' +
//                                 '<div class="ql-task-menu">⋮</div>' +
//                                 '</div>' +
//                                 '<div class="ql-task-content">' +
//                                 '<h4 class="ql-task-title">' + title + '</h4>' +
//                                 '<p class="ql-task-description">⚠️ Tarefa temporária - recarregue a página</p>' +
//                                 '</div>' +
//                                 '<div class="ql-task-footer">' +
//                                 '<div class="ql-task-meta">' +
//                                 '<span class="ql-task-date">Agora (temp)</span>' +
//                                 '</div>' +
//                                 '</div>' +
//                                 '</div>';
//                             
//                             $button.closest('.ql-kanban-column').find('.ql-tasks-list').append(visualTaskHtml);
//                             console.log('⚠️ QL DEBUG: Tarefa visual temporária criada');
//                         }
//                     } else {
//                         console.log('❌ QL DEBUG: Usuário cancelou ou não digitou título');
//                     }
//                 });
                
                // Handler DELEGADO para cards (funciona com elementos dinâmicos)
                // $(document).off('click.ql_task_cards').on('click.ql_task_cards', '.ql-kanban-task', function(e) {
//                     e.preventDefault();
//                     e.stopImmediatePropagation();
//                     
//                     console.log('🎯 QL DEBUG: CLIQUE DELEGADO detectado em card de tarefa!');
//                     console.log('   - Element:', this);
//                     console.log('   - Task ID:', $(this).data('task-id'));
//                     
//                     var taskId = $(this).data('task-id');
//                     var taskTitle = $(this).find('.ql-task-title').text() || $(this).find('h4').text() || $(this).text().substring(0, 50);
//                     
//                     // Abrir modal de tarefa usando QLTaskModals
//                     if (typeof QLTaskModals !== 'undefined' && QLTaskModals.openTaskModal) {
//                         console.log('🎭 QL DEBUG: Abrindo modal QLTaskModals para tarefa', taskId);
//                         
//                         // Criar evento mock para o QLTaskModals
//                         var mockEvent = {
//                             preventDefault: function() {},
//                             stopPropagation: function() {},
//                             currentTarget: this
//                         };
//                         
//                         QLTaskModals.openTaskModal(mockEvent);
//                     } else {
//                         console.warn('⚠️ QL DEBUG: QLTaskModals não disponível, usando editTask');
//                         
//                         // Fallback para editTask se QLTaskModals não estiver disponível
//                         if (typeof editTask === 'function') {
//                             editTask(taskId);
//                         } else {
//                             alert('Sistema de modais não carregado. Recarregue a página.');
//                         }
//                     }
//                     
//                     console.log('✅ QL DEBUG: Processamento de clique em tarefa concluído');
//                 });
//                 
//                 console.log('✅ QL DEBUG: Event handlers diretos instalados');
//                 console.log('   - Botões com handler:', $('.ql-add-task-btn').length);
//                 console.log('   - Cards com handler:', $('.ql-kanban-task').length);
//             }
            
            // Aguardar um pouco e verificar sistema
            setTimeout(function() {
                console.log('✅ QL DEBUG: Sistema completamente configurado');
                console.log('✅ QL DEBUG: Sistema limpo e funcionando');
                
                // QLTaskModals se inicializa automaticamente no próprio arquivo
                console.log('🎭 QL DEBUG: QLTaskModals deveria estar disponível:', typeof QLTaskModals !== 'undefined');
                
                // Verificar se os elementos existem mas não instalar handlers conflitantes
                var addButtons = $('.ql-add-task-btn');
                var taskCards = $('.ql-kanban-task');
                console.log('🔍 QL DEBUG: Elementos na página:');
                console.log('   - Botões de adicionar:', addButtons.length);
                console.log('   - Cards de tarefa:', taskCards.length);
                
            }, 2000);
            
        });
        </script>
        <?php
    }
    
    /**
     * Dashboard Unificado - Visão geral de todas as ferramentas
     */
    public function unified_dashboard_page() {
        global $wpdb;
        
        // Buscar dados consolidados
        $current_user_id = get_current_user_id();
        
        // Verificar quais tabelas existem
        $tables_exist = [
            'ql_projects' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ql_projects'") === $wpdb->prefix . 'ql_projects',
            'ql_tasks' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ql_tasks'") === $wpdb->prefix . 'ql_tasks',
            'ql_moodle_mappings' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ql_moodle_mappings'") === $wpdb->prefix . 'ql_moodle_mappings',
            'gc_projetos' => $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}gc_projetos'") === $wpdb->prefix . 'gc_projetos',
        ];
        
        // Estatísticas gerais com verificação de tabelas
        $stats = [
            'total_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}users"),
            'total_projects' => $tables_exist['ql_projects'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects WHERE status = 'active'") : 0,
            'total_tasks' => $tables_exist['ql_tasks'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE status != 'cancelled'") : 0,
            'completed_tasks' => $tables_exist['ql_tasks'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE status = 'completed'") : 0,
            'gc_projects' => $tables_exist['gc_projetos'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gc_projetos WHERE status = 'ativo'") : 0,
            'moodle_mappings' => $tables_exist['ql_moodle_mappings'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_moodle_mappings") : 0,
            'collective_projects' => $tables_exist['ql_moodle_mappings'] ? $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_moodle_mappings WHERE is_collective = 1") : 0
        ];
        
        // Obter dados de logs e saúde do sistema
        $system_health = class_exists('QL_Integration_Logs') ? QL_Integration_Logs::get_system_health() : null;
        $recent_logs = class_exists('QL_Integration_Logs') ? QL_Integration_Logs::get_recent_logs(20) : [];
        $error_summary = class_exists('QL_Integration_Logs') ? QL_Integration_Logs::get_error_summary(24) : [];
        
        // Verificar se tabela SSO existe
        $sso_table = $wpdb->prefix . 'ql_sso_sessions';
        if ($wpdb->get_var("SHOW TABLES LIKE '$sso_table'") == $sso_table) {
            $stats['active_sso_sessions'] = $wpdb->get_var("SELECT COUNT(*) FROM $sso_table WHERE is_active = 1 AND expires_at > NOW()");
        }
        
        // Projetos do usuário atual
        $user_projects = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT p.id, p.name, p.status, pm.role 
            FROM {$wpdb->prefix}ql_projects p
            LEFT JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id 
            WHERE pm.user_id = %d OR p.owner_id = %d
            ORDER BY p.name
        ", $current_user_id, $current_user_id));
        
        // Tarefas do usuário atual
        $user_tasks = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, p.name as project_name 
            FROM {$wpdb->prefix}ql_tasks t
            JOIN {$wpdb->prefix}ql_projects p ON t.project_id = p.id
            WHERE t.assigned_user_id = %d AND t.status != 'completed' AND t.status != 'cancelled'
            ORDER BY t.due_date ASC, t.priority DESC
            LIMIT 10
        ", $current_user_id));
        
        // Integrações ativas
        $integrations = [
            'moodle' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_moodle_integrations WHERE sync_status = 'active'"),
            'gc_sync' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}gc_lab_sync_mappings WHERE sync_status = 'active'"),
            'user_sync' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_user_mappings WHERE sync_status = 'synced'")
        ];
        
        // Verificar mapeamento do usuário atual
        $user_mapping = null;
        if (class_exists('QL_User_Sync')) {
            $user_sync = QL_User_Sync::get_instance();
            $user_mapping = $user_sync->get_user_mapping($current_user_id);
        }
        
        ?>
        <div class="wrap">
            <h1>🎯 <?php _e('Dashboard Unificado', 'quilombo-lab'); ?></h1>
            <p class="description">
                <?php _e('Visão geral integrada do ecossistema Quilombo Ciência: Moodle ↔ WordPress com gestão de projetos nativa', 'quilombo-lab'); ?>
            </p>
            
            <!-- Cards de Estatísticas -->
            <div class="ql-dashboard-stats">
                <div class="ql-stat-card">
                    <div class="ql-stat-icon">👥</div>
                    <div class="ql-stat-content">
                        <h3><?php echo number_format($stats['total_users']); ?></h3>
                        <p>Usuários WordPress</p>
                        <small>Sistema unificado</small>
                    </div>
                </div>
                
                <div class="ql-stat-card">
                    <div class="ql-stat-icon">📋</div>
                    <div class="ql-stat-content">
                        <h3><?php echo number_format($stats['total_projects']); ?></h3>
                        <p>Projetos QL</p>
                        <small><?php echo $stats['moodle_mappings']; ?> importados do Moodle</small>
                    </div>
                </div>
                
                <div class="ql-stat-card">
                    <div class="ql-stat-icon">✅</div>
                    <div class="ql-stat-content">
                        <h3><?php echo number_format($stats['completed_tasks']); ?>/<?php echo number_format($stats['total_tasks']); ?></h3>
                        <p>Tarefas Concluídas</p>
                        <small><?php echo $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0; ?>% completo</small>
                    </div>
                </div>
                
                <div class="ql-stat-card">
                    <div class="ql-stat-icon">🎯</div>
                    <div class="ql-stat-content">
                        <h3><?php echo $stats['collective_projects']; ?></h3>
                        <p>Trilhas Coletivas</p>
                        <small><?php echo $tables_exist['gc_projetos'] ? $stats['gc_projects'] : 0; ?> com integração GC</small>
                    </div>
                </div>
            </div>
            
            <!-- Status das Integrações -->
            <div class="ql-dashboard-integrations">
                <h2>🔗 Status das Integrações</h2>
                <div class="ql-integration-grid">
                    <div class="ql-integration-card">
                        <h3>🎓 Moodle → QL</h3>
                        <div class="ql-integration-status <?php echo $stats['moodle_mappings'] > 0 ? 'active' : 'inactive'; ?>">
                            <?php if ($stats['moodle_mappings'] > 0): ?>
                                ✅ <?php echo $stats['moodle_mappings']; ?> cursos sincronizados
                            <?php else: ?>
                                ⚠️ Nenhum curso importado
                            <?php endif; ?>
                        </div>
                        <p><small>
                            <?php if (class_exists('QL_Moodle_Integration')): ?>
                                ✅ Módulo de integração ativo
                            <?php else: ?>
                                ❌ Módulo de integração inativo
                            <?php endif; ?>
                        </small></p>
                    </div>
                    
                    <div class="ql-integration-card">
                        <h3>💰 Gestão Coletiva</h3>
                        <div class="ql-integration-status <?php echo $tables_exist['gc_projetos'] ? 'active' : 'inactive'; ?>">
                            <?php if ($tables_exist['gc_projetos']): ?>
                                ✅ <?php echo $stats['gc_projects']; ?> projetos GC disponíveis
                            <?php else: ?>
                                ⚠️ Plugin GC não detectado
                            <?php endif; ?>
                        </div>
                        <p><small>
                            <?php if (class_exists('GC_Projeto')): ?>
                                ✅ Integração financeira ativa
                            <?php else: ?>
                                📦 Funcionamento independente
                            <?php endif; ?>
                        </small></p>
                    </div>
                    
                    <div class="ql-integration-card">
                        <h3>📋 Laboratório de Projetos</h3>
                        <div class="ql-integration-status <?php echo $tables_exist['ql_projects'] ? 'active' : 'inactive'; ?>">
                            <?php if ($tables_exist['ql_projects']): ?>
                                ✅ Sistema nativo ativo
                            <?php else: ?>
                                ❌ Tabelas não encontradas
                            <?php endif; ?>
                        </div>
                        <p><small>WordPress nativo (sem Kanboard)</small></p>
                    </div>
                    
                    <div class="ql-integration-card">
                        <h3>🗃️ Banco de Dados</h3>
                        <div class="ql-integration-status active">
                            ✅ Estrutura v<?php echo defined('QL_DB_VERSION') ? QL_DB_VERSION : '1.0'; ?>
                        </div>
                        <p><small>
                            <?php 
                            $missing_tables = array_filter($tables_exist, function($exists) { return !$exists; });
                            if (empty($missing_tables)): ?>
                                ✅ Todas as tabelas criadas
                            <?php else: ?>
                                ⚠️ <?php echo count($missing_tables); ?> tabelas faltando
                            <?php endif; ?>
                        </small></p>
                    </div>
                </div>
            </div>
            
            <!-- Área de Trabalho do Usuário -->
            <div class="ql-dashboard-workspace">
                <div class="ql-workspace-left">
                    <h2>📋 Meus Projetos (<?php echo count($user_projects); ?>)</h2>
                    <?php if (!empty($user_projects)): ?>
                        <div class="ql-projects-list">
                            <?php foreach ($user_projects as $project): ?>
                                <div class="ql-project-item">
                                    <div class="ql-project-info">
                                        <h4><?php echo esc_html($project->name); ?></h4>
                                        <span class="ql-project-role"><?php echo esc_html($project->role ?: 'Membro'); ?></span>
                                        <span class="ql-project-status ql-status-<?php echo $project->status; ?>">
                                            <?php echo esc_html(ucfirst($project->status)); ?>
                                        </span>
                                    </div>
                                    <div class="ql-project-actions">
                                        <a href="<?php echo admin_url('admin.php?page=quilombo-lab-project-boards&project_id=' . $project->id); ?>" 
                                           class="button button-small">Ver Quadros</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="ql-empty-state">
                            📝 Você ainda não está atribuído a nenhum projeto.
                            <br><a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>">Ver todos os projetos</a>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="ql-workspace-right">
                    <h2>✅ Minhas Tarefas Pendentes (<?php echo count($user_tasks); ?>)</h2>
                    <?php if (!empty($user_tasks)): ?>
                        <div class="ql-tasks-list">
                            <?php foreach ($user_tasks as $task): ?>
                                <div class="ql-task-item ql-priority-<?php echo $task->priority; ?>">
                                    <div class="ql-task-info">
                                        <h4><?php echo esc_html($task->title); ?></h4>
                                        <p class="ql-task-project"><?php echo esc_html($task->project_name); ?></p>
                                        <?php if ($task->due_date): ?>
                                            <p class="ql-task-due <?php echo strtotime($task->due_date) < time() ? 'overdue' : ''; ?>">
                                                📅 <?php echo date_i18n('d/m/Y', strtotime($task->due_date)); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ql-task-status">
                                        <span class="ql-status-badge ql-status-<?php echo $task->status; ?>">
                                            <?php echo esc_html(ucfirst($task->status)); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>" class="button">
                            Ver Todas as Tarefas
                        </a>
                    <?php else: ?>
                        <p class="ql-empty-state">
                            🎉 Você não tem tarefas pendentes!
                            <br><a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>">Explorar projetos</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Nova Arquitetura Info -->
            <div class="ql-architecture-info">
                <h2>🏗️ Arquitetura Simplificada</h2>
                <div class="ql-architecture-content">
                    <div class="ql-architecture-diagram">
                        <div class="ql-arch-box ql-arch-moodle">
                            <h4>🎓 Moodle</h4>
                            <p>Trilhas/Cursos<br>Participantes</p>
                        </div>
                        <div class="ql-arch-arrow">↔ SAML2</div>
                        <div class="ql-arch-box ql-arch-wp">
                            <h4>🌐 WordPress</h4>
                            <p>Quilombo Laboratório<br>Gestão Coletiva</p>
                        </div>
                    </div>
                    <div class="ql-architecture-benefits">
                        <h4>✅ Benefícios da Nova Arquitetura:</h4>
                        <ul>
                            <li><strong>2 sistemas</strong> (antes eram 3 com Kanboard)</li>
                            <li><strong>1 SSO</strong> via SAML2 para autenticação unificada</li>
                            <li><strong>Gestão de projetos nativa</strong> no Quilombo Laboratório</li>
                            <li><strong>Sincronização automática</strong> de usuários Moodle → WordPress</li>
                            <li><strong>Migração manual</strong> controlada dos dados do Kanboard</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Gestão de Usuários e Importação -->
            <?php if (current_user_can('manage_options')): ?>
            <div class="ql-user-management">
                <h2>👥 Gestão de Usuários</h2>
                <div class="ql-user-management-content">
                    <div class="ql-user-stats">
                        <h4>📊 Estatísticas de Sincronização</h4>
                        <div class="ql-user-stats-grid">
                            <div class="ql-user-stat">
                                <span class="count"><?php echo $stats['total_users']; ?></span>
                                <span class="label">Total WordPress</span>
                            </div>
                            <div class="ql-user-stat">
                                <span class="count"><?php echo $stats['moodle_imported_users']; ?></span>
                                <span class="label">Importados do Moodle</span>
                            </div>
                            <div class="ql-user-stat">
                                <span class="count"><?php echo $stats['saml_imported_users']; ?></span>
                                <span class="label">Via SAML</span>
                            </div>
                            <div class="ql-user-stat">
                                <span class="count"><?php echo $stats['synced_users']; ?></span>
                                <span class="label">Sincronizados</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ql-user-actions">
                        <h4>🔄 Ações de Sincronização</h4>
                        <div class="ql-user-action-buttons">
                            <button type="button" id="ql-import-moodle-users" class="button button-primary">
                                📥 Importar Usuários do Moodle
                            </button>
                            <button type="button" id="ql-sync-all-users" class="button button-secondary">
                                🔄 Sincronizar Todos os Usuários
                            </button>
                        </div>
                        <div id="ql-user-sync-results" class="ql-sync-results" style="display: none;">
                            <div class="ql-sync-message"></div>
                        </div>
                        <div class="ql-user-info">
                            <p><strong>ℹ️ Como funciona:</strong></p>
                            <ul>
                                <li><strong>Importação automática:</strong> Usuários do Moodle são importados automaticamente quando fazem login via SAML</li>
                                <li><strong>Importação manual:</strong> Use o botão acima para importar todos os usuários ativos do Moodle</li>
                                <li><strong>Sincronização:</strong> Mantém dados atualizados entre Moodle e WordPress</li>
                                <li><strong>Sem Kanboard:</strong> A gestão de projetos agora é 100% nativa no Quilombo Laboratório</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Sistema de Logs e Monitoramento -->
            <?php if ($system_health): ?>
            <div class="ql-logs-section">
                <h2>📊 Saúde do Sistema</h2>
                
                <div class="ql-health-overview">
                    <div class="ql-health-score">
                        <div class="ql-health-circle ql-health-<?php echo $system_health['health_score'] >= 80 ? 'good' : ($system_health['health_score'] >= 60 ? 'warning' : 'critical'); ?>">
                            <span><?php echo $system_health['health_score']; ?>%</span>
                        </div>
                        <p>Score de Saúde</p>
                    </div>
                    
                    <div class="ql-health-stats">
                        <div class="ql-health-stat">
                            <strong><?php echo $system_health['recent_errors']; ?></strong>
                            <span>Erros nas últimas 24h</span>
                        </div>
                        <div class="ql-health-stat">
                            <strong><?php echo count($system_health['last_syncs']); ?></strong>
                            <span>Integrações ativas</span>
                        </div>
                        <div class="ql-health-stat">
                            <strong><?php echo count($system_health['inactive_systems']); ?></strong>
                            <span>Sistemas inativos</span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($error_summary)): ?>
                <div class="ql-error-summary">
                    <h3>⚠️ Resumo de Erros (24h)</h3>
                    <div class="ql-error-list">
                        <?php foreach ($error_summary as $error): ?>
                        <div class="ql-error-item">
                            <div class="ql-error-header">
                                <strong><?php echo esc_html($error->log_type); ?></strong>
                                <span class="ql-error-count"><?php echo $error->error_count; ?> ocorrências</span>
                            </div>
                            <div class="ql-error-details">
                                <small><?php echo esc_html($error->source_system); ?> → <?php echo esc_html($error->target_system); ?></small>
                                <p><?php echo esc_html($error->error_messages); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="ql-recent-logs">
                    <h3>📋 Logs Recentes</h3>
                    <div class="ql-logs-controls">
                        <select id="ql-log-filter-type">
                            <option value="">Todos os tipos</option>
                            <option value="user_sync">Sincronização de Usuários</option>
                            <option value="project_sync">Sincronização de Projetos</option>
                            <option value="permission">Permissões</option>
                            <option value="saml">SAML/SSO</option>
                            <option value="moodle_integration">Integração Moodle</option>
                            <option value="gc_integration">Integração Gestão Coletiva</option>
                        </select>
                        <select id="ql-log-filter-status">
                            <option value="">Todos os status</option>
                            <option value="success">Sucesso</option>
                            <option value="error">Erro</option>
                            <option value="warning">Aviso</option>
                            <option value="info">Info</option>
                        </select>
                        <button type="button" class="button" onclick="qlRefreshLogs()">🔄 Atualizar</button>
                    </div>
                    
                    <div class="ql-logs-table">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Hora</th>
                                    <th>Tipo</th>
                                    <th>Sistema</th>
                                    <th>Operação</th>
                                    <th>Status</th>
                                    <th>Mensagem</th>
                                </tr>
                            </thead>
                            <tbody id="ql-logs-tbody">
                                <?php foreach ($recent_logs as $log): ?>
                                <tr class="ql-log-row ql-log-<?php echo esc_attr($log->status); ?>">
                                    <td><?php echo date('H:i:s', strtotime($log->created_at)); ?></td>
                                    <td><span class="ql-log-type"><?php echo esc_html($log->log_type); ?></span></td>
                                    <td><?php echo esc_html($log->source_system); ?><?php if ($log->target_system): ?> → <?php echo esc_html($log->target_system); ?><?php endif; ?></td>
                                    <td><?php echo esc_html($log->operation); ?></td>
                                    <td><span class="ql-status-badge ql-status-<?php echo esc_attr($log->status); ?>"><?php echo esc_html($log->status); ?></span></td>
                                    <td><?php echo esc_html($log->message); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .ql-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .ql-stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .ql-stat-icon {
            font-size: 32px;
            margin-right: 15px;
        }
        
        .ql-stat-content h3 {
            margin: 0;
            font-size: 24px;
            color: #2271b1;
        }
        
        .ql-stat-content p {
            margin: 5px 0;
            font-weight: 600;
        }
        
        .ql-stat-content small {
            color: #666;
        }
        
        .ql-dashboard-integrations {
            margin: 30px 0;
        }
        
        .ql-integration-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .ql-integration-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
        }
        
        .ql-integration-card h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .ql-integration-status {
            padding: 8px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .ql-integration-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .ql-integration-status.inactive {
            background: #fff3cd;
            color: #856404;
        }
        
        .ql-dashboard-workspace {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        .ql-projects-list, .ql-tasks-list {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
        }
        
        .ql-project-item, .ql-task-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .ql-project-item:last-child, .ql-task-item:last-child {
            border-bottom: none;
        }
        
        .ql-project-info h4, .ql-task-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
        }
        
        .ql-project-role, .ql-status-badge {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .ql-project-role {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .ql-status-active { background: #d4edda; color: #155724; }
        .ql-status-open { background: #fff3cd; color: #856404; }
        .ql-status-in_progress { background: #d1ecf1; color: #0c5460; }
        .ql-status-completed { background: #d4edda; color: #155724; }
        
        .ql-task-due.overdue {
            color: #d63638;
            font-weight: 600;
        }
        
        .ql-empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
            font-style: italic;
        }
        
        /* Nova Arquitetura Styles */
        .ql-architecture-info {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .ql-architecture-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: start;
        }
        
        .ql-architecture-diagram {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .ql-arch-box {
            background: white;
            border: 2px solid;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            min-width: 120px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .ql-arch-moodle {
            border-color: #4CAF50;
        }
        
        .ql-arch-wp {
            border-color: #2196F3;
        }
        
        .ql-arch-box h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        
        .ql-arch-box p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .ql-arch-arrow {
            font-size: 18px;
            font-weight: bold;
            color: #2196F3;
        }
        
        .ql-architecture-benefits h4 {
            margin: 0 0 15px 0;
            color: #155724;
        }
        
        .ql-architecture-benefits ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .ql-architecture-benefits li {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        /* Gestão de Usuários Styles */
        .ql-user-management {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .ql-user-management-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 15px;
        }
        
        .ql-user-stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 10px;
        }
        
        .ql-user-stat {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
        }
        
        .ql-user-stat .count {
            display: block;
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin-bottom: 5px;
        }
        
        .ql-user-stat .label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }
        
        .ql-user-action-buttons {
            margin: 15px 0;
        }
        
        .ql-user-action-buttons .button {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .ql-sync-results {
            margin: 15px 0;
            padding: 15px;
            border-radius: 4px;
            background: #f1f1f1;
            border-left: 4px solid #0073aa;
        }
        
        .ql-sync-results.success {
            background: #f0fff4;
            border-left-color: #00a32a;
        }
        
        .ql-sync-results.error {
            background: #ffeaed;
            border-left-color: #d63638;
        }
        
        .ql-user-info {
            margin-top: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 6px;
        }
        
        .ql-user-info ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .ql-user-info li {
            margin-bottom: 8px;
            line-height: 1.4;
        }
        
        @media (max-width: 768px) {
            .ql-dashboard-workspace {
                grid-template-columns: 1fr;
            }
            
            .ql-dashboard-stats {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Importar usuários do Moodle
            $('#ql-import-moodle-users').on('click', function() {
                var $button = $(this);
                var $results = $('#ql-user-sync-results');
                var $message = $('.ql-sync-message', $results);
                
                $button.prop('disabled', true).text('⏳ Importando...');
                $results.removeClass('success error').show();
                $message.html('Conectando ao Moodle e importando usuários...');
                
                $.post(ajaxurl, {
                    action: 'ql_import_moodle_users',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('success');
                        $message.html('<strong>✅ Sucesso!</strong><br>' + response.data.message);
                        
                        // Atualizar contadores na página
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $results.addClass('error');
                        $message.html('<strong>❌ Erro:</strong> ' + response.data);
                    }
                }).fail(function() {
                    $results.addClass('error');
                    $message.html('<strong>❌ Erro:</strong> Falha na comunicação com o servidor');
                }).always(function() {
                    $button.prop('disabled', false).text('📥 Importar Usuários do Moodle');
                });
            });
            
            // Sincronizar todos os usuários
            $('#ql-sync-all-users').on('click', function() {
                var $button = $(this);
                var $results = $('#ql-user-sync-results');
                var $message = $('.ql-sync-message', $results);
                
                $button.prop('disabled', true).text('⏳ Sincronizando...');
                $results.removeClass('success error').show();
                $message.html('Sincronizando usuários entre sistemas...');
                
                $.post(ajaxurl, {
                    action: 'ql_sync_all_users',
                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $results.addClass('success');
                        var data = response.data;
                        $message.html('<strong>✅ Sincronização completa!</strong><br>' +
                            'Sucessos: ' + data.success + ' | Erros: ' + data.errors + '<br>' +
                            'Total processado: ' + data.details.length + ' usuários');
                        
                        // Atualizar contadores na página
                        setTimeout(function() {
                            location.reload();
                        }, 3000);
                    } else {
                        $results.addClass('error');
                        $message.html('<strong>❌ Erro:</strong> ' + response.data);
                    }
                }).fail(function() {
                    $results.addClass('error');
                    $message.html('<strong>❌ Erro:</strong> Falha na comunicação com o servidor');
                }).always(function() {
                    $button.prop('disabled', false).text('🔄 Sincronizar Todos os Usuários');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Criar board padrão para um projeto
     */
    private function handle_create_default_board($project_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Permissão insuficiente');
        }
        
        // Verificar se projeto existe
        global $wpdb;
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Projeto não encontrado.</p></div>';
            });
            return;
        }
        
        // Verificar se já existe board
        $existing_board = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ql_boards WHERE project_id = %d LIMIT 1",
            $project_id
        ));
        
        if ($existing_board) {
            add_action('admin_notices', function() use ($project) {
                echo '<div class="notice notice-warning"><p>Projeto "' . esc_html($project->name) . '" já possui um board.</p></div>';
            });
            return;
        }
        
        try {
            // Criar board
            $board_data = [
                'project_id' => $project_id,
                'name' => 'Quadro Principal',
                'description' => 'Quadro principal do projeto ' . $project->name,
                'board_type' => 'kanban',
                'is_default' => 1,
                'created_at' => current_time('mysql')
            ];
            
            $wpdb->insert($wpdb->prefix . 'ql_boards', $board_data);
            $board_id = $wpdb->insert_id;
            
            if (!$board_id) {
                throw new Exception('Erro ao criar board');
            }
            
            // Criar colunas padrão
            $columns = [
                ['name' => 'Backlog', 'color' => '#6c757d', 'position' => 0],
                ['name' => 'Para Fazer', 'color' => '#ffc107', 'position' => 1],
                ['name' => 'Em Progresso', 'color' => '#17a2b8', 'position' => 2],
                ['name' => 'Revisão', 'color' => '#fd7e14', 'position' => 3],
                ['name' => 'Concluído', 'color' => '#28a745', 'position' => 4]
            ];
            
            foreach ($columns as $column) {
                $column_data = [
                    'board_id' => $board_id,
                    'name' => $column['name'],
                    'position' => $column['position'],
                    'color' => $column['color'],
                    'created_at' => current_time('mysql')
                ];
                
                $wpdb->insert($wpdb->prefix . 'ql_columns', $column_data);
            }
            
            add_action('admin_notices', function() use ($project) {
                echo '<div class="notice notice-success"><p>Quadro criado com sucesso para o projeto "' . esc_html($project->name) . '"!</p></div>';
            });
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>Erro ao criar quadro: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    /**
     * Handler para ações de projetos (editar, excluir, etc.)
     */
    private function handle_project_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Ação de exclusão de projeto
        if (isset($_POST['delete_project']) && wp_verify_nonce($_POST['_wpnonce'], 'delete_project')) {
            $project_id = intval($_POST['project_id']);
            $this->delete_project($project_id);
            wp_redirect(add_query_arg('message', 'project_deleted', admin_url('admin.php?page=quilombo-lab-projetos')));
            exit;
        }
        
        // Ação de atualização de projeto
        if (isset($_POST['update_project']) && wp_verify_nonce($_POST['_wpnonce'], 'edit_project')) {
            $project_id = intval($_POST['project_id']);
            $this->update_project($project_id, $_POST);
            wp_redirect(add_query_arg(['message' => 'project_updated', 'project_id' => $project_id], admin_url('admin.php?page=quilombo-lab-projetos')));
            exit;
        }
    }
    
    /**
     * Mostrar formulário de edição de projeto
     */
    private function show_edit_project_form($project_id) {
        global $wpdb;
        
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT p.*, m.course_name, m.is_collective 
             FROM {$wpdb->prefix}ql_projects p 
             LEFT JOIN {$wpdb->prefix}ql_moodle_mappings m ON p.moodle_course_id = m.moodle_course_id 
             WHERE p.id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_die(__('Projeto não encontrado.', 'quilombo-lab'));
        }
        
        $is_moodle_project = !empty($project->moodle_course_id);
        $settings = json_decode($project->settings, true) ?: [];
        $is_collective = $settings['is_collective_project'] ?? false;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Editar Projeto', 'quilombo-lab'); ?>
                <a href="<?php echo admin_url('admin.php?page=quilombo-lab-projetos'); ?>" class="page-title-action">
                    <?php _e('← Voltar aos Projetos', 'quilombo-lab'); ?>
                </a>
            </h1>
            
            <?php if ($is_moodle_project): ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('⚠️ Projeto sincronizado do Moodle', 'quilombo-lab'); ?></strong></p>
                    <p><?php _e('Campos importados do Moodle não podem ser alterados para evitar divergências. Apenas informações locais podem ser editadas.', 'quilombo-lab'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($is_collective): ?>
                <div class="notice notice-warning">
                    <p><strong><?php _e('🏛️ Projeto do Coletivo', 'quilombo-lab'); ?></strong></p>
                    <p><?php _e('Este é o projeto responsável pela gestão do coletivo. Algumas alterações podem afetar funcionalidades importantes.', 'quilombo-lab'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('edit_project'); ?>
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Nome do Projeto', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="name" value="<?php echo esc_attr($project->name); ?>" 
                                   class="regular-text" <?php echo $is_moodle_project ? 'readonly' : ''; ?> />
                            <?php if ($is_moodle_project): ?>
                                <p class="description"><?php _e('Nome importado do Moodle - não pode ser alterado', 'quilombo-lab'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Slug', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="text" name="slug" value="<?php echo esc_attr($project->slug); ?>" 
                                   class="regular-text" <?php echo $is_moodle_project ? 'readonly' : ''; ?> />
                            <?php if ($is_moodle_project): ?>
                                <p class="description"><?php _e('Slug gerado automaticamente do Moodle', 'quilombo-lab'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Descrição', 'quilombo-lab'); ?></th>
                        <td>
                            <textarea name="description" rows="4" cols="50" class="large-text" 
                                      <?php echo $is_moodle_project ? 'readonly' : ''; ?>><?php echo esc_textarea($project->description); ?></textarea>
                            <?php if ($is_moodle_project): ?>
                                <p class="description"><?php _e('Descrição importada do Moodle - não pode ser alterada', 'quilombo-lab'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Status', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="status">
                                <option value="active" <?php selected($project->status, 'active'); ?>><?php _e('Ativo', 'quilombo-lab'); ?></option>
                                <option value="on_hold" <?php selected($project->status, 'on_hold'); ?>><?php _e('Em Pausa', 'quilombo-lab'); ?></option>
                                <option value="completed" <?php selected($project->status, 'completed'); ?>><?php _e('Concluído', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Visibilidade', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="visibility">
                                <option value="public" <?php selected($project->visibility, 'public'); ?>><?php _e('Público', 'quilombo-lab'); ?></option>
                                <option value="private" <?php selected($project->visibility, 'private'); ?>><?php _e('Privado', 'quilombo-lab'); ?></option>
                                <option value="collective" <?php selected($project->visibility, 'collective'); ?>><?php _e('Coletivo', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Prioridade', 'quilombo-lab'); ?></th>
                        <td>
                            <select name="priority">
                                <option value="low" <?php selected($project->priority, 'low'); ?>><?php _e('Baixa', 'quilombo-lab'); ?></option>
                                <option value="normal" <?php selected($project->priority, 'normal'); ?>><?php _e('Normal', 'quilombo-lab'); ?></option>
                                <option value="high" <?php selected($project->priority, 'high'); ?>><?php _e('Alta', 'quilombo-lab'); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cor do Projeto', 'quilombo-lab'); ?></th>
                        <td>
                            <input type="color" name="color" value="<?php echo esc_attr($project->color ?: '#3498db'); ?>" />
                        </td>
                    </tr>
                    
                    <?php if ($is_moodle_project): ?>
                        <tr>
                            <th scope="row"><?php _e('Trilha Moodle', 'quilombo-lab'); ?></th>
                            <td>
                                <strong>ID: <?php echo $project->moodle_course_id; ?></strong>
                                <?php if ($project->course_name): ?>
                                    <br><em><?php echo esc_html($project->course_name); ?></em>
                                <?php endif; ?>
                                <p class="description"><?php _e('Projeto sincronizado automaticamente do Moodle', 'quilombo-lab'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                </table>
                
                <div class="ql-form-actions">
                    <?php submit_button(__('Atualizar Projeto', 'quilombo-lab'), 'primary', 'update_project'); ?>
                    
                    <?php if (!$is_moodle_project && !$is_collective): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                            <h3><?php _e('Zona de Perigo', 'quilombo-lab'); ?></h3>
                            <p><?php _e('Excluir este projeto removerá permanentemente todos os dados associados (quadros, tarefas, anexos, etc.).', 'quilombo-lab'); ?></p>
                            <button type="button" class="button button-link-delete" 
                                    onclick="qlConfirmDeleteProject(<?php echo $project_id; ?>, '<?php echo esc_js($project->name); ?>')">
                                <?php _e('🗑️ Excluir Projeto Permanentemente', 'quilombo-lab'); ?>
                            </button>
                        </div>
                    <?php elseif ($is_moodle_project): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                            <p><em><?php _e('Projetos sincronizados do Moodle não podem ser excluídos. Para remover, desative a sincronização no Moodle.', 'quilombo-lab'); ?></em></p>
                        </div>
                    <?php elseif ($is_collective): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                            <p><em><?php _e('O projeto do coletivo não pode ser excluído. Para alterar, configure outro projeto como responsável pela gestão do coletivo.', 'quilombo-lab'); ?></em></p>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Form de exclusão (oculto) -->
        <form id="delete-project-form" method="post" style="display: none;">
            <?php wp_nonce_field('delete_project'); ?>
            <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
            <input type="hidden" name="delete_project" value="1">
        </form>
        
        <script>
        function qlConfirmDeleteProject(projectId, projectName) {
            if (confirm('ATENÇÃO: Tem certeza que deseja excluir permanentemente o projeto "' + projectName + '"?\n\nEsta ação:\n• Removerá todos os quadros e tarefas\n• Excluirá todos os anexos\n• Não pode ser desfeita\n\nDigite "EXCLUIR" para confirmar:')) {
                var confirmation = prompt('Digite "EXCLUIR" para confirmar a exclusão:');
                if (confirmation === 'EXCLUIR') {
                    document.getElementById('delete-project-form').submit();
                } else {
                    alert('Exclusão cancelada. Texto de confirmação incorreto.');
                }
            }
        }
        </script>
        
        <style>
        .ql-form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ccd0d4;
        }
        
        .button-link-delete {
            color: #b32d2e !important;
            text-decoration: none;
            border: 1px solid #b32d2e;
            background: transparent;
        }
        
        .button-link-delete:hover {
            background: #b32d2e;
            color: white !important;
        }
        </style>
        <?php
    }
    
    /**
     * Atualizar dados de um projeto
     */
    private function update_project($project_id, $data) {
        global $wpdb;
        
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_die(__('Projeto não encontrado.', 'quilombo-lab'));
        }
        
        $is_moodle_project = !empty($project->moodle_course_id);
        
        $update_data = [];
        
        // Campos editáveis sempre
        if (isset($data['status'])) {
            $update_data['status'] = sanitize_text_field($data['status']);
        }
        
        if (isset($data['visibility'])) {
            $update_data['visibility'] = sanitize_text_field($data['visibility']);
        }
        
        if (isset($data['priority'])) {
            $update_data['priority'] = sanitize_text_field($data['priority']);
        }
        
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
        }
        
        // Campos editáveis apenas se não for projeto Moodle
        if (!$is_moodle_project) {
            if (isset($data['name'])) {
                $update_data['name'] = sanitize_text_field($data['name']);
            }
            
            if (isset($data['slug'])) {
                $update_data['slug'] = sanitize_title($data['slug']);
            }
            
            if (isset($data['description'])) {
                $update_data['description'] = sanitize_textarea_field($data['description']);
            }
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            $update_data,
            ['id' => $project_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );
        
        if ($result === false) {
            wp_die(__('Erro ao atualizar projeto.', 'quilombo-lab'));
        }
        
        do_action('ql_project_updated', $project_id, $update_data);
    }
    
    /**
     * Excluir um projeto (apenas projetos manuais, não Moodle nem coletivos)
     */
    private function delete_project($project_id) {
        global $wpdb;
        
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ));
        
        if (!$project) {
            wp_die(__('Projeto não encontrado.', 'quilombo-lab'));
        }
        
        // Verificações de segurança
        $is_moodle_project = !empty($project->moodle_course_id);
        $settings = json_decode($project->settings, true) ?: [];
        $is_collective = $settings['is_collective_project'] ?? false;
        
        if ($is_moodle_project) {
            wp_die(__('Projetos sincronizados do Moodle não podem ser excluídos.', 'quilombo-lab'));
        }
        
        if ($is_collective) {
            wp_die(__('O projeto do coletivo não pode ser excluído.', 'quilombo-lab'));
        }
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Excluir tarefas
            $wpdb->delete($wpdb->prefix . 'ql_tasks', ['project_id' => $project_id]);
            
            // Excluir anexos
            $wpdb->delete($wpdb->prefix . 'ql_attachments', ['project_id' => $project_id]);
            
            // Excluir colunas dos quadros
            $boards = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ql_boards WHERE project_id = %d",
                $project_id
            ));
            
            foreach ($boards as $board_id) {
                $wpdb->delete($wpdb->prefix . 'ql_columns', ['board_id' => $board_id]);
            }
            
            // Excluir quadros
            $wpdb->delete($wpdb->prefix . 'ql_boards', ['project_id' => $project_id]);
            
            // Excluir membros do projeto
            $wpdb->delete($wpdb->prefix . 'ql_project_members', ['project_id' => $project_id]);
            
            // Excluir metadados
            $wpdb->delete($wpdb->prefix . 'ql_project_meta', ['project_id' => $project_id]);
            
            // Finalmente, excluir o projeto
            $wpdb->delete($wpdb->prefix . 'ql_projects', ['id' => $project_id]);
            
            $wpdb->query('COMMIT');
            
            do_action('ql_project_deleted', $project_id);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_die(__('Erro ao excluir projeto: ', 'quilombo-lab') . $e->getMessage());
        }
    }

    /**
     * Dashboard da Organização - Página principal do menu Organização
     */
    public function organization_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Dashboard da Organização', 'quilombo-lab'); ?></h1>
            
            <div class="ql-organization-dashboard">
                <div class="ql-dashboard-grid">
                    <!-- Cards de Resumo -->
                    <div class="ql-dashboard-card ql-card-instances">
                        <div class="ql-card-header">
                            <span class="dashicons dashicons-groups"></span>
                            <h3><?php _e('Instâncias Ativas', 'quilombo-lab'); ?></h3>
                        </div>
                        <div class="ql-card-content">
                            <?php
                            global $wpdb;
                            $instances = $wpdb->get_results("
                                SELECT type, COUNT(*) as count 
                                FROM {$wpdb->prefix}ql_instances 
                                WHERE status = 'active' 
                                GROUP BY type
                            ");
                            
                            if ($instances): ?>
                                <ul class="ql-instance-list">
                                    <?php foreach ($instances as $instance): ?>
                                        <li>
                                            <strong><?php echo ucfirst($instance->type); ?>:</strong>
                                            <span class="ql-count"><?php echo $instance->count; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p><?php _e('Nenhuma instância ativa encontrada.', 'quilombo-lab'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="ql-card-actions">
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-instances'); ?>" class="button">
                                <?php _e('Ver Instâncias', 'quilombo-lab'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Card de Responsabilidades -->
                    <div class="ql-dashboard-card ql-card-responsibilities">
                        <div class="ql-card-header">
                            <span class="dashicons dashicons-admin-users"></span>
                            <h3><?php _e('Responsabilidades', 'quilombo-lab'); ?></h3>
                        </div>
                        <div class="ql-card-content">
                            <?php
                            if (class_exists('QL_Responsibility_System')) {
                                $responsibilities = QL_Responsibility_System::RESPONSIBILITIES;
                                $assigned_count = 0;
                                $total_count = count($responsibilities);
                                
                                // Contar responsabilidades atribuídas
                                $assigned_responsibilities = $wpdb->get_var("
                                    SELECT COUNT(DISTINCT responsibility_type) 
                                    FROM {$wpdb->prefix}ql_responsibility_assignments 
                                    WHERE status = 'active'
                                ");
                                
                                echo "<div class='ql-progress-circle'>";
                                echo "<span class='ql-progress-text'>" . $assigned_responsibilities . "/" . $total_count . "</span>";
                                echo "</div>";
                                echo "<p>" . sprintf(__('%d de %d responsabilidades atribuídas', 'quilombo-lab'), $assigned_responsibilities, $total_count) . "</p>";
                            }
                            ?>
                        </div>
                        <div class="ql-card-actions">
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-responsibilities'); ?>" class="button">
                                <?php _e('Gerenciar Responsabilidades', 'quilombo-lab'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Card de Consultas -->
                    <div class="ql-dashboard-card ql-card-consultations">
                        <div class="ql-card-header">
                            <span class="dashicons dashicons-format-chat"></span>
                            <h3><?php _e('Consultas Abertas', 'quilombo-lab'); ?></h3>
                        </div>
                        <div class="ql-card-content">
                            <?php
                            $open_consultations = $wpdb->get_var("
                                SELECT COUNT(*) 
                                FROM {$wpdb->prefix}ql_consultations 
                                WHERE status = 'open'
                            ");
                            ?>
                            <div class="ql-big-number"><?php echo $open_consultations; ?></div>
                            <p><?php _e('consultas aguardando participação', 'quilombo-lab'); ?></p>
                        </div>
                        <div class="ql-card-actions">
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-consultations'); ?>" class="button">
                                <?php _e('Ver Consultas', 'quilombo-lab'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Card de Contestações -->
                    <div class="ql-dashboard-card ql-card-contestations">
                        <div class="ql-card-header">
                            <span class="dashicons dashicons-warning"></span>
                            <h3><?php _e('Contestações Abertas', 'quilombo-lab'); ?></h3>
                        </div>
                        <div class="ql-card-content">
                            <?php
                            $open_contestations = 0;
                            if (class_exists('QL_Contestations')) {
                                $open_contestations = $wpdb->get_var("
                                    SELECT COUNT(*) 
                                    FROM {$wpdb->prefix}ql_contestations 
                                    WHERE status IN ('open', 'under_review', 'community_vote')
                                ");
                            }
                            ?>
                            <div class="ql-big-number"><?php echo $open_contestations; ?></div>
                            <p><?php _e('contestações em andamento', 'quilombo-lab'); ?></p>
                        </div>
                        <div class="ql-card-actions">
                            <a href="<?php echo admin_url('admin.php?page=quilombo-lab-consultations#contestations'); ?>" class="button">
                                <?php _e('Ver Contestações', 'quilombo-lab'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Atividades Recentes -->
                <div class="ql-recent-activities">
                    <h2><?php _e('Atividades Recentes', 'quilombo-lab'); ?></h2>
                    <?php
                    $recent_activities = $wpdb->get_results("
                        SELECT 
                            'responsibility' as type,
                            CONCAT('Responsabilidade ', responsibility_type, ' atribuída a ', assigned_to_name) as description,
                            created_at as date_created
                        FROM {$wpdb->prefix}ql_responsibility_assignments 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        
                        UNION ALL
                        
                        SELECT 
                            'consultation' as type,
                            CONCAT('Nova consulta: ', title) as description,
                            created_at as date_created
                        FROM {$wpdb->prefix}ql_consultations 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        
                        ORDER BY date_created DESC
                        LIMIT 10
                    ");

                    if ($recent_activities): ?>
                        <ul class="ql-activity-list">
                            <?php foreach ($recent_activities as $activity): ?>
                                <li class="ql-activity-item ql-activity-<?php echo $activity->type; ?>">
                                    <span class="ql-activity-description"><?php echo esc_html($activity->description); ?></span>
                                    <span class="ql-activity-date"><?php echo human_time_diff(strtotime($activity->date_created), current_time('timestamp')) . ' atrás'; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><?php _e('Nenhuma atividade recente encontrada.', 'quilombo-lab'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <style>
            .ql-organization-dashboard {
                margin-top: 20px;
            }

            .ql-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .ql-dashboard-card {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .ql-card-header {
                padding: 15px;
                background: #f6f7f7;
                border-bottom: 1px solid #e1e5e9;
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .ql-card-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            }

            .ql-card-content {
                padding: 20px;
            }

            .ql-card-actions {
                padding: 15px;
                background: #f9f9f9;
                border-top: 1px solid #e1e5e9;
            }

            .ql-big-number {
                font-size: 48px;
                font-weight: bold;
                color: #2271b1;
                line-height: 1;
                margin-bottom: 10px;
            }

            .ql-instance-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .ql-instance-list li {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .ql-count {
                background: #2271b1;
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }

            .ql-progress-circle {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                border: 4px solid #e1e5e9;
                border-top: 4px solid #2271b1;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 15px;
                position: relative;
            }

            .ql-progress-text {
                font-size: 14px;
                font-weight: 600;
                color: #2271b1;
            }

            .ql-recent-activities {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
            }

            .ql-activity-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .ql-activity-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                margin-bottom: 8px;
                background: #f9f9f9;
                border-radius: 6px;
                border-left: 4px solid #2271b1;
            }

            .ql-activity-responsibility {
                border-left-color: #27ae60;
            }

            .ql-activity-consultation {
                border-left-color: #e74c3c;
            }

            .ql-activity-description {
                flex: 1;
                font-weight: 500;
            }

            .ql-activity-date {
                font-size: 12px;
                color: #6c757d;
            }
            </style>
        </div>
        <?php
    }

    /**
     * Página de Gerenciamento de Responsabilidades
     */
    public function responsibilities_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Gerenciamento de Responsabilidades', 'quilombo-lab'); ?></h1>
            
            <div class="ql-responsibilities-container">
                <?php if (class_exists('QL_Responsibility_System')): ?>
                    
                    <!-- Sistema de Responsabilidades Radiculares -->
                    <div class="ql-responsibilities-overview">
                        <h2><?php _e('Sistema Radicular de Responsabilidades', 'quilombo-lab'); ?></h2>
                        <p><?php _e('O modelo radicular distribui responsabilidades de forma não-hierárquica, promovendo autonomia e colaboração.', 'quilombo-lab'); ?></p>
                        
                        <div class="ql-responsibilities-grid">
                            <?php
                            $responsibilities = QL_Responsibility_System::RESPONSIBILITIES;
                            global $wpdb;
                            
                            foreach ($responsibilities as $key => $responsibility):
                                // Verificar quem tem esta responsabilidade atribuída
                                $assignments = $wpdb->get_results($wpdb->prepare("
                                    SELECT assigned_to_name, assigned_to_id, instance_id, created_at
                                    FROM {$wpdb->prefix}ql_responsibility_assignments 
                                    WHERE responsibility_type = %s AND status = 'active'
                                    ORDER BY created_at DESC
                                ", $key));
                            ?>
                                <div class="ql-responsibility-card" style="border-left: 4px solid <?php echo $responsibility['color']; ?>">
                                    <div class="ql-responsibility-header">
                                        <span class="dashicons <?php echo $responsibility['icon']; ?>" style="color: <?php echo $responsibility['color']; ?>"></span>
                                        <h3><?php echo $responsibility['label']; ?></h3>
                                    </div>
                                    
                                    <div class="ql-responsibility-description">
                                        <p><?php echo $responsibility['description']; ?></p>
                                    </div>
                                    
                                    <div class="ql-responsibility-tasks">
                                        <strong><?php _e('Tarefas:', 'quilombo-lab'); ?></strong>
                                        <ul>
                                            <?php foreach ($responsibility['tasks'] as $task): ?>
                                                <li><?php echo $task; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    
                                    <div class="ql-responsibility-actions">
                                        <strong><?php _e('Ações:', 'quilombo-lab'); ?></strong>
                                        <div class="ql-action-tags">
                                            <?php foreach ($responsibility['actions'] as $action): ?>
                                                <span class="ql-action-tag"><?php echo $action; ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="ql-responsibility-assignments">
                                        <strong><?php _e('Atribuições Ativas:', 'quilombo-lab'); ?></strong>
                                        <?php if ($assignments): ?>
                                            <ul class="ql-assignment-list">
                                                <?php foreach ($assignments as $assignment): ?>
                                                    <li>
                                                        <span class="ql-assigned-person"><?php echo esc_html($assignment->assigned_to_name); ?></span>
                                                        <span class="ql-assignment-date"><?php echo human_time_diff(strtotime($assignment->created_at), current_time('timestamp')) . ' atrás'; ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="ql-no-assignments"><?php _e('Nenhuma atribuição ativa', 'quilombo-lab'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ql-responsibility-actions-buttons">
                                        <button class="button button-primary ql-assign-responsibility" 
                                                data-responsibility="<?php echo $key; ?>"
                                                data-label="<?php echo $responsibility['label']; ?>">
                                            <?php _e('Atribuir Responsabilidade', 'quilombo-lab'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Sistema de Responsabilidades não está ativo. Verifique se a classe QL_Responsibility_System está carregada.', 'quilombo-lab'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <style>
            .ql-responsibilities-container {
                margin-top: 20px;
            }

            .ql-responsibilities-overview {
                background: white;
                border: 1px solid #c3c4c7;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
            }

            .ql-responsibilities-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .ql-responsibility-card {
                background: white;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .ql-responsibility-header {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 15px;
            }

            .ql-responsibility-header h3 {
                margin: 0;
                font-size: 18px;
                font-weight: 600;
            }

            .ql-responsibility-description {
                margin-bottom: 15px;
                color: #555;
            }

            .ql-responsibility-tasks ul,
            .ql-assignment-list {
                list-style: none;
                padding: 0;
                margin: 10px 0;
            }

            .ql-responsibility-tasks li,
            .ql-assignment-list li {
                padding: 5px 0;
                border-bottom: 1px solid #f0f0f0;
            }

            .ql-action-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-top: 10px;
            }

            .ql-action-tag {
                background: #f0f0f0;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                color: #555;
            }

            .ql-assignment-list li {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .ql-assigned-person {
                font-weight: 600;
            }

            .ql-assignment-date {
                font-size: 12px;
                color: #6c757d;
            }

            .ql-no-assignments {
                color: #999;
                font-style: italic;
                margin: 10px 0;
            }

            .ql-responsibility-actions-buttons {
                margin-top: 15px;
                text-align: right;
            }
            </style>
        </div>
        <?php
    }

    /**
     * Página de Consultas
     */
    public function consultations_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Sistema de Consultas, Consensos e Contestações', 'quilombo-lab'); ?></h1>
            
            <!-- Navegação por abas -->
            <nav class="nav-tab-wrapper">
                <a href="#consultations" class="nav-tab nav-tab-active" data-tab="consultations"><?php _e('Consultas', 'quilombo-lab'); ?></a>
                <a href="#contestations" class="nav-tab" data-tab="contestations"><?php _e('Contestações', 'quilombo-lab'); ?></a>
            </nav>
            
            <!-- Aba de Consultas -->
            <div id="consultations-tab" class="ql-tab-content ql-consultations-container">
                <!-- Botão para criar nova consulta -->
                <div class="ql-consultations-header">
                    <button class="button button-primary" id="ql-new-consultation">
                        <?php _e('Nova Consulta', 'quilombo-lab'); ?>
                    </button>
                </div>

                <?php
                global $wpdb;
                $consultations = $wpdb->get_results("
                    SELECT c.*, 
                           COUNT(cv.id) as total_votes,
                           SUM(CASE WHEN cv.vote_type = 'support' THEN 1 ELSE 0 END) as support_votes,
                           SUM(CASE WHEN cv.vote_type = 'concern' THEN 1 ELSE 0 END) as concern_votes,
                           SUM(CASE WHEN cv.vote_type = 'block' THEN 1 ELSE 0 END) as block_votes
                    FROM {$wpdb->prefix}ql_consultations c
                    LEFT JOIN {$wpdb->prefix}ql_consultation_votes cv ON c.id = cv.consultation_id
                    GROUP BY c.id
                    ORDER BY c.created_at DESC
                ");

                if ($consultations): ?>
                    <div class="ql-consultations-list">
                        <?php foreach ($consultations as $consultation): ?>
                            <div class="ql-consultation-card ql-consultation-<?php echo $consultation->status; ?>">
                                <div class="ql-consultation-header">
                                    <h3><?php echo esc_html($consultation->title); ?></h3>
                                    <div class="ql-consultation-meta">
                                        <span class="ql-consultation-status ql-status-<?php echo $consultation->status; ?>">
                                            <?php echo ucfirst($consultation->status); ?>
                                        </span>
                                        <span class="ql-consultation-type">
                                            <?php echo ucfirst($consultation->consultation_type); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="ql-consultation-description">
                                    <p><?php echo esc_html($consultation->description); ?></p>
                                </div>

                                <?php if ($consultation->status === 'open'): ?>
                                    <!-- Sistema de votação por consenso -->
                                    <div class="ql-voting-section">
                                        <h4><?php _e('Sua Posição:', 'quilombo-lab'); ?></h4>
                                        <div class="ql-voting-options">
                                            <button class="ql-vote-btn ql-vote-support" 
                                                    data-consultation="<?php echo $consultation->id; ?>" 
                                                    data-vote="support">
                                                👍 <?php _e('Apoio', 'quilombo-lab'); ?>
                                            </button>
                                            <button class="ql-vote-btn ql-vote-concern" 
                                                    data-consultation="<?php echo $consultation->id; ?>" 
                                                    data-vote="concern">
                                                🤔 <?php _e('Tenho Preocupações', 'quilombo-lab'); ?>
                                            </button>
                                            <button class="ql-vote-btn ql-vote-block" 
                                                    data-consultation="<?php echo $consultation->id; ?>" 
                                                    data-vote="block">
                                                ✋ <?php _e('Bloqueio', 'quilombo-lab'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Resultados da votação -->
                                <div class="ql-voting-results">
                                    <h4><?php _e('Resultados:', 'quilombo-lab'); ?></h4>
                                    <div class="ql-vote-bars">
                                        <div class="ql-vote-bar">
                                            <span class="ql-vote-label">👍 Apoio:</span>
                                            <div class="ql-vote-progress">
                                                <div class="ql-vote-fill ql-vote-support-fill" 
                                                     style="width: <?php echo $consultation->total_votes > 0 ? ($consultation->support_votes / $consultation->total_votes * 100) : 0; ?>%"></div>
                                            </div>
                                            <span class="ql-vote-count"><?php echo $consultation->support_votes; ?></span>
                                        </div>
                                        
                                        <div class="ql-vote-bar">
                                            <span class="ql-vote-label">🤔 Preocupações:</span>
                                            <div class="ql-vote-progress">
                                                <div class="ql-vote-fill ql-vote-concern-fill" 
                                                     style="width: <?php echo $consultation->total_votes > 0 ? ($consultation->concern_votes / $consultation->total_votes * 100) : 0; ?>%"></div>
                                            </div>
                                            <span class="ql-vote-count"><?php echo $consultation->concern_votes; ?></span>
                                        </div>
                                        
                                        <div class="ql-vote-bar">
                                            <span class="ql-vote-label">✋ Bloqueios:</span>
                                            <div class="ql-vote-progress">
                                                <div class="ql-vote-fill ql-vote-block-fill" 
                                                     style="width: <?php echo $consultation->total_votes > 0 ? ($consultation->block_votes / $consultation->total_votes * 100) : 0; ?>%"></div>
                                            </div>
                                            <span class="ql-vote-count"><?php echo $consultation->block_votes; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="ql-consultation-summary">
                                        <p><strong><?php echo $consultation->total_votes; ?></strong> <?php _e('participantes votaram', 'quilombo-lab'); ?></p>
                                        
                                        <?php if ($consultation->block_votes > 0): ?>
                                            <p class="ql-consensus-status ql-blocked">
                                                ❌ <?php _e('Proposta bloqueada - necessário revisar preocupações', 'quilombo-lab'); ?>
                                            </p>
                                        <?php elseif ($consultation->concern_votes > 0): ?>
                                            <p class="ql-consensus-status ql-concerns">
                                                ⚠️ <?php _e('Preocupações levantadas - diálogo necessário', 'quilombo-lab'); ?>
                                            </p>
                                        <?php elseif ($consultation->support_votes >= 3): ?>
                                            <p class="ql-consensus-status ql-consensus">
                                                ✅ <?php _e('Consenso alcançado!', 'quilombo-lab'); ?>
                                            </p>
                                        <?php else: ?>
                                            <p class="ql-consensus-status ql-pending">
                                                ⏳ <?php _e('Aguardando mais participações', 'quilombo-lab'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="ql-consultation-footer">
                                    <span class="ql-consultation-date">
                                        <?php echo sprintf(__('Criada %s', 'quilombo-lab'), human_time_diff(strtotime($consultation->created_at), current_time('timestamp')) . ' atrás'); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="ql-no-consultations">
                        <p><?php _e('Nenhuma consulta encontrada. Crie a primeira consulta para iniciar o processo de tomada de decisões participativa.', 'quilombo-lab'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Aba de Contestações -->
            <div id="contestations-tab" class="ql-tab-content ql-contestations-container" style="display: none;">
                <!-- Botão para criar nova contestação -->
                <div class="ql-contestations-header">
                    <button class="button button-primary" id="ql-new-contestation">
                        <?php _e('Nova Contestação', 'quilombo-lab'); ?>
                    </button>
                </div>

                <?php
                // Verificar se a classe de contestações está carregada
                if (class_exists('QL_Contestations')) {
                    // Buscar contestações existentes
                    $contestations = $wpdb->get_results("
                        SELECT c.*, 
                               COUNT(cv.id) as total_votes,
                               SUM(CASE WHEN cv.vote_type = 'support_contestation' THEN cv.vote_weight ELSE 0 END) as support_contestation_weight,
                               SUM(CASE WHEN cv.vote_type = 'support_response' THEN cv.vote_weight ELSE 0 END) as support_response_weight
                        FROM {$wpdb->prefix}ql_contestations c
                        LEFT JOIN {$wpdb->prefix}ql_contestation_votes cv ON c.id = cv.contestation_id
                        GROUP BY c.id
                        ORDER BY c.created_at DESC
                    ");

                    if ($contestations): ?>
                        <div class="ql-contestations-list">
                            <?php foreach ($contestations as $contestation): ?>
                                <div class="ql-contestation-card ql-contestation-<?php echo $contestation->status; ?>">
                                    <div class="ql-contestation-header">
                                        <h3><?php echo esc_html($contestation->title); ?></h3>
                                        <div class="ql-contestation-meta">
                                            <span class="ql-contestation-status ql-status-<?php echo $contestation->status; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $contestation->status)); ?>
                                            </span>
                                            <span class="ql-contestation-urgency ql-urgency-<?php echo $contestation->urgency_level; ?>">
                                                <?php echo ucfirst($contestation->urgency_level); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="ql-contestation-details">
                                        <div class="ql-target-description">
                                            <h4><?php _e('Alvo da Contestação:', 'quilombo-lab'); ?></h4>
                                            <p><?php echo esc_html($contestation->target_description); ?></p>
                                        </div>
                                        
                                        <div class="ql-contestation-description">
                                            <h4><?php _e('Descrição:', 'quilombo-lab'); ?></h4>
                                            <p><?php echo nl2br(esc_html($contestation->description)); ?></p>
                                        </div>
                                    </div>

                                    <div class="ql-contestation-footer">
                                        <span class="ql-contestation-author">
                                            <?php 
                                            $author = get_userdata($contestation->author_id);
                                            echo sprintf(__('Criada por %s', 'quilombo-lab'), esc_html($author->display_name)); 
                                            ?>
                                        </span>
                                        <span class="ql-contestation-date">
                                            <?php echo human_time_diff(strtotime($contestation->created_at), current_time('timestamp')) . ' atrás'; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ql-no-contestations">
                            <p><?php _e('Nenhuma contestação encontrada. As contestações permitem questionar ações de responsabilidades de forma transparente e participativa.', 'quilombo-lab'); ?></p>
                        </div>
                    <?php endif; ?>
                } else { ?>
                    <div class="notice notice-warning">
                        <p><?php _e('Sistema de Contestações não está ativo. Verifique se a classe QL_Contestations está carregada.', 'quilombo-lab'); ?></p>
                    </div>
                <?php } ?>
            </div>

            <style>
            .ql-consultations-container {
                margin-top: 20px;
            }

            .ql-consultations-header {
                margin-bottom: 20px;
            }

            .ql-consultations-list {
                display: grid;
                gap: 20px;
            }

            .ql-consultation-card {
                background: white;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                border-left: 4px solid #ddd;
            }

            .ql-consultation-open {
                border-left-color: #3498db;
            }

            .ql-consultation-closed {
                border-left-color: #95a5a6;
            }

            .ql-consultation-consensus {
                border-left-color: #27ae60;
            }

            .ql-consultation-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
            }

            .ql-consultation-header h3 {
                margin: 0;
                flex: 1;
            }

            .ql-consultation-meta {
                display: flex;
                gap: 10px;
            }

            .ql-consultation-status,
            .ql-consultation-type {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }

            .ql-status-open {
                background: #3498db;
                color: white;
            }

            .ql-status-closed {
                background: #95a5a6;
                color: white;
            }

            .ql-status-consensus {
                background: #27ae60;
                color: white;
            }

            .ql-consultation-type {
                background: #f0f0f0;
                color: #555;
            }

            .ql-voting-section {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 6px;
            }

            .ql-voting-options {
                display: flex;
                gap: 10px;
                margin-top: 10px;
            }

            .ql-vote-btn {
                padding: 10px 15px;
                border: 2px solid transparent;
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s;
                font-weight: 600;
            }

            .ql-vote-support {
                background: #e8f5e8;
                color: #27ae60;
                border-color: #27ae60;
            }

            .ql-vote-concern {
                background: #fff3cd;
                color: #f39c12;
                border-color: #f39c12;
            }

            .ql-vote-block {
                background: #f8d7da;
                color: #e74c3c;
                border-color: #e74c3c;
            }

            .ql-vote-btn:hover {
                opacity: 0.8;
            }

            .ql-voting-results {
                margin-top: 20px;
            }

            .ql-vote-bar {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
                gap: 10px;
            }

            .ql-vote-label {
                min-width: 120px;
                font-size: 14px;
            }

            .ql-vote-progress {
                flex: 1;
                height: 20px;
                background: #f0f0f0;
                border-radius: 10px;
                overflow: hidden;
            }

            .ql-vote-fill {
                height: 100%;
                transition: width 0.3s ease;
            }

            .ql-vote-support-fill {
                background: #27ae60;
            }

            .ql-vote-concern-fill {
                background: #f39c12;
            }

            .ql-vote-block-fill {
                background: #e74c3c;
            }

            .ql-vote-count {
                min-width: 30px;
                text-align: right;
                font-weight: 600;
            }

            .ql-consensus-status {
                margin-top: 15px;
                padding: 10px;
                border-radius: 6px;
                font-weight: 600;
            }

            .ql-consensus {
                background: #e8f5e8;
                color: #27ae60;
            }

            .ql-concerns {
                background: #fff3cd;
                color: #f39c12;
            }

            .ql-blocked {
                background: #f8d7da;
                color: #e74c3c;
            }

            .ql-pending {
                background: #e2e3e5;
                color: #6c757d;
            }

            .ql-consultation-footer {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
            }

            .ql-consultation-date {
                color: #6c757d;
                font-size: 14px;
            }

            .ql-no-consultations {
                text-align: center;
                padding: 40px;
                color: #6c757d;
            }
            
            /* Estilos para navegação por abas */
            .nav-tab-wrapper {
                margin: 20px 0;
            }
            
            .ql-tab-content {
                margin-top: 20px;
            }
            
            /* Estilos para contestações */
            .ql-contestations-container {
                margin-top: 20px;
            }
            
            .ql-contestations-header {
                margin-bottom: 20px;
            }
            
            .ql-contestations-list {
                display: grid;
                gap: 20px;
            }
            
            .ql-contestation-card {
                background: white;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                padding: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                border-left: 4px solid #ddd;
            }
            
            .ql-contestation-open {
                border-left-color: #e74c3c;
            }
            
            .ql-contestation-under_review {
                border-left-color: #f39c12;
            }
            
            .ql-contestation-community_vote {
                border-left-color: #3498db;
            }
            
            .ql-contestation-resolved {
                border-left-color: #27ae60;
            }
            
            .ql-contestation-dismissed {
                border-left-color: #95a5a6;
            }
            
            .ql-contestation-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
            }
            
            .ql-contestation-header h3 {
                margin: 0;
                flex: 1;
                margin-right: 15px;
            }
            
            .ql-contestation-meta {
                display: flex;
                flex-direction: column;
                gap: 5px;
                align-items: flex-end;
            }
            
            .ql-contestation-status,
            .ql-contestation-urgency {
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                color: white;
            }
            
            .ql-status-open {
                background: #e74c3c;
            }
            
            .ql-status-under_review {
                background: #f39c12;
            }
            
            .ql-status-community_vote {
                background: #3498db;
            }
            
            .ql-status-resolved {
                background: #27ae60;
            }
            
            .ql-status-dismissed {
                background: #95a5a6;
            }
            
            .ql-urgency-low {
                background: #95a5a6;
            }
            
            .ql-urgency-medium {
                background: #f39c12;
            }
            
            .ql-urgency-high {
                background: #e74c3c;
            }
            
            .ql-urgency-critical {
                background: #8e44ad;
            }
            
            .ql-contestation-details {
                margin-bottom: 20px;
            }
            
            .ql-contestation-details h4 {
                margin: 15px 0 5px 0;
                font-size: 14px;
                font-weight: 600;
                color: #2c3e50;
            }
            
            .ql-contestation-details p {
                margin: 5px 0;
                line-height: 1.5;
            }
            
            .ql-contestation-footer {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding-top: 15px;
                border-top: 1px solid #f0f0f0;
                font-size: 14px;
                color: #6c757d;
            }
            
            .ql-no-contestations {
                text-align: center;
                padding: 40px;
                color: #6c757d;
            }
            
            /* Estilos para votação de contestações */
            .ql-vote-contestation-fill {
                background: #e74c3c;
            }
            
            .ql-vote-response-fill {
                background: #27ae60;
            }
            
            .ql-vote-mediation-fill {
                background: #3498db;
            }
            
            .ql-vote-weight {
                min-width: 40px;
                text-align: right;
                font-weight: 600;
            }
            </style>
            
            <script>
            jQuery(document).ready(function($) {
                // Navegação por abas
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    // Remover classe ativa de todas as abas
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.ql-tab-content').hide();
                    
                    // Ativar aba clicada
                    $(this).addClass('nav-tab-active');
                    var tabId = $(this).data('tab');
                    $('#' + tabId + '-tab').show();
                });
                
                // Abrir modal de nova contestação
                $('#ql-new-contestation').on('click', function() {
                    // Implementar modal de nova contestação
                    alert('Modal de nova contestação será implementado');
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * Wrapper para página de instâncias - chama o método da classe QL_Instances
     */
    public function instances_page_wrapper() {
        if (class_exists('QL_Instances')) {
            $instances = QL_Instances::get_instance();
            $instances->admin_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php _e('Instâncias Organizacionais', 'quilombo-lab'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Classe QL_Instances não está disponível. Verifique se o plugin está funcionando corretamente.', 'quilombo-lab'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Wrapper para página de papéis - chama o método da classe QL_Organizational_Roles
     */
    public function roles_page_wrapper() {
        if (class_exists('QL_Organizational_Roles')) {
            $roles = QL_Organizational_Roles::get_instance();
            $roles->admin_page();
        } else {
            ?>
            <div class="wrap">
                <h1><?php _e('Papéis Organizativos', 'quilombo-lab'); ?></h1>
                <div class="notice notice-error">
                    <p><?php _e('Classe QL_Organizational_Roles não está disponível. Verifique se o plugin está funcionando corretamente.', 'quilombo-lab'); ?></p>
                </div>
            </div>
            <?php
        }
    }

    /**
     * Página de Territórios - PRIORIDADE 0
     */
    public function territories_page() {
        ?>
        <div class="wrap">
            <h1><?php _e("Sistema de Territorialização", "quilombo-lab"); ?></h1>
            <p class="description">
                <?php _e("Gerencie territórios para organização territorial do coletivo. Territórios podem ser desde um edifício até regiões extensas.", "quilombo-lab"); ?>
            </p>
            
            <!-- Navegação por abas -->
            <nav class="nav-tab-wrapper">
                <a href="#territories" class="nav-tab nav-tab-active" data-tab="territories"><?php _e("Territórios", "quilombo-lab"); ?></a>
                <a href="#map-view" class="nav-tab" data-tab="map-view"><?php _e("Mapa Geral", "quilombo-lab"); ?></a>
                <a href="#user-location" class="nav-tab" data-tab="user-location"><?php _e("Minha Localização", "quilombo-lab"); ?></a>
                <?php if (current_user_can('manage_options')) : ?>
                <a href="#admin-tools" class="nav-tab" data-tab="admin-tools"><?php _e("Ferramentas Admin", "quilombo-lab"); ?></a>
                <?php endif; ?>
            </nav>
            
            <!-- Aba de Territórios -->
            <div id="territories-tab" class="ql-tab-content">
                <div class="ql-territories-header" style="margin: 20px 0;">
                    <button type="button" id="create-territory-btn" class="button button-primary">
                        <?php _e("+ Novo Território", "quilombo-lab"); ?>
                    </button>
                    <a href="<?php echo admin_url("edit.php?post_type=ql_territory"); ?>" class="button">
                        <?php _e("Gerenciar Territórios", "quilombo-lab"); ?>
                    </a>
                </div>
                
                <!-- Modal para criar território -->
                <div id="create-territory-modal" style="display: none;">
                    <div class="ql-modal-overlay">
                        <div class="ql-modal-content">
                            <div class="ql-modal-header">
                                <h3><?php _e("Criar Novo Território", "quilombo-lab"); ?></h3>
                                <button type="button" class="ql-modal-close">&times;</button>
                            </div>
                            <div class="ql-modal-body">
                                <form id="territory-creation-form">
                                    <div class="form-field">
                                        <label for="territory_name"><?php _e("Nome do Território", "quilombo-lab"); ?></label>
                                        <input type="text" id="territory_name" name="territory_name" class="regular-text" required />
                                    </div>
                                    
                                    <div class="form-field">
                                        <label for="territory_type"><?php _e("Tipo de Território", "quilombo-lab"); ?></label>
                                        <select id="territory_type" name="territory_type" class="regular-text">
                                            <?php $this->render_territory_type_options(); ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-field">
                                        <label for="location_method"><?php _e("Definir Localização", "quilombo-lab"); ?></label>
                                        <select id="location_method" name="location_method" class="regular-text">
                                            <option value="nucleo"><?php _e("Usar endereço de um núcleo", "quilombo-lab"); ?></option>
                                            <option value="address"><?php _e("Digite novo endereço", "quilombo-lab"); ?></option>
                                            <option value="map"><?php _e("Selecionar no mapa", "quilombo-lab"); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div id="nucleo-selection" class="form-field">
                                        <label for="territory_nucleo"><?php _e("Núcleo de Referência", "quilombo-lab"); ?></label>
                                        <select id="territory_nucleo" name="territory_nucleo" class="regular-text">
                                            <option value=""><?php _e("Selecione um núcleo...", "quilombo-lab"); ?></option>
                                            <?php $this->render_nucleos_with_address_options(); ?>
                                        </select>
                                    </div>
                                    
                                    <div id="address-input" class="form-field" style="display: none;">
                                        <label for="territory_address"><?php _e("Endereço", "quilombo-lab"); ?></label>
                                        <input type="text" id="territory_address" name="territory_address" class="regular-text" />
                                        <button type="button" id="search-address" class="button"><?php _e("Buscar", "quilombo-lab"); ?></button>
                                    </div>
                                    
                                    <div id="map-selection" style="display: none;">
                                        <div id="territory-creation-map" class="ql-territory-map" style="height: 300px;"></div>
                                    </div>
                                    
                                    <input type="hidden" id="territory_lat" name="territory_lat" />
                                    <input type="hidden" id="territory_lng" name="territory_lng" />
                                    
                                    <div class="ql-modal-footer">
                                        <button type="submit" class="button button-primary"><?php _e("Criar Território", "quilombo-lab"); ?></button>
                                        <button type="button" class="button ql-modal-close"><?php _e("Cancelar", "quilombo-lab"); ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php $this->display_territories_list(); ?>
            </div>
            
            <!-- Aba do Mapa Geral -->
            <div id="map-view-tab" class="ql-tab-content" style="display: none;">
                <h2><?php _e("Mapa de Territórios e Pessoas", "quilombo-lab"); ?></h2>
                <p class="description">
                    <?php _e("Visualize todos os territórios e pessoas cadastradas no sistema com suas localizações geográficas.", "quilombo-lab"); ?>
                </p>

                <!-- Controles de Visibilidade -->
                <div class="ql-map-controls" style="display: flex; gap: 20px; margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 5px; flex-wrap: wrap; align-items: center;">
                    <strong style="margin-right: 10px;"><?php _e("Exibir:", "quilombo-lab"); ?></strong>

                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" id="toggle-territories" checked />
                        <div style="background: #3498db; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                        <span><?php _e("Territórios", "quilombo-lab"); ?></span>
                        <span id="territories-count" style="color: #666; font-size: 12px;">(0)</span>
                    </label>

                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" id="toggle-people" checked />
                        <div style="background: #e74c3c; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                        <span><?php _e("Pessoas", "quilombo-lab"); ?></span>
                        <span id="people-count" style="color: #666; font-size: 12px;">(0)</span>
                    </label>

                    <div style="border-left: 1px solid #ddd; padding-left: 15px; margin-left: 10px;">
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <span><?php _e("Filtrar por território:", "quilombo-lab"); ?></span>
                            <select id="filter-by-territory" style="min-width: 200px;">
                                <option value=""><?php _e("Todos os territórios", "quilombo-lab"); ?></option>
                            </select>
                        </label>
                    </div>

                    <div style="margin-left: auto;">
                        <button type="button" id="refresh-map" class="button">
                            <?php _e("Atualizar Mapa", "quilombo-lab"); ?>
                        </button>
                    </div>
                </div>

                <!-- Legenda do mapa -->
                <div class="ql-map-legend" style="display: flex; gap: 20px; margin: 10px 0; padding: 10px; background: #f5f5f5; border-radius: 5px; flex-wrap: wrap;">
                    <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                        <div style="background: #3498db; width: 20px; height: 20px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                        <span><?php _e("Territórios", "quilombo-lab"); ?></span>
                    </div>
                    <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                        <div style="background: #e74c3c; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                        <span><?php _e("Pessoas", "quilombo-lab"); ?></span>
                    </div>
                    <div class="legend-item" style="display: flex; align-items: center; gap: 8px;">
                        <div style="background: #27ae60; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
                        <span><?php _e("Você (sua localização)", "quilombo-lab"); ?></span>
                    </div>
                    <div class="legend-stats" style="margin-left: auto; color: #666;">
                        <span id="map-stats-count"></span>
                    </div>
                </div>

                <div id="general-territories-map" class="ql-territory-map" style="height: 500px;"></div>
            </div>
            
            <!-- Aba de Localização do Usuário -->
            <div id="user-location-tab" class="ql-tab-content" style="display: none;">
                <h2><?php _e("Definir Minha Localização", "quilombo-lab"); ?></h2>
                <?php echo do_shortcode("[ql_user_location_selector]"); ?>
            </div>

            <?php if (current_user_can('manage_options')) : ?>
            <!-- Aba de Ferramentas Administrativas -->
            <div id="admin-tools-tab" class="ql-tab-content" style="display: none;">
                <h2><?php _e("Ferramentas Administrativas", "quilombo-lab"); ?></h2>
                <p class="description">
                    <?php _e("Ferramentas para gerenciamento em massa de territórios e usuários.", "quilombo-lab"); ?>
                </p>

                <div class="ql-admin-tool-section">
                    <h3><?php _e("Atribuir Usuários ao Território Mundo e Comunidade Mundial", "quilombo-lab"); ?></h3>
                    <p class="description">
                        <?php _e("Esta ferramenta atribui automaticamente todos os usuários existentes ao Território Mundo e à Comunidade Mundial. Usuários que já possuem um território específico não terão seu território alterado, mas serão adicionados como membros do Território Mundo.", "quilombo-lab"); ?>
                    </p>

                    <?php
                    // Mostrar estatísticas atuais
                    $territories_instance = QL_Territories::get_instance();
                    $world_territory_id = $territories_instance->get_world_territory_id();
                    $world_community_id = $territories_instance->get_world_community_id();

                    global $wpdb;
                    $total_users = count(get_users(['fields' => 'ID']));

                    $users_with_world_territory = 0;
                    $community_members = 0;

                    if ($world_territory_id) {
                        $users_with_world_territory = count(get_users([
                            'meta_key' => 'user_territory_id',
                            'meta_value' => $world_territory_id
                        ]));
                    }

                    if ($world_community_id) {
                        $community_members = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_instance_members WHERE instance_id = %d AND status = 'active'",
                            $world_community_id
                        ));
                    }
                    ?>

                    <div class="ql-stats-box" style="background: #f5f5f5; padding: 15px; margin: 15px 0; border-radius: 5px;">
                        <h4 style="margin-top: 0;"><?php _e("Estatísticas Atuais", "quilombo-lab"); ?></h4>
                        <ul style="margin-bottom: 0;">
                            <li><strong><?php _e("Total de usuários:", "quilombo-lab"); ?></strong> <?php echo $total_users; ?></li>
                            <li><strong><?php _e("Território Mundo:", "quilombo-lab"); ?></strong>
                                <?php if ($world_territory_id) : ?>
                                    <?php printf(__("ID %d - %d usuários com este território principal", "quilombo-lab"), $world_territory_id, $users_with_world_territory); ?>
                                <?php else : ?>
                                    <span style="color: #d63638;"><?php _e("Não encontrado!", "quilombo-lab"); ?></span>
                                <?php endif; ?>
                            </li>
                            <li><strong><?php _e("Comunidade Mundial:", "quilombo-lab"); ?></strong>
                                <?php if ($world_community_id) : ?>
                                    <?php printf(__("ID %d - %d membros ativos", "quilombo-lab"), $world_community_id, $community_members); ?>
                                <?php else : ?>
                                    <span style="color: #d63638;"><?php _e("Não encontrada!", "quilombo-lab"); ?></span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>

                    <div class="ql-admin-actions" style="margin-top: 20px;">
                        <button type="button" id="btn-assign-world-defaults" class="button button-primary button-large">
                            <?php _e("Atribuir Todos os Usuários aos Padrões Mundiais", "quilombo-lab"); ?>
                        </button>
                        <span class="spinner" style="float: none; visibility: hidden;"></span>
                    </div>

                    <div id="assign-world-defaults-result" style="display: none; margin-top: 15px; padding: 15px; border-radius: 5px;"></div>
                </div>

                <hr style="margin: 30px 0;">

                <div class="ql-admin-tool-section">
                    <h3><?php _e("Endereço do Coletivo (Núcleo do Território Mundo)", "quilombo-lab"); ?></h3>
                    <p class="description">
                        <?php _e("Configure o endereço principal do coletivo. Este endereço será usado como localização do núcleo do Território Mundo. Todos os usuários cadastrados são automaticamente membros da Comunidade Mundial.", "quilombo-lab"); ?>
                    </p>

                    <?php
                    // Carregar endereço atual do coletivo
                    $collective_address = get_option('ql_collective_address', '');
                    $collective_lat = get_option('ql_collective_lat', '');
                    $collective_lng = get_option('ql_collective_lng', '');
                    ?>

                    <div class="ql-collective-address-form" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 15px 0;">
                        <div class="form-field" style="margin-bottom: 15px;">
                            <label for="collective_address" style="display: block; font-weight: bold; margin-bottom: 5px;">
                                <?php _e("Endereço do Coletivo", "quilombo-lab"); ?>
                            </label>
                            <div style="display: flex; gap: 10px; align-items: flex-start;">
                                <input type="text" id="collective_address" name="collective_address"
                                       value="<?php echo esc_attr($collective_address); ?>"
                                       class="regular-text" style="flex: 1; min-width: 300px;"
                                       placeholder="<?php _e('Ex: Rua da Sede, 123, Bairro, Cidade - Estado', 'quilombo-lab'); ?>" />
                                <button type="button" id="btn-search-collective-address" class="button">
                                    <?php _e("Buscar Coordenadas", "quilombo-lab"); ?>
                                </button>
                            </div>
                        </div>

                        <div class="form-field" style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div>
                                <label for="collective_lat" style="display: block; font-weight: bold; margin-bottom: 5px;">
                                    <?php _e("Latitude", "quilombo-lab"); ?>
                                </label>
                                <input type="text" id="collective_lat" name="collective_lat"
                                       value="<?php echo esc_attr($collective_lat); ?>"
                                       class="regular-text" style="width: 150px;" readonly />
                            </div>
                            <div>
                                <label for="collective_lng" style="display: block; font-weight: bold; margin-bottom: 5px;">
                                    <?php _e("Longitude", "quilombo-lab"); ?>
                                </label>
                                <input type="text" id="collective_lng" name="collective_lng"
                                       value="<?php echo esc_attr($collective_lng); ?>"
                                       class="regular-text" style="width: 150px;" readonly />
                            </div>
                        </div>

                        <div id="collective-address-map" style="height: 300px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; display: <?php echo ($collective_lat && $collective_lng) ? 'block' : 'none'; ?>;"></div>

                        <div class="form-actions">
                            <button type="button" id="btn-save-collective-address" class="button button-primary">
                                <?php _e("Salvar Endereço do Coletivo", "quilombo-lab"); ?>
                            </button>
                            <span class="spinner" id="collective-address-spinner" style="float: none; visibility: hidden;"></span>
                        </div>

                        <div id="collective-address-result" style="display: none; margin-top: 15px; padding: 15px; border-radius: 5px;"></div>
                    </div>

                    <div class="ql-info-box" style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 15px 0;">
                        <strong><?php _e("Como funciona:", "quilombo-lab"); ?></strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li><?php _e("O endereço do coletivo define a localização física do núcleo do Território Mundo.", "quilombo-lab"); ?></li>
                            <li><?php _e("Todos os usuários que se cadastram no site são automaticamente adicionados à Comunidade Mundial.", "quilombo-lab"); ?></li>
                            <li><?php _e("A trilha do site Moodle representa a trilha do projeto do coletivo no Quilombo Lab.", "quilombo-lab"); ?></li>
                        </ul>
                    </div>
                </div>

                <hr style="margin: 30px 0;">

                <div class="ql-admin-tool-section">
                    <h3><?php _e("Verificar/Criar Território Mundo e Comunidade Mundial", "quilombo-lab"); ?></h3>
                    <p class="description">
                        <?php _e("Verifica se o Território Mundo e a Comunidade Mundial existem. Se não existirem, cria automaticamente.", "quilombo-lab"); ?>
                    </p>
                    <button type="button" id="btn-ensure-world-defaults" class="button">
                        <?php _e("Verificar e Criar se Necessário", "quilombo-lab"); ?>
                    </button>
                    <span class="spinner" style="float: none; visibility: hidden;"></span>
                    <div id="ensure-world-defaults-result" style="display: none; margin-top: 15px; padding: 15px; border-radius: 5px;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Navegação por abas
            $(".nav-tab").on("click", function(e) {
                e.preventDefault();
                $(".nav-tab").removeClass("nav-tab-active");
                $(".ql-tab-content").hide();
                $(this).addClass("nav-tab-active");
                var targetTab = $(this).data("tab") + "-tab";
                $("#" + targetTab).show();
                
                // Inicializar mapa quando aba do mapa for aberta
                if (targetTab === "map-view-tab") {
                    setTimeout(function() {
                        initGeneralMap();
                    }, 100);
                }

                // Corrigir exibição do mapa quando aba "Minha Localização" for aberta
                if (targetTab === "user-location-tab") {
                    setTimeout(function() {
                        // O mapa do usuário é gerenciado pelo shortcode em class-ql-territories.php
                        // Emitir evento para que o mapa saiba que a aba foi aberta
                        $(document).trigger('ql-user-location-tab-visible');
                    }, 100);
                }

                // Corrigir exibição do mapa quando aba "Ferramentas Admin" for aberta
                if (targetTab === "admin-tools-tab") {
                    setTimeout(function() {
                        // Emitir evento para o mapa do endereço do coletivo
                        $(document).trigger('ql-admin-tools-tab-visible');
                    }, 150);
                }
            });

            // Variáveis globais para controle do mapa
            var generalMapData = {
                map: null,
                territoriesLayer: null,
                peopleLayer: null,
                territories: [],
                users: [],
                currentFilter: ''
            };

            // Função para inicializar o mapa geral
            function initGeneralMap() {
                if (window.generalMapInitialized && window.generalMap) {
                    // Mapa já inicializado, apenas recarregar dados se necessário
                    return;
                }

                if (!window.L) {
                    console.error('Leaflet não está carregado');
                    return;
                }

                var mapElement = document.getElementById("general-territories-map");
                if (!mapElement) return;

                // Criar mapa
                var map = L.map('general-territories-map', {
                    center: [-15.7942287, -47.8821945],
                    zoom: 4,
                    maxZoom: 18
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }).addTo(map);

                window.generalMapInitialized = true;
                window.generalMap = map;
                generalMapData.map = map;

                // Criar camadas separadas para territórios e pessoas
                generalMapData.territoriesLayer = L.layerGroup().addTo(map);
                generalMapData.peopleLayer = L.layerGroup().addTo(map);

                // Carregar dados do mapa
                loadMapData();

                // Configurar eventos dos controles
                setupMapControls();
            }

            // Função para carregar dados do mapa
            function loadMapData() {
                var map = generalMapData.map;
                if (!map) return;

                // Limpar camadas
                generalMapData.territoriesLayer.clearLayers();
                generalMapData.peopleLayer.clearLayers();
                generalMapData.territories = [];
                generalMapData.users = [];

                // Ícones personalizados
                var territoryIcon = L.divIcon({
                    className: 'ql-territory-marker',
                    html: '<div style="background: #3498db; width: 24px; height: 24px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });

                var userIcon = L.divIcon({
                    className: 'ql-user-marker',
                    html: '<div style="background: #e74c3c; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>',
                    iconSize: [12, 12],
                    iconAnchor: [6, 6]
                });

                var currentUserIcon = L.divIcon({
                    className: 'ql-current-user-marker',
                    html: '<div style="background: #27ae60; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.4);"></div>',
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });

                var allMarkers = [];

                // Carregar territórios
                $.post(ajaxurl, {
                    action: 'ql_get_all_territories',
                    nonce: '<?php echo wp_create_nonce("ql_territories_nonce"); ?>'
                }, function(response) {
                    console.log('Territórios carregados:', response);

                    // Limpar e popular dropdown de filtro
                    var $filterSelect = $('#filter-by-territory');
                    $filterSelect.find('option:not(:first)').remove();

                    if (response.success && response.data && response.data.length > 0) {
                        response.data.forEach(function(territory) {
                            // Adicionar ao dropdown
                            $filterSelect.append('<option value="' + territory.id + '">' + territory.name + '</option>');

                            if (territory.lat && territory.lng) {
                                var marker = L.marker([territory.lat, territory.lng], {
                                    icon: territoryIcon,
                                    territoryId: territory.id,
                                    territoryName: territory.name
                                }).bindPopup('<div class="ql-map-popup"><h4>🗺️ ' + territory.name + '</h4><p>' + (territory.address || '') + '</p><a href="' + territory.edit_url + '" class="territory-link"><?php echo esc_js(__("Editar", "quilombo-lab")); ?></a></div>');

                                generalMapData.territoriesLayer.addLayer(marker);
                                generalMapData.territories.push({
                                    id: territory.id,
                                    name: territory.name,
                                    marker: marker
                                });
                                allMarkers.push(marker);
                            }
                        });

                        $('#territories-count').text('(' + response.data.length + ')');
                    } else {
                        $('#territories-count').text('(0)');
                    }

                    // Carregar usuários
                    $.post(ajaxurl, {
                        action: 'ql_get_all_users_locations',
                        nonce: '<?php echo wp_create_nonce("ql_territories_nonce"); ?>'
                    }, function(usersResponse) {
                        console.log('Usuários carregados:', usersResponse);

                        if (usersResponse.success && usersResponse.data && usersResponse.data.users) {
                            usersResponse.data.users.forEach(function(user) {
                                if (user.lat && user.lng) {
                                    var icon = user.is_current_user ? currentUserIcon : userIcon;
                                    var popupContent = '<div class="ql-map-popup">';
                                    popupContent += '<h4>👤 ' + user.name + '</h4>';
                                    if (user.address) {
                                        popupContent += '<p>📍 ' + user.address + '</p>';
                                    }
                                    if (user.territory) {
                                        popupContent += '<p>🗺️ ' + user.territory + '</p>';
                                    }
                                    if (user.profile_url) {
                                        popupContent += '<a href="' + user.profile_url + '" target="_blank"><?php echo esc_js(__("Ver perfil", "quilombo-lab")); ?></a>';
                                    }
                                    popupContent += '</div>';

                                    var marker = L.marker([user.lat, user.lng], {
                                        icon: icon,
                                        userId: user.id,
                                        userName: user.name,
                                        userTerritory: user.territory || '',
                                        userTerritoryId: user.territory_id || null,
                                        isCurrentUser: user.is_current_user
                                    }).bindPopup(popupContent);

                                    generalMapData.peopleLayer.addLayer(marker);
                                    generalMapData.users.push({
                                        id: user.id,
                                        name: user.name,
                                        territory: user.territory || '',
                                        territoryId: user.territory_id || null,
                                        marker: marker,
                                        isCurrentUser: user.is_current_user
                                    });
                                    allMarkers.push(marker);
                                }
                            });

                            var usersCount = usersResponse.data.total || 0;
                            $('#people-count').text('(' + usersCount + ')');

                            // Atualizar contagem na legenda
                            var territoriesCount = response.success && response.data ? response.data.length : 0;
                            $('#map-stats-count').html(territoriesCount + ' <?php echo esc_js(__("territórios", "quilombo-lab")); ?> | ' + usersCount + ' <?php echo esc_js(__("pessoas", "quilombo-lab")); ?>');
                        } else {
                            $('#people-count').text('(0)');
                        }

                        // Ajustar vista para mostrar todos os marcadores
                        if (allMarkers.length > 0) {
                            var group = new L.featureGroup(allMarkers);
                            map.fitBounds(group.getBounds().pad(0.1));
                        }

                        // Aplicar filtro se já estiver selecionado
                        applyTerritoryFilter();
                    });
                });
            }

            // Função para configurar eventos dos controles
            function setupMapControls() {
                // Toggle de territórios
                $('#toggle-territories').off('change').on('change', function() {
                    var map = generalMapData.map;
                    if (!map) return;

                    if ($(this).is(':checked')) {
                        map.addLayer(generalMapData.territoriesLayer);
                    } else {
                        map.removeLayer(generalMapData.territoriesLayer);
                    }
                });

                // Toggle de pessoas
                $('#toggle-people').off('change').on('change', function() {
                    var map = generalMapData.map;
                    if (!map) return;

                    if ($(this).is(':checked')) {
                        map.addLayer(generalMapData.peopleLayer);
                    } else {
                        map.removeLayer(generalMapData.peopleLayer);
                    }
                });

                // Filtro por território
                $('#filter-by-territory').off('change').on('change', function() {
                    generalMapData.currentFilter = $(this).val();
                    applyTerritoryFilter();
                });

                // Botão atualizar mapa
                $('#refresh-map').off('click').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('<?php echo esc_js(__("Carregando...", "quilombo-lab")); ?>');

                    loadMapData();

                    setTimeout(function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js(__("Atualizar Mapa", "quilombo-lab")); ?>');
                    }, 1000);
                });
            }

            // Função para aplicar filtro por território
            function applyTerritoryFilter() {
                var filterValue = generalMapData.currentFilter;
                var map = generalMapData.map;
                if (!map) return;

                // Se toggle de pessoas estiver desmarcado, não fazer nada
                if (!$('#toggle-people').is(':checked')) return;

                var filteredCount = 0;

                // Se não há filtro, mostrar todos
                if (!filterValue) {
                    generalMapData.users.forEach(function(user) {
                        if (!generalMapData.peopleLayer.hasLayer(user.marker)) {
                            generalMapData.peopleLayer.addLayer(user.marker);
                        }
                        filteredCount++;
                    });
                    $('#people-count').text('(' + filteredCount + ')');
                    return;
                }

                // Filtrar: buscar território selecionado
                var selectedTerritory = generalMapData.territories.find(function(t) {
                    return t.id == filterValue;
                });

                // Mostrar/ocultar marcadores de usuários baseado no ID do território
                generalMapData.users.forEach(function(user) {
                    // Usuário atual sempre visível
                    if (user.isCurrentUser) {
                        if (!generalMapData.peopleLayer.hasLayer(user.marker)) {
                            generalMapData.peopleLayer.addLayer(user.marker);
                        }
                        filteredCount++;
                        return;
                    }

                    // Verificar se o usuário pertence ao território filtrado (usando ID)
                    var belongsToTerritory = user.territoryId && user.territoryId == filterValue;

                    if (belongsToTerritory) {
                        if (!generalMapData.peopleLayer.hasLayer(user.marker)) {
                            generalMapData.peopleLayer.addLayer(user.marker);
                        }
                        filteredCount++;
                    } else {
                        generalMapData.peopleLayer.removeLayer(user.marker);
                    }
                });

                // Atualizar contagem filtrada
                $('#people-count').text('(' + filteredCount + ')');

                // Focar no território selecionado se existe
                if (selectedTerritory && selectedTerritory.marker) {
                    var latlng = selectedTerritory.marker.getLatLng();
                    map.setView(latlng, 10);
                }
            }
            
            // Modal de criação de território
            $('#create-territory-btn').on('click', function() {
                $('#create-territory-modal').show();
            });
            
            $('.ql-modal-close').on('click', function() {
                $('#create-territory-modal').hide();
            });
            
            $(document).on('click', '.ql-modal-overlay', function(e) {
                if (e.target === this) {
                    $('#create-territory-modal').hide();
                }
            });
            
            // Alternar campos conforme método de localização
            $('#location_method').on('change', function() {
                var method = $(this).val();
                $('#nucleo-selection, #address-input, #map-selection').hide();
                
                if (method === 'nucleo') {
                    $('#nucleo-selection').show();
                } else if (method === 'address') {
                    $('#address-input').show();
                } else if (method === 'map') {
                    $('#map-selection').show();
                    // Inicializar mapa se necessário
                    setTimeout(function() {
                        if (!window.territoryCreationMap && window.L) {
                            window.territoryCreationMap = L.map('territory-creation-map', {
                                center: [-15.7942287, -47.8821945],
                                zoom: 4
                            });
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(window.territoryCreationMap);
                            
                            window.territoryCreationMap.on('click', function(e) {
                                var lat = e.latlng.lat;
                                var lng = e.latlng.lng;
                                $('#territory_lat').val(lat);
                                $('#territory_lng').val(lng);
                                
                                if (window.territoryCreationMarker) {
                                    window.territoryCreationMap.removeLayer(window.territoryCreationMarker);
                                }
                                window.territoryCreationMarker = L.marker([lat, lng]).addTo(window.territoryCreationMap);
                            });
                        }
                    }, 100);
                }
            });
            
            // Carregar dados do núcleo selecionado
            $('#territory_nucleo').on('change', function() {
                var nucleoId = $(this).val();
                if (nucleoId) {
                    $.post(ajaxurl, {
                        action: 'ql_get_nucleo_data',
                        nucleo_id: nucleoId,
                        nonce: '<?php echo wp_create_nonce("ql_territories_nonce"); ?>'
                    }, function(response) {
                        if (response.success) {
                            var data = response.data;
                            $('#territory_lat').val(data.lat);
                            $('#territory_lng').val(data.lng);
                            if (!$('#territory_name').val()) {
                                $('#territory_name').val('Território ' + data.nome);
                            }
                        }
                    });
                }
            });
            
            // Buscar endereço
            $('#search-address').on('click', function() {
                var address = $('#territory_address').val();
                if (!address) {
                    alert('Digite um endereço');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('Buscando...');
                
                $.post(ajaxurl, {
                    action: 'ql_search_address',
                    address: address,
                    nonce: '<?php echo wp_create_nonce("ql_territories_nonce"); ?>'
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var result = response.data[0];
                        $('#territory_lat').val(result.lat);
                        $('#territory_lng').val(result.lon);
                        alert('Endereço encontrado!');
                    } else {
                        alert('Endereço não encontrado');
                    }
                }).fail(function() {
                    alert('Erro ao buscar endereço');
                }).always(function() {
                    $btn.prop('disabled', false).text('Buscar');
                });
            });
            
            // Submeter formulário de criação
            $('#territory-creation-form').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('action', 'ql_create_territory');
                formData.append('nonce', '<?php echo wp_create_nonce("ql_territories_nonce"); ?>');

                $.post(ajaxurl, Object.fromEntries(formData), function(response) {
                    if (response.success) {
                        alert('Território criado com sucesso!');
                        $('#create-territory-modal').hide();
                        location.reload();
                    } else {
                        alert(response.data || 'Erro ao criar território');
                    }
                }).fail(function() {
                    alert('Erro ao criar território');
                });
            });

            // Botão: Atribuir todos os usuários aos padrões mundiais
            $('#btn-assign-world-defaults').on('click', function() {
                if (!confirm('<?php echo esc_js(__("Isso irá atribuir TODOS os usuários ao Território Mundo e à Comunidade Mundial. Continuar?", "quilombo-lab")); ?>')) {
                    return;
                }

                var $btn = $(this);
                var $spinner = $btn.next('.spinner');
                var $result = $('#assign-world-defaults-result');

                $btn.prop('disabled', true);
                $spinner.css('visibility', 'visible');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'ql_assign_all_users_world_defaults',
                    nonce: '<?php echo wp_create_nonce("ql_admin_territories_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<strong><?php echo esc_js(__("Operação concluída com sucesso!", "quilombo-lab")); ?></strong><br><br>';
                        html += '<ul>';
                        html += '<li><?php echo esc_js(__("Total de usuários processados:", "quilombo-lab")); ?> ' + data.total_users + '</li>';
                        html += '<li><?php echo esc_js(__("Territórios atribuídos:", "quilombo-lab")); ?> ' + data.territory_assigned + '</li>';
                        html += '<li><?php echo esc_js(__("Membros de território adicionados:", "quilombo-lab")); ?> ' + data.territory_member_added + '</li>';
                        html += '<li><?php echo esc_js(__("Membros da comunidade adicionados:", "quilombo-lab")); ?> ' + data.community_assigned + '</li>';
                        html += '<li><?php echo esc_js(__("Já eram membros (pulados):", "quilombo-lab")); ?> ' + data.skipped + '</li>';
                        html += '</ul>';

                        if (data.errors && data.errors.length > 0) {
                            html += '<br><strong style="color: #d63638;"><?php echo esc_js(__("Avisos:", "quilombo-lab")); ?></strong><ul>';
                            data.errors.forEach(function(err) {
                                html += '<li>' + err + '</li>';
                            });
                            html += '</ul>';
                        }

                        $result.html(html).css('background', '#d4edda').show();
                    } else {
                        $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro:", "quilombo-lab")); ?></strong> ' + (response.data || '<?php echo esc_js(__("Erro desconhecido", "quilombo-lab")); ?>')).css('background', '#f8d7da').show();
                    }
                }).fail(function() {
                    $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro de conexão", "quilombo-lab")); ?></strong>').css('background', '#f8d7da').show();
                }).always(function() {
                    $btn.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                });
            });

            // Botão: Verificar/Criar padrões mundiais
            $('#btn-ensure-world-defaults').on('click', function() {
                var $btn = $(this);
                var $spinner = $btn.next('.spinner');
                var $result = $('#ensure-world-defaults-result');

                $btn.prop('disabled', true);
                $spinner.css('visibility', 'visible');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'ql_ensure_world_defaults',
                    nonce: '<?php echo wp_create_nonce("ql_admin_territories_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<strong><?php echo esc_js(__("Verificação concluída!", "quilombo-lab")); ?></strong><br><br>';
                        html += '<ul>';
                        html += '<li><?php echo esc_js(__("Território Mundo ID:", "quilombo-lab")); ?> ' + (data.territory_id || '<?php echo esc_js(__("Não encontrado", "quilombo-lab")); ?>') + '</li>';
                        html += '<li><?php echo esc_js(__("Comunidade Mundial ID:", "quilombo-lab")); ?> ' + (data.community_id || '<?php echo esc_js(__("Não encontrada", "quilombo-lab")); ?>') + '</li>';
                        html += '</ul>';
                        $result.html(html).css('background', '#d4edda').show();
                    } else {
                        $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro:", "quilombo-lab")); ?></strong> ' + (response.data || '<?php echo esc_js(__("Erro desconhecido", "quilombo-lab")); ?>')).css('background', '#f8d7da').show();
                    }
                }).fail(function() {
                    $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro de conexão", "quilombo-lab")); ?></strong>').css('background', '#f8d7da').show();
                }).always(function() {
                    $btn.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                });
            });

            // ========== ENDEREÇO DO COLETIVO ==========
            var collectiveMap = null;
            var collectiveMarker = null;

            // Inicializar mapa se já houver coordenadas
            <?php if ($collective_lat && $collective_lng): ?>
            initCollectiveMap(<?php echo floatval($collective_lat); ?>, <?php echo floatval($collective_lng); ?>);
            <?php endif; ?>

            function initCollectiveMap(lat, lng) {
                var mapContainer = document.getElementById('collective-address-map');
                if (!mapContainer) return;

                $('#collective-address-map').show();

                if (collectiveMap) {
                    collectiveMap.remove();
                }

                collectiveMap = L.map('collective-address-map').setView([lat, lng], 15);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(collectiveMap);

                collectiveMarker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(collectiveMap);

                collectiveMarker.bindPopup('<strong><?php echo esc_js(__("Sede do Coletivo", "quilombo-lab")); ?></strong>').openPopup();

                // Permitir arrastar o marcador para ajustar posição
                collectiveMarker.on('dragend', function(e) {
                    var pos = e.target.getLatLng();
                    $('#collective_lat').val(pos.lat.toFixed(8));
                    $('#collective_lng').val(pos.lng.toFixed(8));
                });

                // Corrigir renderização
                setTimeout(function() {
                    collectiveMap.invalidateSize();
                }, 200);
            }

            // Listener para quando a aba "Ferramentas Admin" ficar visível
            $(document).on('ql-admin-tools-tab-visible', function() {
                if (collectiveMap) {
                    setTimeout(function() {
                        collectiveMap.invalidateSize();
                    }, 100);
                }
            });

            // Buscar coordenadas do endereço
            $('#btn-search-collective-address').on('click', function() {
                var address = $('#collective_address').val().trim();
                if (!address) {
                    alert('<?php echo esc_js(__("Digite um endereço para buscar", "quilombo-lab")); ?>');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('<?php echo esc_js(__("Buscando...", "quilombo-lab")); ?>');

                $.post(ajaxurl, {
                    action: 'ql_search_address',
                    address: address,
                    nonce: '<?php echo wp_create_nonce("ql_territories_nonce"); ?>'
                }, function(response) {
                    if (response.success && response.data && response.data.length > 0) {
                        var result = response.data[0];
                        // Nominatim retorna 'lon' para longitude, não 'lng'
                        var longitude = result.lon || result.lng;
                        $('#collective_lat').val(result.lat);
                        $('#collective_lng').val(longitude);
                        $('#collective_address').val(result.display_name);
                        initCollectiveMap(parseFloat(result.lat), parseFloat(longitude));
                    } else {
                        alert('<?php echo esc_js(__("Endereço não encontrado. Tente ser mais específico.", "quilombo-lab")); ?>');
                    }
                }).fail(function() {
                    alert('<?php echo esc_js(__("Erro ao buscar endereço", "quilombo-lab")); ?>');
                }).always(function() {
                    $btn.prop('disabled', false).text('<?php echo esc_js(__("Buscar Coordenadas", "quilombo-lab")); ?>');
                });
            });

            // Salvar endereço do coletivo
            $('#btn-save-collective-address').on('click', function() {
                var address = $('#collective_address').val().trim();
                var lat = $('#collective_lat').val();
                var lng = $('#collective_lng').val();

                if (!address || !lat || !lng) {
                    alert('<?php echo esc_js(__("Preencha o endereço e busque as coordenadas antes de salvar.", "quilombo-lab")); ?>');
                    return;
                }

                var $btn = $(this);
                var $spinner = $('#collective-address-spinner');
                var $result = $('#collective-address-result');

                $btn.prop('disabled', true);
                $spinner.css('visibility', 'visible');
                $result.hide();

                $.post(ajaxurl, {
                    action: 'ql_save_collective_address',
                    address: address,
                    lat: lat,
                    lng: lng,
                    nonce: '<?php echo wp_create_nonce("ql_admin_territories_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        $result.html('<strong style="color: #155724;"><?php echo esc_js(__("Endereço do coletivo salvo com sucesso!", "quilombo-lab")); ?></strong> ' + (response.data.message || '')).css('background', '#d4edda').show();
                    } else {
                        $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro:", "quilombo-lab")); ?></strong> ' + (response.data || '<?php echo esc_js(__("Erro desconhecido", "quilombo-lab")); ?>')).css('background', '#f8d7da').show();
                    }
                }).fail(function() {
                    $result.html('<strong style="color: #d63638;"><?php echo esc_js(__("Erro de conexão", "quilombo-lab")); ?></strong>').css('background', '#f8d7da').show();
                }).always(function() {
                    $btn.prop('disabled', false);
                    $spinner.css('visibility', 'hidden');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Exibir lista de territórios
     */
    private function display_territories_list() {
        $territories = get_posts([
            "post_type" => "ql_territory",
            "posts_per_page" => -1,
            "post_status" => "any"
        ]);
        
        if (empty($territories)) {
            ?>
            <div class="ql-empty-state">
                <h3><?php _e("Nenhum território criado ainda", "quilombo-lab"); ?></h3>
                <p><?php _e("Crie o primeiro território para começar a organização territorial do coletivo.", "quilombo-lab"); ?></p>
                <a href="<?php echo admin_url("post-new.php?post_type=ql_territory"); ?>" class="button button-primary">
                    <?php _e("Criar Primeiro Território", "quilombo-lab"); ?>
                </a>
            </div>
            <?php
            return;
        }
        
        echo "<div class=\"ql-territories-grid\">";
        foreach ($territories as $territory) {
            $territory_type = wp_get_post_terms($territory->ID, "territory_type");
            $type_name = $territory_type ? $territory_type[0]->name : "";
            $address = get_post_meta($territory->ID, "_territory_address", true);
            $auto_assign = get_post_meta($territory->ID, "_territory_auto_assign", true);
            $user_count = $this->count_territory_users($territory->ID);
            
            echo "<div class=\"ql-territory-card\">";
            echo "<h3><a href=\"" . admin_url("post.php?post=" . $territory->ID . "&action=edit") . "\">" . esc_html($territory->post_title) . "</a></h3>";
            
            if ($type_name) {
                echo "<div class=\"territory-type\">" . esc_html($type_name) . "</div>";
            }
            
            if ($address) {
                echo "<div class=\"territory-address\">📍 " . esc_html($address) . "</div>";
            }
            
            echo "<div class=\"territory-members\">👥 " . sprintf(_n("%d pessoa", "%d pessoas", $user_count, "quilombo-lab"), $user_count) . "</div>";
            
            if ($auto_assign) {
                echo "<div class=\"territory-auto-assign\">⚡ Auto-atribuição ativa</div>";
            }
            
            echo "<div class=\"territory-actions\">";
            echo "<a href=\"" . admin_url("post.php?post=" . $territory->ID . "&action=edit") . "\" class=\"button\">Editar</a>";
            echo "<a href=\"" . get_permalink($territory->ID) . "\" class=\"button\" target=\"_blank\">Ver</a>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
        
        echo "<style>
        .ql-territories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .ql-territory-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ql-territory-card h3 { margin-top: 0; }
        .territory-type {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 10px;
        }
        .territory-address, .territory-members, .territory-auto-assign {
            margin: 8px 0;
            color: #666;
        }
        .territory-auto-assign {
            color: #27ae60;
            font-weight: bold;
        }
        .territory-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .territory-actions .button {
            margin-right: 10px;
        }
        </style>";
    }
    
    /**
     * Contar usuários em um território
     */
    private function count_territory_users($territory_id) {
        $users = get_users([
            "meta_query" => [
                [
                    "key" => "user_territory_id",
                    "value" => $territory_id,
                    "compare" => "="
                ]
            ]
        ]);
        
        return count($users);
    }
    
    /**
     * Renderizar opções de tipos de território
     */
    private function render_territory_type_options() {
        $territory_types = get_terms([
            'taxonomy' => 'territory_type',
            'hide_empty' => false
        ]);
        
        foreach ($territory_types as $type) {
            echo '<option value="' . esc_attr($type->slug) . '">' . esc_html($type->name) . '</option>';
        }
    }
    
    /**
     * Renderizar opções de núcleos com endereço
     */
    private function render_nucleos_with_address_options() {
        global $wpdb;
        
        $nucleos = $wpdb->get_results("
            SELECT i.id, i.nome, im.endereco_fisico 
            FROM {$wpdb->prefix}ql_instances i
            LEFT JOIN {$wpdb->prefix}ql_instance_meta im ON i.id = im.instance_id
            WHERE i.tipo = 'nucleo' 
            AND i.status = 'ativo'
            AND (im.endereco_fisico IS NOT NULL AND im.endereco_fisico != '')
            ORDER BY i.nome ASC
        ");
        
        foreach ($nucleos as $nucleo) {
            echo '<option value="' . esc_attr($nucleo->id) . '">' . 
                 esc_html($nucleo->nome) . ' - ' . esc_html($nucleo->endereco_fisico) . 
                 '</option>';
        }
    }
    
}
