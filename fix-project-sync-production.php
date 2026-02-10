<?php
/**
 * Fix para sincronização de projetos em produção
 * Adiciona hook de ativação que cria páginas/categorias para projetos QL existentes
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para correção da sincronização em produção
 */
class QL_Production_Sync_Fix {
    
    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_sync_ql_projects'], 20);
    }
    
    /**
     * Sincronizar projetos QL existentes se necessário
     */
    public static function maybe_sync_ql_projects() {
        // Só executar uma vez após ativação/reativação
        if (get_option('ql_projects_pages_synced', false)) {
            return;
        }
        
        // Aguardar carregamento completo do WordPress
        add_action('init', [__CLASS__, 'sync_ql_projects_delayed'], 999);
    }
    
    /**
     * Executar sincronização com delay
     */
    public static function sync_ql_projects_delayed() {
        if (get_option('ql_projects_pages_synced', false)) {
            return;
        }
        
        global $wpdb;
        
        error_log('QL Fix: Iniciando sincronização de projetos QL para produção...');
        
        // Buscar todos os projetos ativos do QL
        $projetos = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE status = 'active' ORDER BY name"
        );
        
        if (empty($projetos)) {
            error_log('QL Fix: Nenhum projeto ativo encontrado');
            update_option('ql_projects_pages_synced', true);
            return;
        }
        
        $synced_count = 0;
        
        foreach ($projetos as $projeto) {
            // Verificar se já tem mapeamento
            $existing_mapping = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ql_trilha_mappings WHERE ql_project_id = %d",
                $projeto->id
            ));
            
            if ($existing_mapping && $existing_mapping->wp_page_id && $existing_mapping->wp_category_id) {
                continue; // Já sincronizado
            }
            
            $page_id = self::create_or_get_project_page($projeto);
            $cat_id = self::create_or_get_project_category($projeto);
            
            if ($page_id && $cat_id) {
                self::create_or_update_mapping($projeto, $page_id, $cat_id);
                $synced_count++;
                error_log("QL Fix: Projeto '{$projeto->name}' sincronizado - Página: {$page_id}, Categoria: {$cat_id}");
            }
        }
        
        error_log("QL Fix: Sincronização concluída - {$synced_count} projetos processados");
        update_option('ql_projects_pages_synced', true);
        
        // Adicionar aviso de sucesso no admin
        set_transient('ql_projects_sync_success', $synced_count, 300);
    }
    
    /**
     * Criar ou obter página do projeto
     */
    private static function create_or_get_project_page($projeto) {
        // Verificar se já existe
        $existing_page = get_page_by_path($projeto->slug);
        if ($existing_page) {
            return $existing_page->ID;
        }
        
        // Criar nova página
        $page_id = wp_insert_post([
            'post_title' => $projeto->name,
            'post_name' => $projeto->slug,
            'post_content' => $projeto->description ?: '',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ]);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Adicionar metadados
            update_post_meta($page_id, '_ql_project_id', $projeto->id);
            update_post_meta($page_id, '_ql_project_type', 'project_page');
            return $page_id;
        }
        
        return false;
    }
    
    /**
     * Criar ou obter categoria do projeto com hierarquia Moodle
     */
    private static function create_or_get_project_category($projeto) {
        // Verificar se já existe
        $existing_cat = get_term_by('slug', $projeto->slug, 'category');
        if ($existing_cat) {
            return $existing_cat->term_id;
        }
        
        // Determinar categoria pai baseada na categoria Moodle
        $parent_cat_id = self::get_moodle_parent_category($projeto);
        
        // Criar nova categoria
        $result = wp_insert_term($projeto->name, 'category', [
            'description' => $projeto->description ?: '',
            'slug' => $projeto->slug,
            'parent' => $parent_cat_id
        ]);
        
        if (!is_wp_error($result)) {
            $cat_id = $result['term_id'];
            // Adicionar metadados
            update_term_meta($cat_id, '_ql_project_id', $projeto->id);
            return $cat_id;
        }
        
        return false;
    }
    
    /**
     * Obter categoria pai do Moodle para o projeto
     */
    private static function get_moodle_parent_category($projeto) {
        if (!$projeto->moodle_course_id) {
            return 0; // Sem curso Moodle = sem hierarquia
        }
        
        global $wpdb;
        
        // Obter configurações do Moodle
        $moodle_settings = QL_Config::get_moodle_settings();
        if (empty($moodle_settings['database'])) {
            return 0;
        }
        
        $moodle_db = $moodle_settings['database'];
        
        try {
            // Buscar categoria do curso no Moodle
            $moodle_category = $wpdb->get_row($wpdb->prepare("
                SELECT cc.id, cc.name, cc.parent 
                FROM {$moodle_db}.mdl_course c
                JOIN {$moodle_db}.mdl_course_categories cc ON c.category = cc.id
                WHERE c.id = %d
            ", $projeto->moodle_course_id));
            
            if ($moodle_category) {
                // Buscar categoria WordPress correspondente
                $wp_categories = get_terms([
                    'taxonomy' => 'category',
                    'meta_query' => [
                        [
                            'key' => '_moodle_category_id',
                            'value' => $moodle_category->id,
                            'compare' => '='
                        ]
                    ],
                    'hide_empty' => false
                ]);
                
                if (!empty($wp_categories)) {
                    error_log("QL Fix: Projeto '{$projeto->name}' será filho da categoria '{$wp_categories[0]->name}' (ID: {$wp_categories[0]->term_id})");
                    return $wp_categories[0]->term_id;
                }
            }
        } catch (Exception $e) {
            error_log('QL Fix: Erro ao buscar categoria pai Moodle - ' . $e->getMessage());
        }
        
        return 0; // Sem categoria pai
    }
    
    /**
     * Criar ou atualizar mapeamento
     */
    private static function create_or_update_mapping($projeto, $page_id, $cat_id) {
        global $wpdb;
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_trilha_mappings WHERE ql_project_id = %d",
            $projeto->id
        ));
        
        if ($existing) {
            // Atualizar existente
            $wpdb->update(
                $wpdb->prefix . 'ql_trilha_mappings',
                [
                    'wp_page_id' => $page_id,
                    'wp_category_id' => $cat_id,
                    'data_sync' => current_time('mysql')
                ],
                ['ql_project_id' => $projeto->id]
            );
        } else {
            // Criar novo - usar ID único para evitar conflito de chave primária
            $unique_gc_id = 1000 + $projeto->id;
            $wpdb->insert(
                $wpdb->prefix . 'ql_trilha_mappings',
                [
                    'gc_projeto_id' => $unique_gc_id,
                    'trilha_term_id' => 0,
                    'wp_page_id' => $page_id,
                    'wp_category_id' => $cat_id,
                    'ql_project_id' => $projeto->id,
                    'data_sync' => current_time('mysql')
                ]
            );
        }
    }
}

// Inicializar fix
QL_Production_Sync_Fix::init();

// Hook para mostrar aviso de sucesso
add_action('admin_notices', function() {
    if ($count = get_transient('ql_projects_sync_success')) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>Quilombo Laboratório:</strong> ' . $count . ' projetos sincronizados com páginas e categorias!</p>';
        echo '</div>';
        delete_transient('ql_projects_sync_success');
    }
});