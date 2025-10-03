<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para formação automática de equipes baseada em dados do Moodle
 */
class QL_Team_Formation {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_ajax_ql_form_teams', [$this, 'ajax_form_teams']);
        add_action('wp_ajax_ql_sync_moodle_groups', [$this, 'ajax_sync_moodle_groups']);
    }
    
    public function init() {
        // Adicionar hooks para formação automática quando projeto for criado
        add_action('ql_project_created_from_moodle', [$this, 'auto_form_teams'], 10, 2);
    }
    
    /**
     * Formar equipes automaticamente quando projeto for criado do Moodle
     */
    public function auto_form_teams($project_id, $moodle_course_data) {
        if (!$project_id || !$moodle_course_data) {
            return false;
        }
        
        try {
            // Buscar participantes do curso no Moodle
            $course_participants = $this->get_moodle_course_participants($moodle_course_data['course_id']);
            
            if (empty($course_participants)) {
                error_log("QL Team Formation: Nenhum participante encontrado para o curso {$moodle_course_data['course_id']}");
                return false;
            }
            
            // Sincronizar usuários do Moodle com WordPress
            $synced_users = $this->sync_moodle_users($course_participants);
            
            // Formar equipes baseado no tipo de trilha
            $team_strategy = $this->determine_team_strategy($moodle_course_data);
            $teams = $this->create_teams($synced_users, $team_strategy);
            
            // Adicionar membros ao projeto
            $this->add_teams_to_project($project_id, $teams);
            
            // Log de sucesso
            error_log("QL Team Formation: Equipes formadas com sucesso para projeto {$project_id}");
            
            return true;
            
        } catch (Exception $e) {
            error_log("QL Team Formation Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar participantes de um curso no Moodle
     */
    private function get_moodle_course_participants($course_id) {
        $moodle_settings = QL_Config::get_moodle_settings();
        $moodle_url = rtrim($moodle_settings['url'], '/');
        $token = $moodle_settings['token'];
        
        if (!$moodle_url || !$token) {
            throw new Exception('Configurações do Moodle não encontradas');
        }
        
        $params = [
            'wstoken' => $token,
            'wsfunction' => 'core_enrol_get_enrolled_users',
            'moodlewsrestformat' => 'json',
            'courseid' => $course_id
        ];
        
        $url = $moodle_url . '/webservice/rest/server.php?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            throw new Exception('Erro na conexão com Moodle: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['exception'])) {
            throw new Exception('Erro Moodle: ' . $data['message']);
        }
        
        return $data ?: [];
    }
    
    /**
     * Sincronizar usuários do Moodle com WordPress
     */
    private function sync_moodle_users($moodle_users) {
        $synced_users = [];
        
        foreach ($moodle_users as $moodle_user) {
            // Verificar se usuário já existe no WordPress
            $wp_user = get_user_by('email', $moodle_user['email']);
            
            if (!$wp_user) {
                // Criar usuário no WordPress
                $user_data = [
                    'user_login' => $this->generate_username($moodle_user),
                    'user_email' => $moodle_user['email'],
                    'display_name' => $moodle_user['fullname'],
                    'first_name' => $moodle_user['firstname'] ?? '',
                    'last_name' => $moodle_user['lastname'] ?? '',
                    'user_pass' => wp_generate_password(),
                    'role' => 'author' // Role padrão para participantes
                ];
                
                $user_id = wp_insert_user($user_data);
                
                if (is_wp_error($user_id)) {
                    error_log("Erro ao criar usuário: " . $user_id->get_error_message());
                    continue;
                }
                
                // Armazenar ID do Moodle como meta
                update_user_meta($user_id, 'moodle_user_id', $moodle_user['id']);
                
                $wp_user = get_user_by('id', $user_id);
            } else {
                // Atualizar meta do Moodle se necessário
                update_user_meta($wp_user->ID, 'moodle_user_id', $moodle_user['id']);
            }
            
            if ($wp_user) {
                $synced_users[] = [
                    'wp_user' => $wp_user,
                    'moodle_data' => $moodle_user,
                    'role' => $this->determine_user_role($moodle_user)
                ];
            }
        }
        
        return $synced_users;
    }
    
    /**
     * Determinar estratégia de formação de equipes baseada no tipo de trilha
     */
    private function determine_team_strategy($course_data) {
        $course_name = strtolower($course_data['fullname'] ?? '');
        $course_category = strtolower($course_data['categoryname'] ?? '');
        
        // Detectar tipo de trilha
        if (strpos($course_name, 'pesquisa') !== false || strpos($course_category, 'research') !== false) {
            return [
                'type' => 'pesquisa',
                'team_size' => [3, 5], // Equipes de 3-5 pessoas
                'strategy' => 'mixed_skills', // Misturar habilidades
                'roles' => ['coordinator', 'researcher', 'analyst', 'writer']
            ];
        } elseif (strpos($course_name, 'criação') !== false || strpos($course_category, 'creation') !== false) {
            return [
                'type' => 'criacao',
                'team_size' => [4, 6], // Equipes de 4-6 pessoas
                'strategy' => 'complementary', // Habilidades complementares
                'roles' => ['coordinator', 'designer', 'developer', 'tester', 'content_creator']
            ];
        } else {
            // Trilha de aprendizagem - grupos menores
            return [
                'type' => 'aprendizagem',
                'team_size' => [2, 4], // Equipes de 2-4 pessoas
                'strategy' => 'peer_learning', // Aprendizado entre pares
                'roles' => ['coordinator', 'member']
            ];
        }
    }
    
    /**
     * Criar equipes baseado na estratégia
     */
    private function create_teams($users, $strategy) {
        $teams = [];
        $total_users = count($users);
        
        if ($total_users === 0) {
            return $teams;
        }
        
        // Calcular número ideal de equipes
        $target_team_size = ($strategy['team_size'][0] + $strategy['team_size'][1]) / 2;
        $num_teams = max(1, round($total_users / $target_team_size));
        
        // Separar coordenadores (professores/facilitadores) dos participantes
        $coordinators = array_filter($users, function($user) {
            return $user['role'] === 'coordinator';
        });
        
        $participants = array_filter($users, function($user) {
            return $user['role'] !== 'coordinator';
        });
        
        // Embaralhar participantes para distribuição aleatória
        shuffle($participants);
        
        // Criar equipes
        for ($i = 0; $i < $num_teams; $i++) {
            $team = [
                'name' => $this->generate_team_name($strategy['type'], $i + 1),
                'type' => $strategy['type'],
                'members' => []
            ];
            
            // Adicionar um coordenador se disponível
            if (!empty($coordinators)) {
                $team['members'][] = array_shift($coordinators);
            }
            
            $teams[] = $team;
        }
        
        // Distribuir participantes entre as equipes
        $team_index = 0;
        foreach ($participants as $participant) {
            $teams[$team_index]['members'][] = $participant;
            $team_index = ($team_index + 1) % $num_teams;
        }
        
        // Remover equipes vazias
        $teams = array_filter($teams, function($team) {
            return !empty($team['members']);
        });
        
        return array_values($teams);
    }
    
    /**
     * Adicionar equipes ao projeto
     */
    private function add_teams_to_project($project_id, $teams) {
        global $wpdb;
        
        foreach ($teams as $team_index => $team) {
            foreach ($team['members'] as $member_index => $member) {
                $role = $member['role'];
                
                // Se é o primeiro membro da equipe, tornar coordenador
                if ($member_index === 0 && $role === 'member') {
                    $role = 'coordinator';
                }
                
                $member_data = [
                    'project_id' => $project_id,
                    'user_id' => $member['wp_user']->ID,
                    'role' => $role,
                    'permissions' => json_encode([
                        'team_id' => $team_index + 1,
                        'team_name' => $team['name'],
                        'can_create_tasks' => true,
                        'can_edit_tasks' => true,
                        'can_delete_tasks' => $role === 'coordinator'
                    ])
                ];
                
                $wpdb->insert(
                    $wpdb->prefix . 'ql_project_members',
                    $member_data
                );
            }
        }
        
        // Salvar informações das equipes como meta do projeto
        $team_summary = array_map(function($team) {
            return [
                'name' => $team['name'],
                'type' => $team['type'],
                'member_count' => count($team['members']),
                'members' => array_map(function($member) {
                    return [
                        'user_id' => $member['wp_user']->ID,
                        'name' => $member['wp_user']->display_name,
                        'role' => $member['role']
                    ];
                }, $team['members'])
            ];
        }, $teams);
        
        update_post_meta($project_id, '_ql_teams_structure', $team_summary);
    }
    
    /**
     * Determinar role do usuário baseado nos dados do Moodle
     */
    private function determine_user_role($moodle_user) {
        // Verificar se é professor/facilitador baseado no role do Moodle
        $roles = $moodle_user['roles'] ?? [];
        
        foreach ($roles as $role) {
            if (in_array(strtolower($role['shortname']), ['teacher', 'editingteacher', 'manager', 'coursecreator'])) {
                return 'coordinator';
            }
        }
        
        return 'member';
    }
    
    /**
     * Gerar nome único para equipe
     */
    private function generate_team_name($type, $number) {
        $prefixes = [
            'pesquisa' => ['Lab', 'Núcleo', 'Grupo', 'Squad'],
            'criacao' => ['Studio', 'Coletivo', 'Atelier', 'Hub'],
            'aprendizagem' => ['Turma', 'Grupo', 'Círculo', 'Time']
        ];
        
        $prefix = $prefixes[$type][array_rand($prefixes[$type])];
        return $prefix . ' ' . $number;
    }
    
    /**
     * Gerar username único
     */
    private function generate_username($moodle_user) {
        $base = sanitize_user($moodle_user['username'] ?? strtolower($moodle_user['firstname'] . $moodle_user['lastname']));
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * AJAX: Formar equipes manualmente
     */
    public function ajax_form_teams() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão insuficiente');
        }
        
        $project_id = intval($_POST['project_id'] ?? 0);
        $team_strategy = sanitize_text_field($_POST['strategy'] ?? 'balanced');
        
        if (!$project_id) {
            wp_send_json_error('ID do projeto inválido');
        }
        
        try {
            // Buscar membros atuais do projeto
            global $wpdb;
            $members = $wpdb->get_results($wpdb->prepare(
                "SELECT pm.*, u.display_name, u.user_email 
                 FROM {$wpdb->prefix}ql_project_members pm 
                 JOIN {$wpdb->prefix}users u ON pm.user_id = u.ID 
                 WHERE pm.project_id = %d",
                $project_id
            ));
            
            if (empty($members)) {
                wp_send_json_error('Nenhum membro encontrado no projeto');
            }
            
            // Converter para formato esperado
            $users = array_map(function($member) {
                return [
                    'wp_user' => (object)[
                        'ID' => $member->user_id,
                        'display_name' => $member->display_name
                    ],
                    'role' => $member->role
                ];
            }, $members);
            
            // Determinar estratégia baseada no tipo escolhido
            $strategies = [
                'balanced' => [
                    'type' => 'balanced',
                    'team_size' => [3, 5],
                    'strategy' => 'balanced'
                ],
                'small' => [
                    'type' => 'small',
                    'team_size' => [2, 3],
                    'strategy' => 'small_groups'
                ],
                'large' => [
                    'type' => 'large',
                    'team_size' => [5, 8],
                    'strategy' => 'large_groups'
                ]
            ];
            
            $strategy = $strategies[$team_strategy] ?? $strategies['balanced'];
            
            // Remover membros atuais (exceto coordenadores principais)
            $wpdb->delete(
                $wpdb->prefix . 'ql_project_members',
                ['project_id' => $project_id, 'role' => 'member']
            );
            
            // Criar novas equipes
            $teams = $this->create_teams($users, $strategy);
            $this->add_teams_to_project($project_id, $teams);
            
            wp_send_json_success([
                'message' => 'Equipes formadas com sucesso!',
                'teams_count' => count($teams),
                'total_members' => count($users)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Erro ao formar equipes: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX: Sincronizar grupos do Moodle
     */
    public function ajax_sync_moodle_groups() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão insuficiente');
        }
        
        $course_id = intval($_POST['course_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        
        if (!$course_id || !$project_id) {
            wp_send_json_error('IDs inválidos');
        }
        
        try {
            $course_data = ['course_id' => $course_id, 'fullname' => 'Sincronização Manual'];
            $result = $this->auto_form_teams($project_id, $course_data);
            
            if ($result) {
                wp_send_json_success('Sincronização concluída com sucesso!');
            } else {
                wp_send_json_error('Erro na sincronização');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
}