<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para sincronização de trilhas Moodle → categorias WordPress
 */
class QL_Trilha_Sync {
    
    private static $instance = null;
    
    // Mapeamento de tipos de trilha
    const TIPOS_TRILHA = [
        'aprendizagem' => 'learning',
        'pesquisa' => 'research', 
        'criacao' => 'creation'
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('gc_trilha_criada', [$this, 'on_trilha_created'], 10, 2);
        add_action('gc_trilha_atualizada', [$this, 'on_trilha_updated'], 10, 2);
    }
    
    public function init() {
        // Verificar se há trilhas para sincronizar
        add_action('admin_init', [$this, 'maybe_sync_existing_trilhas']);
        
        // Registrar custom post types se necessário
        $this->register_custom_taxonomy();
        
        // AJAX hooks
        add_action('wp_ajax_ql_sync_trilha_moodle', [$this, 'ajax_sync_trilha_moodle']);
        add_action('wp_ajax_ql_create_project_from_trilha', [$this, 'ajax_create_project_from_trilha']);
        
        // Webhook listener para Moodle
        add_action('wp_ajax_nopriv_ql_moodle_webhook', [$this, 'handle_moodle_webhook']);
        add_action('wp_ajax_ql_moodle_webhook', [$this, 'handle_moodle_webhook']);
        
        // Cron job para sincronização automática
        add_action('ql_trilha_auto_sync', [$this, 'scheduled_trilha_sync']);
        
        if (!wp_next_scheduled('ql_trilha_auto_sync')) {
            wp_schedule_event(time(), 'hourly', 'ql_trilha_auto_sync');
        }
    }
    
    /**
     * Registrar taxonomia personalizada para trilhas
     */
    private function register_custom_taxonomy() {
        // Taxonomia para tipos de trilha (Aprendizagem, Pesquisa, Criação)
        register_taxonomy('trilha_tipo', ['page', 'post'], [
            'labels' => [
                'name' => 'Tipos de Trilha',
                'singular_name' => 'Tipo de Trilha',
                'menu_name' => 'Tipos de Trilha'
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'tipo-trilha']
        ]);
        
        // Taxonomia para trilhas específicas
        register_taxonomy('trilha', ['page', 'post'], [
            'labels' => [
                'name' => 'Trilhas',
                'singular_name' => 'Trilha',
                'menu_name' => 'Trilhas'
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'rewrite' => ['slug' => 'trilha']
        ]);
    }
    
    /**
     * Sincronizar projetos existentes na primeira execução
     */
    public function maybe_sync_existing_trilhas() {
        if (get_option('ql_projetos_synced', false)) {
            return;
        }
        
        // Verificar se tabelas do GC existem
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}gc_projetos'") != $wpdb->prefix . 'gc_projetos') {
            return;
        }
        
        $this->sync_all_projetos_gc();
        update_option('ql_projetos_synced', true);
    }
    
    /**
     * Sincronizar todos os projetos do GC
     */
    public function sync_all_projetos_gc() {
        global $wpdb;
        
        // Buscar todos os projetos do Gestão Coletiva
        $projetos = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}gc_projetos WHERE status IN ('ativo', 'pausado') ORDER BY nome"
        );
        
        if (empty($projetos)) {
            error_log('QL Trilha Sync: Nenhum projeto encontrado no Gestão Coletiva');
            return;
        }
        
        foreach ($projetos as $projeto) {
            $this->create_or_update_category_from_projeto($projeto);
        }
        
        error_log("QL Trilha Sync: Sincronizados " . count($projetos) . " projetos");
    }
    
    /**
     * Hook quando projeto é criado no GC
     */
    public function on_trilha_created($projeto_id, $projeto_data) {
        $projeto = $this->get_projeto_by_id($projeto_id);
        if ($projeto) {
            $this->create_or_update_category_from_projeto($projeto);
        }
    }
    
    /**
     * Hook quando projeto é atualizado no GC
     */
    public function on_trilha_updated($projeto_id, $projeto_data) {
        $projeto = $this->get_projeto_by_id($projeto_id);
        if ($projeto) {
            $this->create_or_update_category_from_projeto($projeto);
        }
    }
    
    /**
     * Criar ou atualizar categoria WordPress baseada em projeto GC
     */
    private function create_or_update_category_from_projeto($projeto) {
        // 1. Criar/atualizar termo na taxonomia 'trilha'
        $trilha_term_id = $this->create_or_update_trilha_term($projeto);
        
        // 2. Criar/atualizar categoria WordPress
        $category_id = $this->create_or_update_wp_category($projeto);
        
        // 3. Criar/atualizar página do projeto
        $page_id = $this->create_or_update_trilha_page($projeto, $category_id);
        
        // 4. Criar/atualizar projeto no Laboratório
        $project_id = $this->create_or_update_project($projeto, $page_id);
        
        // 5. Salvar mapeamentos
        $this->save_trilha_mappings($projeto->id, [
            'trilha_term_id' => $trilha_term_id,
            'wp_category_id' => $category_id,
            'wp_page_id' => $page_id,
            'ql_project_id' => $project_id
        ]);
        
        return [
            'trilha_term_id' => $trilha_term_id,
            'wp_category_id' => $category_id,
            'wp_page_id' => $page_id,
            'ql_project_id' => $project_id
        ];
    }
    
    /**
     * Criar/atualizar termo do projeto
     */
    private function create_or_update_trilha_term($projeto) {
        // Verificar se já existe
        $existing_term = get_term_by('slug', $projeto->slug, 'trilha');
        
        $args = [
            'description' => $projeto->descricao ?: '',
            'slug' => $projeto->slug
        ];
        
        // Determinar o tipo baseado no nome ou criar tipo genérico
        $tipo = $this->detect_projeto_tipo($projeto->nome);
        $tipo_term_id = $this->get_or_create_tipo_trilha($tipo);
        if ($tipo_term_id) {
            $args['parent'] = $tipo_term_id;
        }
        
        if ($existing_term) {
            // Atualizar termo existente
            wp_update_term($existing_term->term_id, 'trilha', $args);
            $term_id = $existing_term->term_id;
        } else {
            // Criar novo termo
            $result = wp_insert_term($projeto->nome, 'trilha', $args);
            $term_id = is_wp_error($result) ? 0 : $result['term_id'];
        }
        
        if ($term_id) {
            // Salvar metadados do projeto
            update_term_meta($term_id, 'gc_projeto_id', $projeto->id);
            update_term_meta($term_id, 'gc_objetivo_financeiro', $projeto->objetivo_financeiro ?? 0);
            update_term_meta($term_id, 'gc_data_inicio', $projeto->data_inicio ?? '');
        }
        
        return $term_id;
    }
    
    /**
     * Obter ou criar termo do tipo de trilha
     */
    private function get_or_create_tipo_trilha($tipo) {
        $tipos_map = [
            'aprendizagem' => 'Trilhas de Aprendizagem',
            'pesquisa' => 'Trilhas de Pesquisa',
            'criacao' => 'Trilhas de Criação'
        ];
        
        $nome_tipo = $tipos_map[$tipo] ?? $tipos_map['aprendizagem'];
        $slug_tipo = sanitize_title($tipo);
        
        $existing = get_term_by('slug', $slug_tipo, 'trilha_tipo');
        
        if ($existing) {
            return $existing->term_id;
        }
        
        $result = wp_insert_term($nome_tipo, 'trilha_tipo', [
            'slug' => $slug_tipo,
            'description' => "Tipo de trilha: {$nome_tipo}"
        ]);
        
        return is_wp_error($result) ? 0 : $result['term_id'];
    }
    
    /**
     * Criar/atualizar categoria WordPress
     */
    private function create_or_update_wp_category($projeto) {
        // Verificar se já existe categoria mapeada
        $existing_cat_id = $this->get_mapped_category_id($projeto->id);
        
        $args = [
            'cat_name' => $projeto->nome,
            'category_description' => $projeto->descricao ?: '',
            'category_nicename' => $projeto->slug,
            'taxonomy' => 'category'
        ];
        
        // Determinar categoria pai baseada no tipo detectado
        $tipo = $this->detect_projeto_tipo($projeto->nome);
        $parent_cat_id = $this->get_or_create_parent_category($tipo);
        if ($parent_cat_id) {
            $args['category_parent'] = $parent_cat_id;
        }
        
        if ($existing_cat_id && get_term($existing_cat_id, 'category')) {
            // Atualizar categoria existente
            $result = wp_update_term($existing_cat_id, 'category', [
                'name' => $args['cat_name'],
                'description' => $args['category_description'],
                'slug' => $args['category_nicename'],
                'parent' => $args['category_parent'] ?? 0
            ]);
            $cat_id = is_wp_error($result) ? 0 : $existing_cat_id;
        } else {
            // Criar nova categoria
            $result = wp_insert_term($args['cat_name'], 'category', [
                'description' => $args['category_description'],
                'slug' => $args['category_nicename'],
                'parent' => $args['category_parent'] ?? 0
            ]);
            $cat_id = is_wp_error($result) ? 0 : $result['term_id'];
        }
        
        if ($cat_id && !is_wp_error($cat_id)) {
            // Salvar metadados
            update_term_meta($cat_id, 'gc_projeto_id', $projeto->id);
            update_term_meta($cat_id, 'gc_objetivo_financeiro', $projeto->objetivo_financeiro ?? 0);
        }
        
        return is_wp_error($cat_id) ? 0 : $cat_id;
    }
    
    /**
     * Obter ou criar categoria pai por tipo
     */
    private function get_or_create_parent_category($tipo) {
        $parent_cats = [
            'aprendizagem' => 'Trilhas de Aprendizagem',
            'pesquisa' => 'Trilhas de Pesquisa', 
            'criacao' => 'Trilhas de Criação'
        ];
        
        $parent_name = $parent_cats[$tipo] ?? $parent_cats['aprendizagem'];
        $parent_slug = sanitize_title($parent_name);
        
        $existing = get_category_by_slug($parent_slug);
        
        if ($existing) {
            return $existing->term_id;
        }
        
        $result = wp_insert_term($parent_name, 'category', [
            'slug' => $parent_slug,
            'description' => "Categoria para {$parent_name}"
        ]);
        
        return is_wp_error($result) ? 0 : $result['term_id'];
    }
    
    /**
     * Criar/atualizar página do projeto
     */
    private function create_or_update_trilha_page($projeto, $category_id) {
        // Verificar se já existe página mapeada
        $existing_page_id = $this->get_mapped_page_id($projeto->id);
        
        $content = $this->generate_projeto_page_content($projeto, $category_id);
        
        $page_data = [
            'post_title' => $projeto->nome,
            'post_name' => $projeto->slug,
            'post_content' => $content,
            'post_status' => $projeto->visibilidade === 'publico' ? 'publish' : 'private',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_category' => [$category_id]
        ];
        
        if ($existing_page_id && get_post($existing_page_id)) {
            // Atualizar página existente
            $page_data['ID'] = $existing_page_id;
            wp_update_post($page_data);
            $page_id = $existing_page_id;
        } else {
            // Criar nova página
            $page_id = wp_insert_post($page_data);
        }
        
        if ($page_id && !is_wp_error($page_id)) {
            // Salvar metadados
            update_post_meta($page_id, 'gc_projeto_id', $projeto->id);
            update_post_meta($page_id, 'gc_objetivo_financeiro', $projeto->objetivo_financeiro ?? 0);
            update_post_meta($page_id, 'gc_data_inicio', $projeto->data_inicio ?? '');
            
            // Associar às taxonomias
            $trilha_term = get_term_by('slug', $projeto->slug, 'trilha');
            if ($trilha_term) {
                wp_set_post_terms($page_id, [$trilha_term->term_id], 'trilha');
            }
        }
        
        return is_wp_error($page_id) ? 0 : $page_id;
    }
    
    /**
     * Gerar conteúdo da página do projeto
     */
    private function generate_projeto_page_content($projeto, $category_id) {
        $content = '';
        
        // Cabeçalho do projeto
        $content .= "<div class=\"projeto-header\">\n";
        if ($projeto->descricao) {
            $content .= "<p class=\"projeto-descricao\">" . esc_html($projeto->descricao) . "</p>\n";
        }
        
        // Informações do projeto
        $content .= "<div class=\"projeto-info\">\n";
        if ($projeto->objetivo_financeiro > 0) {
            $content .= "<p><strong>Objetivo Financeiro:</strong> R$ " . number_format($projeto->objetivo_financeiro, 2, ',', '.') . "</p>\n";
        }
        if ($projeto->data_inicio) {
            $content .= "<p><strong>Data de Início:</strong> " . date_i18n('d/m/Y', strtotime($projeto->data_inicio)) . "</p>\n";
        }
        $content .= "</div>\n";
        $content .= "</div>\n\n";
        
        // Laboratório de Projetos
        $content .= "<div class=\"projeto-laboratorio\">\n";
        $content .= "<h2>Laboratório de Projetos</h2>\n";
        $content .= "[ql_projeto slug=\"{$projeto->slug}\"]\n";
        $content .= "</div>\n\n";
        
        // Posts da categoria (tarefas)
        $content .= "<div class=\"projeto-posts\">\n";
        $content .= "<h2>Tarefas e Atividades</h2>\n";
        $content .= "<p>Posts categorizados aqui aparecerão como tarefas do projeto.</p>\n";
        $content .= "</div>\n\n";
        
        // Link para o painel administrativo
        $content .= "<div class=\"projeto-admin\">\n";
        $content .= "<h2>Gestão do Projeto</h2>\n";
        $content .= "<p><a href=\"" . admin_url('admin.php?page=quilombo-laboratorio-projetos') . "\" class=\"button button-primary\">Acessar Painel Administrativo</a></p>\n";
        $content .= "</div>\n";
        
        return $content;
    }
    
    /**
     * Criar/atualizar projeto no Laboratório
     */
    private function create_or_update_project($projeto, $page_id) {
        // Por enquanto retornar 0 até a classe QL_Project ser implementada
        // TODO: Implementar quando QL_Project estiver pronta
        
        global $wpdb;
        
        // Verificar se já existe projeto mapeado
        $existing_project_id = $this->get_mapped_project_id($projeto->id);
        
        // Esta seção foi substituída pelo mapeamento correto abaixo
        
        // Mapear campos para a estrutura correta da tabela ql_projects
        $mapped_data = [
            'gc_projeto_id' => $projeto->id,
            'name' => $projeto->nome,
            'slug' => $projeto->slug,
            'description' => $projeto->descricao ?: '',
            'status' => $this->convert_gc_status_to_ql($projeto->status),
            'visibility' => $this->convert_gc_visibility_to_ql($projeto->visibilidade),
            'owner_id' => $projeto->criador_id ?? QL_Config::get_default_admin_id(),
            'start_date' => $projeto->data_inicio ?? null,
            'estimated_hours' => 0,
            'actual_hours' => 0,
            'progress_percentage' => 0,
            'priority' => 'normal',
            'color' => '#3498db',
            'updated_at' => current_time('mysql')
        ];
        
        if ($existing_project_id) {
            // Atualizar projeto existente
            $wpdb->update(
                $wpdb->prefix . 'ql_projects',
                $mapped_data,
                ['id' => $existing_project_id],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s'],
                ['%d']
            );
            
            return $existing_project_id;
        } else {
            // Criar novo projeto
            $mapped_data['created_at'] = current_time('mysql');
            
            $wpdb->insert(
                $wpdb->prefix . 'ql_projects',
                $mapped_data,
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Salvar mapeamentos entre projeto e entidades WordPress
     */
    private function save_trilha_mappings($projeto_id, $mappings) {
        global $wpdb;
        
        // Salvar na tabela de mapeamentos (criar se não existir)
        $this->ensure_mappings_table();
        
        $wpdb->replace(
            $wpdb->prefix . 'ql_trilha_mappings',
            [
                'gc_projeto_id' => $projeto_id,
                'trilha_term_id' => $mappings['trilha_term_id'],
                'wp_category_id' => $mappings['wp_category_id'],
                'wp_page_id' => $mappings['wp_page_id'],
                'ql_project_id' => $mappings['ql_project_id'],
                'data_sync' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%d', '%d', '%s']
        );
    }
    
    /**
     * Garantir que tabela de mapeamentos existe
     */
    private function ensure_mappings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_trilha_mappings';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            gc_projeto_id bigint(20) NOT NULL,
            trilha_term_id bigint(20) DEFAULT 0,
            wp_category_id bigint(20) DEFAULT 0,
            wp_page_id bigint(20) DEFAULT 0,
            ql_project_id bigint(20) DEFAULT 0,
            data_sync datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (gc_projeto_id),
            KEY trilha_term_id (trilha_term_id),
            KEY wp_category_id (wp_category_id),
            KEY wp_page_id (wp_page_id),
            KEY ql_project_id (ql_project_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    // Métodos auxiliares
    
    private function get_projeto_by_id($projeto_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gc_projetos WHERE id = %d",
            $projeto_id
        ));
    }
    
    private function get_mapped_category_id($projeto_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT wp_category_id FROM {$wpdb->prefix}ql_trilha_mappings WHERE gc_projeto_id = %d",
            $projeto_id
        ));
    }
    
    private function get_mapped_page_id($projeto_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT wp_page_id FROM {$wpdb->prefix}ql_trilha_mappings WHERE gc_projeto_id = %d",
            $projeto_id
        ));
    }
    
    private function get_mapped_project_id($projeto_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT ql_project_id FROM {$wpdb->prefix}ql_trilha_mappings WHERE gc_projeto_id = %d",
            $projeto_id
        ));
    }
    
    private function get_template_type($tipo) {
        return self::TIPOS_TRILHA[$tipo] ?? 'learning';
    }
    
    /**
     * Obter mapeamento completo de um projeto
     */
    public function get_projeto_mapping($projeto_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_trilha_mappings WHERE gc_projeto_id = %d",
            $projeto_id
        ));
    }
    
    /**
     * Forçar re-sincronização de todos os projetos
     */
    public function force_resync() {
        delete_option('ql_projetos_synced');
        $this->sync_all_projetos_gc();
        update_option('ql_projetos_synced', true);
    }
    
    /**
     * Detectar tipo de projeto baseado no nome
     */
    private function detect_projeto_tipo($nome) {
        $nome_lower = strtolower($nome);
        
        if (strpos($nome_lower, 'pesquisa') !== false || strpos($nome_lower, 'research') !== false) {
            return 'pesquisa';
        }
        if (strpos($nome_lower, 'cria') !== false || strpos($nome_lower, 'creation') !== false) {
            return 'criacao';
        }
        
        // Padrão é aprendizagem
        return 'aprendizagem';
    }
    
    /**
     * Converter status do GC para QL (tabela ql_projects)
     */
    private function convert_gc_status_to_ql($gc_status) {
        $map = [
            'ativo' => 'active',
            'pausado' => 'on_hold',
            'finalizado' => 'completed',
            'arquivado' => 'archived',
            'rascunho' => 'active'
        ];
        
        return $map[$gc_status] ?? 'active';
    }
    
    /**
     * Converter visibilidade do GC para QL (tabela ql_projects)
     */
    private function convert_gc_visibility_to_ql($gc_visibility) {
        $map = [
            'publico' => 'public',
            'privado' => 'private',
            'listado' => 'team'
        ];
        
        return $map[$gc_visibility] ?? 'team';
    }
}