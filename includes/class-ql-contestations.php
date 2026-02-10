<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para gestão do sistema de contestações no Quilombo Lab
 * Baseada na estrutura do plugin GC mas adaptada para o modelo organizativo radicular
 */
class QL_Contestations {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ql_create_contestation', [$this, 'ajax_create_contestation']);
        add_action('wp_ajax_ql_respond_contestation', [$this, 'ajax_respond_contestation']);
        add_action('wp_ajax_ql_vote_contestation', [$this, 'ajax_vote_contestation']);
        
        // Hook para integração com sistema GC
        add_action('ql_integrate_with_gc', [$this, 'integrate_with_gc_contestations']);
    }
    
    public function init() {
        $this->create_tables();
    }
    
    /**
     * Criar tabelas do sistema de contestações
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela principal de contestações
        $contestations_table = $wpdb->prefix . 'ql_contestations';
        $sql1 = "CREATE TABLE $contestations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            target_type enum('responsibility_action', 'project_decision', 'instance_action', 'resource_allocation', 'role_assignment') NOT NULL,
            target_id mediumint(9) NOT NULL COMMENT 'ID do item sendo contestado',
            target_description text NOT NULL COMMENT 'Descrição do que está sendo contestado',
            contestation_type enum('procedure_violation', 'authority_exceeded', 'consensus_bypassed', 'transparency_lack', 'resource_misuse', 'conflict_interest', 'discrimination', 'other') NOT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            evidence text COMMENT 'Links para evidências, documentos, etc.',
            author_id mediumint(9) NOT NULL,
            author_instance_id mediumint(9) NULL COMMENT 'Instância pela qual a pessoa está contestando',
            status enum('open', 'under_review', 'community_vote', 'resolved', 'dismissed', 'expired') DEFAULT 'open',
            urgency_level enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            affected_instances text COMMENT 'JSON array de instâncias afetadas',
            required_authorities text COMMENT 'JSON array de autoridades que devem responder',
            deadline_response datetime NULL COMMENT 'Prazo para resposta inicial',
            deadline_resolution datetime NULL COMMENT 'Prazo para resolução final',
            post_id mediumint(9) NULL COMMENT 'ID do post criado para votação pública',
            form_id mediumint(9) NULL COMMENT 'ID do formulário Ninja Forms para votação',
            metadata text COMMENT 'JSON com metadados específicos da contestação',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY target_type_id (target_type, target_id),
            KEY author_id (author_id),
            KEY status (status),
            KEY urgency_level (urgency_level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Tabela de respostas às contestações
        $responses_table = $wpdb->prefix . 'ql_contestation_responses';
        $sql2 = "CREATE TABLE $responses_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            contestation_id mediumint(9) NOT NULL,
            responder_id mediumint(9) NOT NULL,
            responder_role varchar(100) NOT NULL COMMENT 'Papel/responsabilidade do responsável',
            response_type enum('explanation', 'justification', 'correction', 'acknowledgment', 'rebuttal') NOT NULL,
            content text NOT NULL,
            proposed_actions text COMMENT 'Ações propostas para resolução',
            evidence text COMMENT 'Links para evidências da resposta',
            is_satisfactory boolean DEFAULT FALSE COMMENT 'Se o contestador considera satisfatória',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contestation_id (contestation_id),
            KEY responder_id (responder_id),
            KEY response_type (response_type)
        ) $charset_collate;";
        
        // Tabela de votos nas contestações
        $votes_table = $wpdb->prefix . 'ql_contestation_votes';
        $sql3 = "CREATE TABLE $votes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            contestation_id mediumint(9) NOT NULL,
            voter_id mediumint(9) NOT NULL,
            vote_type enum('support_contestation', 'support_response', 'mediation_needed', 'insufficient_info') NOT NULL,
            comment text COMMENT 'Comentário opcional do voto',
            vote_weight decimal(3,2) DEFAULT 1.00 COMMENT 'Peso do voto baseado na instância/papel',
            instance_context mediumint(9) NULL COMMENT 'Instância pela qual está votando',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contestation_id (contestation_id),
            KEY voter_id (voter_id),
            KEY vote_type (vote_type),
            UNIQUE KEY unique_vote (contestation_id, voter_id, instance_context)
        ) $charset_collate;";
        
        // Tabela de decisões finais
        $decisions_table = $wpdb->prefix . 'ql_contestation_decisions';
        $sql4 = "CREATE TABLE $decisions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            contestation_id mediumint(9) NOT NULL,
            decision_type enum('consensus', 'community_vote', 'mediation', 'authority_ruling', 'automatic') NOT NULL,
            result enum('contestation_upheld', 'contestation_dismissed', 'partial_resolution', 'mediation_required') NOT NULL,
            deciding_body varchar(255) NOT NULL COMMENT 'Quem decidiu (instância, assembleia, etc.)',
            rationale text NOT NULL COMMENT 'Justificativa da decisão',
            actions_required text COMMENT 'JSON com ações que devem ser tomadas',
            implementation_deadline datetime NULL,
            implemented boolean DEFAULT FALSE,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY contestation_id (contestation_id),
            KEY decision_type (decision_type),
            KEY result (result)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
    
    /**
     * Criar nova contestação
     */
    public function create_contestation($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        $required_fields = ['target_type', 'target_id', 'target_description', 'contestation_type', 'title', 'description'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Campo obrigatório: $field");
            }
        }
        
        // Verificar autorização para contestar
        if (!$this->can_create_contestation(get_current_user_id(), $data)) {
            return new WP_Error('unauthorized', 'Não autorizado a criar esta contestação');
        }
        
        $contestations_table = $wpdb->prefix . 'ql_contestations';
        
        // Determinar prazos baseados na urgência
        $urgency = $data['urgency_level'] ?? 'medium';
        $deadlines = $this->calculate_deadlines($urgency);
        
        // Determinar instâncias e autoridades afetadas
        $affected_data = $this->determine_affected_parties($data['target_type'], $data['target_id']);
        
        $contestation_data = [
            'target_type' => sanitize_text_field($data['target_type']),
            'target_id' => intval($data['target_id']),
            'target_description' => sanitize_textarea_field($data['target_description']),
            'contestation_type' => sanitize_text_field($data['contestation_type']),
            'title' => sanitize_text_field($data['title']),
            'description' => sanitize_textarea_field($data['description']),
            'evidence' => sanitize_textarea_field($data['evidence'] ?? ''),
            'author_id' => get_current_user_id(),
            'author_instance_id' => intval($data['author_instance_id'] ?? 0),
            'urgency_level' => $urgency,
            'affected_instances' => json_encode($affected_data['instances']),
            'required_authorities' => json_encode($affected_data['authorities']),
            'deadline_response' => $deadlines['response'],
            'deadline_resolution' => $deadlines['resolution'],
            'metadata' => json_encode($data['metadata'] ?? [])
        ];
        
        $result = $wpdb->insert($contestations_table, $contestation_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar contestação');
        }
        
        $contestation_id = $wpdb->insert_id;
        
        // Criar post público se necessário
        if (!empty($data['create_public_post'])) {
            $this->create_contestation_post($contestation_id, $contestation_data);
        }
        
        // Notificar partes envolvidas
        $this->notify_contestation_parties($contestation_id, $affected_data);
        
        do_action('ql_contestation_created', $contestation_id, $contestation_data);
        
        return $contestation_id;
    }
    
    /**
     * Responder a uma contestação
     */
    public function respond_to_contestation($contestation_id, $response_data) {
        global $wpdb;
        
        // Verificar se pode responder
        if (!$this->can_respond_to_contestation(get_current_user_id(), $contestation_id)) {
            return new WP_Error('unauthorized', 'Não autorizado a responder a esta contestação');
        }
        
        // Verificar se contestação existe e está aberta
        $contestation = $this->get_contestation($contestation_id);
        if (!$contestation || !in_array($contestation->status, ['open', 'under_review'])) {
            return new WP_Error('invalid_status', 'Contestação não pode receber respostas no estado atual');
        }
        
        $responses_table = $wpdb->prefix . 'ql_contestation_responses';
        
        $response = [
            'contestation_id' => $contestation_id,
            'responder_id' => get_current_user_id(),
            'responder_role' => sanitize_text_field($response_data['responder_role']),
            'response_type' => sanitize_text_field($response_data['response_type']),
            'content' => sanitize_textarea_field($response_data['content']),
            'proposed_actions' => sanitize_textarea_field($response_data['proposed_actions'] ?? ''),
            'evidence' => sanitize_textarea_field($response_data['evidence'] ?? '')
        ];
        
        $result = $wpdb->insert($responses_table, $response);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar resposta');
        }
        
        // Atualizar status da contestação
        $this->update_contestation_status($contestation_id, 'under_review');
        
        // Notificar autor da contestação
        $this->notify_contestation_response($contestation_id, $wpdb->insert_id);
        
        do_action('ql_contestation_response_created', $contestation_id, $wpdb->insert_id);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Criar post público para contestação com formulário de votação
     */
    public function create_contestation_post($contestation_id, $contestation_data) {
        $contestation = $this->get_contestation($contestation_id);
        if (!$contestation) {
            return false;
        }
        
        // Determinar categoria do projeto ou usar categoria padrão
        $project_category = $this->get_project_category($contestation->target_type, $contestation->target_id);
        
        // Título do post
        $post_title = sprintf('Contestação: %s', $contestation->title);
        
        // Conteúdo do post
        $post_content = $this->generate_contestation_post_content($contestation);
        
        // Criar o post
        $post_data = [
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $contestation->author_id,
            'post_category' => $project_category ? [$project_category] : [get_option('default_category')]
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Criar formulário Ninja Forms se disponível
            $form_id = $this->create_voting_form($contestation_id, $post_id);
            
            // Atualizar contestação com IDs do post e formulário
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ql_contestations',
                [
                    'post_id' => $post_id,
                    'form_id' => $form_id
                ],
                ['id' => $contestation_id]
            );
            
            // Adicionar metadados ao post
            update_post_meta($post_id, 'ql_contestation_id', $contestation_id);
            update_post_meta($post_id, 'ql_contestation_type', 'contestation');
            update_post_meta($post_id, 'ql_voting_form_id', $form_id);
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Gerar conteúdo para o post da contestação
     */
    private function generate_contestation_post_content($contestation) {
        $content = '';
        
        // Informações básicas da contestação
        $content .= '<div class="ql-contestation-info">';
        $content .= '<h3>Informações da Contestação</h3>';
        $content .= '<p><strong>Tipo:</strong> ' . $this->get_contestation_type_label($contestation->contestation_type) . '</p>';
        $content .= '<p><strong>Urgência:</strong> ' . ucfirst($contestation->urgency_level) . '</p>';
        $content .= '<p><strong>Alvo da Contestação:</strong> ' . esc_html($contestation->target_description) . '</p>';
        $content .= '</div>';
        
        // Descrição da contestação
        $content .= '<div class="ql-contestation-description">';
        $content .= '<h3>Descrição</h3>';
        $content .= '<p>' . nl2br(esc_html($contestation->description)) . '</p>';
        $content .= '</div>';
        
        // Evidências se houver
        if (!empty($contestation->evidence)) {
            $content .= '<div class="ql-contestation-evidence">';
            $content .= '<h3>Evidências</h3>';
            $content .= '<p>' . nl2br(esc_html($contestation->evidence)) . '</p>';
            $content .= '</div>';
        }
        
        // Respostas existentes
        $responses = $this->get_contestation_responses($contestation->id);
        if (!empty($responses)) {
            $content .= '<div class="ql-contestation-responses">';
            $content .= '<h3>Respostas</h3>';
            foreach ($responses as $response) {
                $responder = get_userdata($response->responder_id);
                $content .= '<div class="ql-response">';
                $content .= '<h4>Resposta de ' . esc_html($responder->display_name) . ' (' . $response->responder_role . ')</h4>';
                $content .= '<p><strong>Tipo:</strong> ' . $this->get_response_type_label($response->response_type) . '</p>';
                $content .= '<p>' . nl2br(esc_html($response->content)) . '</p>';
                if (!empty($response->proposed_actions)) {
                    $content .= '<p><strong>Ações Propostas:</strong> ' . nl2br(esc_html($response->proposed_actions)) . '</p>';
                }
                $content .= '<p><small>Respondido em: ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($response->created_at)) . '</small></p>';
                $content .= '</div>';
            }
            $content .= '</div>';
        }
        
        // Instruções para votação
        $content .= '<div class="ql-voting-instructions">';
        $content .= '<h3>Participação na Resolução</h3>';
        $content .= '<p>Esta contestação está aberta para avaliação da comunidade. Use o formulário abaixo para expressar sua posição:</p>';
        $content .= '<ul>';
        $content .= '<li><strong>Apoio à Contestação:</strong> Concordo que há uma questão válida que precisa ser abordada</li>';
        $content .= '<li><strong>Apoio à Resposta:</strong> Considero a resposta dos responsáveis satisfatória</li>';
        $content .= '<li><strong>Mediação Necessária:</strong> Acredito que é necessário um processo de mediação</li>';
        $content .= '<li><strong>Informações Insuficientes:</strong> Preciso de mais informações para decidir</li>';
        $content .= '</ul>';
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Criar formulário Ninja Forms para votação
     */
    private function create_voting_form($contestation_id, $post_id) {
        // Verificar se Ninja Forms está disponível
        if (!function_exists('Ninja_Forms')) {
            return null;
        }
        
        try {
            // Criar formulário básico
            $form_data = [
                'title' => 'Votação - Contestação #' . $contestation_id,
                'settings' => [
                    'objectType' => 'Form',
                    'editActive' => '',
                    'title' => 'Votação - Contestação #' . $contestation_id,
                    'created_date' => date('m/d/Y'),
                    'modified_date' => date('m/d/Y'),
                    'show_title' => '0',
                    'clear_complete' => '1',
                    'hide_complete' => '1',
                    'logged_in' => '1'
                ]
            ];
            
            // Campos do formulário
            $fields = [
                [
                    'objectType' => 'Field',
                    'order' => 1,
                    'type' => 'radio',
                    'key' => 'vote_type',
                    'label' => 'Sua Posição',
                    'required' => '1',
                    'options' => [
                        ['label' => 'Apoio à Contestação', 'value' => 'support_contestation', 'calc' => ''],
                        ['label' => 'Apoio à Resposta', 'value' => 'support_response', 'calc' => ''],
                        ['label' => 'Mediação Necessária', 'value' => 'mediation_needed', 'calc' => ''],
                        ['label' => 'Informações Insuficientes', 'value' => 'insufficient_info', 'calc' => '']
                    ]
                ],
                [
                    'objectType' => 'Field',
                    'order' => 2,
                    'type' => 'textarea',
                    'key' => 'comment',
                    'label' => 'Comentário (Opcional)',
                    'required' => '0',
                    'placeholder' => 'Explique sua posição (opcional)'
                ],
                [
                    'objectType' => 'Field',
                    'order' => 3,
                    'type' => 'hidden',
                    'key' => 'contestation_id',
                    'default' => $contestation_id
                ],
                [
                    'objectType' => 'Field',
                    'order' => 4,
                    'type' => 'submit',
                    'key' => 'submit',
                    'label' => 'Enviar Voto',
                    'processing_label' => 'Enviando...'
                ]
            ];
            
            // Ações do formulário
            $actions = [
                [
                    'objectType' => 'Action',
                    'type' => 'save',
                    'label' => 'Salvar Voto',
                    'active' => '1'
                ]
            ];
            
            // Usar API do Ninja Forms para criar o formulário
            $form_id = Ninja_Forms()->form()->save($form_data);
            
            if ($form_id) {
                // Adicionar campos
                foreach ($fields as $field_data) {
                    $field_data['parent_id'] = $form_id;
                    Ninja_Forms()->form($form_id)->field()->save($field_data);
                }
                
                // Adicionar ações
                foreach ($actions as $action_data) {
                    $action_data['parent_id'] = $form_id;
                    Ninja_Forms()->form($form_id)->action()->save($action_data);
                }
                
                // Hook para processar submissões
                add_action('ninja_forms_after_submission', [$this, 'process_voting_submission']);
                
                return $form_id;
            }
            
        } catch (Exception $e) {
            error_log('QL Contestations - Erro ao criar formulário: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Processar submissão do formulário de votação
     */
    public function process_voting_submission($form_data) {
        $form_settings = $form_data['settings'];
        $form_fields = $form_data['fields'];
        
        // Verificar se é um formulário de contestação
        $contestation_id = null;
        $vote_type = null;
        $comment = '';
        
        foreach ($form_fields as $field) {
            if ($field['key'] === 'contestation_id') {
                $contestation_id = intval($field['value']);
            } elseif ($field['key'] === 'vote_type') {
                $vote_type = sanitize_text_field($field['value']);
            } elseif ($field['key'] === 'comment') {
                $comment = sanitize_textarea_field($field['value']);
            }
        }
        
        if ($contestation_id && $vote_type) {
            $this->submit_vote($contestation_id, $vote_type, $comment);
        }
    }
    
    /**
     * Registrar voto em contestação
     */
    public function submit_vote($contestation_id, $vote_type, $comment = '', $voter_id = null) {
        if (!$voter_id) {
            $voter_id = get_current_user_id();
        }
        
        // Verificar autorização
        if (!$this->can_vote_on_contestation($voter_id, $contestation_id)) {
            return new WP_Error('unauthorized', 'Não autorizado a votar nesta contestação');
        }
        
        global $wpdb;
        $votes_table = $wpdb->prefix . 'ql_contestation_votes';
        
        // Calcular peso do voto
        $vote_weight = $this->calculate_vote_weight($voter_id, $contestation_id);
        
        // Determinar contexto da instância
        $instance_context = $this->get_voter_instance_context($voter_id, $contestation_id);
        
        $vote_data = [
            'contestation_id' => $contestation_id,
            'voter_id' => $voter_id,
            'vote_type' => $vote_type,
            'comment' => $comment,
            'vote_weight' => $vote_weight,
            'instance_context' => $instance_context
        ];
        
        // Verificar se já votou neste contexto
        $existing_vote = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $votes_table WHERE contestation_id = %d AND voter_id = %d AND instance_context = %d",
            $contestation_id, $voter_id, $instance_context
        ));
        
        if ($existing_vote) {
            // Atualizar voto existente
            $result = $wpdb->update(
                $votes_table,
                $vote_data,
                ['id' => $existing_vote]
            );
        } else {
            // Criar novo voto
            $result = $wpdb->insert($votes_table, $vote_data);
        }
        
        if ($result !== false) {
            // Verificar se a contestação deve mudar de status
            $this->check_contestation_completion($contestation_id);
            
            do_action('ql_contestation_vote_submitted', $contestation_id, $voter_id, $vote_type);
            
            return true;
        }
        
        return new WP_Error('db_error', 'Erro ao registrar voto');
    }
    
    /**
     * Verificar se contestação deve ser finalizada baseada nos votos
     */
    private function check_contestation_completion($contestation_id) {
        global $wpdb;
        
        $contestation = $this->get_contestation($contestation_id);
        if (!$contestation || $contestation->status !== 'community_vote') {
            return;
        }
        
        $votes_table = $wpdb->prefix . 'ql_contestation_votes';
        
        // Obter estatísticas dos votos
        $vote_stats = $wpdb->get_results($wpdb->prepare("
            SELECT vote_type, COUNT(*) as count, SUM(vote_weight) as total_weight
            FROM $votes_table 
            WHERE contestation_id = %d 
            GROUP BY vote_type
        ", $contestation_id));
        
        $total_votes = 0;
        $total_weight = 0;
        $stats = [];
        
        foreach ($vote_stats as $stat) {
            $stats[$stat->vote_type] = [
                'count' => intval($stat->count),
                'weight' => floatval($stat->total_weight)
            ];
            $total_votes += $stat->count;
            $total_weight += $stat->total_weight;
        }
        
        // Critérios para conclusão (configuráveis)
        $min_votes = 5; // Mínimo de votos
        $consensus_threshold = 0.6; // 60% para consenso
        
        if ($total_votes >= $min_votes) {
            $support_contestation = $stats['support_contestation']['weight'] ?? 0;
            $support_response = $stats['support_response']['weight'] ?? 0;
            
            $contestation_ratio = $total_weight > 0 ? $support_contestation / $total_weight : 0;
            $response_ratio = $total_weight > 0 ? $support_response / $total_weight : 0;
            
            if ($contestation_ratio >= $consensus_threshold) {
                $this->resolve_contestation($contestation_id, 'contestation_upheld');
            } elseif ($response_ratio >= $consensus_threshold) {
                $this->resolve_contestation($contestation_id, 'contestation_dismissed');
            } elseif ($total_votes >= 10) { // Após muitos votos sem consenso
                $this->resolve_contestation($contestation_id, 'mediation_required');
            }
        }
    }
    
    /**
     * Resolver contestação com decisão final
     */
    public function resolve_contestation($contestation_id, $result, $rationale = '', $deciding_body = 'community') {
        global $wpdb;
        
        $decisions_table = $wpdb->prefix . 'ql_contestation_decisions';
        
        $decision_data = [
            'contestation_id' => $contestation_id,
            'decision_type' => 'community_vote',
            'result' => $result,
            'deciding_body' => $deciding_body,
            'rationale' => $rationale
        ];
        
        $wpdb->insert($decisions_table, $decision_data);
        $decision_id = $wpdb->insert_id;
        
        // Atualizar status da contestação
        $new_status = ($result === 'mediation_required') ? 'under_review' : 'resolved';
        $this->update_contestation_status($contestation_id, $new_status);
        
        // Executar ações baseadas na decisão
        $this->implement_contestation_decision($contestation_id, $result);
        
        do_action('ql_contestation_resolved', $contestation_id, $result, $decision_id);
        
        return $decision_id;
    }
    
    // Métodos auxiliares
    
    private function can_create_contestation($user_id, $data) {
        // Verificar se usuário está logado
        if (!$user_id) return false;
        
        // Verificar se tem direito de contestar o tipo de ação
        return $this->has_contestation_rights($user_id, $data['target_type']);
    }
    
    private function can_respond_to_contestation($user_id, $contestation_id) {
        $contestation = $this->get_contestation($contestation_id);
        if (!$contestation) return false;
        
        // Verificar se é responsável pela ação contestada
        $authorities = json_decode($contestation->required_authorities, true) ?? [];
        return in_array($user_id, $authorities) || current_user_can('manage_options');
    }
    
    private function can_vote_on_contestation($user_id, $contestation_id) {
        // Qualquer membro da comunidade pode votar
        return $user_id > 0;
    }
    
    private function calculate_deadlines($urgency) {
        $hours_response = ['low' => 72, 'medium' => 48, 'high' => 24, 'critical' => 12];
        $days_resolution = ['low' => 14, 'medium' => 7, 'high' => 3, 'critical' => 1];
        
        return [
            'response' => date('Y-m-d H:i:s', strtotime('+' . $hours_response[$urgency] . ' hours')),
            'resolution' => date('Y-m-d H:i:s', strtotime('+' . $days_resolution[$urgency] . ' days'))
        ];
    }
    
    private function determine_affected_parties($target_type, $target_id) {
        // Implementar lógica para determinar instâncias e autoridades afetadas
        return [
            'instances' => [],
            'authorities' => []
        ];
    }
    
    private function get_contestation($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_contestations WHERE id = %d",
            $id
        ));
    }
    
    private function get_contestation_responses($contestation_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_contestation_responses WHERE contestation_id = %d ORDER BY created_at ASC",
            $contestation_id
        ));
    }
    
    private function update_contestation_status($contestation_id, $status) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'ql_contestations',
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $contestation_id]
        );
    }
    
    // Métodos AJAX
    
    public function ajax_create_contestation() {
        check_ajax_referer('ql_create_contestation', 'nonce');
        
        $data = [
            'target_type' => sanitize_text_field($_POST['target_type']),
            'target_id' => intval($_POST['target_id']),
            'target_description' => sanitize_textarea_field($_POST['target_description']),
            'contestation_type' => sanitize_text_field($_POST['contestation_type']),
            'title' => sanitize_text_field($_POST['title']),
            'description' => sanitize_textarea_field($_POST['description']),
            'evidence' => sanitize_textarea_field($_POST['evidence'] ?? ''),
            'urgency_level' => sanitize_text_field($_POST['urgency_level'] ?? 'medium'),
            'create_public_post' => !empty($_POST['create_public_post'])
        ];
        
        $result = $this->create_contestation($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(['contestation_id' => $result, 'message' => 'Contestação criada com sucesso']);
    }
    
    public function ajax_respond_contestation() {
        check_ajax_referer('ql_respond_contestation', 'nonce');
        
        $contestation_id = intval($_POST['contestation_id']);
        $response_data = [
            'responder_role' => sanitize_text_field($_POST['responder_role']),
            'response_type' => sanitize_text_field($_POST['response_type']),
            'content' => sanitize_textarea_field($_POST['content']),
            'proposed_actions' => sanitize_textarea_field($_POST['proposed_actions'] ?? ''),
            'evidence' => sanitize_textarea_field($_POST['evidence'] ?? '')
        ];
        
        $result = $this->respond_to_contestation($contestation_id, $response_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(['response_id' => $result, 'message' => 'Resposta enviada com sucesso']);
    }
    
    public function ajax_vote_contestation() {
        check_ajax_referer('ql_vote_contestation', 'nonce');
        
        $contestation_id = intval($_POST['contestation_id']);
        $vote_type = sanitize_text_field($_POST['vote_type']);
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        
        $result = $this->submit_vote($contestation_id, $vote_type, $comment);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(['message' => 'Voto registrado com sucesso']);
    }
    
    // Métodos de integração com GC
    
    public function integrate_with_gc_contestations() {
        // Implementar sincronização com sistema GC
        if (class_exists('GC_Contestacao')) {
            // Lógica de integração
        }
    }
    
    // Métodos auxiliares para labels e formatação
    
    private function get_contestation_type_label($type) {
        $labels = [
            'procedure_violation' => 'Violação de Procedimento',
            'authority_exceeded' => 'Autoridade Excedida',
            'consensus_bypassed' => 'Consenso Ignorado',
            'transparency_lack' => 'Falta de Transparência',
            'resource_misuse' => 'Uso Inadequado de Recursos',
            'conflict_interest' => 'Conflito de Interesses',
            'discrimination' => 'Discriminação',
            'other' => 'Outro'
        ];
        return $labels[$type] ?? $type;
    }
    
    private function get_response_type_label($type) {
        $labels = [
            'explanation' => 'Explicação',
            'justification' => 'Justificativa',
            'correction' => 'Correção',
            'acknowledgment' => 'Reconhecimento',
            'rebuttal' => 'Refutação'
        ];
        return $labels[$type] ?? $type;
    }
    
    // Métodos placeholders para implementação futura
    
    private function has_contestation_rights($user_id, $target_type) {
        return true; // Implementar lógica específica
    }
    
    private function calculate_vote_weight($user_id, $contestation_id) {
        return 1.0; // Implementar lógica de peso baseada em instância/papel
    }
    
    private function get_voter_instance_context($user_id, $contestation_id) {
        return null; // Implementar lógica de contexto de instância
    }
    
    private function get_project_category($target_type, $target_id) {
        return null; // Implementar lógica para determinar categoria
    }
    
    private function notify_contestation_parties($contestation_id, $affected_data) {
        // Implementar notificações
    }
    
    private function notify_contestation_response($contestation_id, $response_id) {
        // Implementar notificações de resposta
    }
    
    private function implement_contestation_decision($contestation_id, $result) {
        // Implementar execução das decisões
    }
}