<?php
/**
 * Template de página amigável para projetos do Quilombo Laboratório
 * 
 * Este template é usado para gerar páginas automaticamente quando
 * projetos são publicados no sistema.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para geração de templates de páginas de projetos
 */
class QL_Project_Page_Template {
    
    /**
     * Gerar conteúdo HTML completo da página do projeto
     */
    public static function generate_project_page_content($projeto) {
        // Verificar se é o projeto principal do coletivo
        $is_collective_project = self::is_collective_project($projeto);
        
        $content = '';
        
        // Banner do coletivo (se aplicável)
        if ($is_collective_project) {
            $content .= self::render_collective_banner();
        }
        
        // Cabeçalho principal do projeto
        $content .= self::render_project_header($projeto);
        
        // Seção de informações principais
        $content .= self::render_project_info($projeto);
        
        // Seção de progresso e métricas
        $content .= self::render_project_progress($projeto);
        
        // Seção do laboratório (quadro Kanban)
        $content .= self::render_project_lab($projeto);
        
        // Seção de participantes e círculos
        if ($is_collective_project) {
            $content .= self::render_collective_instances($projeto);
        } else {
            $content .= self::render_project_participants($projeto);
        }
        
        // Seção de atividades recentes
        $content .= self::render_project_activities($projeto);
        
        // Seção de gestão coletiva (se disponível)
        $content .= self::render_project_funding($projeto);
        
        // Rodapé com links de ação
        $content .= self::render_project_footer($projeto);
        
        return $content;
    }
    
    /**
     * Imprimir CSS no head da página
     */
    public static function print_project_page_styles() {
        echo '<style id="ql-projeto-styles">';
        echo self::get_project_page_styles();
        echo '</style>';
    }
    
    /**
     * CSS personalizado para páginas de projeto
     */
    private static function get_project_page_styles() {
        return '
        .ql-projeto-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .ql-projeto-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .ql-projeto-title {
            font-size: 2.5em;
            margin: 0 0 15px 0;
            font-weight: 700;
        }
        
        .ql-projeto-description {
            font-size: 1.2em;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }
        
        .ql-projeto-status {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 20px;
        }
        
        .ql-projeto-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .ql-projeto-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }
        
        .ql-projeto-card h3 {
            color: #333;
            margin-top: 0;
            font-size: 1.4em;
            margin-bottom: 20px;
        }
        
        .ql-progresso-bar {
            background: #f0f0f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 15px 0;
        }
        
        .ql-progresso-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .ql-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.2s;
            margin: 5px;
        }
        
        .ql-button:hover {
            background: #5a6fd8;
            color: white;
        }
        
        .ql-button-secondary {
            background: #6c757d;
        }
        
        .ql-button-secondary:hover {
            background: #545b62;
        }
        
        .ql-participants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .ql-participant-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .ql-timeline {
            border-left: 3px solid #667eea;
            padding-left: 30px;
            margin: 20px 0;
        }
        
        .ql-timeline-item {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .ql-meta {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        /* Estilos para página do coletivo */
        .ql-collective-banner {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .ql-banner-content {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .ql-banner-icon {
            font-size: 3em;
        }
        
        .ql-banner-text h2 {
            margin: 0 0 10px 0;
            font-size: 1.8em;
        }
        
        .ql-banner-text p {
            margin: 0;
            opacity: 0.9;
        }
        
        .ql-button-highlight {
            background: #e74c3c !important;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .ql-instances-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .ql-instance-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }
        
        .ql-instance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        
        .ql-instance-active {
            border-left-color: #27ae60 !important;
            background: #e8f5e8;
        }
        
        .ql-instance-potential {
            border-left-color: #f39c12 !important;
            background: #fdf6e3;
        }
        
        .ql-instance-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }
        
        .ql-instance-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.3em;
        }
        
        .ql-instance-card p {
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .ql-instance-roles {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .ql-role-badge {
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 0.8em;
            font-weight: 600;
        }
        
        .ql-instance-active .ql-role-badge {
            background: #27ae60;
        }
        
        .ql-instance-meta,
        .ql-instance-status {
            margin-top: 10px;
        }
        
        .ql-instance-meta small,
        .ql-instance-status small {
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .ql-collective-actions {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #ecf0f1;
        }
        
        .ql-collective-actions h4 {
            margin-bottom: 20px;
            color: #2c3e50;
            font-size: 1.3em;
        }
        
        .ql-action-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .ql-projeto-page {
                padding: 10px;
            }
            
            .ql-projeto-header {
                padding: 20px;
            }
            
            .ql-projeto-title {
                font-size: 2em;
            }
            
            .ql-projeto-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .ql-banner-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 15px;
            }
            
            .ql-instances-grid {
                grid-template-columns: 1fr;
            }
            
            .ql-action-links {
                grid-template-columns: 1fr;
            }
        }
        ';
    }
    
    /**
     * Cabeçalho do projeto
     */
    private static function render_project_header($projeto) {
        $status = self::get_project_field($projeto, 'status') ?? 'active';
        $status_label = self::get_status_label($status);
        $name = self::get_project_field($projeto, 'name') ?? self::get_project_field($projeto, 'nome') ?? 'Projeto';
        $description = self::get_project_field($projeto, 'description') ?? self::get_project_field($projeto, 'descricao') ?? 'Um projeto do Quilombo Laboratório';

        return '
        <div class="ql-projeto-page">
            <header class="ql-projeto-header">
                <h1 class="ql-projeto-title">' . esc_html($name) . '</h1>
                <p class="ql-projeto-description">' . esc_html($description) . '</p>
                <span class="ql-projeto-status">' . esc_html($status_label) . '</span>
            </header>
        ';
    }
    
    /**
     * Informações principais do projeto
     */
    private static function render_project_info($projeto) {
        $start_date = self::get_project_field($projeto, 'start_date');
        $inicio = $start_date ? date_i18n('d/m/Y', strtotime($start_date)) : 'Não definido';
        $created_at = self::get_project_field($projeto, 'created_at');
        $criacao = $created_at ? date_i18n('d/m/Y', strtotime($created_at)) : 'N/A';
        $visibility = self::get_project_field($projeto, 'visibility') ?? 'public';
        $visibility_label = $visibility === 'public' ? 'Público' : 'Privado';

        return '
            <div class="ql-projeto-grid">
                <div class="ql-projeto-card">
                    <h3>📋 Informações do Projeto</h3>
                    <p><strong>Data de Início:</strong> ' . esc_html($inicio) . '</p>
                    <p><strong>Criado em:</strong> ' . esc_html($criacao) . '</p>
                    <p><strong>Visibilidade:</strong> ' . esc_html($visibility_label) . '</p>
                </div>
        ';
    }

    /**
     * Acessar campo do projeto (suporta objeto e array)
     */
    private static function get_project_field($projeto, $field) {
        if (is_object($projeto)) {
            return $projeto->$field ?? null;
        }
        if (is_array($projeto)) {
            return $projeto[$field] ?? null;
        }
        return null;
    }
    
    /**
     * Progresso e métricas do projeto
     */
    private static function render_project_progress($projeto) {
        $progresso = self::calculate_project_progress($projeto);
        $status = self::get_project_field($projeto, 'status') ?? 'active';
        $status_label = self::get_status_label($status);
        $objetivo = 0; // Gestão financeira via plugin GC separado
        
        return '
                <div class="ql-projeto-card">
                    <h3>📊 Progresso</h3>
                    <p><strong>Completude do Projeto:</strong> ' . $progresso . '%</p>
                    <div class="ql-progresso-bar">
                        <div class="ql-progresso-fill" style="width: ' . $progresso . '%"></div>
                    </div>
                    ' . ($objetivo > 0 ? '
                    <p><strong>Objetivo Financeiro:</strong> R$ ' . number_format($objetivo, 2, ',', '.') . '</p>
                    ' : '') . '
                    <p><strong>Status:</strong> ' . esc_html($status_label) . '</p>
                </div>
            </div>
        ';
    }
    
    /**
     * Seção do laboratório (Quadro Kanban)
     */
    private static function render_project_lab($projeto) {
        $name = self::get_project_field($projeto, 'name') ?? self::get_project_field($projeto, 'nome') ?? 'Projeto';
        $project_id = self::get_project_field($projeto, 'id');

        return '
            <div class="ql-projeto-card" style="grid-column: 1 / -1;">
                <h3>🔬 Laboratório de Projetos</h3>
                <p>Quadro Kanban colaborativo para gestão de tarefas e atividades.</p>
                <div id="ql-kanban-board">
                    ' . ($project_id ? do_shortcode('[ql_board projeto_id="' . intval($project_id) . '"]') : '<p>Projeto sem quadro configurado.</p>') . '
                </div>
                <p style="margin-top: 20px;">
                    <a href="' . admin_url('admin.php?page=quilombo-lab-projetos') . '" class="ql-button">🎯 Ver Quadro Completo</a>
                </p>
            </div>
        ';
    }
    
    /**
     * Participantes e círculos
     */
    private static function render_project_participants($projeto) {
        global $wpdb;
        $project_id = is_object($projeto) ? $projeto->id : (is_array($projeto) ? $projeto['id'] : 0);

        $members = [];
        if ($project_id) {
            // Buscar owner
            $owner_id = is_object($projeto) ? ($projeto->owner_id ?? 0) : ($projeto['owner_id'] ?? 0);

            $members = $wpdb->get_results($wpdb->prepare(
                "SELECT u.display_name, u.user_email, pm.role, pm.joined_at
                 FROM {$wpdb->prefix}ql_project_members pm
                 INNER JOIN {$wpdb->users} u ON pm.user_id = u.ID
                 WHERE pm.project_id = %d
                 ORDER BY pm.joined_at ASC",
                $project_id
            ));

            // Incluir owner se não estiver na lista de membros
            if ($owner_id) {
                $owner_in_list = false;
                $owner_data = get_userdata($owner_id);
                if ($owner_data) {
                    foreach ($members as $m) {
                        if ($m->display_name === $owner_data->display_name) {
                            $owner_in_list = true;
                            break;
                        }
                    }
                    if (!$owner_in_list) {
                        $owner_obj = new stdClass();
                        $owner_obj->display_name = $owner_data->display_name;
                        $owner_obj->user_email = $owner_data->user_email;
                        $owner_obj->role = 'owner';
                        $owner_obj->joined_at = null;
                        array_unshift($members, $owner_obj);
                    }
                }
            }
        }

        $role_labels = [
            'owner' => 'Proprietário',
            'coordinator' => 'Coordenador',
            'manager' => 'Gestor',
            'member' => 'Membro',
            'viewer' => 'Observador'
        ];

        $html = '
            <div class="ql-projeto-card" style="grid-column: 1 / -1;">
                <h3>👥 Participantes</h3>';

        if (!empty($members)) {
            $html .= '<div class="ql-participants-grid">';
            foreach ($members as $member) {
                $role = $role_labels[$member->role] ?? ucfirst($member->role);
                $initial = mb_strtoupper(mb_substr($member->display_name, 0, 1));
                $avatar_url = get_avatar_url($member->user_email, ['size' => 80]);
                $html .= '
                    <div class="ql-participant-card">
                        <img src="' . esc_url($avatar_url) . '" alt="" style="width:60px;height:60px;border-radius:50%;margin-bottom:10px;" />
                        <strong>' . esc_html($member->display_name) . '</strong>
                        <p>' . esc_html($role) . '</p>
                    </div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<p>Nenhum participante registrado.</p>';
        }

        $html .= '</div>';

        return $html;
    }
    
    /**
     * Atividades recentes
     */
    private static function render_project_activities($projeto) {
        global $wpdb;
        $project_id = is_object($projeto) ? $projeto->id : (is_array($projeto) ? $projeto['id'] : 0);

        $tasks = [];
        if ($project_id) {
            $tasks = $wpdb->get_results($wpdb->prepare(
                "SELECT t.title, t.status, t.updated_at,
                        u.display_name as user_name
                 FROM {$wpdb->prefix}ql_tasks t
                 INNER JOIN {$wpdb->prefix}ql_columns c ON t.column_id = c.id
                 INNER JOIN {$wpdb->prefix}ql_boards b ON c.board_id = b.id
                 LEFT JOIN {$wpdb->users} u ON t.assigned_user_id = u.ID
                 WHERE b.project_id = %d
                 ORDER BY t.updated_at DESC
                 LIMIT 5",
                $project_id
            ));
        }

        $html = '
            <div class="ql-projeto-card" style="grid-column: 1 / -1;">
                <h3>📈 Atividades Recentes</h3>
                <div class="ql-timeline">';

        if (!empty($tasks)) {
            foreach ($tasks as $task) {
                $date = date_i18n('d/m/Y H:i', strtotime($task->updated_at));
                $status_label = self::get_status_label($task->status);
                $user = $task->user_name ? ' — ' . esc_html($task->user_name) : '';
                $html .= '
                    <div class="ql-timeline-item">
                        <div class="ql-meta">' . esc_html($date) . '</div>
                        <strong>' . esc_html($task->title) . '</strong> (' . esc_html($status_label) . ')' . $user . '
                    </div>';
            }
        } else {
            $html .= '
                    <div class="ql-timeline-item">
                        <div class="ql-meta">—</div>
                        <strong>Nenhuma atividade recente</strong>
                    </div>';
        }

        $html .= '
                </div>
            </div>';

        return $html;
    }
    
    /**
     * Gestão coletiva e recursos
     */
    private static function render_project_funding($projeto) {
        $tem_gc = class_exists('GC_Admin'); // Verificar se Gestão Coletiva está ativa
        
        if (!$tem_gc) {
            return '';
        }
        
        return '
            <div class="ql-projeto-card" style="grid-column: 1 / -1;">
                <h3>💰 Gestão Coletiva de Recursos</h3>
                <p>Administração transparente e colaborativa dos recursos do projeto.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div>
                        <strong>Arrecadado:</strong><br>
                        <span style="font-size: 1.5em; color: #28a745;">R$ 0,00</span>
                    </div>
                    <div>
                        <strong>Despesas:</strong><br>
                        <span style="font-size: 1.5em; color: #dc3545;">R$ 0,00</span>
                    </div>
                    <div>
                        <strong>Saldo:</strong><br>
                        <span style="font-size: 1.5em; color: #667eea;">R$ 0,00</span>
                    </div>
                </div>
                
                <p style="margin-top: 20px;">
                    <a href="#" class="ql-button">🎁 Fazer Doação</a>
                    <a href="#" class="ql-button ql-button-secondary">📊 Ver Relatório Financeiro</a>
                </p>
            </div>
        ';
    }
    
    /**
     * Rodapé com ações
     */
    private static function render_project_footer($projeto) {
        return '
            <footer style="margin-top: 40px; padding: 30px; background: #f8f9fa; border-radius: 12px; text-align: center;">
                <h3>🚀 Participe do Projeto</h3>
                <p>Interessado em contribuir? Entre em contato ou participe das atividades.</p>
                
                <p style="margin-top: 20px;">
                    <a href="mailto:contato@quilombociencia.org" class="ql-button">✉️ Entrar em Contato</a>
                    <a href="#" class="ql-button ql-button-secondary">📱 Grupo do WhatsApp</a>
                    <a href="' . admin_url('admin.php?page=quilombo-lab-projetos') . '" class="ql-button ql-button-secondary">⚙️ Painel Admin</a>
                </p>
            </footer>
        </div>
        ';
    }
    
    /**
     * Calcular progresso do projeto baseado em tarefas
     */
    private static function calculate_project_progress($projeto) {
        // Usar progress_percentage do projeto se disponível
        $progress = intval(self::get_project_field($projeto, 'progress_percentage') ?? 0);
        if ($progress > 0) {
            return $progress;
        }

        // Calcular baseado em tarefas concluídas / total
        global $wpdb;
        $project_id = intval(self::get_project_field($projeto, 'id') ?? 0);
        if (!$project_id) {
            return 0;
        }

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(t.id) as total,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed
             FROM {$wpdb->prefix}ql_tasks t
             INNER JOIN {$wpdb->prefix}ql_columns c ON t.column_id = c.id
             INNER JOIN {$wpdb->prefix}ql_boards b ON c.board_id = b.id
             WHERE b.project_id = %d",
            $project_id
        ));

        if (!$stats || intval($stats->total) === 0) {
            return 0;
        }

        return round((intval($stats->completed) / intval($stats->total)) * 100);
    }
    
    /**
     * Obter label do status em português
     */
    private static function get_status_label($status) {
        $labels = [
            'active' => 'Projeto Ativo',
            'on_hold' => 'Pausado',
            'completed' => 'Concluído',
            'archived' => 'Arquivado',
            'planning' => 'Planejamento',
            'cancelled' => 'Cancelado'
        ];

        return $labels[$status] ?? ucfirst($status);
    }
    
    /**
     * Verificar se é o projeto principal do coletivo
     */
    private static function is_collective_project($projeto) {
        // Verificar via configuração do plugin
        if (class_exists('QL_Public') && method_exists('QL_Public', 'is_collective_project')) {
            $project_id = self::get_project_field($projeto, 'id');
            if ($project_id && QL_Public::is_collective_project($project_id)) {
                return true;
            }
        }

        // Critérios para identificar projeto do coletivo:
        $name = self::get_project_field($projeto, 'name') ?? self::get_project_field($projeto, 'nome') ?? '';
        $nome_lower = strtolower($name);

        if (strpos($nome_lower, 'quilombo ciência') !== false &&
            strpos($nome_lower, 'fundo') === false) {
            return true;
        }

        // Verificar por slug específico
        $slug = self::get_project_field($projeto, 'slug') ?? '';
        if ($slug === 'quilombo-ciencia' || $slug === 'projeto-coletivo') {
            return true;
        }

        return false;
    }
    
    /**
     * Renderizar banner do coletivo
     */
    private static function render_collective_banner() {
        return '
            <div class="ql-collective-banner">
                <div class="ql-banner-content">
                    <div class="ql-banner-icon">🏛️</div>
                    <div class="ql-banner-text">
                        <h2>Projeto Principal do Coletivo</h2>
                        <p>Esta é a página do projeto responsável pela organização do coletivo Quilombo Ciência</p>
                    </div>
                    <div class="ql-banner-action">
                        <a href="https://quilombociencia.org" class="ql-button ql-button-highlight" target="_blank">
                            🌐 Visitar Site do Coletivo
                        </a>
                    </div>
                </div>
            </div>
        ';
    }
    
    /**
     * Renderizar instâncias do coletivo
     */
    private static function render_collective_instances($projeto) {
        $instances_html = '';

        // Tentar carregar instâncias reais do sistema
        if (class_exists('QL_Instances')) {
            $instances_model = QL_Instances::get_instance();
            $types = ['circulo', 'nucleo', 'comunidade', 'coletivo', 'assembleia'];
            $type_icons = [
                'circulo' => '⚪',
                'nucleo' => '🌐',
                'comunidade' => '📍',
                'coletivo' => '🏛️',
                'assembleia' => '🎯'
            ];

            foreach ($types as $type) {
                $type_instances = $instances_model->get_instances_by_type($type);
                if (!empty($type_instances)) {
                    foreach ($type_instances as $inst) {
                        $status_class = '';
                        $inst_status = is_object($inst) ? ($inst->status ?? '') : ($inst['status'] ?? '');
                        $inst_name = is_object($inst) ? ($inst->name ?? '') : ($inst['name'] ?? '');
                        $inst_description = is_object($inst) ? ($inst->description ?? '') : ($inst['description'] ?? '');

                        if ($inst_status === 'active') {
                            $status_class = ' ql-instance-active';
                        } elseif ($inst_status === 'forming') {
                            $status_class = ' ql-instance-potential';
                        }

                        $icon = $type_icons[$type] ?? '📌';
                        $instances_html .= '
                            <div class="ql-instance-card' . $status_class . '">
                                <div class="ql-instance-icon">' . $icon . '</div>
                                <h4>' . esc_html($inst_name) . '</h4>
                                <p>' . esc_html($inst_description ?: ucfirst($type)) . '</p>
                                <div class="ql-instance-status">
                                    <small>' . esc_html(ucfirst($inst_status)) . '</small>
                                </div>
                            </div>';
                    }
                }
            }
        }

        // Fallback se não há instâncias reais
        if (empty($instances_html)) {
            $instances_html = '
                <div class="ql-instance-card ql-instance-active">
                    <div class="ql-instance-icon">⚪</div>
                    <h4>Círculo Principal</h4>
                    <p>Núcleo inicial do coletivo</p>
                </div>
                <div class="ql-instance-card">
                    <div class="ql-instance-icon">🌐</div>
                    <h4>Núcleo Virtual</h4>
                    <p>Espaço digital de articulação</p>
                </div>';
        }

        return '
            <div class="ql-projeto-card" style="grid-column: 1 / -1;">
                <h3>🏛️ Instâncias do Coletivo</h3>
                <p>Organização e estrutura do coletivo Quilombo Ciência conforme modelo organizativo.</p>

                <div class="ql-instances-grid">
                    ' . $instances_html . '
                </div>

                <div class="ql-collective-actions">
                    <h4>Ações do Coletivo</h4>
                    <div class="ql-action-links">
                        <a href="https://quilombociencia.org/blog" class="ql-button ql-button-secondary">📝 Pilão de Ideias</a>
                        <a href="https://quilombociencia.org/escola" class="ql-button ql-button-secondary">🎓 Escola de Projetos</a>
                        <a href="' . admin_url('admin.php?page=quilombo-lab-projetos') . '" class="ql-button ql-button-secondary">🔬 Laboratório</a>
                    </div>
                </div>
            </div>
        ';
    }
}