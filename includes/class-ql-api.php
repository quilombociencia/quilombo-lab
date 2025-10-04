<?php
/**
 * Classe para API REST do Quilombo Laboratório
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_API {
    
    private static $instance = null;
    private $namespace = 'quilombo-lab/v1';
    
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
     * Construtor
     */
    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Registrar todas as rotas da API
     */
    public function register_routes() {
        // Rotas dos Projetos
        register_rest_route($this->namespace, '/projects', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_projects'],
                'permission_callback' => [$this, 'check_permission']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_project'],
                'permission_callback' => [$this, 'check_create_permission'],
                'args' => $this->get_project_schema()
            ]
        ]);
        
        register_rest_route($this->namespace, '/projects/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_project'],
                'permission_callback' => [$this, 'check_project_access']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_project'],
                'permission_callback' => [$this, 'check_project_edit_permission'],
                'args' => $this->get_project_schema()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_project'],
                'permission_callback' => [$this, 'check_project_delete_permission']
            ]
        ]);
        
        // Rotas dos Boards
        register_rest_route($this->namespace, '/projects/(?P<project_id>\d+)/boards', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_project_boards'],
                'permission_callback' => [$this, 'check_project_access']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_board'],
                'permission_callback' => [$this, 'check_project_edit_permission'],
                'args' => $this->get_board_schema()
            ]
        ]);
        
        register_rest_route($this->namespace, '/boards/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_board'],
                'permission_callback' => [$this, 'check_board_access']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_board'],
                'permission_callback' => [$this, 'check_board_edit_permission'],
                'args' => $this->get_board_schema()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_board'],
                'permission_callback' => [$this, 'check_board_delete_permission']
            ]
        ]);
        
        // Rotas das Colunas
        register_rest_route($this->namespace, '/boards/(?P<board_id>\d+)/columns', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_board_columns'],
                'permission_callback' => [$this, 'check_board_access']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_column'],
                'permission_callback' => [$this, 'check_board_edit_permission'],
                'args' => $this->get_column_schema()
            ]
        ]);
        
        register_rest_route($this->namespace, '/columns/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_column'],
                'permission_callback' => [$this, 'check_column_edit_permission'],
                'args' => $this->get_column_schema()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_column'],
                'permission_callback' => [$this, 'check_column_delete_permission']
            ]
        ]);
        
        // Rotas das Tarefas
        register_rest_route($this->namespace, '/boards/(?P<board_id>\d+)/tasks', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_board_tasks'],
                'permission_callback' => [$this, 'check_board_access']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_task'],
                'permission_callback' => [$this, 'check_task_create_permission'],
                'args' => $this->get_task_schema()
            ]
        ]);
        
        register_rest_route($this->namespace, '/tasks/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_task'],
                'permission_callback' => [$this, 'check_task_access']
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_task'],
                'permission_callback' => [$this, 'check_task_edit_permission'],
                'args' => $this->get_task_schema()
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_task'],
                'permission_callback' => [$this, 'check_task_delete_permission']
            ]
        ]);
        
        // Ações especiais das tarefas
        register_rest_route($this->namespace, '/tasks/(?P<id>\d+)/move', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'move_task'],
            'permission_callback' => [$this, 'check_task_edit_permission'],
            'args' => [
                'column_id' => [
                    'required' => true,
                    'type' => 'integer'
                ],
                'position' => [
                    'required' => false,
                    'type' => 'integer'
                ]
            ]
        ]);
        
        // Busca global
        register_rest_route($this->namespace, '/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'minimum' => 3
                ],
                'type' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['projects', 'boards', 'tasks', 'all']
                ]
            ]
        ]);
        
        // Dashboard/estatísticas
        register_rest_route($this->namespace, '/dashboard/stats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_dashboard_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);
        
        // Usuários do projeto
        register_rest_route($this->namespace, '/projects/(?P<project_id>\d+)/members', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_project_members'],
                'permission_callback' => [$this, 'check_project_access']
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'add_project_member'],
                'permission_callback' => [$this, 'check_project_admin_permission'],
                'args' => [
                    'user_id' => ['required' => true, 'type' => 'integer'],
                    'role' => ['required' => false, 'type' => 'string', 'enum' => ['viewer', 'member', 'coordinator', 'manager']]
                ]
            ]
        ]);
    }
    
    // ========== MÉTODOS DOS PROJETOS ==========
    
    public function get_projects($request) {
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $projects = $project_model->get_user_projects(get_current_user_id());
        
        return rest_ensure_response($projects);
    }
    
    public function get_project($request) {
        $project_id = $request['id'];
        
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $project = $project_model->get_by_id($project_id);
        
        if (!$project) {
            return new WP_Error('not_found', 'Projeto não encontrado', ['status' => 404]);
        }
        
        return rest_ensure_response($project);
    }
    
    public function create_project($request) {
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $result = $project_model->create($request->get_params());
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $project = $project_model->get_by_id($result);
        return rest_ensure_response($project);
    }
    
    public function update_project($request) {
        $project_id = $request['id'];
        
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $result = $project_model->update($project_id, $request->get_params());
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $project = $project_model->get_by_id($project_id);
        return rest_ensure_response($project);
    }
    
    public function delete_project($request) {
        $project_id = $request['id'];
        
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $result = $project_model->delete($project_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['deleted' => true]);
    }
    
    // ========== MÉTODOS DOS BOARDS ==========
    
    public function get_project_boards($request) {
        $project_id = $request['project_id'];
        
        if (!class_exists('QL_Board')) {
            return new WP_Error('class_not_found', 'Classe QL_Board não encontrada', ['status' => 500]);
        }
        
        $board_model = QL_Board::get_instance();
        $boards = $board_model->get_by_project($project_id);
        
        return rest_ensure_response($boards);
    }
    
    public function get_board($request) {
        $board_id = $request['id'];
        
        if (!class_exists('QL_Board')) {
            return new WP_Error('class_not_found', 'Classe QL_Board não encontrada', ['status' => 500]);
        }
        
        $board_model = QL_Board::get_instance();
        $board = $board_model->get_by_id($board_id);
        
        if (!$board) {
            return new WP_Error('not_found', 'Board não encontrado', ['status' => 404]);
        }
        
        return rest_ensure_response($board);
    }
    
    public function create_board($request) {
        if (!class_exists('QL_Board')) {
            return new WP_Error('class_not_found', 'Classe QL_Board não encontrada', ['status' => 500]);
        }
        
        $params = $request->get_params();
        $params['project_id'] = $request['project_id'];
        
        $board_model = QL_Board::get_instance();
        $result = $board_model->create($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $board = $board_model->get_by_id($result);
        return rest_ensure_response($board);
    }
    
    public function update_board($request) {
        $board_id = $request['id'];
        
        if (!class_exists('QL_Board')) {
            return new WP_Error('class_not_found', 'Classe QL_Board não encontrada', ['status' => 500]);
        }
        
        $board_model = QL_Board::get_instance();
        $result = $board_model->update($board_id, $request->get_params());
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $board = $board_model->get_by_id($board_id);
        return rest_ensure_response($board);
    }
    
    public function delete_board($request) {
        $board_id = $request['id'];
        
        if (!class_exists('QL_Board')) {
            return new WP_Error('class_not_found', 'Classe QL_Board não encontrada', ['status' => 500]);
        }
        
        $board_model = QL_Board::get_instance();
        $result = $board_model->delete($board_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['deleted' => true]);
    }
    
    // ========== MÉTODOS DAS COLUNAS ==========
    
    public function get_board_columns($request) {
        $board_id = $request['board_id'];
        
        if (!class_exists('QL_Column')) {
            return new WP_Error('class_not_found', 'Classe QL_Column não encontrada', ['status' => 500]);
        }
        
        $column_model = QL_Column::get_instance();
        $columns = $column_model->get_by_board($board_id);
        
        return rest_ensure_response($columns);
    }
    
    public function create_column($request) {
        if (!class_exists('QL_Column')) {
            return new WP_Error('class_not_found', 'Classe QL_Column não encontrada', ['status' => 500]);
        }
        
        $params = $request->get_params();
        $params['board_id'] = $request['board_id'];
        
        $column_model = QL_Column::get_instance();
        $result = $column_model->create($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $column = $column_model->get_by_id($result);
        return rest_ensure_response($column);
    }
    
    public function update_column($request) {
        $column_id = $request['id'];
        
        if (!class_exists('QL_Column')) {
            return new WP_Error('class_not_found', 'Classe QL_Column não encontrada', ['status' => 500]);
        }
        
        $column_model = QL_Column::get_instance();
        $result = $column_model->update($column_id, $request->get_params());
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $column = $column_model->get_by_id($column_id);
        return rest_ensure_response($column);
    }
    
    public function delete_column($request) {
        $column_id = $request['id'];
        
        if (!class_exists('QL_Column')) {
            return new WP_Error('class_not_found', 'Classe QL_Column não encontrada', ['status' => 500]);
        }
        
        $column_model = QL_Column::get_instance();
        $result = $column_model->delete($column_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['deleted' => true]);
    }
    
    // ========== MÉTODOS DAS TAREFAS ==========
    
    public function get_board_tasks($request) {
        $board_id = $request['board_id'];
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $task_model = QL_Task::get_instance();
        $tasks = $task_model->get_by_board($board_id);
        
        return rest_ensure_response($tasks);
    }
    
    public function get_task($request) {
        $task_id = $request['id'];
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $task_model = QL_Task::get_instance();
        $task = $task_model->get_by_id($task_id);
        
        if (!$task) {
            return new WP_Error('not_found', 'Tarefa não encontrada', ['status' => 404]);
        }
        
        return rest_ensure_response($task);
    }
    
    public function create_task($request) {
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $params = $request->get_params();
        $params['board_id'] = $request['board_id'];
        
        $task_model = QL_Task::get_instance();
        $result = $task_model->create($params);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $task = $task_model->get_by_id($result);
        return rest_ensure_response($task);
    }
    
    public function update_task($request) {
        $task_id = $request['id'];
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $task_model = QL_Task::get_instance();
        $result = $task_model->update($task_id, $request->get_params());
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $task = $task_model->get_by_id($task_id);
        return rest_ensure_response($task);
    }
    
    public function delete_task($request) {
        $task_id = $request['id'];
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $task_model = QL_Task::get_instance();
        $result = $task_model->delete($task_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['deleted' => true]);
    }
    
    public function move_task($request) {
        $task_id = $request['id'];
        $column_id = $request['column_id'];
        $position = $request['position'] ?? null;
        
        if (!class_exists('QL_Task')) {
            return new WP_Error('class_not_found', 'Classe QL_Task não encontrada', ['status' => 500]);
        }
        
        $task_model = QL_Task::get_instance();
        $result = $task_model->move_to_column($task_id, $column_id, $position);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $task = $task_model->get_by_id($task_id);
        return rest_ensure_response($task);
    }
    
    // ========== MÉTODOS AUXILIARES ==========
    
    public function search($request) {
        $search_term = $request['q'];
        $type = $request['type'] ?? 'all';
        
        $results = [];
        
        if ($type === 'all' || $type === 'projects') {
            if (class_exists('QL_Project')) {
                $project_model = QL_Project::get_instance();
                $results['projects'] = $project_model->search($search_term);
            }
        }
        
        if ($type === 'all' || $type === 'boards') {
            if (class_exists('QL_Board')) {
                $board_model = QL_Board::get_instance();
                $results['boards'] = $board_model->search($search_term);
            }
        }
        
        if ($type === 'all' || $type === 'tasks') {
            if (class_exists('QL_Task')) {
                $task_model = QL_Task::get_instance();
                $results['tasks'] = $task_model->search($search_term);
            }
        }
        
        return rest_ensure_response($results);
    }
    
    public function get_dashboard_stats($request) {
        $user_id = get_current_user_id();
        $stats = [];
        
        if (class_exists('QL_Project')) {
            $project_model = QL_Project::get_instance();
            $stats['projects_count'] = count($project_model->get_user_projects($user_id));
        }
        
        if (class_exists('QL_Task')) {
            $task_model = QL_Task::get_instance();
            $user_tasks = $task_model->get_user_tasks($user_id);
            $stats['tasks_count'] = count($user_tasks);
            $stats['completed_tasks'] = count(array_filter($user_tasks, function($task) {
                return $task['status'] === 'completed';
            }));
            $stats['overdue_tasks'] = count(array_filter($user_tasks, function($task) {
                return !empty($task['due_date']) && strtotime($task['due_date']) < time() && $task['status'] !== 'completed';
            }));
        }
        
        return rest_ensure_response($stats);
    }
    
    public function get_project_members($request) {
        $project_id = $request['project_id'];
        
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $members = $project_model->get_project_members($project_id);
        
        return rest_ensure_response($members);
    }
    
    public function add_project_member($request) {
        $project_id = $request['project_id'];
        $user_id = $request['user_id'];
        $role = $request['role'] ?? 'member';
        
        if (!class_exists('QL_Project')) {
            return new WP_Error('class_not_found', 'Classe QL_Project não encontrada', ['status' => 500]);
        }
        
        $project_model = QL_Project::get_instance();
        $result = $project_model->add_member($project_id, $user_id, $role);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return rest_ensure_response(['added' => true]);
    }
    
    // ========== SCHEMAS ==========
    
    private function get_project_schema() {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'description' => ['required' => false, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string', 'enum' => ['active', 'archived', 'completed', 'on_hold']],
            'visibility' => ['required' => false, 'type' => 'string', 'enum' => ['public', 'private', 'team']],
            'start_date' => ['required' => false, 'type' => 'string', 'format' => 'date'],
            'end_date' => ['required' => false, 'type' => 'string', 'format' => 'date']
        ];
    }
    
    private function get_board_schema() {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'description' => ['required' => false, 'type' => 'string'],
            'status' => ['required' => false, 'type' => 'string']
        ];
    }
    
    private function get_column_schema() {
        return [
            'name' => ['required' => true, 'type' => 'string'],
            'description' => ['required' => false, 'type' => 'string'],
            'position' => ['required' => false, 'type' => 'integer'],
            'task_limit' => ['required' => false, 'type' => 'integer'],
            'color' => ['required' => false, 'type' => 'string']
        ];
    }
    
    private function get_task_schema() {
        return [
            'title' => ['required' => true, 'type' => 'string'],
            'description' => ['required' => false, 'type' => 'string'],
            'column_id' => ['required' => true, 'type' => 'integer'],
            'assigned_user_id' => ['required' => false, 'type' => 'integer'],
            'status' => ['required' => false, 'type' => 'string'],
            'priority' => ['required' => false, 'type' => 'integer'],
            'due_date' => ['required' => false, 'type' => 'string', 'format' => 'date'],
            'start_date' => ['required' => false, 'type' => 'string', 'format' => 'date']
        ];
    }
    
    // ========== PERMISSÕES ==========
    
    public function check_permission($request) {
        return current_user_can('read');
    }
    
    public function check_create_permission($request) {
        return current_user_can('ql_create_projects') || current_user_can('edit_posts');
    }
    
    public function check_project_access($request) {
        if (!current_user_can('read')) {
            return false;
        }
        
        $project_id = $request['project_id'] ?? $request['id'];
        
        if (current_user_can('ql_manage_all_projects')) {
            return true;
        }
        
        // Verificar se usuário tem acesso ao projeto específico
        if (class_exists('QL_Project')) {
            $project_model = QL_Project::get_instance();
            return $project_model->user_has_access($project_id, get_current_user_id());
        }
        
        return false;
    }
    
    public function check_project_edit_permission($request) {
        if (!current_user_can('edit_posts')) {
            return false;
        }
        
        return $this->check_project_access($request);
    }
    
    public function check_project_delete_permission($request) {
        if (!current_user_can('delete_posts')) {
            return false;
        }
        
        return $this->check_project_access($request);
    }
    
    public function check_project_admin_permission($request) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return $this->check_project_access($request);
    }
    
    public function check_board_access($request) {
        // Implementar verificação de acesso baseada no projeto do board
        return $this->check_permission($request);
    }
    
    public function check_board_edit_permission($request) {
        return $this->check_board_access($request) && current_user_can('edit_posts');
    }
    
    public function check_board_delete_permission($request) {
        return $this->check_board_access($request) && current_user_can('delete_posts');
    }
    
    public function check_column_edit_permission($request) {
        return $this->check_board_access($request) && current_user_can('edit_posts');
    }
    
    public function check_column_delete_permission($request) {
        return $this->check_board_access($request) && current_user_can('delete_posts');
    }
    
    public function check_task_access($request) {
        return $this->check_permission($request);
    }
    
    public function check_task_create_permission($request) {
        return current_user_can('ql_create_tasks') || current_user_can('edit_posts');
    }
    
    public function check_task_edit_permission($request) {
        return $this->check_task_access($request) && current_user_can('edit_posts');
    }
    
    public function check_task_delete_permission($request) {
        return $this->check_task_access($request) && current_user_can('delete_posts');
    }
    
    /**
     * Registrar rotas estáticas (método auxiliar)
     */
    public static function register_api_routes() {
        $instance = self::get_instance();
        $instance->register_routes();
    }
}