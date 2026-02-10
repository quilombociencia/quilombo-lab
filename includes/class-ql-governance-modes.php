<?php
/**
 * Sistema de Modos de Governança do Quilombo Laboratório
 * 
 * Implementa os três modos de governança conforme modelo organizativo:
 * 1. Assembleia - Todas as instâncias convergem para assembleia centralizada
 * 2. Descentralizado - Círculos, núcleos e pessoas distribuídos por proximidade
 * 3. Inativo - Participantes distribuídos aleatoriamente
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Governance_Modes {
    
    private static $instance = null;
    
    /**
     * Modos de governança disponíveis
     */
    const GOVERNANCE_MODES = [
        'assembly' => [
            'label' => 'Modo Assembleia',
            'description' => 'Todas as instâncias convergem para assembleia centralizada',
            'color' => '#f39c12',
            'icon' => 'dashicons-megaphone',
            'layout' => 'centralized',
            'active_instances' => ['assembleia'],
            'visualization' => 'assembly_cluster'
        ],
        'decentralized' => [
            'label' => 'Modo Descentralizado',
            'description' => 'Círculos, núcleos e pessoas distribuídos por proximidade',
            'color' => '#27ae60',
            'icon' => 'dashicons-networking',
            'layout' => 'force_directed',
            'active_instances' => ['circulo', 'nucleo', 'comunidade'],
            'visualization' => 'proximity_clusters'
        ],
        'inactive' => [
            'label' => 'Coletivo Inativo',
            'description' => 'Participantes distribuídos aleatoriamente',
            'color' => '#95a5a6',
            'icon' => 'dashicons-groups',
            'layout' => 'random',
            'active_instances' => [],
            'visualization' => 'random_distribution'
        ]
    ];
    
    /**
     * Parâmetros de proximidade para modo descentralizado
     */
    const PROXIMITY_PARAMETERS = [
        'shared_projects' => [
            'weight' => 0.4,
            'description' => 'Projetos compartilhados entre instâncias'
        ],
        'responsibility_distribution' => [
            'weight' => 0.35,
            'description' => 'Distribuição de responsabilidades'
        ],
        'consensus_participation' => [
            'weight' => 0.25,
            'description' => 'Participação em consensos (jornada/coorte)'
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
        
        // AJAX handlers
        add_action('wp_ajax_ql_set_governance_mode', [$this, 'ajax_set_governance_mode']);
        add_action('wp_ajax_ql_get_governance_graph', [$this, 'ajax_get_governance_graph']);
        add_action('wp_ajax_ql_toggle_assembly_mode', [$this, 'ajax_toggle_assembly_mode']);
        add_action('wp_ajax_ql_get_proximity_data', [$this, 'ajax_get_proximity_data']);
        
        // Hooks para mudanças de modo
        add_action('ql_governance_mode_changed', [$this, 'on_governance_mode_changed'], 10, 3);
        add_action('ql_assembly_started', [$this, 'on_assembly_started'], 10, 2);
        add_action('ql_assembly_ended', [$this, 'on_assembly_ended'], 10, 2);
    }
    
    public function init() {
        // Criar tabela para persistir estado do modo de governança
        $this->maybe_create_governance_table();
    }
    
    /**
     * Criar tabela para estado de governança
     */
    private function maybe_create_governance_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_governance_state';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                coletivo_id bigint(20) NOT NULL,
                current_mode varchar(50) NOT NULL DEFAULT 'inactive',
                active_assembly_id bigint(20) NULL,
                mode_settings longtext,
                last_mode_change timestamp DEFAULT CURRENT_TIMESTAMP,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY coletivo_id (coletivo_id),
                KEY current_mode (current_mode),
                KEY active_assembly_id (active_assembly_id)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Obter modo atual de governança para um coletivo
     */
    public function get_current_mode($coletivo_id = null) {
        global $wpdb;
        
        if (!$coletivo_id) {
            $coletivo_id = $this->get_default_coletivo_id();
        }
        
        $table_name = $wpdb->prefix . 'ql_governance_state';
        $state = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE coletivo_id = %d",
            $coletivo_id
        ));
        
        if ($state) {
            return [
                'mode' => $state->current_mode,
                'active_assembly_id' => $state->active_assembly_id,
                'settings' => json_decode($state->mode_settings, true) ?: [],
                'last_change' => $state->last_mode_change
            ];
        }
        
        // Modo padrão se não existe registro
        return [
            'mode' => 'inactive',
            'active_assembly_id' => null,
            'settings' => [],
            'last_change' => null
        ];
    }
    
    /**
     * Definir modo de governança
     */
    public function set_governance_mode($coletivo_id, $mode, $settings = []) {
        global $wpdb;
        
        // Validar modo
        if (!isset(self::GOVERNANCE_MODES[$mode])) {
            return new WP_Error('invalid_mode', 'Modo de governança inválido');
        }
        
        // Verificar permissões
        if (!$this->user_can_change_governance_mode(get_current_user_id(), $coletivo_id)) {
            return new WP_Error('insufficient_permissions', 'Permissões insuficientes para alterar modo de governança');
        }
        
        $old_mode = $this->get_current_mode($coletivo_id);
        
        // Preparar dados
        $governance_data = [
            'coletivo_id' => $coletivo_id,
            'current_mode' => $mode,
            'active_assembly_id' => $settings['active_assembly_id'] ?? null,
            'mode_settings' => json_encode($settings),
            'last_mode_change' => current_time('mysql')
        ];
        
        $table_name = $wpdb->prefix . 'ql_governance_state';
        
        // Usar REPLACE para inserir ou atualizar
        $result = $wpdb->replace($table_name, $governance_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao definir modo de governança');
        }
        
        // Disparar hooks para transição de modo
        do_action('ql_governance_mode_changed', $coletivo_id, $old_mode['mode'], $mode, $settings);
        
        return true;
    }
    
    /**
     * Iniciar modo assembleia
     */
    public function start_assembly_mode($coletivo_id, $assembly_id) {
        // Verificar se assembleia existe
        if (!class_exists('QL_Instances')) {
            return new WP_Error('instances_not_available', 'Sistema de instâncias não disponível');
        }
        
        $instances = QL_Instances::get_instance();
        $assembly = $instances->get_instance_by_id($assembly_id);
        
        if (!$assembly || $assembly->type !== 'assembleia') {
            return new WP_Error('invalid_assembly', 'Assembleia não encontrada');
        }
        
        // Definir modo assembleia
        $settings = [
            'active_assembly_id' => $assembly_id,
            'assembly_start_time' => current_time('mysql'),
            'previous_mode' => $this->get_current_mode($coletivo_id)['mode']
        ];
        
        $result = $this->set_governance_mode($coletivo_id, 'assembly', $settings);
        
        if (!is_wp_error($result)) {
            // Disparar hook específico
            do_action('ql_assembly_started', $assembly_id, $coletivo_id);
        }
        
        return $result;
    }
    
    /**
     * Finalizar modo assembleia
     */
    public function end_assembly_mode($coletivo_id, $assembly_id) {
        $current_state = $this->get_current_mode($coletivo_id);
        
        if ($current_state['mode'] !== 'assembly') {
            return new WP_Error('not_in_assembly', 'Coletivo não está em modo assembleia');
        }
        
        if ($current_state['active_assembly_id'] !== $assembly_id) {
            return new WP_Error('wrong_assembly', 'ID da assembleia não confere');
        }
        
        // Retornar ao modo anterior ou descentralizado
        $previous_mode = $current_state['settings']['previous_mode'] ?? 'decentralized';
        
        $settings = [
            'assembly_end_time' => current_time('mysql'),
            'last_assembly_id' => $assembly_id
        ];
        
        $result = $this->set_governance_mode($coletivo_id, $previous_mode, $settings);
        
        if (!is_wp_error($result)) {
            // Disparar hook específico
            do_action('ql_assembly_ended', $assembly_id, $coletivo_id);
        }
        
        return $result;
    }
    
    /**
     * Gerar dados do grafo para visualização
     */
    public function get_governance_graph_data($coletivo_id, $mode = null) {
        if (!$mode) {
            $mode = $this->get_current_mode($coletivo_id)['mode'];
        }
        
        switch ($mode) {
            case 'assembly':
                return $this->generate_assembly_graph($coletivo_id);
            case 'decentralized':
                return $this->generate_decentralized_graph($coletivo_id);
            case 'inactive':
                return $this->generate_inactive_graph($coletivo_id);
            default:
                return new WP_Error('invalid_mode', 'Modo inválido para geração de grafo');
        }
    }
    
    /**
     * Gerar grafo para modo assembleia
     */
    private function generate_assembly_graph($coletivo_id) {
        $current_state = $this->get_current_mode($coletivo_id);
        $assembly_id = $current_state['active_assembly_id'];
        
        if (!$assembly_id) {
            return new WP_Error('no_active_assembly', 'Nenhuma assembleia ativa');
        }
        
        if (!class_exists('QL_Instances')) {
            return new WP_Error('instances_not_available', 'Sistema de instâncias não disponível');
        }
        
        $instances = QL_Instances::get_instance();
        $assembly = $instances->get_instance_by_id($assembly_id);
        
        // Obter todas as instâncias do coletivo
        $all_instances = $this->get_coletivo_instances($coletivo_id);
        $all_members = $this->get_coletivo_members($coletivo_id);
        
        // Calcular participação ativa na assembleia
        $active_assembly_participants = $this->get_active_assembly_participants($assembly_id);
        
        $nodes = [];
        $edges = [];
        
        // Nó central da assembleia
        $assembly_node = [
            'id' => 'assembly_' . $assembly_id,
            'label' => $assembly->name,
            'type' => 'assembleia',
            'size' => 50 + (count($active_assembly_participants) * 2), // Tamanho baseado na participação
            'color' => self::GOVERNANCE_MODES['assembly']['color'],
            'x' => 0, // Centro
            'y' => 0,
            'fixed' => true,
            'metadata' => [
                'total_eligible' => count($all_members),
                'active_participants' => count($active_assembly_participants),
                'participation_rate' => count($all_members) > 0 ? (count($active_assembly_participants) / count($all_members)) * 100 : 0
            ]
        ];
        $nodes[] = $assembly_node;
        
        // Posicionar instâncias e pessoas em círculos concêntricos
        $radius_instances = 200;
        $radius_people = 350;
        
        // Adicionar instâncias em círculo ao redor da assembleia
        $instance_count = count($all_instances);
        foreach ($all_instances as $i => $instance) {
            $angle = (2 * M_PI * $i) / $instance_count;
            $x = $radius_instances * cos($angle);
            $y = $radius_instances * sin($angle);
            
            $node = [
                'id' => 'instance_' . $instance->id,
                'label' => $instance->name,
                'type' => $instance->type,
                'size' => $this->calculate_instance_size($instance),
                'color' => $this->get_instance_color($instance->type),
                'x' => $x,
                'y' => $y,
                'metadata' => [
                    'member_count' => $instance->member_count ?? 0,
                    'status' => $instance->status
                ]
            ];
            $nodes[] = $node;
            
            // Conectar à assembleia
            $edges[] = [
                'id' => 'edge_assembly_' . $instance->id,
                'source' => 'assembly_' . $assembly_id,
                'target' => 'instance_' . $instance->id,
                'weight' => 1,
                'color' => '#bdc3c7'
            ];
        }
        
        // Adicionar pessoas em círculo externo
        $member_count = count($all_members);
        foreach ($all_members as $i => $member) {
            $angle = (2 * M_PI * $i) / $member_count;
            $x = $radius_people * cos($angle);
            $y = $radius_people * sin($angle);
            
            $is_active = in_array($member->user_id, $active_assembly_participants);
            
            $node = [
                'id' => 'person_' . $member->user_id,
                'label' => $member->display_name,
                'type' => 'person',
                'size' => $is_active ? 12 : 8,
                'color' => $is_active ? '#e74c3c' : '#95a5a6', // Vermelho para ativos, cinza para inativos
                'x' => $x,
                'y' => $y,
                'metadata' => [
                    'is_active_in_assembly' => $is_active,
                    'instances' => $this->get_user_instances($member->user_id)
                ]
            ];
            $nodes[] = $node;
            
            // Conectar à assembleia se ativo
            if ($is_active) {
                $edges[] = [
                    'id' => 'edge_assembly_person_' . $member->user_id,
                    'source' => 'assembly_' . $assembly_id,
                    'target' => 'person_' . $member->user_id,
                    'weight' => 0.5,
                    'color' => '#e74c3c'
                ];
            }
        }
        
        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'layout' => 'preset', // Posições fixas para modo assembleia
            'metadata' => [
                'mode' => 'assembly',
                'assembly_id' => $assembly_id,
                'assembly_name' => $assembly->name,
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'participation_stats' => $assembly_node['metadata']
            ]
        ];
    }
    
    /**
     * Gerar grafo para modo descentralizado
     */
    private function generate_decentralized_graph($coletivo_id) {
        $instances = $this->get_coletivo_instances($coletivo_id);
        $members = $this->get_coletivo_members($coletivo_id);
        
        $nodes = [];
        $edges = [];
        
        // Calcular clusters baseados nos parâmetros de proximidade
        $clusters = $this->calculate_proximity_clusters($instances, $members);
        
        // Adicionar instâncias como nós
        foreach ($instances as $instance) {
            $cluster_info = $this->find_instance_cluster($instance->id, $clusters);
            
            $node = [
                'id' => 'instance_' . $instance->id,
                'label' => $instance->name,
                'type' => $instance->type,
                'size' => $this->calculate_instance_size($instance),
                'color' => $this->get_instance_color($instance->type),
                'cluster' => $cluster_info['cluster_id'],
                'metadata' => [
                    'member_count' => $instance->member_count ?? 0,
                    'status' => $instance->status,
                    'cluster_name' => $cluster_info['cluster_name'],
                    'proximity_scores' => $cluster_info['proximity_scores']
                ]
            ];
            $nodes[] = $node;
        }
        
        // Adicionar pessoas como nós
        foreach ($members as $member) {
            $user_instances = $this->get_user_instances($member->user_id);
            $cluster_info = $this->find_person_cluster($member->user_id, $user_instances, $clusters);
            
            $node = [
                'id' => 'person_' . $member->user_id,
                'label' => $member->display_name,
                'type' => 'person',
                'size' => 8,
                'color' => '#34495e',
                'cluster' => $cluster_info['cluster_id'],
                'metadata' => [
                    'instances' => $user_instances,
                    'cluster_name' => $cluster_info['cluster_name']
                ]
            ];
            $nodes[] = $node;
        }
        
        // Gerar arestas baseadas em proximidade
        $edges = $this->generate_proximity_edges($instances, $clusters);
        
        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'layout' => 'force_directed', // Layout automático baseado em forças
            'metadata' => [
                'mode' => 'decentralized',
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'clusters' => $clusters,
                'proximity_parameters' => self::PROXIMITY_PARAMETERS
            ]
        ];
    }
    
    /**
     * Gerar grafo para coletivo inativo
     */
    private function generate_inactive_graph($coletivo_id) {
        $instances = $this->get_coletivo_instances($coletivo_id);
        $members = $this->get_coletivo_members($coletivo_id);
        
        $nodes = [];
        $edges = [];
        
        // Adicionar instâncias com posicionamento aleatório
        foreach ($instances as $instance) {
            $node = [
                'id' => 'instance_' . $instance->id,
                'label' => $instance->name,
                'type' => $instance->type,
                'size' => $this->calculate_instance_size($instance),
                'color' => '#bdc3c7', // Cinza para inativo
                'metadata' => [
                    'member_count' => $instance->member_count ?? 0,
                    'status' => $instance->status
                ]
            ];
            $nodes[] = $node;
        }
        
        // Adicionar pessoas
        foreach ($members as $member) {
            $node = [
                'id' => 'person_' . $member->user_id,
                'label' => $member->display_name,
                'type' => 'person',
                'size' => 6,
                'color' => '#95a5a6', // Cinza para inativo
                'metadata' => [
                    'instances' => $this->get_user_instances($member->user_id)
                ]
            ];
            $nodes[] = $node;
        }
        
        // Conexões mínimas apenas para manter coesão visual
        $edges = $this->generate_minimal_edges($instances, $members);
        
        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'layout' => 'random', // Distribuição aleatória
            'metadata' => [
                'mode' => 'inactive',
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'note' => 'Coletivo em estado inativo - participantes distribuídos aleatoriamente'
            ]
        ];
    }
    
    // Métodos auxiliares
    
    private function get_default_coletivo_id() {
        // Implementar lógica para obter coletivo padrão
        // Por enquanto retorna 1
        return 1;
    }
    
    private function get_coletivo_instances($coletivo_id) {
        global $wpdb;
        
        $instances_table = $wpdb->prefix . 'ql_instances';
        return $wpdb->get_results($wpdb->prepare("
            SELECT i.*, COUNT(m.id) as member_count
            FROM $instances_table i
            LEFT JOIN {$wpdb->prefix}ql_instance_members m ON i.id = m.instance_id AND m.status = 'active'
            WHERE i.status != 'deleted'
            GROUP BY i.id
            ORDER BY i.type, i.created_at
        "));
    }
    
    private function get_coletivo_members($coletivo_id) {
        global $wpdb;
        
        // Obter todos os membros únicos de todas as instâncias
        return $wpdb->get_results("
            SELECT DISTINCT m.user_id, u.display_name, u.user_email
            FROM {$wpdb->prefix}ql_instance_members m
            JOIN {$wpdb->users} u ON m.user_id = u.ID
            WHERE m.status = 'active'
        ");
    }
    
    private function get_active_assembly_participants($assembly_id) {
        // Por enquanto simular - implementar integração com sistema de tarefas
        global $wpdb;
        
        return $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT user_id 
            FROM {$wpdb->prefix}ql_instance_members m
            JOIN {$wpdb->prefix}ql_instances i ON m.instance_id = i.id
            WHERE i.type = 'assembleia' AND i.id = %d AND m.status = 'active'
        ", $assembly_id));
    }
    
    private function calculate_instance_size($instance) {
        $base_sizes = [
            'circulo' => 15,
            'nucleo' => 25,
            'comunidade' => 35,
            'coletivo' => 45,
            'assembleia' => 55
        ];
        
        $base = $base_sizes[$instance->type] ?? 15;
        $member_bonus = ($instance->member_count ?? 0) * 2;
        
        return min($base + $member_bonus, 80); // Máximo 80
    }
    
    private function get_instance_color($type) {
        if (!class_exists('QL_Instances')) {
            return '#95a5a6';
        }
        
        $types = QL_Instances::get_instance_types();
        return $types[$type]['color'] ?? '#95a5a6';
    }
    
    private function get_user_instances($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT i.id, i.name, i.type
            FROM {$wpdb->prefix}ql_instances i
            JOIN {$wpdb->prefix}ql_instance_members m ON i.id = m.instance_id
            WHERE m.user_id = %d AND m.status = 'active'
        ", $user_id));
    }
    
    private function calculate_proximity_clusters($instances, $members) {
        // Implementar algoritmo de clustering baseado nos parâmetros de proximidade
        // Por enquanto, agrupar por núcleos e círculos relacionados
        
        $clusters = [];
        $cluster_id = 1;
        
        foreach ($instances as $instance) {
            if ($instance->type === 'nucleo') {
                $clusters[$cluster_id] = [
                    'id' => $cluster_id,
                    'name' => "Cluster {$instance->name}",
                    'center_instance' => $instance->id,
                    'instances' => [$instance->id],
                    'type' => 'nucleus_cluster'
                ];
                $cluster_id++;
            }
        }
        
        // Agrupar círculos com seus núcleos
        foreach ($instances as $instance) {
            if ($instance->type === 'circulo' && $instance->parent_instance_id) {
                foreach ($clusters as &$cluster) {
                    if ($cluster['center_instance'] === $instance->parent_instance_id) {
                        $cluster['instances'][] = $instance->id;
                        break;
                    }
                }
            }
        }
        
        return $clusters;
    }
    
    private function find_instance_cluster($instance_id, $clusters) {
        foreach ($clusters as $cluster) {
            if (in_array($instance_id, $cluster['instances'])) {
                return [
                    'cluster_id' => $cluster['id'],
                    'cluster_name' => $cluster['name'],
                    'proximity_scores' => [] // Implementar cálculo de scores
                ];
            }
        }
        
        return [
            'cluster_id' => 0,
            'cluster_name' => 'Sem cluster',
            'proximity_scores' => []
        ];
    }
    
    private function find_person_cluster($user_id, $user_instances, $clusters) {
        // Pessoa pertence ao cluster da instância com maior atividade
        if (empty($user_instances)) {
            return [
                'cluster_id' => 0,
                'cluster_name' => 'Sem cluster'
            ];
        }
        
        // Por simplicidade, usar primeira instância
        $first_instance = $user_instances[0];
        return $this->find_instance_cluster($first_instance->id, $clusters);
    }
    
    private function generate_proximity_edges($instances, $clusters) {
        $edges = [];
        
        // Conectar instâncias dentro do mesmo cluster
        foreach ($clusters as $cluster) {
            $cluster_instances = $cluster['instances'];
            for ($i = 0; $i < count($cluster_instances); $i++) {
                for ($j = $i + 1; $j < count($cluster_instances); $j++) {
                    $edges[] = [
                        'id' => 'edge_' . $cluster_instances[$i] . '_' . $cluster_instances[$j],
                        'source' => 'instance_' . $cluster_instances[$i],
                        'target' => 'instance_' . $cluster_instances[$j],
                        'weight' => 2,
                        'color' => '#3498db'
                    ];
                }
            }
        }
        
        return $edges;
    }
    
    private function generate_minimal_edges($instances, $members) {
        // Gerar conexões mínimas para manter coesão visual
        $edges = [];
        
        for ($i = 0; $i < min(count($instances), 5); $i++) {
            for ($j = $i + 1; $j < min(count($instances), 5); $j++) {
                $edges[] = [
                    'id' => 'edge_' . $instances[$i]->id . '_' . $instances[$j]->id,
                    'source' => 'instance_' . $instances[$i]->id,
                    'target' => 'instance_' . $instances[$j]->id,
                    'weight' => 0.5,
                    'color' => '#ecf0f1'
                ];
            }
        }
        
        return $edges;
    }
    
    private function user_can_change_governance_mode($user_id, $coletivo_id) {
        // Verificar se usuário pode alterar modo de governança
        return user_can($user_id, 'manage_options') || user_can($user_id, 'ql_manage_consultations');
    }
    
    // Métodos AJAX
    
    public function ajax_set_governance_mode() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $coletivo_id = intval($_POST['coletivo_id'] ?? 1);
        $mode = sanitize_text_field($_POST['mode']);
        $settings = $_POST['settings'] ? json_decode(stripslashes($_POST['settings']), true) : [];
        
        $result = $this->set_governance_mode($coletivo_id, $mode, $settings);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => 'Modo de governança alterado com sucesso',
                'mode' => $mode,
                'settings' => $settings
            ]);
        }
    }
    
    public function ajax_get_governance_graph() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $coletivo_id = intval($_POST['coletivo_id'] ?? 1);
        $mode = sanitize_text_field($_POST['mode'] ?? '');
        
        $graph_data = $this->get_governance_graph_data($coletivo_id, $mode ?: null);
        
        if (is_wp_error($graph_data)) {
            wp_send_json_error(['message' => $graph_data->get_error_message()]);
        } else {
            wp_send_json_success($graph_data);
        }
    }
    
    public function ajax_toggle_assembly_mode() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $coletivo_id = intval($_POST['coletivo_id'] ?? 1);
        $assembly_id = intval($_POST['assembly_id'] ?? 0);
        $action = sanitize_text_field($_POST['action']); // 'start' or 'end'
        
        if ($action === 'start' && $assembly_id) {
            $result = $this->start_assembly_mode($coletivo_id, $assembly_id);
        } elseif ($action === 'end' && $assembly_id) {
            $result = $this->end_assembly_mode($coletivo_id, $assembly_id);
        } else {
            wp_send_json_error(['message' => 'Parâmetros inválidos']);
            return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success([
                'message' => $action === 'start' ? 'Modo assembleia iniciado' : 'Modo assembleia finalizado',
                'action' => $action,
                'assembly_id' => $assembly_id
            ]);
        }
    }
    
    public function ajax_get_proximity_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $coletivo_id = intval($_POST['coletivo_id'] ?? 1);
        
        $proximity_data = [
            'parameters' => self::PROXIMITY_PARAMETERS,
            'clusters' => $this->calculate_proximity_clusters(
                $this->get_coletivo_instances($coletivo_id),
                $this->get_coletivo_members($coletivo_id)
            )
        ];
        
        wp_send_json_success($proximity_data);
    }
    
    // Hooks para eventos de mudança de modo
    
    public function on_governance_mode_changed($coletivo_id, $old_mode, $new_mode, $settings) {
        error_log("QL Governance: Modo alterado de {$old_mode} para {$new_mode} no coletivo {$coletivo_id}");
        
        // Notificar membros sobre mudança de modo
        $this->notify_mode_change($coletivo_id, $old_mode, $new_mode);
    }
    
    public function on_assembly_started($assembly_id, $coletivo_id) {
        error_log("QL Governance: Assembleia {$assembly_id} iniciada no coletivo {$coletivo_id}");
        
        // Notificar início da assembleia
        $this->notify_assembly_start($assembly_id, $coletivo_id);
    }
    
    public function on_assembly_ended($assembly_id, $coletivo_id) {
        error_log("QL Governance: Assembleia {$assembly_id} finalizada no coletivo {$coletivo_id}");
        
        // Notificar fim da assembleia e transferir responsabilidades
        $this->notify_assembly_end($assembly_id, $coletivo_id);
    }
    
    private function notify_mode_change($coletivo_id, $old_mode, $new_mode) {
        // Implementar notificação de mudança de modo
    }
    
    private function notify_assembly_start($assembly_id, $coletivo_id) {
        // Implementar notificação de início de assembleia
    }
    
    private function notify_assembly_end($assembly_id, $coletivo_id) {
        // Implementar notificação de fim de assembleia
    }
    
    /**
     * Obter definições de modos de governança
     */
    public static function get_governance_modes() {
        return self::GOVERNANCE_MODES;
    }
    
    /**
     * Obter parâmetros de proximidade
     */
    public static function get_proximity_parameters() {
        return self::PROXIMITY_PARAMETERS;
    }
}