<?php
/**
 * Sistema de Papéis Organizativos do Quilombo Laboratório
 * 
 * Implementa os 6 papéis básicos do modelo organizativo:
 * - Participante (base)
 * - Guia (didático-pedagógico)
 * - Orientação (especialista)
 * - Operação (técnico-prático)
 * - Comunicação (informacional/midiático)
 * - Documentação (normativo/auditoria)
 * - Gestão (recursos/administrativo)
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Organizational_Roles {
    
    private static $instance = null;
    
    /**
     * Definição dos papéis organizativos conforme modelo
     */
    const ORGANIZATIONAL_ROLES = [
        'participante' => [
            'label' => 'Participante',
            'description' => 'Pessoa inscrita que pode participar de trilhas e projetos',
            'capabilities' => [
                'ql_participate_trilhas',
                'ql_create_tasks_initial',
                'ql_comment_blog',
                'ql_view_collective_boards'
            ],
            'tasks' => ['Participação ativa', 'Aprendizagem colaborativa'],
            'actions' => ['Participar', 'Aprender', 'Contribuir', 'Interagir'],
            'color' => '#95a5a6',
            'icon' => 'dashicons-groups',
            'is_base' => true
        ],
        
        'guia' => [
            'label' => 'Guia',
            'description' => 'Responsável por guiar participantes em conteúdos, atividades e eventos',
            'capabilities' => [
                'ql_create_trilhas',
                'ql_manage_inscricoes',
                'ql_manage_circulos',
                'ql_admin_projeto_page',
                'ql_activate_projects',
                'ql_set_trilha_modality'
            ],
            'tasks' => ['Didático-pedagógicas'],
            'actions' => ['Guiar', 'Criar trilhas', 'Mediar', 'Explicar', 'Planejar', 'Organizar'],
            'color' => '#3498db',
            'icon' => 'dashicons-welcome-learn-more',
            'hierarchy_level' => 2
        ],
        
        'orientacao' => [
            'label' => 'Orientação',
            'description' => 'Especialista que orienta com conhecimento teórico/prático reconhecido',
            'capabilities' => [
                'ql_approve_trilhas',
                'ql_evaluate_activities', 
                'ql_assign_grades',
                'ql_manage_references',
                'ql_supervise_projects'
            ],
            'tasks' => ['Teóricas', 'Metodológicas', 'Corretivas', 'Instrutivas'],
            'actions' => ['Assessorar', 'Supervisionar', 'Certificar', 'Corrigir', 'Revisar', 'Aconselhar'],
            'color' => '#9b59b6',
            'icon' => 'dashicons-awards',
            'hierarchy_level' => 3,
            'requires_admin_permission' => true
        ],
        
        'operacao' => [
            'label' => 'Operação',
            'description' => 'Especialista técnico-prático para transformações materiais',
            'capabilities' => [
                'ql_execute_technical_tasks',
                'ql_manage_resources',
                'ql_handle_operations',
                'ql_technical_support'
            ],
            'tasks' => ['Técnicas', 'Práticas', 'Operacionais'],
            'actions' => ['Construir', 'Consertar', 'Instalar', 'Executar', 'Operar', 'Manter'],
            'color' => '#e67e22',
            'icon' => 'dashicons-admin-tools',
            'hierarchy_level' => 2
        ],
        
        'comunicacao' => [
            'label' => 'Comunicação',
            'description' => 'Responsável por comunicar e processar informações em todas as mídias',
            'capabilities' => [
                'ql_manage_communications',
                'ql_publish_content',
                'ql_edit_media',
                'ql_manage_blog_posts',
                'ql_public_relations'
            ],
            'tasks' => ['Informacionais', 'Midiáticas', 'Artísticas', 'Jornalísticas'],
            'actions' => ['Captar', 'Editar', 'Escrever', 'Gravar', 'Postar', 'Divulgar', 'Produzir'],
            'color' => '#1abc9c',
            'icon' => 'dashicons-megaphone',
            'hierarchy_level' => 2
        ],
        
        'documentacao' => [
            'label' => 'Documentação',
            'description' => 'Responsável por documentar ações e manter aspectos normativos',
            'capabilities' => [
                'ql_document_processes',
                'ql_audit_activities',
                'ql_manage_consultations',
                'ql_maintain_records',
                'ql_verify_compliance'
            ],
            'tasks' => ['Normativas', 'Técnicas', 'Metodológicas'],
            'actions' => ['Registrar', 'Auditar', 'Relatar', 'Verificar', 'Fiscalizar', 'Consultar'],
            'color' => '#34495e',
            'icon' => 'dashicons-media-document',
            'hierarchy_level' => 3,
            'requires_admin_permission' => true
        ],
        
        'gestao' => [
            'label' => 'Gestão',
            'description' => 'Responsável por gerir recursos e decisões administrativas/financeiras',
            'capabilities' => [
                'ql_manage_resources',
                'ql_financial_operations',
                'ql_make_launches_gc',
                'ql_effectuate_launches_gc',
                'ql_manage_bank_operations',
                'ql_administrative_decisions'
            ],
            'tasks' => ['Administrativas', 'Contábeis', 'Financeiras', 'Econômicas'],
            'actions' => ['Administrar', 'Pagar', 'Receber', 'Lançar', 'Analisar', 'Prestar contas'],
            'color' => '#e74c3c',
            'icon' => 'dashicons-businessman',
            'hierarchy_level' => 3,
            'requires_admin_permission' => true
        ]
    ];
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Hooks para atribuição de papéis
        add_action('ql_assign_organizational_role', [$this, 'assign_role_to_user'], 10, 4);
        add_action('ql_remove_organizational_role', [$this, 'remove_role_from_user'], 10, 3);
        
        // AJAX handlers
        add_action('wp_ajax_ql_assign_role', [$this, 'ajax_assign_role']);
        add_action('wp_ajax_ql_remove_role', [$this, 'ajax_remove_role']);
        add_action('wp_ajax_ql_get_user_roles', [$this, 'ajax_get_user_roles']);
        
        // Hooks de usuário
        add_action('user_register', [$this, 'on_user_register']);
        add_action('profile_update', [$this, 'sync_user_capabilities']);
    }
    
    /**
     * Inicialização
     */
    public function init() {
        // Registrar capabilities personalizadas
        $this->register_organizational_capabilities();
        
        // Criar tabela de relacionamentos se necessário
        $this->maybe_create_roles_table();
        
        // Aplicar capabilities baseadas em papéis existentes
        add_action('wp_loaded', [$this, 'sync_all_user_capabilities']);
    }
    
    /**
     * Registrar capabilities organizativas no WordPress
     */
    public function register_organizational_capabilities() {
        $admin_role = get_role('administrator');
        if (!$admin_role) return;
        
        // Coletar todas as capabilities únicas
        $all_caps = [];
        foreach (self::ORGANIZATIONAL_ROLES as $role_data) {
            $all_caps = array_merge($all_caps, $role_data['capabilities']);
        }
        $all_caps = array_unique($all_caps);
        
        // Adicionar todas as capabilities para administradores
        foreach ($all_caps as $cap) {
            $admin_role->add_cap($cap);
        }
        
        // Capabilities básicas para usuários registrados
        $subscriber_role = get_role('subscriber');
        if ($subscriber_role) {
            foreach (self::ORGANIZATIONAL_ROLES['participante']['capabilities'] as $cap) {
                $subscriber_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Criar tabela para relacionamentos de papéis organizativos
     */
    private function maybe_create_roles_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return; // Tabela já existe
        }
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            role_key varchar(50) NOT NULL,
            context_type varchar(50) NOT NULL DEFAULT 'global',
            context_id bigint(20) NULL,
            assigned_by bigint(20) NULL,
            assigned_at timestamp DEFAULT CURRENT_TIMESTAMP,
            expires_at timestamp NULL,
            is_active tinyint(1) DEFAULT 1,
            metadata longtext,
            PRIMARY KEY (id),
            UNIQUE KEY user_role_context (user_id, role_key, context_type, context_id),
            KEY user_id (user_id),
            KEY role_key (role_key),
            KEY context (context_type, context_id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Log da criação
        error_log("QL Organizational Roles: Tabela $table_name criada");
    }
    
    /**
     * Atribuir papel organizativo a usuário
     */
    public function assign_role_to_user($user_id, $role_key, $context_type = 'global', $context_id = null) {
        global $wpdb;
        
        // Validar papel
        if (!isset(self::ORGANIZATIONAL_ROLES[$role_key])) {
            return new WP_Error('invalid_role', 'Papel organizativo inválido');
        }
        
        $role_data = self::ORGANIZATIONAL_ROLES[$role_key];
        
        // Verificar permissões se necessário
        if (isset($role_data['requires_admin_permission']) && $role_data['requires_admin_permission']) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('insufficient_permissions', 'Permissões insuficientes para atribuir este papel');
            }
        }
        
        // Inserir/atualizar relacionamento
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        
        $result = $wpdb->replace(
            $table_name,
            [
                'user_id' => $user_id,
                'role_key' => $role_key,
                'context_type' => $context_type,
                'context_id' => $context_id,
                'assigned_by' => get_current_user_id(),
                'is_active' => 1
            ],
            ['%d', '%s', '%s', '%d', '%d', '%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao salvar papel no banco');
        }
        
        // Aplicar capabilities ao usuário
        $this->apply_role_capabilities($user_id, $role_key, true);
        
        // Disparar hook
        do_action('ql_organizational_role_assigned', $user_id, $role_key, $context_type, $context_id);
        
        return true;
    }
    
    /**
     * Remover papel organizativo de usuário
     */
    public function remove_role_from_user($user_id, $role_key, $context_type = 'global', $context_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        
        // Marcar como inativo ao invés de deletar (auditoria)
        $result = $wpdb->update(
            $table_name,
            ['is_active' => 0],
            [
                'user_id' => $user_id,
                'role_key' => $role_key,
                'context_type' => $context_type,
                'context_id' => $context_id
            ],
            ['%d'],
            ['%d', '%s', '%s', '%d']
        );
        
        // Remover capabilities (recalcular baseado nos papéis restantes)
        $this->sync_user_capabilities($user_id);
        
        // Disparar hook
        do_action('ql_organizational_role_removed', $user_id, $role_key, $context_type, $context_id);
        
        return $result !== false;
    }
    
    /**
     * Aplicar capabilities de um papel específico
     */
    private function apply_role_capabilities($user_id, $role_key, $add = true) {
        if (!isset(self::ORGANIZATIONAL_ROLES[$role_key])) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        $capabilities = self::ORGANIZATIONAL_ROLES[$role_key]['capabilities'];
        
        foreach ($capabilities as $cap) {
            if ($add) {
                $user->add_cap($cap);
            } else {
                $user->remove_cap($cap);
            }
        }
    }
    
    /**
     * Sincronizar capabilities de um usuário baseado em seus papéis ativos
     */
    public function sync_user_capabilities($user_id) {
        global $wpdb;
        
        $user = get_user_by('id', $user_id);
        if (!$user) return;
        
        // Remover todas as capabilities organizativas primeiro
        foreach (self::ORGANIZATIONAL_ROLES as $role_data) {
            foreach ($role_data['capabilities'] as $cap) {
                $user->remove_cap($cap);
            }
        }
        
        // Buscar papéis ativos do usuário
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        $active_roles = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT role_key FROM $table_name 
             WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
        
        // Aplicar capabilities dos papéis ativos
        foreach ($active_roles as $role) {
            $this->apply_role_capabilities($user_id, $role->role_key, true);
        }
        
        // Garantir capabilities básicas de participante para todos os usuários cadastrados
        foreach (self::ORGANIZATIONAL_ROLES['participante']['capabilities'] as $cap) {
            $user->add_cap($cap);
        }
    }
    
    /**
     * Sincronizar capabilities de todos os usuários
     */
    public function sync_all_user_capabilities() {
        // Executar apenas uma vez por requisição
        static $synced = false;
        if ($synced) return;
        $synced = true;
        
        // Em ambiente de desenvolvimento, sincronizar sempre
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $users = get_users(['fields' => 'ID']);
            foreach ($users as $user_id) {
                $this->sync_user_capabilities($user_id);
            }
        }
    }
    
    /**
     * Hook quando usuário se registra - atribuir papel de participante
     */
    public function on_user_register($user_id) {
        // Todo novo usuário começa como participante
        $this->assign_role_to_user($user_id, 'participante', 'global', null);
        
        // Se for o primeiro usuário, torná-lo gestor do coletivo
        $user_count = count_users();
        if ($user_count['total_users'] <= 1) {
            $this->assign_role_to_user($user_id, 'gestao', 'coletivo', 1);
            $this->assign_role_to_user($user_id, 'guia', 'coletivo', 1);
        }
    }
    
    /**
     * Obter papéis organizativos de um usuário
     */
    public function get_user_roles($user_id, $context_type = null, $context_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        $where_conditions = ["user_id = %d", "is_active = 1"];
        $where_values = [$user_id];
        
        if ($context_type) {
            $where_conditions[] = "context_type = %s";
            $where_values[] = $context_type;
        }
        
        if ($context_id) {
            $where_conditions[] = "context_id = %d";
            $where_values[] = $context_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT role_key, context_type, context_id, assigned_at 
             FROM $table_name 
             WHERE $where_clause 
             ORDER BY assigned_at DESC",
            $where_values
        ));
        
        return $results;
    }
    
    /**
     * Verificar se usuário tem papel específico
     */
    public function user_has_role($user_id, $role_key, $context_type = 'global', $context_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE user_id = %d AND role_key = %s AND context_type = %s 
             AND (context_id = %d OR context_id IS NULL) AND is_active = 1",
            $user_id, $role_key, $context_type, $context_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Obter usuários com papel específico
     */
    public function get_users_with_role($role_key, $context_type = 'global', $context_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_organizational_roles';
        
        $query = "
            SELECT u.*, r.assigned_at, r.context_type, r.context_id
            FROM {$wpdb->users} u
            INNER JOIN $table_name r ON u.ID = r.user_id
            WHERE r.role_key = %s AND r.context_type = %s AND r.is_active = 1
        ";
        
        $values = [$role_key, $context_type];
        
        if ($context_id) {
            $query .= " AND r.context_id = %d";
            $values[] = $context_id;
        }
        
        $query .= " ORDER BY r.assigned_at DESC";
        
        return $wpdb->get_results($wpdb->prepare($query, $values));
    }
    
    /**
     * Adicionar menu administrativo
     */
    public function add_admin_menu() {
        add_submenu_page(
            'quilombo-lab',
            'Papéis Organizativos',
            'Papéis Organizativos',
            'manage_options',
            'ql-organizational-roles',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Página administrativa
     */
    public function admin_page() {
        if (isset($_POST['assign_role']) && wp_verify_nonce($_POST['_wpnonce'], 'ql_assign_role')) {
            $user_id = intval($_POST['user_id']);
            $role_key = sanitize_text_field($_POST['role_key']);
            $context_type = sanitize_text_field($_POST['context_type']);
            $context_id = !empty($_POST['context_id']) ? intval($_POST['context_id']) : null;
            
            $result = $this->assign_role_to_user($user_id, $role_key, $context_type, $context_id);
            
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>Erro: ' . $result->get_error_message() . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Papel atribuído com sucesso!</p></div>';
            }
        }
        
        include QL_PLUGIN_PATH . 'templates/admin-organizational-roles.php';
    }
    
    /**
     * AJAX - Atribuir papel
     */
    public function ajax_assign_role() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $user_id = intval($_POST['user_id']);
        $role_key = sanitize_text_field($_POST['role_key']);
        $context_type = sanitize_text_field($_POST['context_type']);
        $context_id = !empty($_POST['context_id']) ? intval($_POST['context_id']) : null;
        
        $result = $this->assign_role_to_user($user_id, $role_key, $context_type, $context_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Papel atribuído com sucesso']);
        }
    }
    
    /**
     * AJAX - Remover papel
     */
    public function ajax_remove_role() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $user_id = intval($_POST['user_id']);
        $role_key = sanitize_text_field($_POST['role_key']);
        $context_type = sanitize_text_field($_POST['context_type']);
        $context_id = !empty($_POST['context_id']) ? intval($_POST['context_id']) : null;
        
        $result = $this->remove_role_from_user($user_id, $role_key, $context_type, $context_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Papel removido com sucesso']);
        } else {
            wp_send_json_error(['message' => 'Erro ao remover papel']);
        }
    }
    
    /**
     * AJAX - Obter papéis do usuário
     */
    public function ajax_get_user_roles() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $user_id = intval($_POST['user_id']);
        $roles = $this->get_user_roles($user_id);
        
        wp_send_json_success(['roles' => $roles]);
    }
    
    /**
     * Obter definições de papéis
     */
    public static function get_role_definitions() {
        return self::ORGANIZATIONAL_ROLES;
    }
    
    /**
     * Obter papéis hierárquicos de um usuário
     */
    public function get_user_hierarchy($user_id) {
        $user_roles = $this->get_user_roles($user_id);
        
        $hierarchy = [];
        foreach ($user_roles as $role_assignment) {
            $role_key = $role_assignment->role_key;
            $role_data = self::ORGANIZATIONAL_ROLES[$role_key];
            
            $hierarchy[] = [
                'role_key' => $role_key,
                'label' => $role_data['label'],
                'level' => $role_data['hierarchy_level'] ?? 1,
                'context' => $role_assignment->context_type,
                'context_id' => $role_assignment->context_id
            ];
        }
        
        // Ordenar por nível hierárquico (maior = mais poder)
        usort($hierarchy, function($a, $b) {
            return $b['level'] - $a['level'];
        });
        
        return $hierarchy;
    }
    
    /**
     * Verificar se usuário pode atribuir papel a outro usuário
     */
    public function can_assign_role($assigner_id, $target_role_key, $context_type = 'global') {
        // Administradores podem tudo
        if (user_can($assigner_id, 'manage_options')) {
            return true;
        }
        
        // Verificar hierarquia
        $assigner_hierarchy = $this->get_user_hierarchy($assigner_id);
        if (empty($assigner_hierarchy)) {
            return false;
        }
        
        $target_role_level = self::ORGANIZATIONAL_ROLES[$target_role_key]['hierarchy_level'] ?? 1;
        $max_assigner_level = $assigner_hierarchy[0]['level'];
        
        // Só pode atribuir papéis de nível inferior
        return $max_assigner_level > $target_role_level;
    }
}