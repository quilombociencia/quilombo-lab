<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para sistema de permissões unificado entre WordPress, Moodle, Kanboard e Gestão Coletiva
 */
class QL_Unified_Permissions {
    
    private static $instance = null;
    
    // Mapeamento de roles entre sistemas
    const ROLE_MAPPINGS = [
        'wordpress' => [
            'administrator' => 'admin',
            'editor' => 'manager',
            'author' => 'coordinator',
            'contributor' => 'member',
            'subscriber' => 'viewer'
        ],
        'moodle' => [
            'manager' => 'admin',
            'coursecreator' => 'manager',
            'editingteacher' => 'coordinator',
            'teacher' => 'coordinator',
            'student' => 'member',
            'guest' => 'viewer'
        ],
        'kanboard' => [
            'app-admin' => 'admin',
            'app-manager' => 'manager',
            'app-user' => 'member'
        ],
        'gc' => [
            'administrador' => 'admin',
            'gerente' => 'manager',
            'coordenador' => 'coordinator',
            'membro' => 'member',
            'visualizador' => 'viewer'
        ]
    ];
    
    // Permissões por role unificado
    const UNIFIED_PERMISSIONS = [
        'admin' => [
            'manage_system',
            'manage_users',
            'manage_projects',
            'manage_finances',
            'view_all_data',
            'edit_all_data',
            'delete_all_data',
            'manage_integrations'
        ],
        'manager' => [
            'manage_projects',
            'manage_team_members',
            'view_project_finances',
            'edit_project_data',
            'create_projects',
            'manage_project_settings'
        ],
        'coordinator' => [
            'coordinate_project',
            'manage_tasks',
            'assign_tasks',
            'view_team_data',
            'edit_assigned_data',
            'create_tasks'
        ],
        'member' => [
            'view_project_data',
            'edit_own_tasks',
            'comment_tasks',
            'update_task_status',
            'view_assigned_data'
        ],
        'viewer' => [
            'view_public_data',
            'view_assigned_projects'
        ]
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_login', [$this, 'sync_user_permissions'], 10, 2);
        add_action('set_user_role', [$this, 'on_user_role_changed'], 10, 3);
        
        // Hooks para verificação de permissões
        add_filter('user_has_cap', [$this, 'check_unified_capabilities'], 10, 4);
        
        // AJAX endpoints
        add_action('wp_ajax_ql_update_user_permissions', [$this, 'ajax_update_user_permissions']);
        add_action('wp_ajax_ql_check_user_permission', [$this, 'ajax_check_user_permission']);
    }
    
    public function init() {
        // Criar tabela de permissões unificadas
        $this->ensure_permissions_table();
        
        // Registrar capabilities customizadas do WordPress
        $this->register_custom_capabilities();
    }
    
    /**
     * Criar tabela de permissões unificadas
     */
    private function ensure_permissions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_unified_permissions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            `system` varchar(50) NOT NULL,
            `role` varchar(50) NOT NULL,
            unified_role varchar(50) NOT NULL,
            permissions json NULL,
            project_id bigint(20) NULL,
            context varchar(100) DEFAULT 'global',
            is_active tinyint(1) DEFAULT 1,
            granted_by bigint(20) NULL,
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY `system` (`system`),
            KEY `role` (`role`),
            KEY unified_role (unified_role),
            KEY project_id (project_id),
            KEY context (context),
            UNIQUE KEY user_system_context (user_id, `system`, context, project_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Registrar capabilities customizadas no WordPress
     */
    private function register_custom_capabilities() {
        // Adicionar capabilities aos roles existentes
        $admin_caps = self::UNIFIED_PERMISSIONS['admin'];
        $manager_caps = self::UNIFIED_PERMISSIONS['manager'];
        $coordinator_caps = self::UNIFIED_PERMISSIONS['coordinator'];
        
        // Administrator - todas as permissões
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($admin_caps as $cap) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Editor - permissões de manager
        $editor_role = get_role('editor');
        if ($editor_role) {
            foreach ($manager_caps as $cap) {
                $editor_role->add_cap($cap);
            }
        }
        
        // Author - permissões de coordinator
        $author_role = get_role('author');
        if ($author_role) {
            foreach ($coordinator_caps as $cap) {
                $author_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Sincronizar permissões quando usuário faz login
     */
    public function sync_user_permissions($user_login, $user) {
        // Buscar mapeamento do usuário
        $user_sync = QL_User_Sync::get_instance();
        $mapping = $user_sync->get_user_mapping($user->ID);
        
        if (!$mapping) {
            return; // Usuário não sincronizado
        }
        
        // Sincronizar permissões de cada sistema
        $this->sync_wordpress_permissions($user);
        
        if ($mapping->moodle_user_id) {
            $this->sync_moodle_permissions($user, $mapping->moodle_user_id);
        }
        
        if ($mapping->kanboard_user_id) {
            $this->sync_kanboard_permissions($user, $mapping->kanboard_user_id);
        }
        
        // Sempre sincronizar GC (mesmo banco)
        $this->sync_gc_permissions($user);
    }
    
    /**
     * Sincronizar permissões do WordPress
     */
    private function sync_wordpress_permissions($user) {
        $wp_roles = $user->roles;
        
        foreach ($wp_roles as $wp_role) {
            $unified_role = $this->map_role_to_unified('wordpress', $wp_role);
            
            $this->save_user_permission($user->ID, [
                'system' => 'wordpress',
                'role' => $wp_role,
                'unified_role' => $unified_role,
                'permissions' => json_encode(self::UNIFIED_PERMISSIONS[$unified_role] ?? []),
                'context' => 'global'
            ]);
        }
    }
    
    /**
     * Sincronizar permissões do Moodle
     */
    private function sync_moodle_permissions($user, $moodle_user_id) {
        try {
            $moodle_db = $this->get_moodle_connection();
            
            // Buscar roles do usuário no Moodle
            $stmt = $moodle_db->prepare("
                SELECT DISTINCT r.shortname, c.id as course_id, c.fullname
                FROM mdl_role_assignments ra
                JOIN mdl_role r ON ra.roleid = r.id
                LEFT JOIN mdl_context ctx ON ra.contextid = ctx.id
                LEFT JOIN mdl_course c ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                WHERE ra.userid = ?
            ");
            $stmt->execute([$moodle_user_id]);
            $moodle_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($moodle_roles as $role_data) {
                $unified_role = $this->map_role_to_unified('moodle', $role_data['shortname']);
                $context = $role_data['course_id'] ? 'course_' . $role_data['course_id'] : 'global';
                
                $this->save_user_permission($user->ID, [
                    'system' => 'moodle',
                    'role' => $role_data['shortname'],
                    'unified_role' => $unified_role,
                    'permissions' => json_encode(self::UNIFIED_PERMISSIONS[$unified_role] ?? []),
                    'context' => $context,
                    'project_id' => $role_data['course_id']
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Moodle Permissions Sync Error: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar permissões do Kanboard
     */
    private function sync_kanboard_permissions($user, $kanboard_user_id) {
        try {
            $kanboard_db = $this->get_kanboard_connection();
            
            // Buscar role global do usuário
            $stmt = $kanboard_db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$kanboard_user_id]);
            $global_role = $stmt->fetchColumn();
            
            if ($global_role) {
                $unified_role = $this->map_role_to_unified('kanboard', $global_role);
                
                $this->save_user_permission($user->ID, [
                    'system' => 'kanboard',
                    'role' => $global_role,
                    'unified_role' => $unified_role,
                    'permissions' => json_encode(self::UNIFIED_PERMISSIONS[$unified_role] ?? []),
                    'context' => 'global'
                ]);
            }
            
            // Buscar permissões específicas de projetos
            $stmt = $kanboard_db->prepare("
                SELECT p.id as project_id, p.name, phu.role
                FROM project_has_users phu
                JOIN projects p ON phu.project_id = p.id
                WHERE phu.user_id = ? AND p.is_active = 1
            ");
            $stmt->execute([$kanboard_user_id]);
            $project_roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($project_roles as $project_role) {
                $unified_role = $this->map_role_to_unified('kanboard', $project_role['role']);
                
                $this->save_user_permission($user->ID, [
                    'system' => 'kanboard',
                    'role' => $project_role['role'],
                    'unified_role' => $unified_role,
                    'permissions' => json_encode(self::UNIFIED_PERMISSIONS[$unified_role] ?? []),
                    'context' => 'project',
                    'project_id' => $project_role['project_id']
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Kanboard Permissions Sync Error: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar permissões do Gestão Coletiva
     */
    private function sync_gc_permissions($user) {
        global $wpdb;
        
        // Buscar roles do usuário nos projetos GC
        $gc_roles = $wpdb->get_results($wpdb->prepare("
            SELECT pm.projeto_id, pm.role, p.nome as projeto_nome
            FROM {$wpdb->prefix}gc_projeto_membros pm
            JOIN {$wpdb->prefix}gc_projetos p ON pm.projeto_id = p.id
            WHERE pm.user_id = %d AND pm.status = 'ativo'
        ", $user->ID));
        
        foreach ($gc_roles as $gc_role) {
            $unified_role = $this->map_role_to_unified('gc', $gc_role->role);
            
            $this->save_user_permission($user->ID, [
                'system' => 'gc',
                'role' => $gc_role->role,
                'unified_role' => $unified_role,
                'permissions' => json_encode(self::UNIFIED_PERMISSIONS[$unified_role] ?? []),
                'context' => 'project',
                'project_id' => $gc_role->projeto_id
            ]);
        }
    }
    
    /**
     * Mapear role de sistema específico para role unificado
     */
    private function map_role_to_unified($system, $role) {
        return self::ROLE_MAPPINGS[$system][$role] ?? 'viewer';
    }
    
    /**
     * Salvar permissão do usuário
     */
    private function save_user_permission($user_id, $permission_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_unified_permissions';
        $permission_data['user_id'] = $user_id;
        
        // Verificar se já existe
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT id FROM $table_name 
            WHERE user_id = %d AND `system` = %s AND context = %s AND project_id %s
        ", 
            $user_id, 
            $permission_data['system'], 
            $permission_data['context'],
            $permission_data['project_id'] ? "= {$permission_data['project_id']}" : "IS NULL"
        ));
        
        if ($existing) {
            // Atualizar
            $wpdb->update($table_name, $permission_data, ['id' => $existing->id]);
        } else {
            // Inserir
            $wpdb->insert($table_name, $permission_data);
        }
    }
    
    /**
     * Verificar se usuário tem permissão específica
     */
    public function user_can($user_id, $permission, $context = 'global', $project_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_unified_permissions';
        
        // Construir query baseada no contexto
        $where_project = $project_id ? $wpdb->prepare("AND project_id = %d", $project_id) : "AND project_id IS NULL";
        
        $permissions = $wpdb->get_results($wpdb->prepare("
            SELECT permissions, unified_role 
            FROM $table_name 
            WHERE user_id = %d AND context = %s $where_project AND is_active = 1
        ", $user_id, $context));
        
        // Verificar permissão global se não encontrou no contexto específico
        if (empty($permissions) && $context !== 'global') {
            $permissions = $wpdb->get_results($wpdb->prepare("
                SELECT permissions, unified_role 
                FROM $table_name 
                WHERE user_id = %d AND context = 'global' AND is_active = 1
            ", $user_id));
        }
        
        foreach ($permissions as $perm_data) {
            $user_permissions = json_decode($perm_data->permissions, true) ?: [];
            
            if (in_array($permission, $user_permissions)) {
                return true;
            }
            
            // Verificar se é admin (tem todas as permissões)
            if ($perm_data->unified_role === 'admin') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Obter role unificado mais alto do usuário
     */
    public function get_user_highest_role($user_id, $context = 'global', $project_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_unified_permissions';
        $where_project = $project_id ? $wpdb->prepare("AND project_id = %d", $project_id) : "AND project_id IS NULL";
        
        $roles = $wpdb->get_col($wpdb->prepare("
            SELECT unified_role 
            FROM $table_name 
            WHERE user_id = %d AND context = %s $where_project AND is_active = 1
        ", $user_id, $context));
        
        // Hierarquia de roles (do maior para o menor)
        $role_hierarchy = ['admin', 'manager', 'coordinator', 'member', 'viewer'];
        
        foreach ($role_hierarchy as $role) {
            if (in_array($role, $roles)) {
                return $role;
            }
        }
        
        return 'viewer'; // Role padrão
    }
    
    /**
     * Hook para verificação de capabilities do WordPress
     */
    public function check_unified_capabilities($allcaps, $caps, $args, $user) {
        if (!$user || !$user->ID) {
            return $allcaps;
        }
        
        // Para cada capability verificada
        foreach ($caps as $cap) {
            // Verificar se é uma permissão unificada
            $unified_permissions = array_merge(...array_values(self::UNIFIED_PERMISSIONS));
            
            if (in_array($cap, $unified_permissions)) {
                $context = $args[2] ?? 'global';
                $project_id = $args[3] ?? null;
                
                if ($this->user_can($user->ID, $cap, $context, $project_id)) {
                    $allcaps[$cap] = true;
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Quando role do usuário é alterado
     */
    public function on_user_role_changed($user_id, $role, $old_roles) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $this->sync_wordpress_permissions($user);
        }
    }
    
    /**
     * Obter conexão com Moodle
     */
    private function get_moodle_connection() {
        $database_name = QL_Config::get_moodle_database_name();
        $dsn = QL_Config::get_external_db_dsn($database_name);
        $credentials = QL_Config::get_external_db_credentials();
        
        return new PDO($dsn, $credentials['username'], $credentials['password'], $credentials['options']);
    }
    
    /**
     * Obter conexão com Kanboard
     */
    private function get_kanboard_connection() {
        $database_name = QL_Config::get_kanboard_database_name();
        $dsn = QL_Config::get_external_db_dsn($database_name);
        $credentials = QL_Config::get_external_db_credentials();
        
        return new PDO($dsn, $credentials['username'], $credentials['password'], $credentials['options']);
    }
    
    /**
     * AJAX: Atualizar permissões do usuário
     */
    public function ajax_update_user_permissions() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_users')) {
            wp_send_json_error('Permissão insuficiente');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];
        
        if (!$user_id) {
            wp_send_json_error('ID do usuário inválido');
        }
        
        // Atualizar permissões
        foreach ($permissions as $perm_data) {
            $this->save_user_permission($user_id, $perm_data);
        }
        
        wp_send_json_success('Permissões atualizadas com sucesso');
    }
    
    /**
     * AJAX: Verificar permissão do usuário
     */
    public function ajax_check_user_permission() {
        $user_id = intval($_POST['user_id'] ?? get_current_user_id());
        $permission = sanitize_text_field($_POST['permission'] ?? '');
        $context = sanitize_text_field($_POST['context'] ?? 'global');
        $project_id = intval($_POST['project_id'] ?? 0) ?: null;
        
        $has_permission = $this->user_can($user_id, $permission, $context, $project_id);
        
        wp_send_json_success([
            'has_permission' => $has_permission,
            'user_role' => $this->get_user_highest_role($user_id, $context, $project_id)
        ]);
    }
    
    /**
     * Obter resumo de permissões do usuário
     */
    public function get_user_permissions_summary($user_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_unified_permissions';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT `system`, `role`, unified_role, context, project_id, permissions
            FROM $table_name 
            WHERE user_id = %d AND is_active = 1
            ORDER BY `system`, context, project_id
        ", $user_id));
    }
}