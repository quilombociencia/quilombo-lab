<?php
/**
 * Ferramenta administrativa para forçar ressincronização de projetos
 * Acessível via: /wp-admin/admin.php?page=ql-resync-projects
 */

// Impedir acesso direto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para ferramenta de ressincronização
 */
class QL_Admin_Resync_Tool {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 99);
        add_action('wp_ajax_ql_force_resync_projects', [__CLASS__, 'ajax_force_resync']);
    }
    
    /**
     * Adicionar página ao menu admin
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'quilombo-lab-settings',
            'Ressincronizar Projetos',
            'Ressincronizar',
            'manage_options',
            'ql-resync-projects',
            [__CLASS__, 'admin_page']
        );
    }
    
    /**
     * Página administrativa
     */
    public static function admin_page() {
        global $wpdb;
        
        // Estatísticas atuais
        $total_projects = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects WHERE status = 'active'");
        $mapped_projects = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects p 
            INNER JOIN {$wpdb->prefix}ql_trilha_mappings m ON p.id = m.ql_project_id 
            WHERE p.status = 'active' AND m.wp_page_id > 0 AND m.wp_category_id > 0
        ");
        $unmapped_projects = $total_projects - $mapped_projects;
        
        ?>
        <div class="wrap">
            <h1>🔄 Ressincronização de Projetos</h1>
            
            <div class="notice notice-info">
                <p><strong>Status atual:</strong></p>
                <ul>
                    <li>📊 Total de projetos ativos: <strong><?php echo $total_projects; ?></strong></li>
                    <li>✅ Projetos com páginas/categorias: <strong><?php echo $mapped_projects; ?></strong></li>
                    <li>⚠️ Projetos sem sincronização: <strong><?php echo $unmapped_projects; ?></strong></li>
                </ul>
            </div>
            
            <?php if ($unmapped_projects > 0): ?>
            <div class="notice notice-warning">
                <p><strong>⚠️ Atenção:</strong> Existem <?php echo $unmapped_projects; ?> projetos sem páginas/categorias criadas.</p>
                <p>Use a ferramenta abaixo para corrigi-los.</p>
            </div>
            <?php else: ?>
            <div class="notice notice-success">
                <p><strong>✅ Tudo certo!</strong> Todos os projetos têm páginas e categorias sincronizadas.</p>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>🛠️ Ferramentas de Sincronização</h2>
                
                <p><strong>Opção 1: Ressincronização Automática</strong></p>
                <p>Força a ressincronização na próxima visita ao painel administrativo.</p>
                <button type="button" class="button button-primary" onclick="resetSyncFlag()">
                    🔄 Agendar Ressincronização
                </button>
                
                <hr>
                
                <p><strong>Opção 2: Sincronização Imediata</strong></p>
                <p>Executa a sincronização agora mesmo via AJAX.</p>
                <button type="button" class="button button-secondary" onclick="forceSyncNow()" id="sync-now-btn">
                    ⚡ Sincronizar Agora
                </button>
                
                <hr>
                
                <p><strong>Opção 3: Sincronização da Hierarquia Moodle</strong></p>
                <p>Sincroniza a estrutura hierárquica de categorias do Moodle para o WordPress.</p>
                <button type="button" class="button button-primary" onclick="syncMoodleHierarchy()" id="hierarchy-btn">
                    🏗️ Sincronizar Hierarquia
                </button>
                
                <div id="sync-results" style="margin-top: 15px;"></div>
            </div>
            
            <div class="card">
                <h2>📋 Projetos Ativos</h2>
                <?php self::display_projects_table(); ?>
            </div>
        </div>
        
        <script>
        function resetSyncFlag() {
            if (confirm('Confirmar ressincronização automática?')) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=ql_reset_sync_flag&_ajax_nonce=<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        alert('✅ Ressincronização agendada! Recarregue a página para ver o resultado.');
                        location.reload();
                    } else {
                        alert('❌ Erro: ' + (data.data || 'Falha desconhecida'));
                    }
                });
            }
        }
        
        function forceSyncNow() {
            const btn = document.getElementById('sync-now-btn');
            const results = document.getElementById('sync-results');
            
            btn.disabled = true;
            btn.textContent = '⏳ Sincronizando...';
            results.innerHTML = '<div class="notice notice-info"><p>Executando sincronização...</p></div>';
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ql_force_resync_projects&_ajax_nonce=<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
            }).then(response => response.json()).then(data => {
                btn.disabled = false;
                btn.textContent = '⚡ Sincronizar Agora';
                
                if (data.success) {
                    results.innerHTML = '<div class="notice notice-success"><p>✅ ' + data.data.message + '</p></div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    results.innerHTML = '<div class="notice notice-error"><p>❌ Erro: ' + (data.data || 'Falha desconhecida') + '</p></div>';
                }
            });
        }
        
        function syncMoodleHierarchy() {
            const btn = document.getElementById('hierarchy-btn');
            const results = document.getElementById('sync-results');
            
            btn.disabled = true;
            btn.textContent = '⏳ Sincronizando hierarquia...';
            results.innerHTML = '<div class="notice notice-info"><p>Sincronizando hierarquia de categorias do Moodle...</p></div>';
            
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=ql_sync_moodle_hierarchy&_ajax_nonce=<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
            }).then(response => response.json()).then(data => {
                btn.disabled = false;
                btn.textContent = '🏗️ Sincronizar Hierarquia';
                
                if (data.success) {
                    results.innerHTML = '<div class="notice notice-success"><p>✅ ' + data.data.message + '</p></div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    results.innerHTML = '<div class="notice notice-error"><p>❌ Erro: ' + (data.data || 'Falha desconhecida') + '</p></div>';
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Mostrar tabela de projetos
     */
    public static function display_projects_table() {
        global $wpdb;
        
        $projects = $wpdb->get_results("
            SELECT p.*, m.wp_page_id, m.wp_category_id 
            FROM {$wpdb->prefix}ql_projects p 
            LEFT JOIN {$wpdb->prefix}ql_trilha_mappings m ON p.id = m.ql_project_id 
            WHERE p.status = 'active' 
            ORDER BY p.name
        ");
        
        if (empty($projects)) {
            echo '<p>Nenhum projeto ativo encontrado.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome do Projeto</th>
                    <th>Slug</th>
                    <th>Página</th>
                    <th>Categoria</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td><?php echo $project->id; ?></td>
                    <td><strong><?php echo esc_html($project->name); ?></strong></td>
                    <td><code><?php echo esc_html($project->slug); ?></code></td>
                    <td>
                        <?php if ($project->wp_page_id): ?>
                            <span style="color: green;">✅ ID: <?php echo $project->wp_page_id; ?></span>
                        <?php else: ?>
                            <span style="color: red;">❌ Não criada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($project->wp_category_id): ?>
                            <span style="color: green;">✅ ID: <?php echo $project->wp_category_id; ?></span>
                        <?php else: ?>
                            <span style="color: red;">❌ Não criada</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($project->wp_page_id && $project->wp_category_id): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span> Sincronizado
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color: orange;"></span> Pendente
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX para forçar ressincronização
     */
    public static function ajax_force_resync() {
        if (!check_ajax_referer('ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissões insuficientes');
        }
        
        // Forçar execução da sincronização
        delete_option('ql_projects_pages_synced');
        
        if (class_exists('QL_Production_Sync_Fix')) {
            QL_Production_Sync_Fix::sync_ql_projects_delayed();
        }
        
        wp_send_json_success([
            'message' => 'Sincronização executada com sucesso!'
        ]);
    }
}

// Inicializar ferramenta
add_action('init', ['QL_Admin_Resync_Tool', 'init']);