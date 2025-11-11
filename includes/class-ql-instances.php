<?php
/**
 * Sistema de Instâncias Organizacionais do Quilombo Laboratório
 * 
 * Implementa a hierarquia organizacional:
 * - Círculos (3-6 pessoas, instância mínima)
 * - Núcleos (3+ círculos, território compartilhado)  
 * - Comunidades (pessoas com vínculos territoriais)
 * - Coletivos (rede de projetos e pessoas)
 * - Assembleias (instância máxima deliberativa)
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Instances {
    
    private static $instance = null;
    
    /**
     * Tipos de instância conforme modelo organizativo
     */
    const INSTANCE_TYPES = [
        'circulo' => [
            'label' => 'Círculo',
            'description' => 'Grupo de 3 a 6 participantes (ou 1-2 no círculo inicial)',
            'min_members' => 1, // Círculo inicial
            'max_members' => 6, // Círculo completo
            'min_members_active' => 3, // Para considerar ativo/completo
            'required_for_project_publish' => true,
            'color' => '#3498db',
            'icon' => 'dashicons-groups',
            'parent_type' => null,
            'child_types' => ['nucleo'],
            'can_have_roles' => true,
            'territory_required' => false
        ],
        
        'nucleo' => [
            'label' => 'Núcleo',
            'description' => 'Agrupamento de no mínimo 3 círculos com território compartilhado',
            'min_circles' => 3,
            'max_circles' => null, // Ilimitado
            'physical_address_required' => true,
            'virtual_page_required' => true,
            'color' => '#9b59b6',
            'icon' => 'dashicons-location',
            'parent_type' => null,
            'child_types' => ['comunidade'],
            'can_have_roles' => true,
            'territory_required' => true
        ],
        
        'comunidade' => [
            'label' => 'Comunidade',
            'description' => 'Grupo de pessoas com vínculos mútuos em mesmo território',
            'territory_scope_required' => true,
            'belongs_to_territory' => true,
            'color' => '#27ae60',
            'icon' => 'dashicons-admin-multisite',
            'parent_type' => null,
            'child_types' => ['coletivo'],
            'can_have_roles' => true,
            'territory_required' => true
        ],
        
        'coletivo' => [
            'label' => 'Coletivo',
            'description' => 'Rede de pessoas, projetos e comunidades',
            'min_active_projects' => 1,
            'min_active_circles' => 3,
            'can_span_territories' => true,
            'requires_founding_assembly' => true,
            'color' => '#e74c3c',
            'icon' => 'dashicons-networking',
            'parent_type' => null,
            'child_types' => [],
            'can_have_roles' => true,
            'territory_required' => false
        ],
        
        'assembleia' => [
            'label' => 'Assembleia',
            'description' => 'Instância máxima de deliberação do coletivo',
            'is_event_based' => true,
            'has_agenda' => true,
            'has_periodicity' => true,
            'can_be_extraordinary' => true,
            'requires_quorum' => true,
            'color' => '#f39c12',
            'icon' => 'dashicons-megaphone',
            'parent_type' => 'coletivo',
            'child_types' => [],
            'can_have_roles' => false,
            'territory_required' => false
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
        
        // Hooks para gestão de instâncias
        add_action('ql_instance_created', [$this, 'on_instance_created'], 10, 3);
        add_action('ql_instance_updated', [$this, 'on_instance_updated'], 10, 3);
        add_action('ql_instance_member_added', [$this, 'on_member_added'], 10, 4);
        add_action('ql_instance_member_removed', [$this, 'on_member_removed'], 10, 4);
        
        // AJAX handlers
        add_action('wp_ajax_ql_create_instance', [$this, 'ajax_create_instance']);
        add_action('wp_ajax_ql_add_member_to_instance', [$this, 'ajax_add_member']);
        add_action('wp_ajax_ql_remove_member_from_instance', [$this, 'ajax_remove_member']);
        add_action('wp_ajax_ql_get_instance_hierarchy', [$this, 'ajax_get_hierarchy']);
        
        // Shortcodes
        add_shortcode('ql_instance_display', [$this, 'shortcode_instance_display']);
        add_shortcode('ql_instances_list', [$this, 'shortcode_instances_list']);
    }
    
    /**
     * Inicialização
     */
    public function init() {
        // Criar tabelas se necessário
        $this->maybe_create_instances_tables();
        
        // Registrar post types para instâncias (opcional - para páginas públicas)
        $this->register_instance_post_types();
    }
    
    /**
     * Criar tabelas para instâncias
     */
    private function maybe_create_instances_tables() {
        global $wpdb;
        
        // Tabela principal de instâncias
        $instances_table = $wpdb->prefix . 'ql_instances';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$instances_table'") !== $instances_table) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $instances_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                name varchar(255) NOT NULL,
                slug varchar(255) NOT NULL,
                description longtext,
                status varchar(50) DEFAULT 'active',
                parent_instance_id bigint(20) NULL,
                creator_id bigint(20) NOT NULL,
                project_id bigint(20) NULL,
                territory_id bigint(20) NULL,
                physical_address text,
                virtual_page_url text,
                settings longtext,
                metadata longtext,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY slug_type (slug, type),
                KEY type (type),
                KEY status (status),
                KEY parent_instance_id (parent_instance_id),
                KEY creator_id (creator_id),
                KEY project_id (project_id)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
        
        // Tabela de membros de instâncias
        $members_table = $wpdb->prefix . 'ql_instance_members';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$members_table'") !== $members_table) {
            $sql = "CREATE TABLE $members_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                instance_id bigint(20) NOT NULL,
                user_id bigint(20) NOT NULL,
                role varchar(50) DEFAULT 'member',
                joined_at timestamp DEFAULT CURRENT_TIMESTAMP,
                joined_by bigint(20) NULL,
                status varchar(50) DEFAULT 'active',
                metadata longtext,
                PRIMARY KEY (id),
                UNIQUE KEY instance_user (instance_id, user_id),
                KEY instance_id (instance_id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
        
        // Tabela de relacionamentos entre instâncias
        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$relationships_table'") !== $relationships_table) {
            $sql = "CREATE TABLE $relationships_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                parent_instance_id bigint(20) NOT NULL,
                child_instance_id bigint(20) NOT NULL,
                relationship_type varchar(50) NOT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                metadata longtext,
                PRIMARY KEY (id),
                UNIQUE KEY parent_child (parent_instance_id, child_instance_id),
                KEY parent_instance_id (parent_instance_id),
                KEY child_instance_id (child_instance_id)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
        
        error_log("QL Instances: Tabelas criadas/verificadas");
    }
    
    /**
     * Registrar post types para páginas públicas das instâncias
     */
    private function register_instance_post_types() {
        // Post type para núcleos (que precisam de páginas públicas)
        register_post_type('ql_nucleo_page', [
            'labels' => [
                'name' => 'Páginas de Núcleos',
                'singular_name' => 'Página de Núcleo',
                'add_new' => 'Adicionar Página',
                'add_new_item' => 'Adicionar Nova Página de Núcleo',
                'edit_item' => 'Editar Página de Núcleo',
                'new_item' => 'Nova Página de Núcleo',
                'view_item' => 'Ver Página de Núcleo',
                'search_items' => 'Buscar Páginas de Núcleos',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-location',
            'rewrite' => ['slug' => 'nucleo'],
            'show_in_rest' => true
        ]);
    }
    
    /**
     * Criar nova instância
     */
    public function create_instance($type, $name, $creator_id, $options = []) {
        global $wpdb;
        
        // Validar tipo
        if (!isset(self::INSTANCE_TYPES[$type])) {
            return new WP_Error('invalid_type', 'Tipo de instância inválido');
        }
        
        $type_config = self::INSTANCE_TYPES[$type];
        
        // Validar permissões
        if (!$this->user_can_create_instance($creator_id, $type)) {
            return new WP_Error('insufficient_permissions', 'Permissões insuficientes para criar esta instância');
        }
        
        // Gerar slug único
        $slug = $this->generate_unique_slug($name, $type);
        
        // Preparar dados
        $instance_data = [
            'type' => $type,
            'name' => sanitize_text_field($name),
            'slug' => $slug,
            'description' => sanitize_textarea_field($options['description'] ?? ''),
            'status' => 'active',
            'creator_id' => $creator_id,
            'parent_instance_id' => $options['parent_id'] ?? null,
            'project_id' => $options['project_id'] ?? null,
            'territory_id' => $options['territory_id'] ?? null,
            'physical_address' => sanitize_textarea_field($options['physical_address'] ?? ''),
            'virtual_page_url' => esc_url($options['virtual_page_url'] ?? ''),
            'settings' => json_encode($options['settings'] ?? []),
            'metadata' => json_encode($options['metadata'] ?? [])
        ];
        
        // Inserir no banco
        $table_name = $wpdb->prefix . 'ql_instances';
        $result = $wpdb->insert($table_name, $instance_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar instância no banco');
        }
        
        $instance_id = $wpdb->insert_id;
        
        // Adicionar criador como primeiro membro
        $this->add_member_to_instance($instance_id, $creator_id, 'founder');
        
        // Criar página pública se necessário
        if ($type === 'nucleo') {
            $this->create_nucleo_public_page($instance_id, $name, $options);
        }
        
        // Disparar hook
        do_action('ql_instance_created', $instance_id, $type, $instance_data);
        
        return $instance_id;
    }
    
    /**
     * Adicionar membro à instância
     */
    public function add_member_to_instance($instance_id, $user_id, $role = 'member', $added_by = null) {
        global $wpdb;
        
        $instance = $this->get_instance($instance_id);
        if (!$instance) {
            return new WP_Error('instance_not_found', 'Instância não encontrada');
        }
        
        // Verificar limites para círculos
        if ($instance->type === 'circulo') {
            $member_count = $this->get_member_count($instance_id);
            $max_members = self::INSTANCE_TYPES['circulo']['max_members'];
            
            if ($member_count >= $max_members) {
                return new WP_Error('circle_full', 'Círculo já possui o número máximo de membros');
            }
        }
        
        // Inserir membro
        $table_name = $wpdb->prefix . 'ql_instance_members';
        $result = $wpdb->replace($table_name, [
            'instance_id' => $instance_id,
            'user_id' => $user_id,
            'role' => $role,
            'joined_by' => $added_by ?: get_current_user_id(),
            'status' => 'active'
        ]);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao adicionar membro');
        }
        
        // Atualizar status da instância se necessário
        $this->update_instance_status($instance_id);
        
        // Disparar hook
        do_action('ql_instance_member_added', $instance_id, $user_id, $role, $added_by);
        
        return true;
    }
    
    /**
     * Remover membro da instância
     */
    public function remove_member_from_instance($instance_id, $user_id, $removed_by = null) {
        global $wpdb;
        
        // Marcar como inativo ao invés de deletar (auditoria)
        $table_name = $wpdb->prefix . 'ql_instance_members';
        $result = $wpdb->update(
            $table_name,
            ['status' => 'inactive'],
            ['instance_id' => $instance_id, 'user_id' => $user_id],
            ['%s'],
            ['%d', '%d']
        );
        
        // Atualizar status da instância
        $this->update_instance_status($instance_id);
        
        // Disparar hook
        do_action('ql_instance_member_removed', $instance_id, $user_id, $removed_by);
        
        return $result !== false;
    }
    
    /**
     * Obter instância por ID
     */
    public function get_instance($instance_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_instances';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND status != 'deleted'",
            $instance_id
        ));
    }
    
    /**
     * Obter membros de uma instância
     */
    public function get_instance_members($instance_id, $status = 'active') {
        global $wpdb;
        
        $members_table = $wpdb->prefix . 'ql_instance_members';
        $users_table = $wpdb->users;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, u.display_name, u.user_email 
             FROM $members_table m 
             JOIN $users_table u ON m.user_id = u.ID 
             WHERE m.instance_id = %d AND m.status = %s 
             ORDER BY m.joined_at ASC",
            $instance_id, $status
        ));
    }
    
    /**
     * Obter número de membros ativos
     */
    public function get_member_count($instance_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_instance_members';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE instance_id = %d AND status = 'active'",
            $instance_id
        ));
    }
    
    /**
     * Atualizar status da instância baseado em regras
     */
    public function update_instance_status($instance_id) {
        $instance = $this->get_instance($instance_id);
        if (!$instance) return;
        
        $type_config = self::INSTANCE_TYPES[$instance->type];
        $member_count = $this->get_member_count($instance_id);
        
        $new_status = 'active';
        
        // Lógica específica por tipo
        switch ($instance->type) {
            case 'circulo':
                if ($member_count === 0) {
                    $new_status = 'empty';
                } elseif ($member_count < $type_config['min_members_active']) {
                    $new_status = 'initial'; // Círculo inicial
                } else {
                    $new_status = 'complete'; // Círculo completo
                }
                break;
                
            case 'nucleo':
                $circle_count = $this->count_child_instances($instance_id, 'circulo');
                if ($circle_count < $type_config['min_circles']) {
                    $new_status = 'forming';
                } else {
                    $new_status = 'active';
                }
                break;
                
            case 'coletivo':
                $circle_count = $this->count_child_instances($instance_id, 'circulo');
                if ($circle_count < $type_config['min_active_circles']) {
                    $new_status = 'forming';
                } else {
                    $new_status = 'active';
                }
                break;
        }
        
        // Atualizar no banco
        global $wpdb;
        $table_name = $wpdb->prefix . 'ql_instances';
        $wpdb->update(
            $table_name,
            ['status' => $new_status],
            ['id' => $instance_id],
            ['%s'],
            ['%d']
        );
    }
    
    /**
     * Contar instâncias filhas de um tipo específico
     */
    private function count_child_instances($parent_id, $child_type) {
        global $wpdb;
        
        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        $instances_table = $wpdb->prefix . 'ql_instances';
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $relationships_table r 
             JOIN $instances_table i ON r.child_instance_id = i.id 
             WHERE r.parent_instance_id = %d 
             AND i.type = %s 
             AND i.status IN ('active', 'complete')",
            $parent_id, $child_type
        ));
    }
    
    /**
     * Verificar se usuário pode criar instância
     */
    public function user_can_create_instance($user_id, $type) {
        // Administradores podem criar qualquer instância
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Verificar capabilities específicas
        $required_caps = [
            'circulo' => 'ql_create_trilhas', // Guias podem criar círculos
            'nucleo' => 'ql_manage_circulos', // Gestores podem criar núcleos
            'comunidade' => 'ql_manage_resources', // Operação pode criar comunidades
            'coletivo' => 'ql_manage_settings', // Gestão pode criar coletivos
            'assembleia' => 'ql_manage_consultations' // Documentação pode convocar assembleias
        ];
        
        $required_cap = $required_caps[$type] ?? 'manage_options';
        return user_can($user_id, $required_cap);
    }
    
    /**
     * Gerar slug único
     */
    private function generate_unique_slug($name, $type) {
        global $wpdb;
        
        $base_slug = sanitize_title($name);
        $slug = $base_slug;
        $counter = 1;
        
        $table_name = $wpdb->prefix . 'ql_instances';
        
        while ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE slug = %s AND type = %s",
            $slug, $type
        )) > 0) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Criar página pública para núcleo
     */
    private function create_nucleo_public_page($instance_id, $name, $options) {
        $page_data = [
            'post_title' => $name . ' - Núcleo',
            'post_content' => '[ql_instance_display id="' . $instance_id . '"]',
            'post_status' => 'publish',
            'post_type' => 'ql_nucleo_page',
            'meta_input' => [
                'ql_instance_id' => $instance_id,
                'ql_physical_address' => $options['physical_address'] ?? '',
                'ql_territory_scope' => $options['territory_scope'] ?? ''
            ]
        ];
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Atualizar instância com URL da página
            global $wpdb;
            $table_name = $wpdb->prefix . 'ql_instances';
            $wpdb->update(
                $table_name,
                ['virtual_page_url' => get_permalink($page_id)],
                ['id' => $instance_id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    /**
     * Obter hierarquia completa de instâncias
     */
    public function get_instances_hierarchy($root_type = null) {
        global $wpdb;
        
        $instances_table = $wpdb->prefix . 'ql_instances';
        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        
        // Base query
        $query = "
            SELECT i.*, 
                   COUNT(m.id) as member_count,
                   GROUP_CONCAT(DISTINCT m.user_id) as member_ids
            FROM $instances_table i
            LEFT JOIN {$wpdb->prefix}ql_instance_members m ON i.id = m.instance_id AND m.status = 'active'
            WHERE i.status != 'deleted'
        ";
        
        if ($root_type) {
            $query .= $wpdb->prepare(" AND i.type = %s", $root_type);
        }
        
        $query .= " GROUP BY i.id ORDER BY i.type, i.created_at";
        
        $instances = $wpdb->get_results($query);
        
        // Organizar em hierarquia
        $hierarchy = [];
        $instances_by_id = [];
        
        foreach ($instances as $instance) {
            $instances_by_id[$instance->id] = $instance;
            $instance->children = [];
        }
        
        // Construir árvore
        foreach ($instances as $instance) {
            if ($instance->parent_instance_id && isset($instances_by_id[$instance->parent_instance_id])) {
                $instances_by_id[$instance->parent_instance_id]->children[] = $instance;
            } else {
                $hierarchy[] = $instance;
            }
        }
        
        return $hierarchy;
    }
    
    /**
     * AJAX - Criar instância
     */
    public function ajax_create_instance() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $type = sanitize_text_field($_POST['type']);
        $name = sanitize_text_field($_POST['name']);
        $creator_id = get_current_user_id();
        
        $options = [
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'parent_id' => !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
            'project_id' => !empty($_POST['project_id']) ? intval($_POST['project_id']) : null,
            'physical_address' => sanitize_textarea_field($_POST['physical_address'] ?? ''),
            'virtual_page_url' => esc_url($_POST['virtual_page_url'] ?? '')
        ];
        
        $result = $this->create_instance($type, $name, $creator_id, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => 'Instância criada com sucesso',
                'instance_id' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Adicionar membro
     */
    public function ajax_add_member() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $instance_id = intval($_POST['instance_id']);
        $user_id = intval($_POST['user_id']);
        $role = sanitize_text_field($_POST['role'] ?? 'member');
        
        $result = $this->add_member_to_instance($instance_id, $user_id, $role);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Membro adicionado com sucesso']);
        }
    }
    
    /**
     * AJAX - Remover membro
     */
    public function ajax_remove_member() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $instance_id = intval($_POST['instance_id']);
        $user_id = intval($_POST['user_id']);
        
        $result = $this->remove_member_from_instance($instance_id, $user_id);
        
        if ($result) {
            wp_send_json_success(['message' => 'Membro removido com sucesso']);
        } else {
            wp_send_json_error(['message' => 'Erro ao remover membro']);
        }
    }
    
    /**
     * AJAX - Obter hierarquia
     */
    public function ajax_get_hierarchy() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $root_type = sanitize_text_field($_POST['root_type'] ?? '');
        $hierarchy = $this->get_instances_hierarchy($root_type ?: null);
        
        wp_send_json_success(['hierarchy' => $hierarchy]);
    }
    
    /**
     * Shortcode para exibir instância
     */
    public function shortcode_instance_display($atts) {
        $atts = shortcode_atts([
            'id' => 0,
            'type' => '',
            'slug' => ''
        ], $atts);
        
        // TODO: Implementar template de exibição pública
        return '<div class="ql-instance-display">Exibição de instância em desenvolvimento...</div>';
    }
    
    /**
     * Shortcode para listar instâncias
     */
    public function shortcode_instances_list($atts) {
        $atts = shortcode_atts([
            'type' => '',
            'limit' => 10,
            'show_members' => 'false'
        ], $atts);
        
        // TODO: Implementar listagem pública
        return '<div class="ql-instances-list">Listagem de instâncias em desenvolvimento...</div>';
    }
    
    /**
     * Adicionar menu administrativo
     */
    public function add_admin_menu() {
        add_submenu_page(
            'quilombo-lab',
            'Instâncias Organizacionais',
            'Instâncias',
            'manage_options',
            'ql-instances',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Página administrativa
     */
    public function admin_page() {
        include QL_PLUGIN_PATH . 'templates/admin-instances.php';
    }
    
    /**
     * Hook quando instância é criada
     */
    public function on_instance_created($instance_id, $type, $data) {
        error_log("QL Instances: Nova instância {$type} criada - ID: {$instance_id}");
        
        // Lógica específica por tipo
        switch ($type) {
            case 'circulo':
                // Se for círculo de projeto, ativar projeto quando completo
                if ($data['project_id']) {
                    $this->maybe_activate_project($data['project_id'], $instance_id);
                }
                break;
                
            case 'coletivo':
                // Criar instâncias básicas necessárias
                $this->setup_coletivo_initial_structure($instance_id);
                break;
        }
    }
    
    /**
     * Configurar estrutura inicial de coletivo
     */
    private function setup_coletivo_initial_structure($coletivo_id) {
        // Criar círculo inicial de gestão
        $gestao_circle = $this->create_instance(
            'circulo',
            'Círculo de Gestão Inicial',
            get_current_user_id(),
            [
                'parent_id' => $coletivo_id,
                'description' => 'Círculo inicial responsável pela gestão do coletivo',
                'metadata' => ['is_management_circle' => true]
            ]
        );
        
        if (!is_wp_error($gestao_circle)) {
            error_log("QL Instances: Círculo de gestão criado para coletivo {$coletivo_id}");
        }
    }
    
    /**
     * Verificar se projeto pode ser ativado baseado no círculo
     */
    private function maybe_activate_project($project_id, $circle_id) {
        $member_count = $this->get_member_count($circle_id);
        $min_active = self::INSTANCE_TYPES['circulo']['min_members_active'];
        
        if ($member_count >= $min_active) {
            // Ativar projeto
            do_action('ql_project_activate_from_circle', $project_id, $circle_id);
        }
    }
    
    /**
     * Obter definições de tipos de instância
     */
    public static function get_instance_types() {
        return self::INSTANCE_TYPES;
    }
    
    /**
     * Verificar se instância está completa/ativa conforme regras
     */
    public function is_instance_complete($instance_id) {
        $instance = $this->get_instance($instance_id);
        if (!$instance) return false;
        
        return in_array($instance->status, ['active', 'complete']);
    }
}