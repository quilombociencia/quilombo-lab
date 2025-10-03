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
        
        <style>
        .ql-dashboard {
            padding: 20px 0;
        }
        .ql-projetos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .ql-projeto-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ql-projeto-card h4 {
            margin: 0 0 10px 0;
            color: #2271b1;
        }
        .ql-projeto-meta {
            font-size: 0.9em;
            color: #666;
            margin: 10px 0;
        }
        .ql-projeto-actions {
            text-align: right;
            margin-top: 15px;
        }
        .ql-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .ql-stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .ql-stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2271b1;
        }
        </style>
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
        
        <style>
        .ql-projetos-lista {
            display: grid;
            grid-template-columns: repeat(var(--colunas, 3), 1fr);
            gap: 20px;
        }
        @media (max-width: 768px) {
            .ql-projetos-lista {
                grid-template-columns: 1fr;
            }
        }
        </style>
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
        
        <style>
        .ql-projeto-single {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .ql-projeto-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .ql-projeto-header h1 {
            margin: 0 0 10px 0;
            color: #2271b1;
        }
        .ql-projeto-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-ativo { background: #d4edda; color: #155724; }
        .status-pausado { background: #fff3cd; color: #856404; }
        .status-concluido { background: #cce7ff; color: #004085; }
        .ql-projeto-descricao {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        </style>
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
        
        <style>
        .ql-board-container {
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f5f5f5;
        }
        .ql-kanban-board {
            display: flex;
            min-width: 100%;
            height: 100%;
            padding: 20px;
            gap: 20px;
        }
        .ql-kanban-column {
            flex: 1;
            min-width: 250px;
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .ql-column-header {
            font-weight: bold;
            padding: 10px 0;
            border-bottom: 2px solid #eee;
            margin-bottom: 15px;
        }
        .ql-task-card {
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .ql-task-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .ql-task-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ql-task-meta {
            font-size: 0.85em;
            color: #666;
        }
        </style>
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
        ?>
        <div class="ql-projeto-card">
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
        <?php
        return ob_get_clean();
    }
    
    private function render_project_board($project_id, $modo = 'completo') {
        // Placeholder - implementar quando QL_Board existir
        return '<div class="ql-board-placeholder">
            <p>Quadro Kanban será implementado em breve.</p>
            <p>Projeto ID: ' . esc_html($project_id) . '</p>
        </div>';
    }
    
    private function render_project_timeline($project_id) {
        // Placeholder - implementar quando QL_Timeline existir
        return '<div class="ql-timeline-placeholder">
            <p>Timeline será implementada em breve.</p>
            <p>Projeto ID: ' . esc_html($project_id) . '</p>
        </div>';
    }
    
    private function render_general_stats() {
        global $wpdb;
        
        // Stats básicas
        $total_projetos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projetos");
        $projetos_ativos = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projetos WHERE status = 'ativo'");
        $total_tarefas = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tarefas");
        $tarefas_concluidas = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_tarefas WHERE status = 'concluida'");
        
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
        return '<p>Estatísticas do projeto em desenvolvimento.</p>';
    }
    
    private function render_user_stats($user_id) {
        return '<p>Estatísticas do usuário em desenvolvimento.</p>';
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
                       p.slug, p.owner_id, p.moodle_course_id,
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
                    p.slug, p.owner_id, p.moodle_course_id,
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
                    p.slug, p.owner_id, p.moodle_course_id,
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
        
        $where = "pm.usuario_id = %d";
        $params = [$user_id];
        
        if ($atts['status'] !== 'todos') {
            $where .= " AND p.status = %s";
            $params[] = $atts['status'];
        }
        
        $sql = "SELECT p.*, t.nome as trilha_nome, pm.papel as user_role
                FROM {$wpdb->prefix}ql_projetos p
                INNER JOIN {$wpdb->prefix}ql_projeto_membros pm ON p.id = pm.projeto_id
                LEFT JOIN {$wpdb->prefix}gc_trilhas t ON p.trilha_id = t.id
                WHERE {$where}
                ORDER BY p.data_criacao DESC
                LIMIT %d";
        
        $params[] = intval($atts['limite']);
        
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
    
    private function user_can_view_project($project) {
        if (!$project) return false;
        
        // Projetos públicos todos podem ver
        if ($project->visibilidade === 'publica') {
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
        
        // Verificar se é membro do projeto
        global $wpdb;
        $is_member = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_projeto_membros 
             WHERE projeto_id = %d AND usuario_id = %d",
            $project->id,
            get_current_user_id()
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
        
        // Verificar se é coordenador do projeto
        global $wpdb;
        $is_coordinator = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_projeto_membros 
             WHERE projeto_id = %d AND usuario_id = %d AND papel IN ('coordenador', 'admin')",
            $project->id,
            get_current_user_id()
        ));
        
        return $is_coordinator > 0;
    }
    
    private function get_project_url($project) {
        return add_query_arg('projeto', $project->slug, home_url('/laboratorio/'));
    }
    
    private function get_project_edit_url($project) {
        return admin_url('admin.php?page=quilombo-laboratorio-projetos&action=edit&id=' . $project->id);
    }
    
    private function get_status_label($status) {
        $labels = [
            'planejamento' => 'Planejamento',
            'ativo' => 'Ativo',
            'pausado' => 'Pausado',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado'
        ];
        
        return $labels[$status] ?? ucfirst($status);
    }
}