<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sistema de Distribuição de Poderes de Decisão
 * 
 * Implementa as regras de autoridade conforme modelo organizativo radicular
 */
class QL_Decision_Authority {
    
    private static $instance = null;
    
    /**
     * Mapeamento de poderes de decisão por tipo de instância
     * Conforme documento modelo_organizativo.md
     */
    const DECISION_POWERS = [
        'assembleia' => [
            'power_level' => 'absolute',
            'scope' => 'all_collective_aspects',
            'restrictions' => [
                'agenda_required' => true,
                'representativity_required' => true,
                'duration_limit' => true
            ],
            'can_decide' => [
                'collective_direction',
                'resource_allocation',
                'instance_creation',
                'fundamental_changes',
                'conflict_resolution'
            ]
        ],
        'circulo' => [
            'power_level' => 'responsibility_tasks',
            'scope' => 'assigned_responsibilities',
            'restrictions' => [
                'consultation_required_for_relevant_decisions' => true,
                'can_assume_decline_transfer_responsibility' => true
            ],
            'can_decide' => [
                'responsibility_tasks',
                'internal_organization',
                'task_distribution',
                'methodology_choices'
            ]
        ],
        'nucleo' => [
            'power_level' => 'responsibility_tasks',
            'scope' => 'assigned_responsibilities',
            'restrictions' => [
                'consultation_required_for_relevant_decisions' => true,
                'can_assume_decline_transfer_responsibility' => true
            ],
            'can_decide' => [
                'responsibility_tasks',
                'resource_sharing',
                'territorial_activities',
                'inter_circle_coordination'
            ]
        ],
        'evento' => [
            'power_level' => 'limited_by_responsibilities',
            'scope' => 'event_tasks',
            'restrictions' => [
                'duration_limited' => true,
                'agenda_limited' => true,
                'representativity_limited' => true,
                'restricted_to_assigned_tasks' => true
            ],
            'can_decide' => [
                'event_execution',
                'immediate_coordination',
                'resource_use_during_event'
            ]
        ],
        'comunidade' => [
            'power_level' => 'territorial_absolute',
            'scope' => 'territorial_actions',
            'restrictions' => [
                'no_collective_decision_power' => true,
                'only_through_collective_instances' => true,
                'cannot_have_collective_responsibilities' => true
            ],
            'can_decide' => [
                'territorial_actions',
                'community_tasks',
                'local_resource_use'
            ]
        ],
        'coletivo' => [
            'power_level' => 'through_instances',
            'scope' => 'collective_management',
            'restrictions' => [
                'decisions_through_instances' => true,
                'documentation_consensus_required' => true,
                'common_resources_managed' => true
            ],
            'can_decide' => [
                'documentation_consensus',
                'common_resources',
                'responsibilities_distribution'
            ]
        ],
        'pessoa' => [
            'power_level' => 'individual',
            'scope' => 'personal_participation',
            'restrictions' => [
                'consultation_required_for_impact' => true
            ],
            'can_decide' => [
                'instance_participation',
                'role_acceptance',
                'task_assumption',
                'responsibility_actions',
                'personal_contributions'
            ]
        ]
    ];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Filtros para verificar autoridade de decisão
        add_filter('ql_can_make_decision', [$this, 'check_decision_authority'], 10, 4);
        add_filter('ql_requires_consultation', [$this, 'check_consultation_requirement'], 10, 3);
        add_action('ql_before_decision', [$this, 'validate_decision_authority'], 10, 3);
    }
    
    /**
     * Verificar autoridade para tomar decisão
     */
    public function check_decision_authority($can_decide, $decision_type, $user_id, $context) {
        $authority_level = $this->get_user_authority_level($user_id, $context);
        $required_level = $this->get_required_authority_level($decision_type, $context);
        
        // Verificar se tem autoridade suficiente
        if (!$this->has_sufficient_authority($authority_level, $required_level)) {
            return false;
        }
        
        // Verificar restrições específicas
        $restrictions = $this->check_decision_restrictions($decision_type, $user_id, $context);
        
        return $restrictions['allowed'] ?? false;
    }
    
    /**
     * Obter nível de autoridade do usuário
     */
    private function get_user_authority_level($user_id, $context) {
        $authority = [
            'level' => 'none',
            'scope' => [],
            'responsibilities' => [],
            'instances' => []
        ];
        
        // Verificar se é administrador
        if (user_can($user_id, 'manage_options')) {
            $authority['level'] = 'admin';
            $authority['scope'] = ['global'];
            return $authority;
        }
        
        // Verificar responsabilidades ativas
        if (class_exists('QL_Responsibility_System')) {
            $responsibility_system = QL_Responsibility_System::get_instance();
            $user_responsibilities = $responsibility_system->get_active_responsibilities([
                'assignee_type' => 'person',
                'assignee_id' => $user_id
            ]);
            
            foreach ($user_responsibilities as $resp) {
                $authority['responsibilities'][] = [
                    'type' => $resp->responsibility_key,
                    'context' => $resp->context_type,
                    'context_id' => $resp->context_id
                ];
                
                // Gestão tem maior autoridade
                if ($resp->responsibility_key === 'gestao') {
                    $authority['level'] = 'management';
                }
            }
        }
        
        // Verificar participação em instâncias
        if (class_exists('QL_Instances')) {
            $instances = QL_Instances::get_instance();
            
            // Buscar instâncias onde é membro
            $user_instances = $this->get_user_instances($user_id);
            $authority['instances'] = $user_instances;
            
            // Definir nível baseado nas instâncias
            if (!empty($user_instances)) {
                $authority['level'] = 'participant';
                
                foreach ($user_instances as $instance) {
                    if ($instance['type'] === 'assembleia') {
                        $authority['level'] = 'assembly_member';
                    }
                }
            }
        }
        
        return $authority;
    }
    
    /**
     * Obter nível de autoridade necessário para decisão
     */
    private function get_required_authority_level($decision_type, $context) {
        $required = [
            'level' => 'participant',
            'consultation_required' => false,
            'consensus_required' => false
        ];
        
        // Mapeamento de tipos de decisão
        $decision_requirements = [
            'create_project' => ['level' => 'participant', 'consultation_required' => false],
            'activate_project' => ['level' => 'management', 'consultation_required' => true],
            'assign_responsibility' => ['level' => 'management', 'consultation_required' => false],
            'transfer_responsibility' => ['level' => 'responsible_party', 'consultation_required' => true],
            'resource_allocation' => ['level' => 'management', 'consultation_required' => true],
            'instance_creation' => ['level' => 'management', 'consultation_required' => false],
            'collective_changes' => ['level' => 'assembly_member', 'consultation_required' => true, 'consensus_required' => true],
            'territorial_actions' => ['level' => 'community_member', 'consultation_required' => false]
        ];
        
        return $decision_requirements[$decision_type] ?? $required;
    }
    
    /**
     * Verificar se tem autoridade suficiente
     */
    private function has_sufficient_authority($user_authority, $required_authority) {
        $authority_hierarchy = [
            'none' => 0,
            'participant' => 1,
            'responsible_party' => 2,
            'community_member' => 2,
            'management' => 3,
            'assembly_member' => 4,
            'admin' => 5
        ];
        
        $user_level = $authority_hierarchy[$user_authority['level']] ?? 0;
        $required_level = $authority_hierarchy[$required_authority['level']] ?? 1;
        
        return $user_level >= $required_level;
    }
    
    /**
     * Verificar restrições específicas da decisão
     */
    private function check_decision_restrictions($decision_type, $user_id, $context) {
        $result = ['allowed' => true, 'restrictions' => []];
        
        // Verificar restrições por tipo de instância (se aplicável)
        if (isset($context['instance_type'])) {
            $instance_powers = self::DECISION_POWERS[$context['instance_type']] ?? [];
            
            // Verificar se o tipo de decisão está no escopo da instância
            if (!empty($instance_powers['can_decide']) && 
                !in_array($decision_type, $instance_powers['can_decide'])) {
                $result['allowed'] = false;
                $result['restrictions'][] = 'decision_type_out_of_scope';
            }
            
            // Aplicar restrições específicas
            foreach ($instance_powers['restrictions'] ?? [] as $restriction => $required) {
                if ($required) {
                    $restriction_check = $this->check_specific_restriction($restriction, $context);
                    if (!$restriction_check['met']) {
                        $result['allowed'] = false;
                        $result['restrictions'][] = $restriction;
                    }
                }
            }
        }
        
        // Verificar restrições temporais
        if ($this->is_time_sensitive_decision($decision_type)) {
            $time_check = $this->check_temporal_restrictions($decision_type, $context);
            if (!$time_check['allowed']) {
                $result['allowed'] = false;
                $result['restrictions'][] = 'temporal_restriction';
            }
        }
        
        return $result;
    }
    
    /**
     * Verificar restrição específica
     */
    private function check_specific_restriction($restriction, $context) {
        $result = ['met' => true, 'details' => []];
        
        switch ($restriction) {
            case 'agenda_required':
                $result['met'] = !empty($context['agenda']);
                break;
                
            case 'representativity_required':
                $min_participants = $context['min_participants'] ?? 3;
                $actual_participants = $context['participants_count'] ?? 0;
                $result['met'] = $actual_participants >= $min_participants;
                break;
                
            case 'consultation_required_for_relevant_decisions':
                if ($this->is_relevant_decision($context['decision_type'] ?? '', $context)) {
                    $result['met'] = !empty($context['consultation_id']);
                }
                break;
                
            case 'duration_limit':
                $max_duration = $context['max_duration_hours'] ?? 4;
                $actual_duration = $context['duration_hours'] ?? 0;
                $result['met'] = $actual_duration <= $max_duration;
                break;
                
            case 'no_collective_decision_power':
                $result['met'] = ($context['scope'] ?? '') !== 'collective';
                break;
                
            case 'documentation_consensus_required':
                $result['met'] = !empty($context['documented_consensus']);
                break;
        }
        
        return $result;
    }
    
    /**
     * Verificar se decisão é relevante (requer consulta)
     */
    private function is_relevant_decision($decision_type, $context) {
        $relevant_decisions = [
            'resource_allocation',
            'responsibility_transfer',
            'instance_creation',
            'methodology_change',
            'territorial_impact'
        ];
        
        return in_array($decision_type, $relevant_decisions);
    }
    
    /**
     * Verificar se decisão é sensível ao tempo
     */
    private function is_time_sensitive_decision($decision_type) {
        $time_sensitive = [
            'emergency_response',
            'event_coordination',
            'resource_emergency',
            'conflict_mediation'
        ];
        
        return in_array($decision_type, $time_sensitive);
    }
    
    /**
     * Verificar restrições temporais
     */
    private function check_temporal_restrictions($decision_type, $context) {
        $result = ['allowed' => true, 'restrictions' => []];
        
        // Verificar horário de funcionamento
        $current_hour = (int) current_time('H');
        if ($current_hour < 6 || $current_hour > 22) {
            // Decisões fora do horário normal requerem justificativa
            if (empty($context['emergency_justification'])) {
                $result['allowed'] = false;
                $result['restrictions'][] = 'outside_working_hours';
            }
        }
        
        // Verificar período de reflexão para decisões importantes
        if ($this->requires_reflection_period($decision_type)) {
            $proposal_time = strtotime($context['proposal_datetime'] ?? '');
            $min_reflection = $context['min_reflection_hours'] ?? 24;
            
            if (time() < ($proposal_time + ($min_reflection * 3600))) {
                $result['allowed'] = false;
                $result['restrictions'][] = 'insufficient_reflection_time';
            }
        }
        
        return $result;
    }
    
    /**
     * Verificar se requer período de reflexão
     */
    private function requires_reflection_period($decision_type) {
        $reflection_required = [
            'collective_changes',
            'large_resource_allocation',
            'instance_dissolution',
            'fundamental_rule_change'
        ];
        
        return in_array($decision_type, $reflection_required);
    }
    
    /**
     * Obter instâncias do usuário
     */
    private function get_user_instances($user_id) {
        if (!class_exists('QL_Instances')) {
            return [];
        }
        
        global $wpdb;
        $members_table = $wpdb->prefix . 'ql_instance_members';
        $instances_table = $wpdb->prefix . 'ql_instances';
        
        $instances = $wpdb->get_results($wpdb->prepare("
            SELECT i.*, m.role as member_role
            FROM $instances_table i
            JOIN $members_table m ON i.id = m.instance_id
            WHERE m.user_id = %d AND m.status = 'active'
        ", $user_id), ARRAY_A);
        
        return $instances;
    }
    
    /**
     * Verificar se requer consulta
     */
    public function check_consultation_requirement($requires, $decision_type, $context) {
        $instance_type = $context['instance_type'] ?? null;
        
        if (!$instance_type) {
            return $requires;
        }
        
        $powers = self::DECISION_POWERS[$instance_type] ?? [];
        $restrictions = $powers['restrictions'] ?? [];
        
        // Círculos e núcleos requerem consulta para decisões relevantes
        if (in_array($instance_type, ['circulo', 'nucleo'])) {
            if (isset($restrictions['consultation_required_for_relevant_decisions']) && 
                $restrictions['consultation_required_for_relevant_decisions']) {
                return $this->is_relevant_decision($decision_type, $context);
            }
        }
        
        return $requires;
    }
    
    /**
     * Validar autoridade antes da decisão
     */
    public function validate_decision_authority($decision_type, $user_id, $context) {
        $can_decide = apply_filters('ql_can_make_decision', false, $decision_type, $user_id, $context);
        
        if (!$can_decide) {
            $authority = $this->get_user_authority_level($user_id, $context);
            $required = $this->get_required_authority_level($decision_type, $context);
            
            error_log("QL Decision Authority: User {$user_id} denied decision '{$decision_type}' - insufficient authority");
            
            do_action('ql_decision_denied', $decision_type, $user_id, $context, [
                'user_authority' => $authority,
                'required_authority' => $required
            ]);
            
            throw new Exception("Autoridade insuficiente para decisão: {$decision_type}");
        }
    }
    
    /**
     * Criar decisão autônoma (dentro da autoridade da pessoa/instância)
     */
    public function create_autonomous_decision($title, $description, $decision_type, $context = []) {
        $user_id = get_current_user_id();
        
        // Validar autoridade
        $this->validate_decision_authority($decision_type, $user_id, $context);
        
        // Criar registro de decisão
        global $wpdb;
        $decisions_table = $wpdb->prefix . 'ql_decisions';
        
        $data = [
            'decision_type' => 'autonomous',
            'title' => sanitize_text_field($title),
            'description' => sanitize_textarea_field($description),
            'decided_by' => $user_id,
            'decision_result' => 'approved',
            'affected_instances' => json_encode($context['affected_instances'] ?? []),
            'metadata' => json_encode([
                'decision_subtype' => $decision_type,
                'context' => $context,
                'authority_level' => $this->get_user_authority_level($user_id, $context)
            ])
        ];
        
        $result = $wpdb->insert($decisions_table, $data);
        
        if ($result === false) {
            throw new Exception('Erro ao registrar decisão');
        }
        
        $decision_id = $wpdb->insert_id;
        
        // Disparar hooks
        do_action('ql_autonomous_decision_created', $decision_id, $decision_type, $context);
        
        return $decision_id;
    }
    
    /**
     * Obter poderes de decisão por tipo de instância
     */
    public static function get_decision_powers($instance_type = null) {
        if ($instance_type) {
            return self::DECISION_POWERS[$instance_type] ?? [];
        }
        
        return self::DECISION_POWERS;
    }
    
    /**
     * Verificar se instância pode assumir responsabilidade
     */
    public function can_assume_responsibility($instance_type, $instance_id, $responsibility_key) {
        // Apenas círculos e núcleos podem assumir responsabilidades conforme modelo
        if (!in_array($instance_type, ['circulo', 'nucleo'])) {
            return false;
        }
        
        $powers = self::DECISION_POWERS[$instance_type] ?? [];
        
        // Verificar se tem poder para assumir responsabilidades
        return isset($powers['restrictions']['can_assume_decline_transfer_responsibility']) && 
               $powers['restrictions']['can_assume_decline_transfer_responsibility'];
    }
    
    /**
     * Verificar se pessoa pode participar de assembleia
     */
    public function can_participate_in_assembly($user_id, $assembly_id) {
        if (!class_exists('QL_Instances')) {
            return false;
        }
        
        $instances = QL_Instances::get_instance();
        $assembly = $instances->get_instance_by_id($assembly_id);
        
        if (!$assembly || $assembly->type !== 'assembleia') {
            return false;
        }
        
        // Verificar se é membro do coletivo ou instâncias relacionadas
        $assembly_metadata = json_decode($assembly->metadata ?: '{}', true);
        $collective_id = $assembly_metadata['collective_id'] ?? null;
        
        if ($collective_id) {
            return $this->is_collective_member($user_id, $collective_id);
        }
        
        return false;
    }
    
    /**
     * Verificar se é membro do coletivo
     */
    private function is_collective_member($user_id, $collective_id) {
        // Verificar se participa de alguma instância do coletivo
        $user_instances = $this->get_user_instances($user_id);
        
        foreach ($user_instances as $instance) {
            $instance_metadata = json_decode($instance['metadata'] ?: '{}', true);
            if (isset($instance_metadata['collective_id']) && 
                $instance_metadata['collective_id'] == $collective_id) {
                return true;
            }
        }
        
        return false;
    }
}