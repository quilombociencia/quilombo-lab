<?php
/**
 * Sincronização da Hierarquia de Categorias Moodle → WordPress
 * 
 * Replica a estrutura hierárquica de categorias do Moodle nas categorias WordPress
 * mantendo a relação pai-filho e organizando os projetos corretamente.
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para sincronização hierárquica
 */
class QL_Moodle_Hierarchy_Sync {
    
    private static $instance = null;
    private static $category_mapping = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init() {
        add_action('init', [__CLASS__, 'maybe_sync_hierarchy'], 999);
        add_action('wp_ajax_ql_sync_moodle_hierarchy', [__CLASS__, 'ajax_sync_hierarchy']);
    }
    
    /**
     * Sincronizar hierarquia se necessário
     */
    public static function maybe_sync_hierarchy() {
        // Só executar se necessário
        if (get_option('ql_moodle_hierarchy_synced', false)) {
            return;
        }
        
        self::sync_moodle_categories_hierarchy();
        update_option('ql_moodle_hierarchy_synced', true);
        
        error_log('QL Hierarchy: Sincronização de hierarquia Moodle concluída');
    }
    
    /**
     * Sincronizar todas as categorias do Moodle com hierarquia
     */
    public static function sync_moodle_categories_hierarchy() {
        global $wpdb;
        
        // Obter configurações do Moodle
        $moodle_settings = QL_Config::get_moodle_settings();
        if (empty($moodle_settings['database'])) {
            error_log('QL Hierarchy: Configuração de banco Moodle não encontrada');
            return false;
        }
        
        $moodle_db = $moodle_settings['database'];
        
        try {
            // Buscar todas as categorias do Moodle em ordem hierárquica
            $moodle_categories = $wpdb->get_results("
                SELECT id, name, parent, sortorder, depth, path 
                FROM {$moodle_db}.mdl_course_categories 
                ORDER BY depth, sortorder
            ");
            
            if (empty($moodle_categories)) {
                error_log('QL Hierarchy: Nenhuma categoria encontrada no Moodle');
                return false;
            }
            
            // Primeiro passo: criar/atualizar categorias raiz (parent = 0)
            foreach ($moodle_categories as $moodle_cat) {
                if ($moodle_cat->parent == 0) {
                    self::create_or_update_wp_category($moodle_cat, 0);
                }
            }
            
            // Segundo passo: criar/atualizar categorias filhas
            foreach ($moodle_categories as $moodle_cat) {
                if ($moodle_cat->parent > 0) {
                    $parent_wp_id = self::get_wp_category_by_moodle_id($moodle_cat->parent);
                    self::create_or_update_wp_category($moodle_cat, $parent_wp_id);
                }
            }
            
            // Terceiro passo: reorganizar projetos nas categorias corretas
            self::reorganize_projects_in_hierarchy();
            
            return true;
            
        } catch (Exception $e) {
            error_log('QL Hierarchy: Erro na sincronização - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Criar ou atualizar categoria WordPress com hierarquia
     */
    private static function create_or_update_wp_category($moodle_cat, $parent_wp_id = 0) {
        // Verificar se categoria já existe (por metadado)
        $existing_terms = get_terms([
            'taxonomy' => 'category',
            'meta_query' => [
                [
                    'key' => '_moodle_category_id',
                    'value' => $moodle_cat->id,
                    'compare' => '='
                ]
            ],
            'hide_empty' => false
        ]);
        
        $cat_args = [
            'description' => "Categoria sincronizada do Moodle (ID: {$moodle_cat->id})",
            'parent' => $parent_wp_id,
            'slug' => sanitize_title($moodle_cat->name)
        ];
        
        if (!empty($existing_terms)) {
            // Atualizar categoria existente
            $wp_cat_id = $existing_terms[0]->term_id;
            wp_update_term($wp_cat_id, 'category', array_merge($cat_args, [
                'name' => $moodle_cat->name
            ]));
        } else {
            // Criar nova categoria
            $result = wp_insert_term($moodle_cat->name, 'category', $cat_args);
            
            if (!is_wp_error($result)) {
                $wp_cat_id = $result['term_id'];
                
                // Adicionar metadados para rastreamento
                update_term_meta($wp_cat_id, '_moodle_category_id', $moodle_cat->id);
                update_term_meta($wp_cat_id, '_moodle_parent_id', $moodle_cat->parent);
                update_term_meta($wp_cat_id, '_moodle_depth', $moodle_cat->depth);
                update_term_meta($wp_cat_id, '_moodle_path', $moodle_cat->path);
                update_term_meta($wp_cat_id, '_sync_source', 'moodle_hierarchy');
            } else {
                error_log('QL Hierarchy: Erro ao criar categoria ' . $moodle_cat->name . ' - ' . $result->get_error_message());
                return false;
            }
        }
        
        // Salvar mapeamento para referência
        self::$category_mapping[$moodle_cat->id] = $wp_cat_id;
        
        error_log("QL Hierarchy: Categoria '{$moodle_cat->name}' (Moodle ID: {$moodle_cat->id}) → WordPress ID: {$wp_cat_id}, Parent: {$parent_wp_id}");
        
        return $wp_cat_id;
    }
    
    /**
     * Obter ID da categoria WordPress pelo ID do Moodle
     */
    private static function get_wp_category_by_moodle_id($moodle_id) {
        // Verificar cache local primeiro
        if (isset(self::$category_mapping[$moodle_id])) {
            return self::$category_mapping[$moodle_id];
        }
        
        // Buscar no banco
        $terms = get_terms([
            'taxonomy' => 'category',
            'meta_query' => [
                [
                    'key' => '_moodle_category_id',
                    'value' => $moodle_id,
                    'compare' => '='
                ]
            ],
            'hide_empty' => false
        ]);
        
        if (!empty($terms)) {
            $wp_cat_id = $terms[0]->term_id;
            self::$category_mapping[$moodle_id] = $wp_cat_id;
            return $wp_cat_id;
        }
        
        return 0;
    }
    
    /**
     * Reorganizar projetos nas categorias hierárquicas corretas
     */
    private static function reorganize_projects_in_hierarchy() {
        global $wpdb;
        
        // Buscar todos os mapeamentos de projetos
        $project_mappings = $wpdb->get_results("
            SELECT 
                p.id as project_id, 
                p.name as project_name,
                p.moodle_course_id,
                m.wp_category_id,
                m.wp_page_id
            FROM {$wpdb->prefix}ql_projects p
            INNER JOIN {$wpdb->prefix}ql_trilha_mappings m ON p.id = m.ql_project_id
            WHERE p.moodle_course_id IS NOT NULL
        ");
        
        $moodle_settings = QL_Config::get_moodle_settings();
        $moodle_db = $moodle_settings['database'];
        
        foreach ($project_mappings as $mapping) {
            // Buscar categoria correta do curso no Moodle
            $moodle_course = $wpdb->get_row($wpdb->prepare("
                SELECT c.category as moodle_category_id
                FROM {$moodle_db}.mdl_course c
                WHERE c.id = %d
            ", $mapping->moodle_course_id));
            
            if ($moodle_course) {
                $correct_wp_category = self::get_wp_category_by_moodle_id($moodle_course->moodle_category_id);
                
                if ($correct_wp_category && $correct_wp_category != $mapping->wp_category_id) {
                    // Atualizar categoria do projeto
                    $wpdb->update(
                        $wpdb->prefix . 'ql_trilha_mappings',
                        ['wp_category_id' => $correct_wp_category],
                        ['project_id' => $mapping->project_id]
                    );
                    
                    // Atualizar categoria da página WordPress
                    if ($mapping->wp_page_id) {
                        wp_set_post_categories($mapping->wp_page_id, [$correct_wp_category]);
                    }
                    
                    error_log("QL Hierarchy: Projeto '{$mapping->project_name}' movido para categoria correta (ID: {$correct_wp_category})");
                }
            }
        }
    }
    
    /**
     * AJAX para sincronização manual
     */
    public static function ajax_sync_hierarchy() {
        if (!check_ajax_referer('ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissões insuficientes');
        }
        
        // Forçar ressincronização
        delete_option('ql_moodle_hierarchy_synced');
        
        $success = self::sync_moodle_categories_hierarchy();
        
        if ($success) {
            update_option('ql_moodle_hierarchy_synced', true);
            wp_send_json_success([
                'message' => 'Hierarquia de categorias sincronizada com sucesso!'
            ]);
        } else {
            wp_send_json_error('Erro na sincronização da hierarquia');
        }
    }
    
    /**
     * Obter estrutura hierárquica para exibição
     */
    public static function get_hierarchy_tree() {
        $categories = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_sync_source',
                    'value' => 'moodle_hierarchy',
                    'compare' => '='
                ]
            ]
        ]);
        
        // Organizar em árvore
        $tree = [];
        $category_map = [];
        
        // Primeiro, indexar todas as categorias
        foreach ($categories as $cat) {
            $category_map[$cat->term_id] = $cat;
            $cat->children = [];
        }
        
        // Depois, organizar hierarquia
        foreach ($categories as $cat) {
            if ($cat->parent == 0) {
                $tree[] = $cat;
            } else {
                if (isset($category_map[$cat->parent])) {
                    $category_map[$cat->parent]->children[] = $cat;
                }
            }
        }
        
        return $tree;
    }
}

// Inicializar
QL_Moodle_Hierarchy_Sync::init();