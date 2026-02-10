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
        // Comentado: menu movido para QL_Admin sob menu Organização
        // add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Hooks para gestão de instâncias
        add_action('ql_instance_created', [$this, 'on_instance_created'], 10, 3);
        add_action('ql_instance_updated', [$this, 'on_instance_updated'], 10, 3);
        add_action('ql_instance_member_added', [$this, 'on_member_added'], 10, 4);
        add_action('ql_instance_member_removed', [$this, 'on_member_removed'], 10, 4);
        
        // AJAX handlers
        add_action('wp_ajax_ql_import_moodle_groups', [$this, 'ajax_import_moodle_groups']);
        add_action('wp_ajax_ql_sync_instances_from_moodle', [$this, 'ajax_sync_instances']);
        add_action('wp_ajax_ql_add_member_to_instance', [$this, 'ajax_add_member']);
        add_action('wp_ajax_ql_remove_member_from_instance', [$this, 'ajax_remove_member']);
        add_action('wp_ajax_ql_get_instance_hierarchy', [$this, 'ajax_get_hierarchy']);
        add_action('wp_ajax_ql_update_nucleus_address', [$this, 'ajax_update_nucleus_address']);
        add_action('wp_ajax_ql_get_nucleus_address', [$this, 'ajax_get_nucleus_address']);
        add_action('wp_ajax_ql_create_community', [$this, 'ajax_create_community']);
        add_action('wp_ajax_ql_create_assembly', [$this, 'ajax_create_assembly']);
        add_action('wp_ajax_ql_schedule_assembly', [$this, 'ajax_schedule_assembly']);
        
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
     * DESABILITADO: Conforme modelo organizativo, páginas são criadas apenas para projetos
     */
    private function register_instance_post_types() {
        // Post types REMOVIDOS para núcleos - não devem aparecer no menu WordPress
        // Conforme especificação: núcleos são mapeados de agrupamentos do Moodle
        // Páginas são criadas apenas para projetos quando publicados
        return;
        
        /*
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
        */
    }
    
    /**
     * Criar nova instância - DESABILITADO
     * Círculos e Núcleos devem ser criados apenas através do Moodle (grupos/agrupamentos)
     */
    public function create_instance($type, $name, $creator_id, $options = []) {
        // Círculos e núcleos não podem ser criados pelo plugin
        if (in_array($type, ['circulo', 'nucleo'])) {
            return new WP_Error(
                'creation_disabled', 
                'Círculos e núcleos devem ser criados no Moodle como grupos/agrupamentos'
            );
        }
        
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
        
        // Disparar hook
        do_action('ql_instance_created', $instance_id, $type, $instance_data);
        
        return $instance_id;
    }
    
    /**
     * Adicionar membro à instância
     */
    public function add_member_to_instance($instance_id, $user_id, $role = 'member', $added_by = null) {
        global $wpdb;
        
        $instance = $this->get_instance_by_id($instance_id);
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
    public function get_instance_by_id($instance_id) {
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
        $instance = $this->get_instance_by_id($instance_id);
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
     * Obter instâncias filhas de uma instância pai
     */
    public function get_child_instances($parent_id, $child_type = null) {
        global $wpdb;

        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        $instances_table = $wpdb->prefix . 'ql_instances';

        $sql = "SELECT i.*
                FROM $instances_table i
                LEFT JOIN $relationships_table r ON r.child_instance_id = i.id
                WHERE (r.parent_instance_id = %d OR i.parent_instance_id = %d)";

        $params = [$parent_id, $parent_id];

        if ($child_type) {
            $sql .= " AND i.type = %s";
            $params[] = $child_type;
        }

        $sql .= " AND i.status != 'deleted' ORDER BY i.name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Obter todas as instâncias de um tipo específico
     */
    public function get_instances_by_type($type, $status = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ql_instances';

        $sql = "SELECT * FROM $table_name WHERE type = %s";
        $params = [$type];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        } else {
            $sql .= " AND status != 'deleted'";
        }

        $sql .= " ORDER BY name ASC";

        return $wpdb->get_results($wpdb->prepare($sql, $params));
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
     * DESABILITADO: Conforme modelo organizativo, páginas são criadas apenas para projetos
     */
    private function create_nucleo_public_page($instance_id, $name, $options) {
        // Método desabilitado - não criar páginas para núcleos
        return;
        
        /*
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
        */
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
     * Importar grupos/agrupamentos do Moodle como círculos/núcleos
     */
    public function import_from_moodle_groups($course_id = null) {
        if (!class_exists('QL_Moodle_Integration')) {
            return new WP_Error('moodle_not_available', 'Integração Moodle não disponível');
        }
        
        $moodle = QL_Moodle_Integration::get_instance();
        
        try {
            // Obter grupos do curso ou todos os grupos
            $groups = $course_id ? 
                $moodle->get_course_groups($course_id) : 
                $moodle->get_all_groups();
                
            // Obter agrupamentos
            $groupings = $course_id ? 
                $moodle->get_course_groupings($course_id) : 
                $moodle->get_all_groupings();
            
            $imported = ['circles' => 0, 'nuclei' => 0, 'members' => 0, 'errors' => []];

            // Importar grupos como círculos
            foreach ($groups as $group) {
                // Buscar membros do grupo
                $group_id = is_array($group) ? ($group['id'] ?? null) : null;
                if ($group_id) {
                    try {
                        $member_ids = $moodle->get_group_members($group_id);
                        if (!empty($member_ids)) {
                            $members_data = $moodle->get_users_by_ids($member_ids);
                            $group['members'] = $members_data;
                        }
                    } catch (Exception $e) {
                        error_log('QL Instances: Erro ao buscar membros do grupo ' . $group_id . ': ' . $e->getMessage());
                    }
                }

                $result = $this->import_moodle_group_as_circle($group);
                if (is_wp_error($result)) {
                    $imported['errors'][] = "Grupo {$group['name']}: " . $result->get_error_message();
                } else {
                    $imported['circles']++;
                    if (!empty($group['members'])) {
                        $imported['members'] += count($group['members']);
                    }
                }
            }

            // Importar agrupamentos como núcleos
            foreach ($groupings as $grouping) {
                $result = $this->import_moodle_grouping_as_nucleus($grouping);
                if (is_wp_error($result)) {
                    $imported['errors'][] = "Agrupamento {$grouping['name']}: " . $result->get_error_message();
                } else {
                    $imported['nuclei']++;
                }
            }
            
            return $imported;
            
        } catch (Exception $e) {
            return new WP_Error('import_failed', $e->getMessage());
        }
    }
    
    /**
     * Importar grupo Moodle como círculo
     */
    private function import_moodle_group_as_circle($moodle_group) {
        global $wpdb;
        
        // Verificar se já existe
        $table_name = $wpdb->prefix . 'ql_instances';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE type = 'circulo' AND JSON_EXTRACT(metadata, '$.moodle_group_id') = %d",
            $moodle_group['id']
        ));
        
        if ($existing) {
            return $existing->id; // Já existe
        }
        
        // Criar círculo
        $instance_data = [
            'type' => 'circulo',
            'name' => sanitize_text_field($moodle_group['name']),
            'slug' => $this->generate_unique_slug($moodle_group['name'], 'circulo'),
            'description' => sanitize_textarea_field($moodle_group['description'] ?? ''),
            'status' => 'active',
            'creator_id' => 1, // Sistema
            'settings' => json_encode(['source' => 'moodle']),
            'metadata' => json_encode([
                'moodle_group_id' => $moodle_group['id'],
                'moodle_course_id' => $moodle_group['courseid'],
                'imported_at' => current_time('mysql')
            ])
        ];
        
        $result = $wpdb->insert($table_name, $instance_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao importar grupo');
        }
        
        $instance_id = $wpdb->insert_id;
        
        // Importar membros do grupo
        if (isset($moodle_group['members'])) {
            $this->import_group_members($instance_id, $moodle_group['members']);
        }
        
        return $instance_id;
    }
    
    /**
     * Importar agrupamento Moodle como núcleo
     */
    private function import_moodle_grouping_as_nucleus($moodle_grouping) {
        global $wpdb;
        
        // Verificar se já existe
        $table_name = $wpdb->prefix . 'ql_instances';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE type = 'nucleo' AND JSON_EXTRACT(metadata, '$.moodle_grouping_id') = %d",
            $moodle_grouping['id']
        ));
        
        if ($existing) {
            return $existing->id; // Já existe
        }
        
        // Criar núcleo
        $instance_data = [
            'type' => 'nucleo',
            'name' => sanitize_text_field($moodle_grouping['name']),
            'slug' => $this->generate_unique_slug($moodle_grouping['name'], 'nucleo'),
            'description' => sanitize_textarea_field($moodle_grouping['description'] ?? ''),
            'status' => 'active',
            'creator_id' => 1, // Sistema
            'settings' => json_encode(['source' => 'moodle']),
            'metadata' => json_encode([
                'moodle_grouping_id' => $moodle_grouping['id'],
                'moodle_course_id' => $moodle_grouping['courseid'],
                'imported_at' => current_time('mysql')
            ])
        ];
        
        $result = $wpdb->insert($table_name, $instance_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao importar agrupamento');
        }
        
        $instance_id = $wpdb->insert_id;
        
        // Relacionar círculos do agrupamento
        if (isset($moodle_grouping['groups'])) {
            $this->link_grouping_circles($instance_id, $moodle_grouping['groups']);
        }
        
        return $instance_id;
    }
    
    /**
     * Importar membros de grupo Moodle
     */
    private function import_group_members($circle_id, $moodle_members) {
        foreach ($moodle_members as $member) {
            $wp_user = get_user_by('email', $member['email']);
            if ($wp_user) {
                $this->add_member_to_instance($circle_id, $wp_user->ID, 'member');
            }
        }
    }
    
    /**
     * Relacionar círculos a núcleo baseado em agrupamento Moodle
     */
    private function link_grouping_circles($nucleus_id, $moodle_groups) {
        global $wpdb;
        
        $instances_table = $wpdb->prefix . 'ql_instances';
        $relationships_table = $wpdb->prefix . 'ql_instance_relationships';
        
        foreach ($moodle_groups as $group_id) {
            // Encontrar círculo correspondente
            $circle = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $instances_table WHERE type = 'circulo' AND JSON_EXTRACT(metadata, '$.moodle_group_id') = %d",
                $group_id
            ));
            
            if ($circle) {
                // Criar relacionamento
                $wpdb->replace($relationships_table, [
                    'parent_instance_id' => $nucleus_id,
                    'child_instance_id' => $circle->id,
                    'relationship_type' => 'nucleus_circle'
                ]);
                
                // Atualizar parent_instance_id do círculo
                $wpdb->update(
                    $instances_table,
                    ['parent_instance_id' => $nucleus_id],
                    ['id' => $circle->id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
    
    /**
     * AJAX - Importar grupos/agrupamentos do Moodle
     */
    public function ajax_import_moodle_groups() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        
        $result = $this->import_from_moodle_groups($course_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => sprintf(
                    'Importação concluída: %d círculos, %d núcleos',
                    $result['circles'],
                    $result['nuclei']
                ),
                'result' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Sincronizar instâncias com Moodle
     */
    public function ajax_sync_instances() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $result = $this->import_from_moodle_groups();
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => 'Sincronização concluída',
                'result' => $result
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
     * AJAX - Atualizar endereço de núcleo
     */
    public function ajax_update_nucleus_address() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $nucleus_id = intval($_POST['nucleus_id']);
        $physical_address = sanitize_textarea_field($_POST['physical_address']);
        $virtual_page_url = esc_url($_POST['virtual_page_url']);
        
        $result = $this->update_nucleus_address($nucleus_id, $physical_address, $virtual_page_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Endereço atualizado com sucesso']);
        }
    }
    
    /**
     * AJAX - Obter endereço de núcleo
     */
    public function ajax_get_nucleus_address() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $nucleus_id = intval($_POST['nucleus_id']);
        $nucleus = $this->get_instance_by_id($nucleus_id);
        
        if (!$nucleus || $nucleus->type !== 'nucleo') {
            wp_send_json_error(['message' => 'Núcleo não encontrado']);
            return;
        }
        
        wp_send_json_success([
            'physical_address' => $nucleus->physical_address,
            'virtual_page_url' => $nucleus->virtual_page_url
        ]);
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
                
                // Hook para integração Moodle: criar grupo correspondente
                do_action('ql_circle_created', $instance_id, $data);
                break;
                
            case 'nucleo':
                // Hook para integração Moodle: criar agrupamento correspondente
                do_action('ql_nucleus_created', $instance_id, $data);
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
        // NOTA: Círculos de gestão devem ser criados no Moodle como grupos
        // Este método apenas registra que o coletivo foi criado
        error_log("QL Instances: Coletivo {$coletivo_id} criado - círculos devem ser criados no Moodle");
        
        // Notificar sobre necessidade de criar grupos no Moodle
        do_action('ql_coletivo_created_needs_moodle_setup', $coletivo_id);
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
        $instance = $this->get_instance_by_id($instance_id);
        if (!$instance) return false;
        
        return in_array($instance->status, ['active', 'complete']);
    }
    
    /**
     * Atualizar endereço de núcleo (físico e virtual)
     * Conforme modelo organizativo: núcleos precisam de endereço físico e virtual
     */
    public function update_nucleus_address($nucleus_id, $physical_address, $virtual_page_url = '') {
        global $wpdb;
        
        $nucleus = $this->get_instance_by_id($nucleus_id);
        if (!$nucleus) {
            return new WP_Error('nucleus_not_found', 'Núcleo não encontrado');
        }
        
        if ($nucleus->type !== 'nucleo') {
            return new WP_Error('not_nucleus', 'Esta instância não é um núcleo');
        }
        
        // Validar endereço físico (obrigatório para núcleos)
        if (empty(trim($physical_address))) {
            return new WP_Error('address_required', 'Endereço físico é obrigatório para núcleos');
        }
        
        // Atualizar no banco
        $table_name = $wpdb->prefix . 'ql_instances';
        $result = $wpdb->update(
            $table_name,
            [
                'physical_address' => sanitize_textarea_field($physical_address),
                'virtual_page_url' => esc_url($virtual_page_url)
            ],
            ['id' => $nucleus_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar endereço');
        }
        
        // Disparar hook
        do_action('ql_nucleus_address_updated', $nucleus_id, $physical_address, $virtual_page_url);
        
        return true;
    }
    
    /**
     * Criar comunidade - HABILITADO conforme modelo organizativo
     */
    public function create_community($name, $territory_scope, $creator_id, $options = []) {
        // Validar escopo territorial
        if (empty(trim($territory_scope))) {
            return new WP_Error('territory_required', 'Escopo territorial é obrigatório para comunidades');
        }
        
        // Verificar se já existe comunidade para este território
        global $wpdb;
        $table_name = $wpdb->prefix . 'ql_instances';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE type = 'comunidade' 
             AND JSON_EXTRACT(metadata, '$.territory_scope') = %s
             AND status = 'active'",
            $territory_scope
        ));
        
        if ($existing > 0) {
            return new WP_Error('territory_taken', 'Já existe uma comunidade para este território');
        }
        
        // Preparar dados da comunidade
        $community_data = [
            'type' => 'comunidade',
            'name' => sanitize_text_field($name),
            'slug' => $this->generate_unique_slug($name, 'comunidade'),
            'description' => sanitize_textarea_field($options['description'] ?? "Comunidade do território: {$territory_scope}"),
            'status' => 'active',
            'creator_id' => $creator_id,
            'territory_id' => $options['territory_id'] ?? null,
            'settings' => json_encode([
                'territory_scope' => $territory_scope,
                'membership_criteria' => $options['membership_criteria'] ?? 'territorial_belonging'
            ]),
            'metadata' => json_encode([
                'territory_scope' => $territory_scope,
                'created_at' => current_time('mysql'),
                'belongs_to_territory' => true,
                'auto_membership' => $options['auto_membership'] ?? true
            ])
        ];
        
        $result = $wpdb->insert($table_name, $community_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar comunidade');
        }
        
        $community_id = $wpdb->insert_id;
        
        // Adicionar criador como primeiro membro
        $this->add_member_to_instance($community_id, $creator_id, 'founder');
        
        // Auto-adicionar usuários que pertencem ao território (se habilitado)
        if ($options['auto_membership'] ?? true) {
            $this->add_territorial_members($community_id, $territory_scope);
        }
        
        // Disparar hook
        do_action('ql_community_created', $community_id, $territory_scope);
        
        return $community_id;
    }
    
    /**
     * Criar assembleia - HABILITADO conforme modelo organizativo
     */
    public function create_assembly($name, $coletivo_id, $creator_id, $options = []) {
        global $wpdb;

        // Verificar se coletivo existe
        $coletivo = $this->get_instance_by_id($coletivo_id);
        if (!$coletivo || $coletivo->type !== 'coletivo') {
            return new WP_Error('invalid_coletivo', 'Coletivo não encontrado ou inválido');
        }

        $table_name = $wpdb->prefix . 'ql_instances';

        // Preparar dados da assembleia
        $assembly_data = [
            'type' => 'assembleia',
            'name' => sanitize_text_field($name),
            'slug' => $this->generate_unique_slug($name, 'assembleia'),
            'description' => sanitize_textarea_field($options['description'] ?? ''),
            'status' => $options['status'] ?? 'scheduled',
            'creator_id' => $creator_id,
            'parent_instance_id' => $coletivo_id,
            'settings' => json_encode([
                'assembly_type' => $options['assembly_type'] ?? 'ordinary', // ordinary, extraordinary
                'quorum_required' => $options['quorum_required'] ?? true,
                'min_quorum_percentage' => $options['min_quorum_percentage'] ?? 50,
                'voting_method' => $options['voting_method'] ?? 'consensus'
            ]),
            'metadata' => json_encode([
                'scheduled_date' => $options['scheduled_date'] ?? null,
                'scheduled_time' => $options['scheduled_time'] ?? null,
                'location' => $options['location'] ?? '',
                'agenda' => $options['agenda'] ?? '',
                'is_extraordinary' => $options['is_extraordinary'] ?? false,
                'convocation_notice_days' => $options['convocation_notice_days'] ?? 7,
                'created_at' => current_time('mysql')
            ])
        ];

        $result = $wpdb->insert($table_name, $assembly_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar assembleia');
        }
        
        $assembly_id = $wpdb->insert_id;
        
        // Se for assembleia agendada, criar evento no calendário
        if (!empty($options['scheduled_date'])) {
            $this->create_assembly_calendar_event($assembly_id, $options);
        }
        
        // Notificar membros do coletivo sobre a convocação
        $this->notify_assembly_convocation($assembly_id, $coletivo_id);
        
        // Disparar hook
        do_action('ql_assembly_created', $assembly_id, $coletivo_id);
        
        return $assembly_id;
    }
    
    /**
     * Agendar assembleia (para assembleias periódicas)
     */
    public function schedule_assembly($assembly_id, $schedule_data) {
        $assembly = $this->get_instance_by_id($assembly_id);
        if (!$assembly || $assembly->type !== 'assembleia') {
            return new WP_Error('invalid_assembly', 'Assembleia não encontrada');
        }
        
        // Validar dados do agendamento
        if (empty($schedule_data['date']) || empty($schedule_data['time'])) {
            return new WP_Error('invalid_schedule', 'Data e horário são obrigatórios');
        }
        
        // Atualizar metadados da assembleia
        $metadata = json_decode($assembly->metadata, true) ?: [];
        $metadata['scheduled_date'] = sanitize_text_field($schedule_data['date']);
        $metadata['scheduled_time'] = sanitize_text_field($schedule_data['time']);
        $metadata['location'] = sanitize_text_field($schedule_data['location'] ?? '');
        $metadata['agenda'] = sanitize_textarea_field($schedule_data['agenda'] ?? '');
        $metadata['updated_at'] = current_time('mysql');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ql_instances';
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'scheduled',
                'metadata' => json_encode($metadata)
            ],
            ['id' => $assembly_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao agendar assembleia');
        }
        
        // Criar evento no calendário
        $this->create_assembly_calendar_event($assembly_id, $schedule_data);
        
        // Disparar hook
        do_action('ql_assembly_scheduled', $assembly_id, $schedule_data);
        
        return true;
    }
    
    /**
     * Adicionar membros territoriais à comunidade
     */
    private function add_territorial_members($community_id, $territory_scope) {
        // Buscar usuários que pertencem ao território
        $users = get_users([
            'meta_query' => [
                [
                    'key' => 'ql_territory',
                    'value' => $territory_scope,
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        
        foreach ($users as $user) {
            $this->add_member_to_instance($community_id, $user->ID, 'member');
        }
        
        error_log("QL Instances: Added " . count($users) . " territorial members to community {$community_id}");
    }
    
    /**
     * Criar evento de calendário para assembleia
     */
    private function create_assembly_calendar_event($assembly_id, $schedule_data) {
        if (!class_exists('QL_Calendar')) {
            return;
        }
        
        $calendar = QL_Calendar::get_instance();
        
        $assembly = $this->get_instance_by_id($assembly_id);
        if (!$assembly) return;
        
        $event_data = [
            'title' => $assembly->name,
            'description' => $assembly->description . "\n\nAgenda:\n" . ($schedule_data['agenda'] ?? ''),
            'event_type' => 'assembleia',
            'event_date' => $schedule_data['date'],
            'event_time' => $schedule_data['time'] ?? null,
            'location' => $schedule_data['location'] ?? '',
            'color' => '#dc3545', // Vermelho para assembleias
            'project_id' => null // Assembleia é de nível organizacional
        ];
        
        $event_id = $calendar->create_custom_event($event_data);
        
        if (!is_wp_error($event_id)) {
            // Atualizar metadata da assembleia com ID do evento
            $metadata = json_decode($assembly->metadata, true) ?: [];
            $metadata['calendar_event_id'] = $event_id;
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'ql_instances';
            $wpdb->update(
                $table_name,
                ['metadata' => json_encode($metadata)],
                ['id' => $assembly_id],
                ['%s'],
                ['%d']
            );
            
            error_log("QL Instances: Calendar event {$event_id} created for assembly {$assembly_id}");
        }
    }
    
    /**
     * Notificar convocação de assembleia
     */
    private function notify_assembly_convocation($assembly_id, $coletivo_id) {
        // Obter membros do coletivo
        $members = $this->get_instance_members($coletivo_id);
        
        $assembly = $this->get_instance_by_id($assembly_id);
        $metadata = json_decode($assembly->metadata, true) ?: [];
        
        $notification_data = [
            'type' => 'assembly_convocation',
            'assembly_id' => $assembly_id,
            'assembly_name' => $assembly->name,
            'scheduled_date' => $metadata['scheduled_date'] ?? null,
            'scheduled_time' => $metadata['scheduled_time'] ?? null,
            'location' => $metadata['location'] ?? '',
            'agenda' => $metadata['agenda'] ?? ''
        ];
        
        foreach ($members as $member) {
            // Enviar notificação (por email, dashboard, etc.)
            do_action('ql_send_assembly_notification', $member->user_id, $notification_data);
        }
        
        error_log("QL Instances: Assembly convocation notifications sent to " . count($members) . " members");
    }
    
    /**
     * AJAX - Criar comunidade
     */
    public function ajax_create_community() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('ql_manage_resources')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
            return;
        }
        
        $name = sanitize_text_field($_POST['name']);
        $territory_scope = sanitize_text_field($_POST['territory_scope']);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $auto_membership = !empty($_POST['auto_membership']);
        
        if (empty($name) || empty($territory_scope)) {
            wp_send_json_error(['message' => 'Nome e escopo territorial são obrigatórios']);
            return;
        }
        
        $options = [
            'description' => $description,
            'auto_membership' => $auto_membership
        ];
        
        $result = $this->create_community($name, $territory_scope, get_current_user_id(), $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => 'Comunidade criada com sucesso',
                'community_id' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Criar assembleia
     */
    public function ajax_create_assembly() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('ql_manage_consultations')) {
            wp_send_json_error(['message' => 'Permissões insuficientes para convocar assembleias']);
            return;
        }
        
        $name = sanitize_text_field($_POST['name']);
        $coletivo_id = intval($_POST['coletivo_id']);
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $is_extraordinary = !empty($_POST['is_extraordinary']);
        
        if (empty($name) || !$coletivo_id) {
            wp_send_json_error(['message' => 'Nome e coletivo são obrigatórios']);
            return;
        }
        
        $options = [
            'description' => $description,
            'is_extraordinary' => $is_extraordinary,
            'assembly_type' => $is_extraordinary ? 'extraordinary' : 'ordinary'
        ];
        
        $result = $this->create_assembly($name, $coletivo_id, get_current_user_id(), $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => 'Assembleia criada com sucesso',
                'assembly_id' => $result
            ]);
        }
    }
    
    /**
     * AJAX - Agendar assembleia
     */
    public function ajax_schedule_assembly() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('ql_manage_consultations')) {
            wp_send_json_error(['message' => 'Permissões insuficientes']);
            return;
        }
        
        $assembly_id = intval($_POST['assembly_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $location = sanitize_text_field($_POST['location'] ?? '');
        $agenda = sanitize_textarea_field($_POST['agenda'] ?? '');
        
        if (!$assembly_id || empty($date) || empty($time)) {
            wp_send_json_error(['message' => 'Assembly ID, data e horário são obrigatórios']);
            return;
        }
        
        $schedule_data = [
            'date' => $date,
            'time' => $time,
            'location' => $location,
            'agenda' => $agenda
        ];
        
        $result = $this->schedule_assembly($assembly_id, $schedule_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Assembleia agendada com sucesso']);
        }
    }
}