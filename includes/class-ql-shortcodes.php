<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gerenciar shortcodes do plugin
 */
class QL_Shortcodes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_shortcodes']);
    }
    
    /**
     * Registrar todos os shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('ql_dashboard', [$this, 'dashboard_shortcode']);
        add_shortcode('ql_projetos', [$this, 'projects_shortcode']);
        add_shortcode('ql_projeto', [$this, 'single_project_shortcode']);
        add_shortcode('ql_board', [$this, 'board_shortcode']);
        add_shortcode('ql_meus_projetos', [$this, 'user_projects_shortcode']);
        add_shortcode('ql_estatisticas', [$this, 'stats_shortcode']);
    }
    
    /**
     * Shortcode para dashboard principal
     */
    public function dashboard_shortcode($atts) {
        $atts = shortcode_atts([
            'mostrar' => 'todos', // todos, meus, publicos
            'limite' => 10
        ], $atts);
        
        if (!is_user_logged_in() && $atts['mostrar'] === 'meus') {
            return '<p>Faça login para ver seus projetos.</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-dashboard">
            <h2>Laboratório de Projetos</h2>
            
            <?php if (is_user_logged_in()): ?>
                <div class="ql-dashboard-actions">
                    <a href="#" class="button button-primary ql-novo-projeto">
                        Novo Projeto
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="ql-projetos-grid">
                <?php echo $this->render_projects_list($atts); ?>
            </div>
            
            <?php if (current_user_can('ql_view_reports')): ?>
                <div class="ql-dashboard-stats">
                    <h3>Estatísticas Gerais</h3>
                    <?php echo $this->render_general_stats(); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para listar projetos
     */
    public function projects_shortcode($atts) {
        $atts = shortcode_atts([
            'tipo' => 'todos',
            'limite' => 12,
            'colunas' => 3,
            'mostrar_meta' => 'sim'
        ], $atts);
        
        ob_start();
        ?>
        <div class="ql-projetos-lista" style="--colunas: <?php echo esc_attr($atts['colunas']); ?>">
            <?php echo $this->render_projects_list($atts); ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para projeto individual
     */
    public function single_project_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'slug' => '',
            'mostrar_board' => 'sim',
            'mostrar_timeline' => 'sim'
        ], $atts);
        
        $project = null;
        
        if ($atts['id']) {
            $project = $this->get_project_by_id($atts['id']);
        } elseif ($atts['slug']) {
            $project = $this->get_project_by_slug($atts['slug']);
        }
        
        if (!$project) {
            return '<p>Projeto não encontrado.</p>';
        }
        
        // Verificar permissões
        if (!$this->user_can_view_project($project)) {
            return '<p>Você não tem permissão para ver este projeto.</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-projeto-single">
            <header class="ql-projeto-header">
                <h1><?php echo esc_html($project->nome); ?></h1>
                <div class="ql-projeto-meta">
                    <span class="status status-<?php echo esc_attr($project->status); ?>">
                        <?php echo $this->get_status_label($project->status); ?>
                    </span>
                    <span class="trilha">
                        Trilha: <?php echo esc_html($project->trilha_nome ?? 'N/A'); ?>
                    </span>
                </div>
            </header>
            
            <?php if ($project->descricao): ?>
                <div class="ql-projeto-descricao">
                    <?php echo wp_kses_post(wpautop($project->descricao)); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['mostrar_board'] === 'sim'): ?>
                <div class="ql-projeto-board">
                    <h3>Quadro de Tarefas</h3>
                    <?php echo $this->render_project_board($project->id); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['mostrar_timeline'] === 'sim'): ?>
                <div class="ql-projeto-timeline">
                    <h3>Linha do Tempo</h3>
                    <?php echo $this->render_project_timeline($project->id); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para board/quadro kanban
     */
    public function board_shortcode($atts) {
        $atts = shortcode_atts([
            'projeto_id' => 0,
            'altura' => '600px',
            'modo' => 'completo' // completo, simples
        ], $atts);
        
        if (!$atts['projeto_id']) {
            return '<p>ID do projeto é obrigatório.</p>';
        }
        
        $project = $this->get_project_by_id($atts['projeto_id']);
        if (!$project || !$this->user_can_view_project($project)) {
            return '<p>Projeto não encontrado ou sem permissão.</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-board-container" style="height: <?php echo esc_attr($atts['altura']); ?>">
            <?php echo $this->render_project_board($atts['projeto_id'], $atts['modo']); ?>
        </div>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para projetos do usuário
     */
    public function user_projects_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Faça login para ver seus projetos.</p>';
        }
        
        $atts = shortcode_atts([
            'status' => 'todos',
            'limite' => 10
        ], $atts);
        
        $user_id = get_current_user_id();
        $projects = $this->get_user_projects($user_id, $atts);
        
        if (empty($projects)) {
            return '<p>Você ainda não participa de nenhum projeto.</p>';
        }
        
        ob_start();
        ?>
        <div class="ql-meus-projetos">
            <h3>Meus Projetos</h3>
            <div class="ql-projetos-lista">
                <?php foreach ($projects as $project): ?>
                    <div class="ql-projeto-card">
                        <h4>
                            <a href="<?php echo $this->get_project_url($project); ?>">
                                <?php echo esc_html($project->nome); ?>
                            </a>
                        </h4>
                        <div class="ql-projeto-meta">
                            <span class="status status-<?php echo esc_attr($project->status); ?>">
                                <?php echo $this->get_status_label($project->status); ?>
                            </span>
                            <span class="role">
                                Papel: <?php echo esc_html($project->user_role ?? 'Membro'); ?>
                            </span>
                        </div>
                        <?php if ($project->descricao): ?>
                            <p class="ql-projeto-excerpt">
                                <?php echo esc_html(wp_trim_words($project->descricao, 20)); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode para estatísticas
     */
    public function stats_shortcode($atts) {
        $atts = shortcode_atts([
            'tipo' => 'geral', // geral, projeto, usuario
            'projeto_id' => 0,
            'periodo' => 30 // dias
        ], $atts);
        
        ob_start();
        ?>
        <div class="ql-estatisticas">
            <?php if ($atts['tipo'] === 'geral'): ?>
                <?php echo $this->render_general_stats(); ?>
            <?php elseif ($atts['tipo'] === 'projeto' && $atts['projeto_id']): ?>
                <?php echo $this->render_project_stats($atts['projeto_id']); ?>
            <?php elseif ($atts['tipo'] === 'usuario' && is_user_logged_in()): ?>
                <?php echo $this->render_user_stats(get_current_user_id()); ?>
            <?php else: ?>
                <p>Configuração de estatísticas inválida.</p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Métodos auxiliares para renderização
    
    private function render_projects_list($atts) {
        $projects = $this->get_projects($atts);
        
        if (empty($projects)) {
            return '<p>Nenhum projeto encontrado.</p>';
        }
        
        $output = '';
        foreach ($projects as $project) {
            $output .= $this->render_project_card($project, $atts);
        }
        
        return $output;
    }
    
    private function render_project_card($project, $atts = []) {
        ob_start();
        
        // Obter imagem do projeto
        $featured_image_id = isset($project->featured_image_id) ? $project->featured_image_id : null;
        $image_url = null;

        if ($featured_image_id) {
            $image_url = wp_get_attachment_image_url($featured_image_id, 'medium');
        }
        
        ?>
        <div class="ql-projeto-card">
            <?php if ($image_url): ?>
                <div class="ql-projeto-image">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($project->nome); ?>" />
                </div>
            <?php endif; ?>
            
            <div class="ql-projeto-content">
                <h4>
                    <a href="<?php echo $this->get_project_url($project); ?>">
                        <?php echo esc_html($project->nome); ?>
                    </a>
                </h4>
            
            <?php if (!empty($atts['mostrar_meta']) && $atts['mostrar_meta'] === 'sim'): ?>
                <div class="ql-projeto-meta">
                    <span class="status status-<?php echo esc_attr($project->status); ?>">
                        <?php echo $this->get_status_label($project->status); ?>
                    </span>
                    <?php if ($project->trilha_nome): ?>
                        <span class="trilha">
                            <?php echo esc_html($project->trilha_nome); ?>
                        </span>
                    <?php endif; ?>
                    <span class="data">
                        Criado: <?php echo date_i18n('d/m/Y', strtotime($project->data_criacao)); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <?php if ($project->descricao): ?>
                <p class="ql-projeto-excerpt">
                    <?php echo esc_html(wp_trim_words($project->descricao, 20)); ?>
                </p>
            <?php endif; ?>
            
                <div class="ql-projeto-actions">
                    <a href="<?php echo $this->get_project_url($project); ?>" class="button">
                        Ver Projeto
                    </a>
                    <?php if ($this->user_can_edit_project($project)): ?>
                        <a href="<?php echo $this->get_project_edit_url($project); ?>" class="button">
                            Editar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_project_board($project_id, $modo = 'completo') {
        if (!class_exists('QL_Board')) {
            return '<p class="ql-empty">' . __('Quadro Kanban não disponível.', 'quilombo-lab') . '</p>';
        }

        $board_model = QL_Board::get_instance();
        $boards = $board_model->get_by_project($project_id);

        if (empty($boards)) {
            return '<p class="ql-empty">' . __('Nenhum quadro disponível para este projeto.', 'quilombo-lab') . '</p>';
        }

        // Usar o primeiro board (default)
        $board = $boards[0];

        if (empty($board['columns'])) {
            return '<p class="ql-empty">' . __('Quadro sem colunas configuradas.', 'quilombo-lab') . '</p>';
        }

        ob_start();
        ?>
        <div class="ql-public-kanban-board">
            <?php foreach ($board['columns'] as $column): ?>
                <div class="ql-public-kanban-column">
                    <div class="ql-public-column-header" style="background-color: <?php echo esc_attr($column['color']); ?>;">
                        <h3 class="ql-public-column-title"><?php echo esc_html($column['name']); ?></h3>
                        <span class="ql-public-column-count"><?php echo count($column['tasks']); ?></span>
                    </div>
                    <div class="ql-public-tasks-list">
                        <?php if (!empty($column['tasks'])): ?>
                            <?php foreach ($column['tasks'] as $task): ?>
                                <div class="ql-public-task">
                                    <div class="ql-public-task-title"><?php echo esc_html($task['title']); ?></div>
                                    <?php if (!empty($task['description'])): ?>
                                        <div class="ql-public-task-description">
                                            <?php echo esc_html(wp_trim_words(strip_tags($task['description']), 20)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ql-public-task-meta">
                                        <div class="ql-public-task-priority priority-<?php echo esc_attr($task['priority'] ?? 2); ?>">
                                            <?php echo esc_html($this->get_priority_label($task['priority'] ?? 2)); ?>
                                        </div>
                                        <?php if (!empty($task['due_date'])): ?>
                                            <div class="ql-public-task-due-date <?php echo (strtotime($task['due_date']) < time()) ? 'overdue' : ''; ?>">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date_i18n('d/m', strtotime($task['due_date'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_project_timeline($project_id) {
        global $wpdb;

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT t.title, t.status, t.updated_at, t.created_at,
                    u.display_name as user_name
             FROM {$wpdb->prefix}ql_tasks t
             INNER JOIN {$wpdb->prefix}ql_columns c ON t.column_id = c.id
             INNER JOIN {$wpdb->prefix}ql_boards b ON c.board_id = b.id
             LEFT JOIN {$wpdb->users} u ON t.assigned_user_id = u.ID
             WHERE b.project_id = %d
             ORDER BY t.updated_at DESC
             LIMIT 10",
            $project_id
        ));

        if (empty($tasks)) {
            return '<p class="ql-empty">' . __('Nenhuma atividade recente.', 'quilombo-lab') . '</p>';
        }

        ob_start();
        ?>
        <div class="ql-timeline-list">
            <?php foreach ($tasks as $task): ?>
                <div class="ql-timeline-item">
                    <div class="ql-timeline-date">
                        <?php echo date_i18n('d/m/Y H:i', strtotime($task->updated_at)); ?>
                    </div>
                    <div class="ql-timeline-title">
                        <?php echo esc_html($task->title); ?>
                        <span class="status status-<?php echo esc_attr($task->status); ?>">
                            <?php echo esc_html($this->get_status_label($task->status)); ?>
                        </span>
                    </div>
                    <?php if ($task->user_name): ?>
                        <div class="ql-timeline-user">
                            <?php echo esc_html($task->user_name); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_general_stats() {
        global $wpdb;

        // Stats básicas
        $total_projetos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects");
        $projetos_ativos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects WHERE status = 'active'");
        $total_tarefas = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks");
        $tarefas_concluidas = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE status = 'completed'");
        
        ob_start();
        ?>
        <div class="ql-stats-grid">
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo esc_html($total_projetos ?: 0); ?></div>
                <div class="ql-stat-label">Total de Projetos</div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo esc_html($projetos_ativos ?: 0); ?></div>
                <div class="ql-stat-label">Projetos Ativos</div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo esc_html($total_tarefas ?: 0); ?></div>
                <div class="ql-stat-label">Total de Tarefas</div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo esc_html($tarefas_concluidas ?: 0); ?></div>
                <div class="ql-stat-label">Tarefas Concluídas</div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
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
             LEFT JOIN {$wpdb->prefix}ql_columns c ON b.id = c.board_id
             LEFT JOIN {$wpdb->prefix}ql_tasks t ON c.id = t.column_id
             WHERE p.id = %d",
            $project_id
        ));

        ob_start();
        ?>
        <div class="ql-stats-grid">
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats->total_boards); ?></div>
                <div class="ql-stat-label"><?php _e('Quadros', 'quilombo-lab'); ?></div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats->total_tasks); ?></div>
                <div class="ql-stat-label"><?php _e('Tarefas', 'quilombo-lab'); ?></div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats->completed_tasks); ?></div>
                <div class="ql-stat-label"><?php _e('Concluídas', 'quilombo-lab'); ?></div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($stats->active_tasks); ?></div>
                <div class="ql-stat-label"><?php _e('Em Andamento', 'quilombo-lab'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function render_user_stats($user_id) {
        global $wpdb;

        // Projetos como owner
        $owned_projects = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects WHERE owner_id = %d AND status != 'archived'",
            $user_id
        ));

        // Projetos como membro
        $member_projects = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.project_id) FROM {$wpdb->prefix}ql_project_members pm
             INNER JOIN {$wpdb->prefix}ql_projects p ON pm.project_id = p.id
             WHERE pm.user_id = %d AND p.status != 'archived'",
            $user_id
        ));

        // Tarefas atribuídas
        $assigned_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE assigned_user_id = %d",
            $user_id
        ));

        // Tarefas concluídas
        $completed_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_tasks WHERE assigned_user_id = %d AND status = 'completed'",
            $user_id
        ));

        ob_start();
        ?>
        <div class="ql-stats-grid">
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($owned_projects) + intval($member_projects); ?></div>
                <div class="ql-stat-label"><?php _e('Projetos', 'quilombo-lab'); ?></div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($assigned_tasks); ?></div>
                <div class="ql-stat-label"><?php _e('Tarefas Atribuídas', 'quilombo-lab'); ?></div>
            </div>
            <div class="ql-stat-card">
                <div class="ql-stat-number"><?php echo intval($completed_tasks); ?></div>
                <div class="ql-stat-label"><?php _e('Tarefas Concluídas', 'quilombo-lab'); ?></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // Métodos utilitários
    
    private function get_projects($atts) {
        global $wpdb;
        
        $where = "1=1";
        $params = [];
        
        if ($atts['mostrar'] === 'meus' && is_user_logged_in()) {
            $where .= " AND (p.owner_id = %d OR p.id IN (
                SELECT project_id FROM {$wpdb->prefix}ql_project_members 
                WHERE user_id = %d AND is_active = 1
            ))";
            $params[] = get_current_user_id();
            $params[] = get_current_user_id();
        } elseif ($atts['mostrar'] === 'publicos') {
            $where .= " AND p.visibility = 'public'";
        }
        
        // Sempre filtrar projetos não arquivados para shortcodes públicos
        $where .= " AND p.status != 'archived'";
        
        $sql = "SELECT p.id, p.name as nome, p.description as descricao, p.status, 
                       p.visibility as visibilidade, p.created_at as data_criacao,
                       p.slug, p.owner_id, p.moodle_course_id, p.featured_image_id,
                       CASE 
                           WHEN p.moodle_course_id IS NOT NULL THEN 'Trilha Moodle'
                           ELSE 'Projeto Livre'
                       END as trilha_nome
                FROM {$wpdb->prefix}ql_projects p 
                WHERE {$where} 
                ORDER BY p.created_at DESC 
                LIMIT %d";
        
        $params[] = intval($atts['limite']);
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    private function get_project_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.id, p.name as nome, p.description as descricao, p.status, 
                    p.visibility as visibilidade, p.created_at as data_criacao,
                    p.slug, p.owner_id, p.moodle_course_id, p.featured_image_id,
                    CASE 
                        WHEN p.moodle_course_id IS NOT NULL THEN 'Trilha Moodle'
                        ELSE 'Projeto Livre'
                    END as trilha_nome
             FROM {$wpdb->prefix}ql_projects p 
             WHERE p.id = %d",
            $id
        ));
    }
    
    private function get_project_by_slug($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT p.id, p.name as nome, p.description as descricao, p.status, 
                    p.visibility as visibilidade, p.created_at as data_criacao,
                    p.slug, p.owner_id, p.moodle_course_id, p.featured_image_id,
                    CASE 
                        WHEN p.moodle_course_id IS NOT NULL THEN 'Trilha Moodle'
                        ELSE 'Projeto Livre'
                    END as trilha_nome
             FROM {$wpdb->prefix}ql_projects p 
             WHERE p.slug = %s",
            $slug
        ));
    }
    
    private function get_user_projects($user_id, $atts) {
        global $wpdb;

        $where = "(p.owner_id = %d OR pm.user_id = %d)";
        $params = [$user_id, $user_id];

        if ($atts['status'] !== 'todos') {
            $where .= " AND p.status = %s";
            $params[] = $atts['status'];
        }

        $sql = "SELECT p.id, p.name as nome, p.description as descricao, p.status,
                       p.visibility as visibilidade, p.created_at as data_criacao,
                       p.slug, p.owner_id, p.featured_image_id,
                       CASE
                           WHEN p.moodle_course_id IS NOT NULL THEN 'Trilha Moodle'
                           ELSE 'Projeto Livre'
                       END as trilha_nome,
                       pm.role as user_role
                FROM {$wpdb->prefix}ql_projects p
                LEFT JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id AND pm.user_id = %d
                WHERE {$where}
                GROUP BY p.id
                ORDER BY p.created_at DESC
                LIMIT %d";

        $params = array_merge([$user_id], $params);
        $params[] = intval($atts['limite']);

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    private function user_can_view_project($project) {
        if (!$project) return false;

        // Projetos públicos todos podem ver
        if ($project->visibilidade === 'public') {
            return true;
        }

        // Usuários não logados só veem públicos
        if (!is_user_logged_in()) {
            return false;
        }

        // Admins veem tudo
        if (current_user_can('ql_manage_all_projects')) {
            return true;
        }

        $current_user_id = get_current_user_id();

        // Owner pode ver
        if (isset($project->owner_id) && (int)$project->owner_id === $current_user_id) {
            return true;
        }

        // Verificar se é membro do projeto
        global $wpdb;
        $is_member = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_project_members
             WHERE project_id = %d AND user_id = %d",
            $project->id,
            $current_user_id
        ));

        return $is_member > 0;
    }
    
    private function user_can_edit_project($project) {
        if (!$project || !is_user_logged_in()) {
            return false;
        }

        if (current_user_can('ql_manage_all_projects')) {
            return true;
        }

        $current_user_id = get_current_user_id();

        // Owner pode editar
        if (isset($project->owner_id) && (int)$project->owner_id === $current_user_id) {
            return true;
        }

        // Verificar se é coordenador do projeto
        global $wpdb;
        $is_coordinator = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_project_members
             WHERE project_id = %d AND user_id = %d AND role IN ('coordinator', 'manager')",
            $project->id,
            $current_user_id
        ));

        return $is_coordinator > 0;
    }
    
    private function get_project_url($project) {
        return add_query_arg('projeto', $project->slug, home_url('/lab/'));
    }
    
    private function get_project_edit_url($project) {
        return admin_url('admin.php?page=quilombo-lab-projetos&action=edit&id=' . $project->id);
    }
    
    private function get_status_label($status) {
        $labels = [
            'active' => 'Ativo',
            'on_hold' => 'Pausado',
            'completed' => 'Concluído',
            'archived' => 'Arquivado',
            'planning' => 'Planejamento',
            'cancelled' => 'Cancelado'
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    private function get_priority_label($priority) {
        $labels_int = [
            1 => __('Baixa', 'quilombo-lab'),
            2 => __('Normal', 'quilombo-lab'),
            3 => __('Alta', 'quilombo-lab'),
            4 => __('Urgente', 'quilombo-lab')
        ];

        $labels_str = [
            'low' => __('Baixa', 'quilombo-lab'),
            'normal' => __('Normal', 'quilombo-lab'),
            'high' => __('Alta', 'quilombo-lab'),
            'urgent' => __('Urgente', 'quilombo-lab')
        ];

        if (is_numeric($priority)) {
            return $labels_int[intval($priority)] ?? $labels_int[2];
        }

        return $labels_str[$priority] ?? __('Normal', 'quilombo-lab');
    }
}