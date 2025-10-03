<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gestão de usuários do Quilombo Laboratório
 */
class QL_User {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('user_register', [$this, 'on_user_register']);
        add_action('profile_update', [$this, 'on_profile_update']);
        add_action('show_user_profile', [$this, 'show_user_profile_fields']);
        add_action('edit_user_profile', [$this, 'show_user_profile_fields']);
        add_action('personal_options_update', [$this, 'save_user_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);
    }
    
    public function init() {
        // Adicionar capabilities personalizadas
        $this->add_custom_capabilities();
        
        // Adicionar roles personalizados
        $this->add_custom_roles();
    }
    
    /**
     * Adicionar capabilities personalizadas
     */
    private function add_custom_capabilities() {
        $capabilities = [
            'ql_view_projects' => __('Ver projetos do Laboratório', 'quilombo-laboratorio'),
            'ql_create_tasks' => __('Criar tarefas', 'quilombo-laboratorio'),
            'ql_edit_own_tasks' => __('Editar próprias tarefas', 'quilombo-laboratorio'),
            'ql_edit_all_tasks' => __('Editar todas as tarefas', 'quilombo-laboratorio'),
            'ql_delete_tasks' => __('Excluir tarefas', 'quilombo-laboratorio'),
            'ql_manage_projects' => __('Gerenciar projetos', 'quilombo-laboratorio'),
            'ql_manage_boards' => __('Gerenciar quadros', 'quilombo-laboratorio'),
            'ql_view_reports' => __('Ver relatórios', 'quilombo-laboratorio'),
            'ql_manage_users' => __('Gerenciar usuários do Laboratório', 'quilombo-laboratorio')
        ];
        
        // Adicionar capabilities aos roles existentes
        $admin_role = get_role('administrator');
        $editor_role = get_role('editor');
        $author_role = get_role('author');
        
        if ($admin_role) {
            foreach ($capabilities as $cap => $description) {
                $admin_role->add_cap($cap);
            }
        }
        
        if ($editor_role) {
            $editor_caps = [
                'ql_view_projects',
                'ql_create_tasks',
                'ql_edit_own_tasks',
                'ql_edit_all_tasks',
                'ql_delete_tasks',
                'ql_manage_boards',
                'ql_view_reports'
            ];
            
            foreach ($editor_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
        
        if ($author_role) {
            $author_caps = [
                'ql_view_projects',
                'ql_create_tasks',
                'ql_edit_own_tasks',
                'ql_view_reports'
            ];
            
            foreach ($author_caps as $cap) {
                $author_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Adicionar roles personalizados
     */
    private function add_custom_roles() {
        // Project Manager
        add_role('ql_project_manager', __('Gerente de Projeto', 'quilombo-laboratorio'), [
            'read' => true,
            'ql_view_projects' => true,
            'ql_create_tasks' => true,
            'ql_edit_own_tasks' => true,
            'ql_edit_all_tasks' => true,
            'ql_delete_tasks' => true,
            'ql_manage_projects' => true,
            'ql_manage_boards' => true,
            'ql_view_reports' => true
        ]);
        
        // Team Member
        add_role('ql_team_member', __('Membro da Equipe', 'quilombo-laboratorio'), [
            'read' => true,
            'ql_view_projects' => true,
            'ql_create_tasks' => true,
            'ql_edit_own_tasks' => true,
            'ql_view_reports' => true
        ]);
        
        // Observer
        add_role('ql_observer', __('Observador', 'quilombo-laboratorio'), [
            'read' => true,
            'ql_view_projects' => true,
            'ql_view_reports' => true
        ]);
    }
    
    /**
     * Quando um usuário é registrado
     */
    public function on_user_register($user_id) {
        // Adicionar metadados padrão do Laboratório
        update_user_meta($user_id, 'ql_user_preferences', [
            'notifications_enabled' => true,
            'default_project_view' => 'kanban',
            'timezone' => get_option('timezone_string', 'America/Sao_Paulo'),
            'language' => 'pt_BR'
        ]);
        
        // Adicionar ao projeto padrão se configurado
        $default_project = get_option('ql_default_project_for_new_users');
        if ($default_project) {
            $this->add_user_to_project($user_id, $default_project, 'member');
        }
        
        // Log da ação
        error_log("QL User: Novo usuário registrado - ID: {$user_id}");
    }
    
    /**
     * Quando perfil é atualizado
     */
    public function on_profile_update($user_id) {
        // Sincronizar dados com outras tabelas se necessário
        $this->sync_user_data($user_id);
    }
    
    /**
     * Exibir campos personalizados no perfil
     */
    public function show_user_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        
        $preferences = get_user_meta($user->ID, 'ql_user_preferences', true);
        $preferences = is_array($preferences) ? $preferences : [];
        
        $user_projects = $this->get_user_projects($user->ID);
        $user_stats = $this->get_user_stats($user->ID);
        ?>
        
        <h3><?php _e('Configurações do Laboratório', 'quilombo-laboratorio'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Notificações', 'quilombo-laboratorio'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ql_notifications_enabled" value="1" 
                               <?php checked($preferences['notifications_enabled'] ?? true); ?> />
                        <?php _e('Receber notificações do Laboratório', 'quilombo-laboratorio'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Visualização Padrão', 'quilombo-laboratorio'); ?></th>
                <td>
                    <select name="ql_default_project_view">
                        <option value="kanban" <?php selected($preferences['default_project_view'] ?? 'kanban', 'kanban'); ?>>
                            <?php _e('Quadro Kanban', 'quilombo-laboratorio'); ?>
                        </option>
                        <option value="list" <?php selected($preferences['default_project_view'] ?? 'kanban', 'list'); ?>>
                            <?php _e('Lista de Tarefas', 'quilombo-laboratorio'); ?>
                        </option>
                        <option value="calendar" <?php selected($preferences['default_project_view'] ?? 'kanban', 'calendar'); ?>>
                            <?php _e('Calendário', 'quilombo-laboratorio'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Fuso Horário', 'quilombo-laboratorio'); ?></th>
                <td>
                    <select name="ql_timezone">
                        <?php
                        $current_timezone = $preferences['timezone'] ?? get_option('timezone_string', 'America/Sao_Paulo');
                        $timezones = [
                            'America/Sao_Paulo' => 'São Paulo (GMT-3)',
                            'America/Rio_Branco' => 'Rio Branco (GMT-5)',
                            'America/Manaus' => 'Manaus (GMT-4)',
                            'UTC' => 'UTC (GMT+0)'
                        ];
                        
                        foreach ($timezones as $tz => $label) {
                            echo '<option value="' . esc_attr($tz) . '"' . selected($current_timezone, $tz, false) . '>' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <h3><?php _e('Estatísticas do Laboratório', 'quilombo-laboratorio'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Projetos', 'quilombo-laboratorio'); ?></th>
                <td>
                    <p><strong><?php echo count($user_projects); ?></strong> <?php _e('projetos participando', 'quilombo-laboratorio'); ?></p>
                    <?php if (!empty($user_projects)): ?>
                        <ul style="margin-top: 10px;">
                            <?php foreach ($user_projects as $project): ?>
                                <li>
                                    <strong><?php echo esc_html($project['name']); ?></strong> 
                                    <span class="description">(<?php echo esc_html($project['role']); ?>)</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Tarefas', 'quilombo-laboratorio'); ?></th>
                <td>
                    <p><strong><?php echo $user_stats['total_tasks']; ?></strong> <?php _e('tarefas atribuídas', 'quilombo-laboratorio'); ?></p>
                    <p><strong><?php echo $user_stats['completed_tasks']; ?></strong> <?php _e('tarefas concluídas', 'quilombo-laboratorio'); ?></p>
                    <p><strong><?php echo $user_stats['active_tasks']; ?></strong> <?php _e('tarefas ativas', 'quilombo-laboratorio'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Última Atividade', 'quilombo-laboratorio'); ?></th>
                <td>
                    <p><?php echo $user_stats['last_activity'] ? date_i18n('d/m/Y H:i', strtotime($user_stats['last_activity'])) : __('Nunca', 'quilombo-laboratorio'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php if (current_user_can('ql_manage_users')): ?>
            <h3><?php _e('Gestão de Projetos', 'quilombo-laboratorio'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Adicionar a Projeto', 'quilombo-laboratorio'); ?></th>
                    <td>
                        <select name="ql_add_to_project" id="ql-add-to-project">
                            <option value=""><?php _e('Selecione um projeto...', 'quilombo-laboratorio'); ?></option>
                            <?php
                            $all_projects = $this->get_all_projects();
                            foreach ($all_projects as $project) {
                                echo '<option value="' . esc_attr($project['id']) . '">' . esc_html($project['name']) . '</option>';
                            }
                            ?>
                        </select>
                        
                        <select name="ql_project_role" id="ql-project-role">
                            <option value="member"><?php _e('Membro', 'quilombo-laboratorio'); ?></option>
                            <option value="manager"><?php _e('Gerente', 'quilombo-laboratorio'); ?></option>
                            <option value="observer"><?php _e('Observador', 'quilombo-laboratorio'); ?></option>
                        </select>
                        
                        <button type="button" id="ql-add-user-to-project" class="button">
                            <?php _e('Adicionar', 'quilombo-laboratorio'); ?>
                        </button>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            $('#ql-add-user-to-project').click(function() {
                                var projectId = $('#ql-add-to-project').val();
                                var role = $('#ql-project-role').val();
                                var userId = <?php echo $user->ID; ?>;
                                
                                if (!projectId) {
                                    alert('<?php _e('Selecione um projeto', 'quilombo-laboratorio'); ?>');
                                    return;
                                }
                                
                                $.post(ajaxurl, {
                                    action: 'ql_add_user_to_project',
                                    user_id: userId,
                                    project_id: projectId,
                                    role: role,
                                    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>'
                                }, function(response) {
                                    if (response.success) {
                                        alert('<?php _e('Usuário adicionado ao projeto com sucesso!', 'quilombo-laboratorio'); ?>');
                                        location.reload();
                                    } else {
                                        alert('<?php _e('Erro:', 'quilombo-laboratorio'); ?> ' + response.data);
                                    }
                                });
                            });
                        });
                        </script>
                    </td>
                </tr>
            </table>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Salvar campos personalizados do perfil
     */
    public function save_user_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        $preferences = [
            'notifications_enabled' => isset($_POST['ql_notifications_enabled']),
            'default_project_view' => sanitize_text_field($_POST['ql_default_project_view'] ?? 'kanban'),
            'timezone' => sanitize_text_field($_POST['ql_timezone'] ?? 'America/Sao_Paulo'),
            'language' => 'pt_BR'
        ];
        
        update_user_meta($user_id, 'ql_user_preferences', $preferences);
    }
    
    /**
     * Obter projetos do usuário
     */
    public function get_user_projects($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.id, p.name, pm.role 
             FROM {$wpdb->prefix}ql_projects p
             JOIN {$wpdb->prefix}ql_project_members pm ON p.id = pm.project_id
             WHERE pm.user_id = %d AND pm.status = 'active'
             ORDER BY p.name ASC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Obter estatísticas do usuário
     */
    public function get_user_stats($user_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status IN ('open', 'in_progress') THEN 1 ELSE 0 END) as active_tasks,
                MAX(updated_at) as last_activity
             FROM {$wpdb->prefix}ql_tasks 
             WHERE assigned_user_id = %d",
            $user_id
        ), ARRAY_A);
        
        return [
            'total_tasks' => intval($stats['total_tasks'] ?? 0),
            'completed_tasks' => intval($stats['completed_tasks'] ?? 0),
            'active_tasks' => intval($stats['active_tasks'] ?? 0),
            'last_activity' => $stats['last_activity']
        ];
    }
    
    /**
     * Obter todos os projetos
     */
    private function get_all_projects() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}ql_projects WHERE status = 'active' ORDER BY name ASC",
            ARRAY_A
        );
    }
    
    /**
     * Adicionar usuário a projeto
     */
    public function add_user_to_project($user_id, $project_id, $role = 'member') {
        global $wpdb;
        
        // Verificar se já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ql_project_members 
             WHERE user_id = %d AND project_id = %d",
            $user_id, $project_id
        ));
        
        if ($existing) {
            // Atualizar role se diferente
            $wpdb->update(
                $wpdb->prefix . 'ql_project_members',
                [
                    'role' => $role,
                    'status' => 'active',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Criar novo
            $wpdb->insert(
                $wpdb->prefix . 'ql_project_members',
                [
                    'user_id' => $user_id,
                    'project_id' => $project_id,
                    'role' => $role,
                    'status' => 'active',
                    'added_by' => get_current_user_id(),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s', '%s']
            );
        }
        
        // Hook para extensibilidade
        do_action('ql_user_added_to_project', $user_id, $project_id, $role);
        
        return true;
    }
    
    /**
     * Remover usuário de projeto
     */
    public function remove_user_from_project($user_id, $project_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ql_project_members',
            [
                'status' => 'inactive',
                'updated_at' => current_time('mysql')
            ],
            [
                'user_id' => $user_id,
                'project_id' => $project_id
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        // Hook para extensibilidade
        do_action('ql_user_removed_from_project', $user_id, $project_id);
        
        return $result !== false;
    }
    
    /**
     * Verificar se usuário tem acesso a projeto
     */
    public function user_can_access_project($user_id, $project_id) {
        global $wpdb;
        
        // Admins sempre podem acessar
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Verificar se é membro do projeto
        $is_member = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_project_members 
             WHERE user_id = %d AND project_id = %d AND status = 'active'",
            $user_id, $project_id
        ));
        
        if ($is_member) {
            return true;
        }
        
        // Verificar se projeto é público
        $is_public = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ql_projects 
             WHERE id = %d AND visibility = 'public'",
            $project_id
        ));
        
        return $is_public > 0;
    }
    
    /**
     * Sincronizar dados do usuário
     */
    private function sync_user_data($user_id) {
        // Sincronizar nome de exibição nas tarefas
        global $wpdb;
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }
        
        // Atualizar cache de nomes nas tarefas (se houver cache)
        wp_cache_delete("ql_user_tasks_{$user_id}", 'quilombo_laboratorio');
        
        // Log da sincronização
        error_log("QL User: Dados sincronizados para usuário {$user_id}");
    }
}