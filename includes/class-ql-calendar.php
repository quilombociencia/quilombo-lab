<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para funcionalidades de calendário do Quilombo Laboratório
 */
class QL_Calendar {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_ql_get_calendar_events', [$this, 'ajax_get_calendar_events']);
        add_action('wp_ajax_ql_create_calendar_event', [$this, 'ajax_create_calendar_event']);
        add_action('wp_ajax_ql_update_calendar_event', [$this, 'ajax_update_calendar_event']);
        add_action('wp_ajax_ql_delete_calendar_event', [$this, 'ajax_delete_calendar_event']);
    }
    
    /**
     * Obter eventos do calendário
     */
    public function get_calendar_events($start_date, $end_date, $project_id = null, $user_id = null) {
        global $wpdb;
        
        $tasks_table = $wpdb->prefix . 'ql_tasks';
        $projects_table = $wpdb->prefix . 'ql_projects';
        $users_table = $wpdb->users;
        
        $where_conditions = ["t.due_date BETWEEN %s AND %s"];
        $where_values = [$start_date, $end_date];
        
        if ($project_id) {
            $where_conditions[] = "t.project_id = %d";
            $where_values[] = $project_id;
        }
        
        if ($user_id) {
            $where_conditions[] = "t.assigned_user_id = %d";
            $where_values[] = $user_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT 
                t.id,
                t.title,
                t.description,
                t.due_date,
                t.status,
                t.priority,
                t.time_estimate,
                t.created_at,
                p.name as project_name,
                p.id as project_id,
                u.display_name as assigned_user_name,
                u.ID as assigned_user_id
            FROM {$tasks_table} t
            LEFT JOIN {$projects_table} p ON t.project_id = p.id
            LEFT JOIN {$users_table} u ON t.assigned_user_id = u.ID
            WHERE {$where_clause}
            ORDER BY t.due_date, t.priority DESC
        ", $where_values);
        
        $tasks = $wpdb->get_results($query, ARRAY_A);
        
        // Converter tarefas para formato de eventos de calendário
        $events = [];
        foreach ($tasks as $task) {
            $events[] = $this->task_to_calendar_event($task);
        }
        
        // Adicionar eventos personalizados (milestones, reuniões, etc.)
        $custom_events = $this->get_custom_events($start_date, $end_date, $project_id);
        $events = array_merge($events, $custom_events);
        
        return $events;
    }
    
    /**
     * Converter tarefa para evento de calendário
     */
    private function task_to_calendar_event($task) {
        $priority_colors = [
            1 => '#17a2b8', // Baixa - azul
            2 => '#6c757d', // Normal - cinza
            3 => '#fd7e14', // Alta - laranja
            4 => '#dc3545'  // Urgente - vermelho
        ];
        
        $status_colors = [
            'open' => '#6c757d',
            'in_progress' => '#007bff',
            'review' => '#ffc107',
            'completed' => '#28a745'
        ];
        
        return [
            'id' => 'task_' . $task['id'],
            'title' => $task['title'],
            'start' => $task['due_date'],
            'end' => $task['due_date'], // Para tarefas, usar mesmo dia
            'allDay' => true,
            'color' => $priority_colors[$task['priority']] ?? '#6c757d',
            'borderColor' => $status_colors[$task['status']] ?? '#6c757d',
            'extendedProps' => [
                'type' => 'task',
                'task_id' => $task['id'],
                'description' => $task['description'],
                'status' => $task['status'],
                'priority' => $task['priority'],
                'project_name' => $task['project_name'],
                'project_id' => $task['project_id'],
                'assigned_user' => $task['assigned_user_name'],
                'time_estimate' => $task['time_estimate'],
                'created_at' => $task['created_at']
            ]
        ];
    }
    
    /**
     * Obter eventos personalizados (milestones, reuniões, etc.)
     */
    private function get_custom_events($start_date, $end_date, $project_id = null) {
        global $wpdb;
        
        // Tabela para eventos personalizados (criar se necessário)
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        // Verificar se tabela existe
        if ($wpdb->get_var("SHOW TABLES LIKE '{$events_table}'") !== $events_table) {
            $this->create_calendar_events_table();
        }
        
        $where_conditions = ["event_date BETWEEN %s AND %s"];
        $where_values = [$start_date, $end_date];
        
        if ($project_id) {
            $where_conditions[] = "project_id = %d";
            $where_values[] = $project_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = $wpdb->prepare("
            SELECT *
            FROM {$events_table}
            WHERE {$where_clause}
            ORDER BY event_date, event_time
        ", $where_values);
        
        $custom_events = $wpdb->get_results($query, ARRAY_A);
        
        $events = [];
        foreach ($custom_events as $event) {
            $events[] = [
                'id' => 'custom_' . $event['id'],
                'title' => $event['title'],
                'start' => $event['event_date'] . ($event['event_time'] ? 'T' . $event['event_time'] : ''),
                'end' => $event['end_date'] ? 
                    ($event['end_date'] . ($event['end_time'] ? 'T' . $event['end_time'] : '')) : 
                    null,
                'allDay' => !$event['event_time'],
                'color' => $event['color'] ?: '#007bff',
                'extendedProps' => [
                    'type' => 'custom',
                    'event_id' => $event['id'],
                    'description' => $event['description'],
                    'event_type' => $event['event_type'],
                    'location' => $event['location'],
                    'project_id' => $event['project_id']
                ]
            ];
        }
        
        return $events;
    }
    
    /**
     * Criar tabela de eventos personalizados
     */
    private function create_calendar_events_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_calendar_events';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            event_type varchar(50) DEFAULT 'meeting',
            event_date date NOT NULL,
            event_time time NULL,
            end_date date NULL,
            end_time time NULL,
            location varchar(255),
            color varchar(7) DEFAULT '#007bff',
            project_id mediumint(9),
            created_by mediumint(9),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY project_id (project_id),
            KEY event_date (event_date),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Criar evento personalizado
     */
    public function create_custom_event($event_data) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        $data = [
            'title' => sanitize_text_field($event_data['title']),
            'description' => sanitize_textarea_field($event_data['description'] ?? ''),
            'event_type' => sanitize_text_field($event_data['event_type'] ?? 'meeting'),
            'event_date' => sanitize_text_field($event_data['event_date']),
            'event_time' => !empty($event_data['event_time']) ? sanitize_text_field($event_data['event_time']) : null,
            'end_date' => !empty($event_data['end_date']) ? sanitize_text_field($event_data['end_date']) : null,
            'end_time' => !empty($event_data['end_time']) ? sanitize_text_field($event_data['end_time']) : null,
            'location' => sanitize_text_field($event_data['location'] ?? ''),
            'color' => sanitize_hex_color($event_data['color'] ?? '#007bff'),
            'project_id' => !empty($event_data['project_id']) ? intval($event_data['project_id']) : null,
            'created_by' => get_current_user_id()
        ];
        
        $result = $wpdb->insert($events_table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar evento');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Atualizar evento personalizado
     */
    public function update_custom_event($event_id, $event_data) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        $data = [
            'title' => sanitize_text_field($event_data['title']),
            'description' => sanitize_textarea_field($event_data['description'] ?? ''),
            'event_type' => sanitize_text_field($event_data['event_type'] ?? 'meeting'),
            'event_date' => sanitize_text_field($event_data['event_date']),
            'event_time' => !empty($event_data['event_time']) ? sanitize_text_field($event_data['event_time']) : null,
            'end_date' => !empty($event_data['end_date']) ? sanitize_text_field($event_data['end_date']) : null,
            'end_time' => !empty($event_data['end_time']) ? sanitize_text_field($event_data['end_time']) : null,
            'location' => sanitize_text_field($event_data['location'] ?? ''),
            'color' => sanitize_hex_color($event_data['color'] ?? '#007bff'),
            'project_id' => !empty($event_data['project_id']) ? intval($event_data['project_id']) : null
        ];
        
        $result = $wpdb->update(
            $events_table,
            $data,
            ['id' => intval($event_id)],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar evento');
        }
        
        return true;
    }
    
    /**
     * Excluir evento personalizado
     */
    public function delete_custom_event($event_id) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        $result = $wpdb->delete(
            $events_table,
            ['id' => intval($event_id)],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao excluir evento');
        }
        
        return true;
    }
    
    /**
     * AJAX: Obter eventos do calendário
     */
    public function ajax_get_calendar_events() {
        check_ajax_referer('ql_calendar_nonce', 'nonce');
        
        if (!current_user_can('read')) {
            wp_die('Permissão insuficiente');
        }
        
        $start_date = sanitize_text_field($_GET['start'] ?? date('Y-m-01'));
        $end_date = sanitize_text_field($_GET['end'] ?? date('Y-m-t'));
        $project_id = !empty($_GET['project_id']) ? intval($_GET['project_id']) : null;
        $user_id = !empty($_GET['user_id']) ? intval($_GET['user_id']) : null;
        
        $events = $this->get_calendar_events($start_date, $end_date, $project_id, $user_id);
        
        wp_send_json_success($events);
    }
    
    /**
     * AJAX: Criar evento personalizado
     */
    public function ajax_create_calendar_event() {
        check_ajax_referer('ql_calendar_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permissão insuficiente');
        }
        
        $event_data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'event_type' => $_POST['event_type'] ?? 'meeting',
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'location' => $_POST['location'] ?? '',
            'color' => $_POST['color'] ?? '#007bff',
            'project_id' => $_POST['project_id'] ?? null
        ];
        
        if (empty($event_data['title']) || empty($event_data['event_date'])) {
            wp_send_json_error('Título e data são obrigatórios');
        }
        
        $event_id = $this->create_custom_event($event_data);
        
        if (is_wp_error($event_id)) {
            wp_send_json_error($event_id->get_error_message());
        }
        
        wp_send_json_success([
            'event_id' => $event_id,
            'message' => 'Evento criado com sucesso'
        ]);
    }
    
    /**
     * AJAX: Atualizar evento personalizado
     */
    public function ajax_update_calendar_event() {
        check_ajax_referer('ql_calendar_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permissão insuficiente');
        }
        
        $event_id = intval($_POST['event_id'] ?? 0);
        if (!$event_id) {
            wp_send_json_error('ID do evento é obrigatório');
        }
        
        $event_data = [
            'title' => $_POST['title'] ?? '',
            'description' => $_POST['description'] ?? '',
            'event_type' => $_POST['event_type'] ?? 'meeting',
            'event_date' => $_POST['event_date'] ?? '',
            'event_time' => $_POST['event_time'] ?? '',
            'end_date' => $_POST['end_date'] ?? '',
            'end_time' => $_POST['end_time'] ?? '',
            'location' => $_POST['location'] ?? '',
            'color' => $_POST['color'] ?? '#007bff',
            'project_id' => $_POST['project_id'] ?? null
        ];
        
        $result = $this->update_custom_event($event_id, $event_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Evento atualizado com sucesso');
    }
    
    /**
     * AJAX: Excluir evento personalizado
     */
    public function ajax_delete_calendar_event() {
        check_ajax_referer('ql_calendar_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_die('Permissão insuficiente');
        }
        
        $event_id = intval($_POST['event_id'] ?? 0);
        if (!$event_id) {
            wp_send_json_error('ID do evento é obrigatório');
        }
        
        $result = $this->delete_custom_event($event_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Evento excluído com sucesso');
    }
    
    /**
     * Gerar visualização de calendário para admin
     */
    public function render_calendar_view($project_id = null) {
        wp_enqueue_script('ql-calendar-js', QL_PLUGIN_URL . 'assets/js/calendar.js', ['jquery'], QL_PLUGIN_VERSION, true);
        wp_enqueue_style('ql-calendar-css', QL_PLUGIN_URL . 'assets/css/calendar.css', [], QL_PLUGIN_VERSION);
        
        // FullCalendar CDN
        $cdn_settings = QL_Config::get_cdn_settings();
        wp_enqueue_script('fullcalendar', $cdn_settings['fullcalendar'], [], '6.1.8', true);
        
        wp_localize_script('ql-calendar-js', 'ql_calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ql_calendar_nonce'),
            'project_id' => $project_id,
            'strings' => [
                'loading' => __('Carregando eventos...', 'quilombo-laboratorio'),
                'new_event' => __('Novo Evento', 'quilombo-laboratorio'),
                'edit_event' => __('Editar Evento', 'quilombo-laboratorio'),
                'delete_confirm' => __('Tem certeza que deseja excluir este evento?', 'quilombo-laboratorio')
            ]
        ]);
        
        ob_start();
        ?>
        <div id="ql-calendar-container">
            <div id="ql-calendar-toolbar">
                <button type="button" id="ql-new-event-btn" class="button button-primary">
                    <?php _e('Novo Evento', 'quilombo-laboratorio'); ?>
                </button>
                
                <select id="ql-calendar-filter-project">
                    <option value=""><?php _e('Todos os projetos', 'quilombo-laboratorio'); ?></option>
                    <?php
                    $projects = QL_Project::get_instance()->get_all(['status' => 'active']);
                    foreach ($projects as $project) {
                        $selected = $project_id == $project['id'] ? 'selected' : '';
                        echo '<option value="' . $project['id'] . '" ' . $selected . '>' . 
                             esc_html($project['name']) . '</option>';
                    }
                    ?>
                </select>
                
                <select id="ql-calendar-filter-user">
                    <option value=""><?php _e('Todos os usuários', 'quilombo-laboratorio'); ?></option>
                    <?php
                    $users = get_users(['capability' => 'ql_view_projects']);
                    foreach ($users as $user) {
                        echo '<option value="' . $user->ID . '">' . 
                             esc_html($user->display_name) . '</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div id="ql-calendar"></div>
        </div>
        
        <!-- Modal para eventos -->
        <div id="ql-event-modal" class="ql-modal hidden">
            <div class="ql-modal-backdrop"></div>
            <div class="ql-modal-content">
                <div class="ql-modal-header">
                    <h3 class="ql-modal-title"><?php _e('Novo Evento', 'quilombo-laboratorio'); ?></h3>
                    <button class="ql-modal-close">&times;</button>
                </div>
                <div class="ql-modal-body">
                    <form id="ql-event-form">
                        <input type="hidden" id="event-id" name="event_id">
                        
                        <div class="ql-form-group">
                            <label for="event-title"><?php _e('Título *', 'quilombo-laboratorio'); ?></label>
                            <input type="text" id="event-title" name="title" required>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-description"><?php _e('Descrição', 'quilombo-laboratorio'); ?></label>
                            <textarea id="event-description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-type"><?php _e('Tipo', 'quilombo-laboratorio'); ?></label>
                                <select id="event-type" name="event_type">
                                    <option value="meeting"><?php _e('Reunião', 'quilombo-laboratorio'); ?></option>
                                    <option value="milestone"><?php _e('Marco', 'quilombo-laboratorio'); ?></option>
                                    <option value="deadline"><?php _e('Prazo', 'quilombo-laboratorio'); ?></option>
                                    <option value="workshop"><?php _e('Workshop', 'quilombo-laboratorio'); ?></option>
                                    <option value="other"><?php _e('Outro', 'quilombo-laboratorio'); ?></option>
                                </select>
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-color"><?php _e('Cor', 'quilombo-laboratorio'); ?></label>
                                <input type="color" id="event-color" name="color" value="#007bff">
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-date"><?php _e('Data *', 'quilombo-laboratorio'); ?></label>
                                <input type="date" id="event-date" name="event_date" required>
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-time"><?php _e('Horário', 'quilombo-laboratorio'); ?></label>
                                <input type="time" id="event-time" name="event_time">
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-end-date"><?php _e('Data Final', 'quilombo-laboratorio'); ?></label>
                                <input type="date" id="event-end-date" name="end_date">
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-end-time"><?php _e('Horário Final', 'quilombo-laboratorio'); ?></label>
                                <input type="time" id="event-end-time" name="end_time">
                            </div>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-location"><?php _e('Local', 'quilombo-laboratorio'); ?></label>
                            <input type="text" id="event-location" name="location">
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-project"><?php _e('Projeto', 'quilombo-laboratorio'); ?></label>
                            <select id="event-project" name="project_id">
                                <option value=""><?php _e('Nenhum projeto específico', 'quilombo-laboratorio'); ?></option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>">
                                        <?php echo esc_html($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="ql-modal-footer">
                    <button type="button" class="button ql-modal-close"><?php _e('Cancelar', 'quilombo-laboratorio'); ?></button>
                    <button type="button" id="ql-delete-event-btn" class="button button-secondary hidden"><?php _e('Excluir', 'quilombo-laboratorio'); ?></button>
                    <button type="button" id="ql-save-event-btn" class="button button-primary"><?php _e('Salvar', 'quilombo-laboratorio'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}