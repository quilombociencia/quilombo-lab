<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para funcionalidades públicas do Quilombo Laboratório
 */
class QL_Public {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_filter('the_content', [$this, 'filter_project_content']);
        add_action('template_redirect', [$this, 'handle_project_redirects']);
        
        // Hooks para sincronização com posts públicos
        add_action('ql_project_created', [$this, 'sync_public_post_on_create'], 10, 2);
        add_action('ql_project_updated', [$this, 'sync_public_post_on_update'], 10, 2);
        add_action('ql_project_archived', [$this, 'sync_public_post_on_archive'], 10, 1);
    }
    
    public function init() {
        // Registrar custom post types públicos se necessário
        $this->register_public_post_types();
        
        // Adicionar rewrite rules para URLs amigáveis
        add_rewrite_rule(
            '^lab/projeto/([^/]+)/?$',
            'index.php?pagename=lab&ql_project_slug=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^lab/quadro/([^/]+)/?$',
            'index.php?pagename=lab&ql_board_slug=$matches[1]',
            'top'
        );
        
        // Adicionar query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'ql_project_slug';
            $vars[] = 'ql_board_slug';
            return $vars;
        });
    }
    
    /**
     * Registrar post types públicos
     */
    private function register_public_post_types() {
        // Post type para projetos públicos
        register_post_type('ql_public_project', [
            'label' => __('Projetos Públicos', 'quilombo-lab'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => false, // Não mostrar no admin (gerenciado pelo plugin)
            'show_in_menu' => false,
            'query_var' => true,
            'rewrite' => ['slug' => 'projeto'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'labels' => [
                'name' => __('Projetos Públicos', 'quilombo-lab'),
                'singular_name' => __('Projeto Público', 'quilombo-lab')
            ]
        ]);
    }
    
    /**
     * Enfileirar scripts e estilos públicos
     */
    public function enqueue_scripts() {
        // CSS público
        wp_enqueue_style(
            'ql-public-css',
            QL_PLUGIN_URL . 'assets/css/public.css',
            [],
            QL_PLUGIN_VERSION
        );
        
        // JavaScript público
        wp_enqueue_script(
            'ql-public-js',
            QL_PLUGIN_URL . 'assets/js/public.js',
            ['jquery'],
            QL_PLUGIN_VERSION,
            true
        );
        
        // Localizar script com dados AJAX
        wp_localize_script('ql-public-js', 'ql_public_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ql_public_nonce'),
            'strings' => [
                'loading' => __('Carregando...', 'quilombo-lab'),
                'error' => __('Erro ao carregar dados', 'quilombo-lab'),
                'no_projects' => __('Nenhum projeto encontrado', 'quilombo-lab')
            ]
        ]);
        
        // Enfileirar Font Awesome para ícones
        $cdn_settings = QL_Config::get_cdn_settings();
        wp_enqueue_style(
            'font-awesome',
            $cdn_settings['fontawesome'],
            [],
            '6.0.0'
        );
    }
    
    /**
     * Filtrar conteúdo de projetos
     */
    public function filter_project_content($content) {
        global $post;
        
        // Só processar em páginas específicas
        if (!is_singular() || !$post) {
            return $content;
        }
        
        // Verificar se é uma página de projeto
        $project_slug = get_query_var('ql_project_slug');
        $board_slug = get_query_var('ql_board_slug');
        
        if ($project_slug) {
            return $this->render_project_public_page($project_slug) . $content;
        }
        
        if ($board_slug) {
            return $this->render_board_public_page($board_slug) . $content;
        }
        
        return $content;
    }
    
    /**
     * Renderizar página pública do projeto
     */
    private function render_project_public_page($project_slug) {
        if (!class_exists('QL_Project')) {
            return '<p class="ql-error">' . __('Funcionalidade não disponível', 'quilombo-lab') . '</p>';
        }
        
        global $wpdb;
        
        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE slug = %s AND visibility = 'public'",
            $project_slug
        ), ARRAY_A);
        
        if (!$project) {
            return '<p class="ql-error">' . __('Projeto não encontrado ou não é público', 'quilombo-lab') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-public-project">
            <div class="ql-project-header">
                <h1 class="ql-project-title"><?php echo esc_html($project['name']); ?></h1>
                
                <?php if ($project['description']): ?>
                    <div class="ql-project-description">
                        <?php echo wp_kses_post($project['description']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="ql-project-meta">
                    <div class="ql-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?php echo sprintf(__('Criado em %s', 'quilombo-lab'), date_i18n('d/m/Y', strtotime($project['created_at']))); ?></span>
                    </div>
                    
                    <?php if ($project['start_date']): ?>
                        <div class="ql-meta-item">
                            <i class="fas fa-play"></i>
                            <span><?php echo sprintf(__('Início: %s', 'quilombo-lab'), date_i18n('d/m/Y', strtotime($project['start_date']))); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="ql-meta-item">
                        <span class="ql-status-badge ql-status-<?php echo esc_attr($project['status']); ?>">
                            <?php echo esc_html(ucfirst($project['status'])); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="ql-project-stats">
                <?php echo $this->render_project_stats($project['id']); ?>
            </div>
            
            <div class="ql-project-boards">
                <h2><?php _e('Quadros do Projeto', 'quilombo-lab'); ?></h2>
                <?php echo $this->render_public_boards($project['id']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar estatísticas do projeto
     */
    private function render_project_stats($project_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT b.id) as total_boards,
                COUNT(DISTINCT t.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
                COUNT(DISTINCT CASE WHEN t.status = 'in_progress' THEN t.id END) as active_tasks
             FROM {$wpdb->prefix}ql_projects p
             LEFT JOIN {$wpdb->prefix}ql_boards b ON p.id = b.project_id
             LEFT JOIN {$wpdb->prefix}ql_tasks t ON b.id = t.board_id
             WHERE p.id = %d",
            $project_id
        ), ARRAY_A);
        
        ob_start();
        ?>
        <div class="ql-stats-grid">
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats['total_boards']); ?></div>
                <div class="ql-stat-label"><?php _e('Quadros', 'quilombo-lab'); ?></div>
            </div>
            
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats['total_tasks']); ?></div>
                <div class="ql-stat-label"><?php _e('Tarefas', 'quilombo-lab'); ?></div>
            </div>
            
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats['completed_tasks']); ?></div>
                <div class="ql-stat-label"><?php _e('Concluídas', 'quilombo-lab'); ?></div>
            </div>
            
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats['active_tasks']); ?></div>
                <div class="ql-stat-label"><?php _e('Em Andamento', 'quilombo-lab'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar quadros públicos
     */
    private function render_public_boards($project_id) {
        if (!class_exists('QL_Board')) {
            return '<p class="ql-error">' . __('Funcionalidade não disponível', 'quilombo-lab') . '</p>';
        }
        
        $board_model = QL_Board::get_instance();
        $boards = $board_model->get_by_project($project_id);
        
        if (empty($boards)) {
            return '<p class="ql-empty">' . __('Nenhum quadro disponível', 'quilombo-lab') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-boards-grid">
            <?php foreach ($boards as $board): ?>
                <div class="ql-board-card">
                    <div class="ql-board-header">
                        <h3><?php echo esc_html($board['name']); ?></h3>
                        <?php if ($board['description']): ?>
                            <p><?php echo esc_html($board['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ql-board-stats">
                        <span class="ql-stat-item">
                            <i class="fas fa-columns"></i>
                            <?php echo count($board['columns']); ?> <?php _e('colunas', 'quilombo-lab'); ?>
                        </span>
                        <span class="ql-stat-item">
                            <i class="fas fa-tasks"></i>
                            <?php echo $board['tasks_count']; ?> <?php _e('tarefas', 'quilombo-lab'); ?>
                        </span>
                    </div>
                    
                    <div class="ql-board-actions">
                        <a href="<?php echo esc_url(home_url("/lab/quadro/{$board['slug']}")); ?>" 
                           class="ql-btn ql-btn-primary">
                            <i class="fas fa-eye"></i>
                            <?php _e('Ver Quadro', 'quilombo-lab'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar página pública do quadro
     */
    private function render_board_public_page($board_slug) {
        if (!class_exists('QL_Board')) {
            return '<p class="ql-error">' . __('Funcionalidade não disponível', 'quilombo-lab') . '</p>';
        }
        
        global $wpdb;
        
        $board = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, p.name as project_name, p.slug as project_slug 
             FROM {$wpdb->prefix}ql_boards b
             JOIN {$wpdb->prefix}ql_projects p ON b.project_id = p.id
             WHERE b.slug = %s AND p.visibility = 'public'",
            $board_slug
        ), ARRAY_A);
        
        if (!$board) {
            return '<p class="ql-error">' . __('Quadro não encontrado ou não é público', 'quilombo-lab') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-public-board">
            <div class="ql-board-header">
                <nav class="ql-breadcrumb">
                    <a href="<?php echo esc_url(home_url("/lab/projeto/{$board['project_slug']}")); ?>">
                        <?php echo esc_html($board['project_name']); ?>
                    </a>
                    <span class="separator">›</span>
                    <span class="current"><?php echo esc_html($board['name']); ?></span>
                </nav>
                
                <h1 class="ql-board-title"><?php echo esc_html($board['name']); ?></h1>
                
                <?php if ($board['description']): ?>
                    <div class="ql-board-description">
                        <?php echo wp_kses_post($board['description']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="ql-kanban-board" id="ql-public-kanban">
                <?php echo $this->render_kanban_columns($board['id']); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar colunas Kanban
     */
    private function render_kanban_columns($board_id) {
        if (!class_exists('QL_Column')) {
            return '<p class="ql-error">' . __('Funcionalidade não disponível', 'quilombo-lab') . '</p>';
        }
        
        $column_model = QL_Column::get_instance();
        $columns = $column_model->get_by_board($board_id);
        
        if (empty($columns)) {
            return '<p class="ql-empty">' . __('Nenhuma coluna configurada', 'quilombo-lab') . '</p>';
        }
        
        ob_start();
        
        foreach ($columns as $column) {
            ?>
            <div class="ql-kanban-column" data-column-id="<?php echo $column['id']; ?>">
                <div class="ql-column-header" style="background-color: <?php echo esc_attr($column['color']); ?>;">
                    <h3 class="ql-column-title">
                        <?php echo esc_html($column['name']); ?>
                        <span class="ql-column-count"><?php echo count($column['tasks']); ?></span>
                    </h3>
                </div>
                
                <div class="ql-tasks-list">
                    <?php foreach ($column['tasks'] as $task): ?>
                        <div class="ql-kanban-task" data-task-id="<?php echo $task['id']; ?>">
                            <div class="ql-task-title"><?php echo esc_html($task['title']); ?></div>
                            
                            <?php if ($task['description']): ?>
                                <div class="ql-task-description">
                                    <?php echo wp_trim_words(strip_tags($task['description']), 20); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ql-task-meta">
                                <div class="ql-task-priority priority-<?php echo $task['priority']; ?>">
                                    <?php echo $this->get_priority_label($task['priority']); ?>
                                </div>
                                
                                <?php if ($task['assigned_user_name']): ?>
                                    <div class="ql-task-assignee">
                                        <span class="ql-task-avatar">
                                            <?php echo substr($task['assigned_user_name'], 0, 1); ?>
                                        </span>
                                        <span class="ql-assignee-name"><?php echo esc_html($task['assigned_user_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($task['due_date']): ?>
                                    <div class="ql-task-due-date <?php echo (strtotime($task['due_date']) < time()) ? 'overdue' : ''; ?>">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo date_i18n('d/m', strtotime($task['due_date'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Obter label da prioridade
     */
    private function get_priority_label($priority) {
        $labels = [
            1 => __('Baixa', 'quilombo-lab'),
            2 => __('Normal', 'quilombo-lab'),
            3 => __('Alta', 'quilombo-lab'),
            4 => __('Urgente', 'quilombo-lab')
        ];
        
        return $labels[$priority] ?? $labels[2];
    }
    
    /**
     * Lidar com redirecionamentos de projeto
     */
    public function handle_project_redirects() {
        $project_slug = get_query_var('ql_project_slug');
        $board_slug = get_query_var('ql_board_slug');
        
        if ($project_slug || $board_slug) {
            // Verificar se existe uma página "laboratorio" 
            $laboratorio_page = get_page_by_path('laboratorio');
            
            if (!$laboratorio_page) {
                // Criar página automaticamente se não existir
                $this->create_laboratorio_page();
            }
        }
    }
    
    /**
     * Criar página do laboratório automaticamente
     */
    private function create_laboratorio_page() {
        $page_data = [
            'post_title' => __('Laboratório de Projetos', 'quilombo-lab'),
            'post_content' => __('Esta página exibe os projetos públicos do Laboratório.', 'quilombo-lab'),
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_name' => 'laboratorio'
        ];
        
        wp_insert_post($page_data);
    }
    
    /**
     * Sincronizar criação de post público quando projeto é criado
     */
    public function sync_public_post_on_create($project_id, $project_data) {
        // Só criar post se o projeto for público
        if (empty($project_data['visibility']) || $project_data['visibility'] !== 'public') {
            return;
        }
        
        $this->create_or_update_public_post($project_id, $project_data);
    }
    
    /**
     * Sincronizar atualização de post público quando projeto é atualizado
     */
    public function sync_public_post_on_update($project_id, $project_data = null) {
        global $wpdb;
        
        // Buscar dados atuais do projeto se não fornecidos
        $project = $project_data ?: $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $project_id
        ), ARRAY_A);
        
        if (!$project) {
            return;
        }
        
        // Se projeto foi arquivado, remover post público
        if ($project['status'] === 'archived') {
            $this->remove_public_post($project_id);
            return;
        }
        
        if ($project['visibility'] === 'public') {
            // Projeto é público - criar ou atualizar post
            $this->create_or_update_public_post($project_id, $project);
        } else {
            // Projeto não é mais público - remover post se existir
            $this->remove_public_post($project_id);
        }
    }
    
    /**
     * Sincronizar arquivamento - remover post público
     */
    public function sync_public_post_on_archive($project_id) {
        $this->remove_public_post($project_id);
    }
    
    /**
     * Criar ou atualizar post público
     */
    private function create_or_update_public_post($project_id, $project_data) {
        // Buscar post existente
        $existing_post = get_posts([
            'post_type' => 'ql_public_project',
            'meta_query' => [
                [
                    'key' => '_ql_project_id',
                    'value' => $project_id,
                    'compare' => '='
                ]
            ],
            'post_status' => ['publish', 'draft', 'trash'],
            'numberposts' => 1
        ]);
        
        $post_id = $existing_post ? $existing_post[0]->ID : null;
        
        // Dados do post
        $post_data = [
            'post_title' => $project_data['name'],
            'post_content' => $this->generate_project_content($project_data),
            'post_excerpt' => wp_trim_words(strip_tags($project_data['description'] ?? ''), 30),
            'post_status' => 'publish',
            'post_type' => 'ql_public_project',
            'post_name' => $project_data['slug']
        ];
        
        if ($post_id) {
            // Atualizar post existente
            $post_data['ID'] = $post_id;
            wp_update_post($post_data);
        } else {
            // Criar novo post
            $post_id = wp_insert_post($post_data);
        }
        
        if ($post_id && !is_wp_error($post_id)) {
            // Adicionar meta dados
            update_post_meta($post_id, '_ql_project_id', $project_id);
            update_post_meta($post_id, '_ql_project_status', $project_data['status']);
            update_post_meta($post_id, '_ql_project_owner', $project_data['owner_id']);
            update_post_meta($post_id, '_ql_project_created', $project_data['created_at']);
            
            if (!empty($project_data['start_date'])) {
                update_post_meta($post_id, '_ql_project_start_date', $project_data['start_date']);
            }
            
            if (!empty($project_data['moodle_course_id'])) {
                update_post_meta($post_id, '_ql_moodle_course_id', $project_data['moodle_course_id']);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Remover post público
     */
    private function remove_public_post($project_id) {
        $existing_post = get_posts([
            'post_type' => 'ql_public_project',
            'meta_query' => [
                [
                    'key' => '_ql_project_id',
                    'value' => $project_id,
                    'compare' => '='
                ]
            ],
            'post_status' => ['publish', 'draft'],
            'numberposts' => 1
        ]);
        
        if ($existing_post) {
            wp_delete_post($existing_post[0]->ID, true);
        }
    }
    
    /**
     * Gerar conteúdo do post do projeto
     */
    private function generate_project_content($project_data) {
        $content = '';
        
        // Se é o projeto do coletivo, adicionar destaque
        if (self::is_collective_project($project_data['id'])) {
            $content .= "**🏠 Projeto Principal do Coletivo Quilombo Ciência**\n\n";
            $content .= "*Este é o projeto que representa a gestão e administração do coletivo. Qualquer trilha pode assumir essa responsabilidade conforme a estrutura organizativa do Quilombo Ciência.*\n\n";
            $content .= "---\n\n";
        }
        
        if (!empty($project_data['description'])) {
            $content .= $project_data['description'] . "\n\n";
        }
        
        // Adicionar shortcode para exibir o projeto
        $content .= "[ql_projeto id=\"{$project_data['id']}\"]";
        
        return $content;
    }
    
    /**
     * Sincronizar todos os projetos públicos existentes
     */
    public function sync_all_public_projects() {
        global $wpdb;
        
        $public_projects = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE visibility = 'public' AND status != 'archived'",
            ARRAY_A
        );
        
        $synced = 0;
        foreach ($public_projects as $project) {
            $post_id = $this->create_or_update_public_post($project['id'], $project);
            if ($post_id) {
                $synced++;
            }
        }
        
        return $synced;
    }
    
    /**
     * Verificar se um projeto é o projeto do coletivo
     */
    public static function is_collective_project($project_id) {
        $settings = get_option('quilombo_laboratorio_settings', []);
        $projeto_coletivo_id = $settings['projeto_coletivo_id'] ?? 0;
        
        return $projeto_coletivo_id > 0 && $projeto_coletivo_id == $project_id;
    }
    
    /**
     * Obter o projeto do coletivo
     */
    public static function get_collective_project() {
        $settings = get_option('quilombo_laboratorio_settings', []);
        $projeto_coletivo_id = $settings['projeto_coletivo_id'] ?? 0;
        
        if ($projeto_coletivo_id <= 0) {
            return null;
        }
        
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $projeto_coletivo_id
        ), ARRAY_A);
    }
}