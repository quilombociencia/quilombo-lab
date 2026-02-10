<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sistema Radicular de Responsabilidades do Quilombo Laboratório
 * 
 * Gerencia a distribuição não-hierárquica de responsabilidades entre pessoas e instâncias
 */
class QL_Responsibility_System {
    
    private static $instance = null;
    
    /**
     * 6 Responsabilidades básicas do modelo organizativo
     */
    const RESPONSIBILITIES = [
        'guia' => [
            'label' => 'Guia',
            'description' => 'Responsável por guiar os demais participantes em conteúdos, atividades e eventos',
            'tasks' => ['Didático-pedagógicas'],
            'actions' => ['Guiar', 'Criar trilhas', 'Mediar', 'Explicar', 'Planejar', 'Organizar'],
            'color' => '#3498db',
            'icon' => 'dashicons-welcome-learn-more'
        ],
        'orientacao' => [
            'label' => 'Orientação',
            'description' => 'Responsável por orientar e propor referências, metodologias e boas práticas',
            'tasks' => ['Teóricas', 'Metodológicas', 'Corretivas', 'Instrutivas'],
            'actions' => ['Assessorar', 'Supervisionar', 'Certificar', 'Corrigir', 'Revisar', 'Aconselhar'],
            'color' => '#9b59b6',
            'icon' => 'dashicons-book-alt'
        ],
        'operacao' => [
            'label' => 'Operação',
            'description' => 'Responsável por executar ações que envolvem transformação material',
            'tasks' => ['Técnicas', 'Práticas', 'Operacionais'],
            'actions' => ['Construir', 'Consertar', 'Limpar', 'Transportar', 'Cozinhar', 'Instalar'],
            'color' => '#e74c3c',
            'icon' => 'dashicons-admin-tools'
        ],
        'comunicacao' => [
            'label' => 'Comunicação',
            'description' => 'Responsável por comunicar e registrar informações entre pessoas e instâncias',
            'tasks' => ['Informacionais', 'Midiáticas', 'Artísticas', 'Jornalísticas'],
            'actions' => ['Captar', 'Editar', 'Escrever', 'Gravar', 'Postar', 'Divulgar', 'Produzir'],
            'color' => '#f39c12',
            'icon' => 'dashicons-megaphone'
        ],
        'documentacao' => [
            'label' => 'Documentação',
            'description' => 'Responsável por documentar ações, mudanças e patrimônio do projeto',
            'tasks' => ['Normativas', 'Técnicas', 'Metodológicas'],
            'actions' => ['Registrar', 'Auditar', 'Relatar', 'Verificar', 'Fiscalizar', 'Consultar'],
            'color' => '#27ae60',
            'icon' => 'dashicons-media-document'
        ],
        'gestao' => [
            'label' => 'Gestão',
            'description' => 'Responsável por gerir recursos disponíveis e necessários',
            'tasks' => ['Administrativas', 'Contábeis', 'Financeiras', 'Econômicas'],
            'actions' => ['Captar recursos', 'Administrar', 'Prestar contas', 'Analisar', 'Consultar'],
            'color' => '#34495e',
            'icon' => 'dashicons-chart-line'
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
        add_action('wp_ajax_ql_assign_responsibility', [$this, 'ajax_assign_responsibility']);
        add_action('wp_ajax_ql_transfer_responsibility', [$this, 'ajax_transfer_responsibility']);
        add_action('wp_ajax_ql_create_consultation', [$this, 'ajax_create_consultation']);
        add_action('wp_ajax_ql_vote_consultation', [$this, 'ajax_vote_consultation']);
        
        // Hooks para verificar decisões
        add_filter('ql_can_make_decision', [$this, 'check_decision_authority'], 10, 4);
        add_action('ql_before_important_action', [$this, 'require_consultation'], 10, 3);
    }
    
    public function init() {
        $this->create_responsibility_tables();
    }
    
    /**
     * Criar tabelas para sistema de responsabilidades
     */
    private function create_responsibility_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de responsabilidades atribuídas
        $responsibilities_table = $wpdb->prefix . 'ql_responsibilities';
        $sql1 = "CREATE TABLE $responsibilities_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            responsibility_key varchar(50) NOT NULL,
            assignee_type enum('person', 'circle', 'nucleus') NOT NULL,
            assignee_id mediumint(9) NOT NULL,
            context_type enum('global', 'project', 'trail', 'instance') NOT NULL,
            context_id mediumint(9) NULL,
            assigned_by mediumint(9) NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('active', 'transferred', 'declined') DEFAULT 'active',
            notes text,
            PRIMARY KEY (id),
            KEY assignee (assignee_type, assignee_id),
            KEY context (context_type, context_id),
            KEY responsibility (responsibility_key),
            KEY status (status)
        ) $charset_collate;";
        
        // Tabela de consultas para decisões
        $consultations_table = $wpdb->prefix . 'ql_consultations';
        $sql2 = "CREATE TABLE $consultations_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            created_by mediumint(9) NOT NULL,
            target_instances text NOT NULL COMMENT 'JSON array de instâncias que devem ser consultadas',
            action_proposed text NOT NULL COMMENT 'Descrição da ação proposta',
            consultation_type enum('decision', 'information', 'feedback') DEFAULT 'decision',
            start_date datetime DEFAULT CURRENT_TIMESTAMP,
            end_date datetime NULL,
            status enum('open', 'closed', 'approved', 'rejected') DEFAULT 'open',
            min_quorum int DEFAULT 50 COMMENT 'Percentual mínimo de participação',
            approval_threshold int DEFAULT 60 COMMENT 'Percentual mínimo para aprovação',
            metadata text COMMENT 'JSON com dados específicos da consulta',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY status (status),
            KEY dates (start_date, end_date)
        ) $charset_collate;";
        
        // Tabela de votos nas consultas
        $consultation_votes_table = $wpdb->prefix . 'ql_consultation_votes';
        $sql3 = "CREATE TABLE $consultation_votes_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            consultation_id mediumint(9) NOT NULL,
            voter_id mediumint(9) NOT NULL,
            vote enum('approve', 'reject', 'abstain', 'needs_discussion') NOT NULL,
            comment text,
            vote_weight decimal(3,2) DEFAULT 1.00 COMMENT 'Peso do voto baseado no papel',
            voted_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY consultation_id (consultation_id),
            KEY voter_id (voter_id),
            UNIQUE KEY unique_vote (consultation_id, voter_id)
        ) $charset_collate;";
        
        // Tabela de decisões e consensos
        $decisions_table = $wpdb->prefix . 'ql_decisions';
        $sql4 = "CREATE TABLE $decisions_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            consultation_id mediumint(9) NULL,
            decision_type enum('consensus', 'consultation', 'autonomous', 'assembly') NOT NULL,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            decided_by mediumint(9) NOT NULL,
            decision_result enum('approved', 'rejected', 'postponed') NOT NULL,
            implementation_status enum('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
            affected_instances text COMMENT 'JSON array de instâncias impactadas',
            implementation_deadline datetime NULL,
            decided_at datetime DEFAULT CURRENT_TIMESTAMP,
            metadata text COMMENT 'JSON com dados da decisão',
            PRIMARY KEY (id),
            KEY consultation_id (consultation_id),
            KEY decided_by (decided_by),
            KEY decision_type (decision_type),
            KEY implementation_status (implementation_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
    
    /**
     * Atribuir responsabilidade a pessoa ou instância
     */
    public function assign_responsibility($responsibility_key, $assignee_type, $assignee_id, $context_type = 'global', $context_id = null, $assigned_by = null) {
        global $wpdb;
        
        if (!array_key_exists($responsibility_key, self::RESPONSIBILITIES)) {
            return new WP_Error('invalid_responsibility', 'Responsabilidade inválida');
        }
        
        $assigned_by = $assigned_by ?: get_current_user_id();
        
        // Verificar se já existe responsabilidade ativa
        $responsibilities_table = $wpdb->prefix . 'ql_responsibilities';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $responsibilities_table 
             WHERE responsibility_key = %s 
             AND assignee_type = %s 
             AND assignee_id = %d 
             AND context_type = %s 
             AND context_id = %s 
             AND status = 'active'",
            $responsibility_key, $assignee_type, $assignee_id, $context_type, $context_id
        ));
        
        if ($existing) {
            return new WP_Error('responsibility_exists', 'Responsabilidade já atribuída');
        }
        
        // Verificar autoridade para atribuir
        if (!$this->can_assign_responsibility($assigned_by, $responsibility_key, $context_type, $context_id)) {
            return new WP_Error('insufficient_authority', 'Sem autoridade para atribuir esta responsabilidade');
        }
        
        $data = [
            'responsibility_key' => $responsibility_key,
            'assignee_type' => $assignee_type,
            'assignee_id' => $assignee_id,
            'context_type' => $context_type,
            'context_id' => $context_id,
            'assigned_by' => $assigned_by,
            'status' => 'active'
        ];
        
        $result = $wpdb->insert($responsibilities_table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atribuir responsabilidade');
        }
        
        $responsibility_id = $wpdb->insert_id;
        
        // Disparar hook
        do_action('ql_responsibility_assigned', $responsibility_id, $responsibility_key, $assignee_type, $assignee_id);
        
        return $responsibility_id;
    }
    
    /**
     * Transferir responsabilidade
     */
    public function transfer_responsibility($responsibility_id, $new_assignee_type, $new_assignee_id, $transferred_by = null) {
        global $wpdb;
        
        $transferred_by = $transferred_by ?: get_current_user_id();
        $responsibilities_table = $wpdb->prefix . 'ql_responsibilities';
        
        // Buscar responsabilidade atual
        $responsibility = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $responsibilities_table WHERE id = %d AND status = 'active'",
            $responsibility_id
        ));
        
        if (!$responsibility) {
            return new WP_Error('responsibility_not_found', 'Responsabilidade não encontrada');
        }
        
        // Verificar autoridade para transferir
        if (!$this->can_transfer_responsibility($transferred_by, $responsibility)) {
            return new WP_Error('cannot_transfer', 'Sem autoridade para transferir esta responsabilidade');
        }
        
        // Marcar responsabilidade atual como transferida
        $wpdb->update(
            $responsibilities_table,
            ['status' => 'transferred'],
            ['id' => $responsibility_id],
            ['%s'],
            ['%d']
        );
        
        // Criar nova atribuição
        $new_responsibility_id = $this->assign_responsibility(
            $responsibility->responsibility_key,
            $new_assignee_type,
            $new_assignee_id,
            $responsibility->context_type,
            $responsibility->context_id,
            $transferred_by
        );
        
        if (is_wp_error($new_responsibility_id)) {
            // Reverter status se falhar
            $wpdb->update(
                $responsibilities_table,
                ['status' => 'active'],
                ['id' => $responsibility_id],
                ['%s'],
                ['%d']
            );
            return $new_responsibility_id;
        }
        
        // Disparar hook
        do_action('ql_responsibility_transferred', $responsibility_id, $new_responsibility_id);
        
        return $new_responsibility_id;
    }
    
    /**
     * Criar consulta para decisão
     */
    public function create_consultation($title, $description, $action_proposed, $target_instances, $consultation_type = 'decision', $options = []) {
        global $wpdb;
        
        $created_by = get_current_user_id();
        $consultations_table = $wpdb->prefix . 'ql_consultations';
        
        $data = [
            'title' => sanitize_text_field($title),
            'description' => sanitize_textarea_field($description),
            'created_by' => $created_by,
            'target_instances' => json_encode($target_instances),
            'action_proposed' => sanitize_textarea_field($action_proposed),
            'consultation_type' => $consultation_type,
            'end_date' => $options['end_date'] ?? null,
            'min_quorum' => intval($options['min_quorum'] ?? 50),
            'approval_threshold' => intval($options['approval_threshold'] ?? 60),
            'metadata' => json_encode($options['metadata'] ?? [])
        ];
        
        $result = $wpdb->insert($consultations_table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar consulta');
        }
        
        $consultation_id = $wpdb->insert_id;
        
        // Criar post público automaticamente
        $this->create_consultation_post($consultation_id, $data);
        
        // Notificar instâncias envolvidas
        $this->notify_consultation_participants($consultation_id, $target_instances);
        
        // Disparar hook
        do_action('ql_consultation_created', $consultation_id, $target_instances);
        
        return $consultation_id;
    }
    
    /**
     * Votar em consulta
     */
    public function vote_consultation($consultation_id, $vote, $comment = '', $voter_id = null) {
        global $wpdb;
        
        $voter_id = $voter_id ?: get_current_user_id();
        $votes_table = $wpdb->prefix . 'ql_consultation_votes';
        
        // Verificar se consulta existe e está aberta
        $consultations_table = $wpdb->prefix . 'ql_consultations';
        $consultation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $consultations_table WHERE id = %d AND status = 'open'",
            $consultation_id
        ));
        
        if (!$consultation) {
            return new WP_Error('consultation_not_found', 'Consulta não encontrada ou não está aberta');
        }
        
        // Verificar se pessoa pode votar
        if (!$this->can_vote_consultation($voter_id, $consultation)) {
            return new WP_Error('cannot_vote', 'Não autorizado a votar nesta consulta');
        }
        
        // Calcular peso do voto baseado no papel
        $vote_weight = $this->calculate_vote_weight($voter_id, $consultation);
        
        // Inserir ou atualizar voto
        $vote_data = [
            'consultation_id' => $consultation_id,
            'voter_id' => $voter_id,
            'vote' => $vote,
            'comment' => sanitize_textarea_field($comment),
            'vote_weight' => $vote_weight
        ];
        
        $existing_vote = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $votes_table WHERE consultation_id = %d AND voter_id = %d",
            $consultation_id, $voter_id
        ));
        
        if ($existing_vote) {
            $result = $wpdb->update(
                $votes_table,
                $vote_data,
                ['id' => $existing_vote],
                ['%d', '%d', '%s', '%s', '%f'],
                ['%d']
            );
        } else {
            $result = $wpdb->insert($votes_table, $vote_data);
        }
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao registrar voto');
        }
        
        // Verificar se consulta deve ser finalizada
        $this->check_consultation_completion($consultation_id);
        
        // Disparar hook
        do_action('ql_consultation_vote_cast', $consultation_id, $voter_id, $vote);
        
        return true;
    }
    
    /**
     * Verificar se pode atribuir responsabilidade
     */
    private function can_assign_responsibility($user_id, $responsibility_key, $context_type, $context_id) {
        // Administradores podem atribuir qualquer responsabilidade
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        
        // Verificar se tem responsabilidade de gestão no contexto
        if ($this->has_responsibility($user_id, 'gestao', $context_type, $context_id)) {
            return true;
        }
        
        // Verificar se é guia da trilha/projeto
        if ($context_type === 'project' && $this->has_responsibility($user_id, 'guia', $context_type, $context_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar se pode transferir responsabilidade
     */
    private function can_transfer_responsibility($user_id, $responsibility) {
        // Próprio responsável pode transferir
        if ($responsibility->assignee_type === 'person' && $responsibility->assignee_id == $user_id) {
            return true;
        }
        
        // Quem atribuiu pode transferir
        if ($responsibility->assigned_by == $user_id) {
            return true;
        }
        
        // Gestão pode transferir
        if ($this->has_responsibility($user_id, 'gestao', $responsibility->context_type, $responsibility->context_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verificar se tem responsabilidade específica
     */
    public function has_responsibility($user_id, $responsibility_key, $context_type = null, $context_id = null) {
        global $wpdb;
        
        $responsibilities_table = $wpdb->prefix . 'ql_responsibilities';
        
        $where_conditions = [
            "responsibility_key = %s",
            "assignee_type = 'person'",
            "assignee_id = %d",
            "status = 'active'"
        ];
        $where_values = [$responsibility_key, $user_id];
        
        if ($context_type) {
            $where_conditions[] = "context_type = %s";
            $where_values[] = $context_type;
            
            if ($context_id) {
                $where_conditions[] = "context_id = %d";
                $where_values[] = $context_id;
            }
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $responsibilities_table WHERE $where_clause",
            $where_values
        ));
        
        return $count > 0;
    }
    
    /**
     * Verificar se pode votar em consulta
     */
    private function can_vote_consultation($user_id, $consultation) {
        $target_instances = json_decode($consultation->target_instances, true);
        
        // Verificar se pertence às instâncias consultadas
        foreach ($target_instances as $instance) {
            if ($this->user_belongs_to_instance($user_id, $instance['type'], $instance['id'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calcular peso do voto baseado no papel
     */
    private function calculate_vote_weight($user_id, $consultation) {
        // Por padrão, todos os votos têm peso 1.0
        // Pode ser ajustado baseado em responsabilidades específicas
        return 1.0;
    }
    
    /**
     * Verificar se usuário pertence à instância
     */
    private function user_belongs_to_instance($user_id, $instance_type, $instance_id) {
        if (!class_exists('QL_Instances')) {
            return false;
        }
        
        $instances = QL_Instances::get_instance();
        $members = $instances->get_instance_members($instance_id);
        
        foreach ($members as $member) {
            if ($member->user_id == $user_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar se consulta deve ser finalizada
     */
    private function check_consultation_completion($consultation_id) {
        global $wpdb;
        
        $consultations_table = $wpdb->prefix . 'ql_consultations';
        $votes_table = $wpdb->prefix . 'ql_consultation_votes';
        
        $consultation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $consultations_table WHERE id = %d",
            $consultation_id
        ));
        
        if (!$consultation || $consultation->status !== 'open') {
            return;
        }
        
        // Contar votos
        $vote_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_votes,
                SUM(CASE WHEN vote = 'approve' THEN vote_weight ELSE 0 END) as approve_weight,
                SUM(CASE WHEN vote = 'reject' THEN vote_weight ELSE 0 END) as reject_weight,
                SUM(vote_weight) as total_weight
             FROM $votes_table WHERE consultation_id = %d",
            $consultation_id
        ));
        
        // Calcular total de participantes elegíveis
        $target_instances = json_decode($consultation->target_instances, true);
        $total_eligible = $this->count_eligible_participants($target_instances);
        
        if ($total_eligible == 0) return;
        
        $participation_rate = ($vote_stats->total_votes / $total_eligible) * 100;
        $approval_rate = $vote_stats->total_weight > 0 ? 
            ($vote_stats->approve_weight / $vote_stats->total_weight) * 100 : 0;
        
        $new_status = 'open';
        
        // Verificar se atingiu quórum e prazo
        $deadline_passed = $consultation->end_date && strtotime($consultation->end_date) < time();
        
        if ($participation_rate >= $consultation->min_quorum || $deadline_passed) {
            if ($approval_rate >= $consultation->approval_threshold) {
                $new_status = 'approved';
            } else {
                $new_status = 'rejected';
            }
        }
        
        if ($new_status !== 'open') {
            $wpdb->update(
                $consultations_table,
                ['status' => $new_status],
                ['id' => $consultation_id],
                ['%s'],
                ['%d']
            );
            
            // Criar registro de decisão
            $this->create_decision_record($consultation);
            
            // Disparar hook
            do_action('ql_consultation_completed', $consultation_id, $new_status);
        }
    }
    
    /**
     * Contar participantes elegíveis
     */
    private function count_eligible_participants($target_instances) {
        $total = 0;
        
        if (!class_exists('QL_Instances')) {
            return $total;
        }
        
        $instances = QL_Instances::get_instance();
        
        foreach ($target_instances as $instance) {
            $members = $instances->get_instance_members($instance['id']);
            $total += count($members);
        }
        
        return $total;
    }
    
    /**
     * Criar registro de decisão
     */
    private function create_decision_record($consultation) {
        global $wpdb;
        
        $decisions_table = $wpdb->prefix . 'ql_decisions';
        
        $result = $consultation->status === 'approved' ? 'approved' : 'rejected';
        
        $data = [
            'consultation_id' => $consultation->id,
            'decision_type' => 'consultation',
            'title' => $consultation->title,
            'description' => $consultation->description,
            'decided_by' => $consultation->created_by,
            'decision_result' => $result,
            'affected_instances' => $consultation->target_instances,
            'metadata' => json_encode([
                'consultation_type' => $consultation->consultation_type,
                'action_proposed' => $consultation->action_proposed
            ])
        ];
        
        $wpdb->insert($decisions_table, $data);
        
        $decision_id = $wpdb->insert_id;
        
        // Disparar hook
        do_action('ql_decision_created', $decision_id, $result);
        
        return $decision_id;
    }
    
    /**
     * Notificar participantes da consulta
     */
    private function notify_consultation_participants($consultation_id, $target_instances) {
        // Implementar notificações por email, dashboard, etc.
        do_action('ql_notify_consultation_participants', $consultation_id, $target_instances);
    }
    
    /**
     * Obter responsabilidades ativas
     */
    public function get_active_responsibilities($filters = []) {
        global $wpdb;
        
        $responsibilities_table = $wpdb->prefix . 'ql_responsibilities';
        
        $where_conditions = ["status = 'active'"];
        $where_values = [];
        
        if (isset($filters['responsibility_key'])) {
            $where_conditions[] = "responsibility_key = %s";
            $where_values[] = $filters['responsibility_key'];
        }
        
        if (isset($filters['assignee_type'])) {
            $where_conditions[] = "assignee_type = %s";
            $where_values[] = $filters['assignee_type'];
        }
        
        if (isset($filters['assignee_id'])) {
            $where_conditions[] = "assignee_id = %d";
            $where_values[] = $filters['assignee_id'];
        }
        
        if (isset($filters['context_type'])) {
            $where_conditions[] = "context_type = %s";
            $where_values[] = $filters['context_type'];
        }
        
        if (isset($filters['context_id'])) {
            $where_conditions[] = "context_id = %d";
            $where_values[] = $filters['context_id'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT * FROM $responsibilities_table WHERE $where_clause ORDER BY assigned_at DESC";
        
        if (empty($where_values)) {
            return $wpdb->get_results($query);
        } else {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        }
    }
    
    /**
     * Obter definições de responsabilidades
     */
    public static function get_responsibility_definitions() {
        return self::RESPONSIBILITIES;
    }
    
    /**
     * AJAX - Atribuir responsabilidade
     */
    public function ajax_assign_responsibility() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $responsibility_key = sanitize_text_field($_POST['responsibility_key']);
        $assignee_type = sanitize_text_field($_POST['assignee_type']);
        $assignee_id = intval($_POST['assignee_id']);
        $context_type = sanitize_text_field($_POST['context_type'] ?? 'global');
        $context_id = !empty($_POST['context_id']) ? intval($_POST['context_id']) : null;
        
        $result = $this->assign_responsibility($responsibility_key, $assignee_type, $assignee_id, $context_type, $context_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Responsabilidade atribuída com sucesso', 'id' => $result]);
        }
    }
    
    /**
     * AJAX - Transferir responsabilidade
     */
    public function ajax_transfer_responsibility() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $responsibility_id = intval($_POST['responsibility_id']);
        $new_assignee_type = sanitize_text_field($_POST['new_assignee_type']);
        $new_assignee_id = intval($_POST['new_assignee_id']);
        
        $result = $this->transfer_responsibility($responsibility_id, $new_assignee_type, $new_assignee_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Responsabilidade transferida com sucesso', 'new_id' => $result]);
        }
    }
    
    /**
     * AJAX - Criar consulta
     */
    public function ajax_create_consultation() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_textarea_field($_POST['description']);
        $action_proposed = sanitize_textarea_field($_POST['action_proposed']);
        $target_instances = json_decode(stripslashes($_POST['target_instances']), true);
        $consultation_type = sanitize_text_field($_POST['consultation_type'] ?? 'decision');
        
        $options = [];
        if (!empty($_POST['end_date'])) {
            $options['end_date'] = sanitize_text_field($_POST['end_date']);
        }
        if (!empty($_POST['min_quorum'])) {
            $options['min_quorum'] = intval($_POST['min_quorum']);
        }
        if (!empty($_POST['approval_threshold'])) {
            $options['approval_threshold'] = intval($_POST['approval_threshold']);
        }
        
        $result = $this->create_consultation($title, $description, $action_proposed, $target_instances, $consultation_type, $options);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Consulta criada com sucesso', 'consultation_id' => $result]);
        }
    }
    
    /**
     * AJAX - Votar em consulta
     */
    public function ajax_vote_consultation() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        $consultation_id = intval($_POST['consultation_id']);
        $vote = sanitize_text_field($_POST['vote']);
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');
        
        $result = $this->vote_consultation($consultation_id, $vote, $comment);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        } else {
            wp_send_json_success(['message' => 'Voto registrado com sucesso']);
        }
    }
    
    /**
     * Criar post público para consulta com formulário de votação
     */
    private function create_consultation_post($consultation_id, $consultation_data) {
        global $wpdb;
        
        // Obter dados completos da consulta
        $consultation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_consultations WHERE id = %d",
            $consultation_id
        ));
        
        if (!$consultation) {
            return false;
        }
        
        // Determinar categoria do projeto ou usar categoria padrão
        $project_category = $this->get_project_category_for_consultation($consultation);
        
        // Título do post
        $post_title = sprintf('Consulta: %s', $consultation->title);
        
        // Conteúdo do post
        $post_content = $this->generate_consultation_post_content($consultation);
        
        // Criar o post
        $post_data = [
            'post_title' => $post_title,
            'post_content' => $post_content,
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_author' => $consultation->created_by,
            'post_category' => $project_category ? [$project_category] : [get_option('default_category')]
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Criar formulário Ninja Forms se disponível
            $form_id = $this->create_consultation_voting_form($consultation_id, $post_id);
            
            // Atualizar consulta com IDs do post e formulário
            $wpdb->update(
                $wpdb->prefix . 'ql_consultations',
                [
                    'post_id' => $post_id,
                    'form_id' => $form_id
                ],
                ['id' => $consultation_id]
            );
            
            // Adicionar metadados ao post
            update_post_meta($post_id, 'ql_consultation_id', $consultation_id);
            update_post_meta($post_id, 'ql_consultation_type', 'consultation');
            update_post_meta($post_id, 'ql_voting_form_id', $form_id);
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Gerar conteúdo para o post da consulta
     */
    private function generate_consultation_post_content($consultation) {
        $content = '';
        
        // Informações básicas da consulta
        $content .= '<div class="ql-consultation-info">';
        $content .= '<h3>Informações da Consulta</h3>';
        $content .= '<p><strong>Tipo:</strong> ' . ucfirst($consultation->consultation_type) . '</p>';
        
        // Instâncias consultadas
        $target_instances = json_decode($consultation->target_instances, true);
        if (!empty($target_instances)) {
            $content .= '<p><strong>Instâncias Consultadas:</strong> ';
            $instance_names = [];
            foreach ($target_instances as $instance_id) {
                // Buscar nome da instância
                $instance = $this->get_instance_name($instance_id);
                if ($instance) {
                    $instance_names[] = $instance;
                }
            }
            $content .= implode(', ', $instance_names) . '</p>';
        }
        $content .= '</div>';
        
        // Descrição da consulta
        $content .= '<div class="ql-consultation-description">';
        $content .= '<h3>Descrição</h3>';
        $content .= '<p>' . nl2br(esc_html($consultation->description)) . '</p>';
        $content .= '</div>';
        
        // Ação proposta
        if (!empty($consultation->action_proposed)) {
            $content .= '<div class="ql-consultation-action">';
            $content .= '<h3>Ação Proposta</h3>';
            $content .= '<p>' . nl2br(esc_html($consultation->action_proposed)) . '</p>';
            $content .= '</div>';
        }
        
        // Instruções para votação
        $content .= '<div class="ql-voting-instructions">';
        $content .= '<h3>Como Participar</h3>';
        $content .= '<p>Esta consulta está aberta para participação da comunidade. Use o formulário abaixo para expressar sua posição:</p>';
        $content .= '<ul>';
        $content .= '<li><strong>👍 Apoio:</strong> Concordo com a proposta apresentada</li>';
        $content .= '<li><strong>🤔 Tenho Preocupações:</strong> Concordo com a direção mas tenho questões que gostaria de ver abordadas</li>';
        $content .= '<li><strong>✋ Bloqueio:</strong> Não posso apoiar esta proposta pelos motivos que vou explicar</li>';
        $content .= '</ul>';
        $content .= '<p><em>Lembre-se: no modelo de consenso, um bloqueio deve ser fundamentado e acompanhado de alternativas construtivas.</em></p>';
        $content .= '</div>';
        
        // Informações sobre prazos
        if ($consultation->end_date) {
            $content .= '<div class="ql-consultation-deadline">';
            $content .= '<h3>⏰ Prazo</h3>';
            $content .= '<p><strong>Esta consulta encerra em:</strong> ';
            $content .= date_i18n(get_option('date_format') . ' às ' . get_option('time_format'), strtotime($consultation->end_date));
            $content .= '</p>';
            $content .= '</div>';
        }
        
        return $content;
    }
    
    /**
     * Criar formulário Ninja Forms para votação na consulta
     */
    private function create_consultation_voting_form($consultation_id, $post_id) {
        // Verificar se Ninja Forms está disponível
        if (!function_exists('Ninja_Forms')) {
            return null;
        }
        
        try {
            // Criar formulário básico
            $form_data = [
                'title' => 'Votação - Consulta #' . $consultation_id,
                'settings' => [
                    'objectType' => 'Form',
                    'editActive' => '',
                    'title' => 'Votação - Consulta #' . $consultation_id,
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
                        ['label' => '👍 Apoio', 'value' => 'support', 'calc' => ''],
                        ['label' => '🤔 Tenho Preocupações', 'value' => 'concern', 'calc' => ''],
                        ['label' => '✋ Bloqueio', 'value' => 'block', 'calc' => '']
                    ]
                ],
                [
                    'objectType' => 'Field',
                    'order' => 2,
                    'type' => 'textarea',
                    'key' => 'comment',
                    'label' => 'Comentário',
                    'required' => '1',
                    'placeholder' => 'Explique sua posição, especialmente se você tem preocupações ou está bloqueando'
                ],
                [
                    'objectType' => 'Field',
                    'order' => 3,
                    'type' => 'hidden',
                    'key' => 'consultation_id',
                    'default' => $consultation_id
                ],
                [
                    'objectType' => 'Field',
                    'order' => 4,
                    'type' => 'submit',
                    'key' => 'submit',
                    'label' => 'Enviar Participação',
                    'processing_label' => 'Enviando...'
                ]
            ];
            
            // Ações do formulário
            $actions = [
                [
                    'objectType' => 'Action',
                    'type' => 'save',
                    'label' => 'Salvar Participação',
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
                
                // Hook para processar submissões de consultas
                add_action('ninja_forms_after_submission', [$this, 'process_consultation_voting_submission']);
                
                return $form_id;
            }
            
        } catch (Exception $e) {
            error_log('QL Consultations - Erro ao criar formulário: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Processar submissão do formulário de votação de consulta
     */
    public function process_consultation_voting_submission($form_data) {
        $form_settings = $form_data['settings'];
        $form_fields = $form_data['fields'];
        
        // Verificar se é um formulário de consulta
        $consultation_id = null;
        $vote_type = null;
        $comment = '';
        
        foreach ($form_fields as $field) {
            if ($field['key'] === 'consultation_id') {
                $consultation_id = intval($field['value']);
            } elseif ($field['key'] === 'vote_type') {
                $vote_type = sanitize_text_field($field['value']);
            } elseif ($field['key'] === 'comment') {
                $comment = sanitize_textarea_field($field['value']);
            }
        }
        
        if ($consultation_id && $vote_type) {
            $this->vote_consultation($consultation_id, $vote_type, $comment);
        }
    }
    
    /**
     * Auxiliares para criação de posts
     */
    private function get_project_category_for_consultation($consultation) {
        // Implementar lógica para determinar categoria baseada no contexto da consulta
        // Por enquanto, usar categoria padrão
        return get_option('default_category');
    }
    
    private function get_instance_name($instance_id) {
        global $wpdb;
        
        $instance = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}ql_instances WHERE id = %d",
            $instance_id
        ));
        
        return $instance ? $instance->name : 'Instância #' . $instance_id;
    }
}