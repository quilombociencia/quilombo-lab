<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de integração com o Plugin Gestão Coletiva
 * Responsável por sincronizar dados e funcionalidades entre os dois plugins
 */
class QL_GC_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hooks de integração com Gestão Coletiva
        add_action('init', [$this, 'setup_integration_hooks']);
        add_action('wp_loaded', [$this, 'verify_gc_compatibility']);
    }
    
    /**
     * Configurar hooks de integração
     */
    public function setup_integration_hooks() {
        if (!$this->is_gc_active()) {
            return;
        }
        
        // REMOVIDO: Hooks quando projeto GC é criado/atualizado
        // O Laboratório é agora a fonte única de dados, não sincroniza DE GC
        // add_action('gc_projeto_criado', [$this, 'on_gc_project_created'], 10, 2);
        // add_action('gc_projeto_atualizado', [$this, 'on_gc_project_updated'], 10, 2);
        // add_action('gc_projeto_arquivado', [$this, 'on_gc_project_archived'], 10, 1);
        
        // Hooks quando trilha é criada/sincronizada
        add_action('gc_trilha_sincronizada', [$this, 'on_trilha_synchronized'], 10, 2);
        
        // Hooks para lançamentos financeiros
        add_action('gc_lancamento_efetivado', [$this, 'on_gc_expense_confirmed'], 10, 2);
        add_action('gc_lancamento_cancelado', [$this, 'on_gc_expense_cancelled'], 10, 2);
        
        // Hooks para membros do projeto
        add_action('gc_membro_adicionado', [$this, 'on_gc_member_added'], 10, 3);
        add_action('gc_membro_removido', [$this, 'on_gc_member_removed'], 10, 3);
        
        // API endpoints customizados
        add_action('rest_api_init', [$this, 'register_integration_endpoints']);
        
        // Hooks PARA sincronizar projetos QL PARA GC (unidirecional)
        add_action('ql_project_created', [$this, 'sync_to_gc_on_create'], 10, 2);
        add_action('ql_project_updated', [$this, 'sync_to_gc_on_update'], 10, 2);
        add_action('ql_project_archived', [$this, 'sync_to_gc_on_archive'], 10, 1);
    }
    
    /**
     * Verificar compatibilidade com Gestão Coletiva (opcional)
     */
    public function verify_gc_compatibility() {
        if (!$this->is_gc_active()) {
            // GC não está ativo - QL funciona independentemente, apenas log para debug
            error_log('QL: Gestão Coletiva não está ativa. QL funcionando independentemente.');
            return;
        }
        
        // Verificar versão mínima - apenas log, não bloquear
        if (!$this->is_gc_compatible_version()) {
            error_log('QL: Gestão Coletiva versão incompatível detectada. Funcionalidades GC limitadas.');
            return;
        }
        
        // Verificar se tabelas existem - não é mais obrigatório
        if (!$this->verify_gc_tables()) {
            if (current_user_can('manage_options')) {
                add_action('admin_notices', [$this, 'gc_tables_optional_notice']);
            }
            // Continuar funcionando mesmo sem todas as tabelas
        }
        
        error_log('Quilombo Laboratório: Plugin funcionando ' . ($this->verify_gc_tables() ? 'com integração GC completa' : 'de forma independente'));
    }
    
    /**
     * Verificar se Gestão Coletiva está ativo
     */
    private function is_gc_active() {
        return function_exists('gestao_coletiva_version') || class_exists('GestaoColetiva');
    }
    
    /**
     * Verificar versão compatível
     */
    private function is_gc_compatible_version() {
        if (function_exists('gestao_coletiva_version')) {
            return version_compare(gestao_coletiva_version(), '2.0.0', '>=');
        }
        
        // Verificar se é uma versão recente baseada na constante
        if (defined('GC_VERSION')) {
            return version_compare(GC_VERSION, '2.0.0', '>=');
        }
        
        return true; // Assumir compatibilidade se não conseguir verificar
    }
    
    /**
     * Verificar se tabelas do GC existem
     */
    private function verify_gc_tables() {
        global $wpdb;
        
        $required_tables = [
            $wpdb->prefix . 'gc_projetos',
            $wpdb->prefix . 'gc_lancamentos'
        ];
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Quando projeto GC é criado
     */
    public function on_gc_project_created($projeto_id, $projeto_data) {
        try {
            // Verificar se já existe projeto do laboratório
            $existing = $this->get_ql_project_by_gc_id($projeto_id);
            if ($existing) {
                error_log("Quilombo Laboratório: Projeto QL já existe para GC projeto {$projeto_id}");
                return;
            }
            
            // Criar projeto do laboratório
            $ql_project_id = $this->create_ql_project_from_gc($projeto_id, $projeto_data);
            
            if ($ql_project_id) {
                error_log("Quilombo Laboratório: Projeto {$ql_project_id} criado para GC projeto {$projeto_id}");
                
                // Disparar hook para outras integrações
                do_action('ql_project_created_from_gc', $ql_project_id, $projeto_id, $projeto_data);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao criar projeto QL: " . $e->getMessage());
        }
    }
    
    /**
     * Quando projeto GC é atualizado
     */
    public function on_gc_project_updated($projeto_id, $projeto_data) {
        try {
            $ql_project = $this->get_ql_project_by_gc_id($projeto_id);
            
            if ($ql_project) {
                $this->sync_ql_project_from_gc($ql_project->id, $projeto_data);
                error_log("Quilombo Laboratório: Projeto {$ql_project->id} sincronizado");
            }
            
        } catch (Exception $e) {
            error_log("Erro ao sincronizar projeto QL: " . $e->getMessage());
        }
    }
    
    /**
     * Quando projeto GC é arquivado
     */
    public function on_gc_project_archived($projeto_id) {
        try {
            $ql_project = $this->get_ql_project_by_gc_id($projeto_id);
            
            if ($ql_project) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'ql_projects',
                    ['status' => 'archived'],
                    ['id' => $ql_project->id]
                );
                
                error_log("Quilombo Laboratório: Projeto {$ql_project->id} arquivado");
            }
            
        } catch (Exception $e) {
            error_log("Erro ao arquivar projeto QL: " . $e->getMessage());
        }
    }
    
    /**
     * Quando trilha é sincronizada do Moodle
     */
    public function on_trilha_synchronized($trilha_id, $trilha_data) {
        try {
            // Verificar se trilha tem projeto GC
            if (class_exists('GC_Trilha')) {
                $projeto_gc = GC_Trilha::get_projeto($trilha_id);
                
                if ($projeto_gc) {
                    // Criar ou atualizar projeto QL
                    $this->create_or_update_ql_from_trilha($projeto_gc->id, $trilha_data);
                }
            }
            
        } catch (Exception $e) {
            error_log("Erro ao sincronizar trilha: " . $e->getMessage());
        }
    }
    
    /**
     * Quando lançamento é efetivado (despesa confirmada)
     */
    public function on_gc_expense_confirmed($lancamento_id, $lancamento_data) {
        try {
            // Se tem kanboard_task_id, atualizar custo real da tarefa
            if (!empty($lancamento_data['kanboard_task_id'])) {
                $this->update_task_actual_cost($lancamento_data['kanboard_task_id'], $lancamento_data['valor']);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar custo da tarefa: " . $e->getMessage());
        }
    }
    
    /**
     * Criar projeto QL a partir de projeto GC
     */
    private function create_ql_project_from_gc($gc_projeto_id, $projeto_data) {
        global $wpdb;
        
        // Determinar tipo de template baseado na trilha
        $template_type = $this->determine_template_type($projeto_data);
        
        // Dados do projeto QL
        $ql_data = [
            'gc_projeto_id' => $gc_projeto_id,
            'name' => $projeto_data['nome'],
            'slug' => $projeto_data['slug'] . '-lab',
            'description' => $projeto_data['descricao'] ?? '',
            'status' => $this->map_gc_status_to_ql($projeto_data['status']),
            'visibility' => $this->map_gc_visibility_to_ql($projeto_data['visibilidade']),
            'owner_id' => $projeto_data['criador_id'],
            'color' => $this->generate_project_color($template_type),
            'settings' => json_encode([
                'template_type' => $template_type,
                'auto_sync_gc' => true,
                'financial_integration' => true
            ]),
            'moodle_course_id' => $projeto_data['trilha_id'] ?? null
        ];
        
        // Inserir projeto
        $result = $wpdb->insert($wpdb->prefix . 'ql_projects', $ql_data);
        
        if ($result === false) {
            throw new Exception('Erro ao inserir projeto QL no banco de dados');
        }
        
        $ql_project_id = $wpdb->insert_id;
        
        // Criar estrutura básica (board, colunas) baseada no template
        $this->create_project_structure($ql_project_id, $template_type);
        
        // Sincronizar membros se existirem
        $this->sync_project_members($ql_project_id, $gc_projeto_id);
        
        return $ql_project_id;
    }
    
    /**
     * Determinar tipo de template baseado nos dados do projeto
     */
    private function determine_template_type($projeto_data) {
        // Se tem trilha_id, buscar tipo da trilha
        if (!empty($projeto_data['trilha_id']) && class_exists('GC_Trilha')) {
            $trilha = GC_Trilha::get_by_id($projeto_data['trilha_id']);
            if ($trilha && !empty($trilha->tipo)) {
                return 'trilha_' . $trilha->tipo;
            }
        }
        
        // Tentar determinar pelo nome
        $nome_lower = strtolower($projeto_data['nome']);
        if (strpos($nome_lower, 'pesquisa') !== false) {
            return 'trilha_pesquisa';
        }
        if (strpos($nome_lower, 'criação') !== false || strpos($nome_lower, 'criacao') !== false) {
            return 'trilha_criacao';
        }
        
        // Padrão
        return 'trilha_aprendizagem';
    }
    
    /**
     * Criar estrutura do projeto baseada no template
     */
    private function create_project_structure($project_id, $template_type) {
        global $wpdb;
        
        // Buscar template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT template_data FROM {$wpdb->prefix}ql_project_templates WHERE template_type = %s AND is_active = 1 LIMIT 1",
            $template_type
        ));
        
        if (!$template) {
            // Fallback para template básico
            $template = $wpdb->get_row(
                "SELECT template_data FROM {$wpdb->prefix}ql_project_templates WHERE template_type = 'trilha_aprendizagem' AND is_active = 1 LIMIT 1"
            );
        }
        
        if (!$template) {
            error_log("Nenhum template encontrado para {$template_type}");
            return;
        }
        
        $template_data = json_decode($template->template_data, true);
        
        // Criar boards
        foreach ($template_data['boards'] as $board_data) {
            $board_id = $wpdb->insert(
                $wpdb->prefix . 'ql_boards',
                [
                    'project_id' => $project_id,
                    'name' => $board_data['name'],
                    'board_type' => $board_data['type'],
                    'is_default' => $board_data['is_default'] ?? false
                ]
            );
            
            if ($board_id) {
                $board_id = $wpdb->insert_id;
                
                // Criar colunas
                foreach ($template_data['columns'] as $column_data) {
                    $wpdb->insert(
                        $wpdb->prefix . 'ql_columns',
                        [
                            'board_id' => $board_id,
                            'name' => $column_data['name'],
                            'position' => $column_data['position'],
                            'color' => $column_data['color']
                        ]
                    );
                }
                
                // Criar tarefas padrão se existirem
                if (!empty($template_data['default_tasks'])) {
                    $this->create_default_tasks($project_id, $board_id, $template_data['default_tasks']);
                }
            }
        }
        
        error_log("Estrutura criada para projeto {$project_id} usando template {$template_type}");
    }
    
    /**
     * Criar tarefas padrão do template
     */
    private function create_default_tasks($project_id, $board_id, $default_tasks) {
        global $wpdb;
        
        // Buscar colunas do board
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT id, position FROM {$wpdb->prefix}ql_columns WHERE board_id = %d ORDER BY position",
            $board_id
        ));
        
        if (empty($columns)) {
            return;
        }
        
        $columns_by_position = [];
        foreach ($columns as $column) {
            $columns_by_position[$column->position] = $column->id;
        }
        
        // Criar tarefas
        foreach ($default_tasks as $task_data) {
            $column_id = $columns_by_position[$task_data['column']] ?? $columns[0]->id;
            
            $task_number = $this->generate_task_number($project_id);
            
            $wpdb->insert(
                $wpdb->prefix . 'ql_tasks',
                [
                    'project_id' => $project_id,
                    'board_id' => $board_id,
                    'column_id' => $column_id,
                    'title' => $task_data['title'],
                    'task_number' => $task_number,
                    'creator_id' => QL_Config::get_current_or_default_user_id(),
                    'status' => 'open',
                    'priority' => 'normal'
                ]
            );
        }
    }
    
    /**
     * Gerar número único da tarefa
     */
    private function generate_task_number($project_id) {
        global $wpdb;
        
        $prefix = QL_Database::get_setting('task_number_prefix', 'QL');
        
        // Buscar último número para este projeto
        $last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING(task_number, %d) AS UNSIGNED)) 
             FROM {$wpdb->prefix}ql_tasks 
             WHERE project_id = %d AND task_number LIKE %s",
            strlen($prefix) + 2, // +1 para o hífen +1 para começar após
            $project_id,
            $prefix . '-%'
        ));
        
        $next_number = ($last_number ?: 0) + 1;
        
        return sprintf('%s-%03d', $prefix, $next_number);
    }
    
    /**
     * Sincronizar membros do projeto
     */
    private function sync_project_members($ql_project_id, $gc_projeto_id) {
        if (!class_exists('GC_Projeto')) {
            return;
        }
        
        $gc_members = GC_Projeto::get_members($gc_projeto_id);
        
        foreach ($gc_members as $member) {
            $this->add_ql_project_member(
                $ql_project_id,
                $member->usuario_id,
                $this->map_gc_role_to_ql($member->role)
            );
        }
    }
    
    /**
     * Adicionar membro ao projeto QL
     */
    private function add_ql_project_member($project_id, $user_id, $role) {
        global $wpdb;
        
        $wpdb->replace(
            $wpdb->prefix . 'ql_project_members',
            [
                'project_id' => $project_id,
                'user_id' => $user_id,
                'role' => $role,
                'is_active' => true
            ]
        );
    }
    
    /**
     * Mapear status GC para status QL
     */
    private function map_gc_status_to_ql($gc_status) {
        $mapping = [
            'ativo' => 'active',
            'rascunho' => 'on_hold',
            'pausado' => 'on_hold',
            'finalizado' => 'completed',
            'arquivado' => 'archived'
        ];
        
        return $mapping[$gc_status] ?? 'active';
    }
    
    /**
     * Mapear visibilidade GC para visibilidade QL
     */
    private function map_gc_visibility_to_ql($gc_visibility) {
        $mapping = [
            'publico' => 'public',
            'listado' => 'public',
            'privado' => 'private'
        ];
        
        return $mapping[$gc_visibility] ?? 'team';
    }
    
    /**
     * Mapear role GC para role QL
     */
    private function map_gc_role_to_ql($gc_role) {
        $mapping = [
            'visualizador' => 'viewer',
            'colaborador' => 'member',
            'gestor' => 'coordinator',
            'admin' => 'manager'
        ];
        
        return $mapping[$gc_role] ?? 'member';
    }
    
    /**
     * Gerar cor do projeto baseada no tipo
     */
    private function generate_project_color($template_type) {
        $colors = [
            'trilha_aprendizagem' => '#3498db',
            'trilha_pesquisa' => '#9b59b6',
            'trilha_criacao' => '#e67e22',
            'custom' => '#34495e'
        ];
        
        return $colors[$template_type] ?? $colors['custom'];
    }
    
    /**
     * Buscar projeto QL por ID do projeto GC
     */
    private function get_ql_project_by_gc_id($gc_projeto_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE gc_projeto_id = %d",
            $gc_projeto_id
        ));
    }
    
    /**
     * Atualizar custo real de uma tarefa
     */
    private function update_task_actual_cost($kanboard_task_id, $cost) {
        global $wpdb;
        
        // O kanboard_task_id agora é o ID da tarefa QL
        $wpdb->update(
            $wpdb->prefix . 'ql_tasks',
            ['actual_cost' => $cost],
            ['id' => $kanboard_task_id]
        );
    }
    
    /**
     * Registrar endpoints de integração
     */
    public function register_integration_endpoints() {
        register_rest_route('quilombo-laboratorio/v1', '/gc-sync/project/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_project_endpoint'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Endpoint para forçar sincronização de projeto
     */
    public function sync_project_endpoint($request) {
        $gc_projeto_id = $request->get_param('id');
        
        try {
            if (class_exists('GC_Projeto')) {
                $projeto = GC_Projeto::get_by_id($gc_projeto_id);
                if ($projeto) {
                    $this->on_gc_project_updated($gc_projeto_id, (array) $projeto);
                    
                    return new WP_REST_Response([
                        'success' => true,
                        'message' => 'Projeto sincronizado com sucesso'
                    ]);
                }
            }
            
            return new WP_Error('project_not_found', 'Projeto não encontrado', ['status' => 404]);
            
        } catch (Exception $e) {
            return new WP_Error('sync_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * Notices administrativos
     */
    public function gc_not_active_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Quilombo Laboratório requer o Plugin Gestão Coletiva ativo para funcionar corretamente.', 'quilombo-laboratorio');
        echo '</p></div>';
    }
    
    public function gc_version_notice() {
        echo '<div class="notice notice-info"><p>';
        echo __('Para funcionalidades financeiras avançadas, instale o Plugin Gestão Coletiva versão 2.0.0 ou superior. O Quilombo Laboratório funciona independentemente.', 'quilombo-laboratorio');
        echo '</p></div>';
    }
    
    public function gc_tables_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Tabelas do Plugin Gestão Coletiva não encontradas. Verifique a instalação.', 'quilombo-laboratorio');
        echo '</p></div>';
    }
    
    public function gc_tables_optional_notice() {
        echo '<div class="notice notice-info"><p>';
        echo __('Plugin Gestão Coletiva detectado mas algumas tabelas não foram encontradas. Funcionalidades de integração podem estar limitadas.', 'quilombo-laboratorio');
        echo '</p></div>';
    }
    
    public function gc_optional_notice() {
        echo '<div class="notice notice-info"><p>';
        echo __('Quilombo Laboratório funcionando de forma independente. Para funcionalidades avançadas de gestão financeira, ative o Plugin Gestão Coletiva.', 'quilombo-laboratorio');
        echo '</p></div>';
    }
    
    /**
     * Obter dados financeiros do projeto via GC
     */
    public function get_project_financial_data($gc_projeto_id) {
        if (!class_exists('GC_Projeto')) {
            return null;
        }
        
        return GC_Projeto::get_financial_summary($gc_projeto_id);
    }
    
    /**
     * Sincronizar projeto QL para GC quando criado
     */
    public function sync_to_gc_on_create($ql_project_id, $project_data) {
        try {
            // Verificar se projeto GC já existe
            $existing_gc = $this->get_gc_project_by_ql_id($ql_project_id);
            if ($existing_gc) {
                error_log("Projeto GC já existe para QL projeto {$ql_project_id}");
                return;
            }
            
            // Criar projeto GC
            $gc_project_id = $this->create_gc_project_from_ql($ql_project_id, $project_data);
            
            if ($gc_project_id) {
                // Atualizar projeto QL com referência GC
                $this->link_projects($ql_project_id, $gc_project_id);
                error_log("Projeto GC {$gc_project_id} criado para QL projeto {$ql_project_id}");
                
                // Disparar hook para outras integrações
                do_action('ql_gc_project_synced', $ql_project_id, $gc_project_id);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao sincronizar projeto QL para GC: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar projeto QL para GC quando atualizado
     */
    public function sync_to_gc_on_update($ql_project_id, $project_data) {
        try {
            $gc_project = $this->get_gc_project_by_ql_id($ql_project_id);
            
            if ($gc_project) {
                $this->update_gc_project_from_ql($gc_project->id, $project_data);
                error_log("Projeto GC {$gc_project->id} atualizado do QL projeto {$ql_project_id}");
            } else {
                // Se não existe, criar
                $this->sync_to_gc_on_create($ql_project_id, $project_data);
            }
            
        } catch (Exception $e) {
            error_log("Erro ao atualizar projeto GC: " . $e->getMessage());
        }
    }
    
    /**
     * Sincronizar arquivamento para GC
     */
    public function sync_to_gc_on_archive($ql_project_id) {
        try {
            $gc_project = $this->get_gc_project_by_ql_id($ql_project_id);
            
            if ($gc_project) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'gc_projetos',
                    ['status' => 'arquivado'],
                    ['id' => $gc_project->id]
                );
                
                error_log("Projeto GC {$gc_project->id} arquivado");
            }
            
        } catch (Exception $e) {
            error_log("Erro ao arquivar projeto GC: " . $e->getMessage());
        }
    }
    
    /**
     * Buscar projeto GC por ID do projeto QL
     */
    private function get_gc_project_by_ql_id($ql_project_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}gc_projetos WHERE ql_project_id = %d",
            $ql_project_id
        ));
    }
    
    /**
     * Criar projeto GC a partir de projeto QL
     */
    private function create_gc_project_from_ql($ql_project_id, $project_data) {
        if (!class_exists('GC_Projeto')) {
            return false;
        }
        
        global $wpdb;
        
        // Buscar dados completos do projeto QL
        $ql_project = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_projects WHERE id = %d",
            $ql_project_id
        ));
        
        if (!$ql_project) {
            throw new Exception("Projeto QL {$ql_project_id} não encontrado");
        }
        
        $gc_data = [
            'nome' => $ql_project->name,
            'slug' => $ql_project->slug . '-gc',
            'descricao' => $ql_project->description,
            'status' => $this->map_ql_status_to_gc($ql_project->status),
            'visibilidade' => $this->map_ql_visibility_to_gc($ql_project->visibility),
            'objetivo_financeiro' => 0,
            'criador_id' => $ql_project->owner_id,
            'data_criacao' => $ql_project->created_at ?: current_time('mysql'),
            'data_atualizacao' => current_time('mysql'),
            'ql_project_id' => $ql_project_id // Guardar referência
        ];
        
        $result = $wpdb->insert($wpdb->prefix . 'gc_projetos', $gc_data);
        
        if ($result === false) {
            throw new Exception('Erro ao criar projeto GC: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Atualizar projeto GC a partir de projeto QL
     */
    private function update_gc_project_from_ql($gc_project_id, $project_data) {
        global $wpdb;
        
        $update_data = [
            'nome' => $project_data['name'],
            'descricao' => $project_data['description'],
            'status' => $this->map_ql_status_to_gc($project_data['status']),
            'data_atualizacao' => current_time('mysql')
        ];
        
        $result = $wpdb->update(
            $wpdb->prefix . 'gc_projetos',
            $update_data,
            ['id' => $gc_project_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            throw new Exception('Erro ao atualizar projeto GC: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Vincular projetos QL e GC
     */
    private function link_projects($ql_project_id, $gc_project_id) {
        global $wpdb;
        
        // Atualizar projeto QL com referência GC
        $wpdb->update(
            $wpdb->prefix . 'ql_projects',
            ['gc_projeto_id' => $gc_project_id],
            ['id' => $ql_project_id]
        );
        
        // Criar mapeamento na tabela de sincronização
        $wpdb->replace(
            $wpdb->prefix . 'gc_lab_sync_mappings',
            [
                'gc_project_id' => $gc_project_id,
                'ql_project_id' => $ql_project_id,
                'sync_direction' => 'lab_to_gc', // Unidirecional
                'last_sync' => current_time('mysql'),
                'sync_status' => 'active'
            ]
        );
    }
    
    /**
     * Mapear status QL para status GC
     */
    private function map_ql_status_to_gc($ql_status) {
        $mapping = [
            'active' => 'ativo',
            'on_hold' => 'pausado',
            'completed' => 'finalizado',
            'archived' => 'arquivado'
        ];
        
        return $mapping[$ql_status] ?? 'ativo';
    }
    
    /**
     * Mapear visibilidade QL para visibilidade GC
     */
    private function map_ql_visibility_to_gc($ql_visibility) {
        $mapping = [
            'public' => 'publico',
            'team' => 'listado',
            'private' => 'privado'
        ];
        
        return $mapping[$ql_visibility] ?? 'publico';
    }
    
    /**
     * Criar lançamento no GC a partir de tarefa QL
     */
    public function create_gc_expense_from_task($task_id, $cost, $description) {
        if (!class_exists('GC_Lancamento')) {
            return false;
        }
        
        global $wpdb;
        
        // Buscar dados da tarefa e projeto
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, p.gc_projeto_id 
             FROM {$wpdb->prefix}ql_tasks t
             JOIN {$wpdb->prefix}ql_projects p ON t.project_id = p.id
             WHERE t.id = %d",
            $task_id
        ));
        
        if (!$task) {
            return false;
        }
        
        try {
            $lancamento_data = [
                'projeto_id' => $task->gc_projeto_id,
                'tipo' => 'despesa',
                'descricao_curta' => 'Tarefa: ' . $task->title,
                'descricao_detalhada' => $description,
                'valor' => $cost,
                'autor_id' => $task->creator_id,
                'kanboard_task_id' => $task_id // Referência para nossa tarefa
            ];
            
            if (method_exists('GC_Lancamento', 'create')) {
                $lancamento_id = GC_Lancamento::create($lancamento_data);
            } else {
                error_log("Método GC_Lancamento::create não encontrado");
                return false;
            }
            
            if ($lancamento_id) {
                // Atualizar tarefa com referência ao lançamento
                $wpdb->update(
                    $wpdb->prefix . 'ql_tasks',
                    ['gc_lancamento_id' => $lancamento_id],
                    ['id' => $task_id]
                );
                
                return $lancamento_id;
            }
            
        } catch (Exception $e) {
            error_log("Erro ao criar lançamento GC: " . $e->getMessage());
        }
        
        return false;
    }
}