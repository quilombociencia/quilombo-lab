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
        
        // Moodle Calendar integration
        add_action('wp_ajax_ql_sync_moodle_calendar', [$this, 'ajax_sync_moodle_calendar']);
        add_action('wp_ajax_ql_setup_moodle_calendar', [$this, 'ajax_setup_moodle_calendar']);
        
        // WordPress Calendar integration
        add_action('wp_ajax_ql_sync_wp_calendar', [$this, 'ajax_sync_wp_calendar']);
        
        // Cron para sincronização automática
        add_action('ql_sync_moodle_calendar_cron', [$this, 'sync_moodle_calendar_events']);
        
        // Hook quando evento é criado para mapear instâncias
        add_action('ql_calendar_event_created', [$this, 'map_event_to_instances'], 10, 2);
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
                'loading' => __('Carregando eventos...', 'quilombo-lab'),
                'new_event' => __('Novo Evento', 'quilombo-lab'),
                'edit_event' => __('Editar Evento', 'quilombo-lab'),
                'delete_confirm' => __('Tem certeza que deseja excluir este evento?', 'quilombo-lab')
            ]
        ]);
        
        ob_start();
        ?>
        <div id="ql-calendar-container">
            <div id="ql-calendar-toolbar">
                <button type="button" id="ql-new-event-btn" class="button button-primary">
                    <?php _e('Novo Evento', 'quilombo-lab'); ?>
                </button>
                
                <select id="ql-calendar-filter-project">
                    <option value=""><?php _e('Todos os projetos', 'quilombo-lab'); ?></option>
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
                    <option value=""><?php _e('Todos os usuários', 'quilombo-lab'); ?></option>
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
                    <h3 class="ql-modal-title"><?php _e('Novo Evento', 'quilombo-lab'); ?></h3>
                    <button class="ql-modal-close">&times;</button>
                </div>
                <div class="ql-modal-body">
                    <form id="ql-event-form">
                        <input type="hidden" id="event-id" name="event_id">
                        
                        <div class="ql-form-group">
                            <label for="event-title"><?php _e('Título *', 'quilombo-lab'); ?></label>
                            <input type="text" id="event-title" name="title" required>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-description"><?php _e('Descrição', 'quilombo-lab'); ?></label>
                            <textarea id="event-description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-type"><?php _e('Tipo', 'quilombo-lab'); ?></label>
                                <select id="event-type" name="event_type">
                                    <option value="meeting"><?php _e('Reunião', 'quilombo-lab'); ?></option>
                                    <option value="milestone"><?php _e('Marco', 'quilombo-lab'); ?></option>
                                    <option value="deadline"><?php _e('Prazo', 'quilombo-lab'); ?></option>
                                    <option value="workshop"><?php _e('Workshop', 'quilombo-lab'); ?></option>
                                    <option value="other"><?php _e('Outro', 'quilombo-lab'); ?></option>
                                </select>
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-color"><?php _e('Cor', 'quilombo-lab'); ?></label>
                                <input type="color" id="event-color" name="color" value="#007bff">
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-date"><?php _e('Data *', 'quilombo-lab'); ?></label>
                                <input type="date" id="event-date" name="event_date" required>
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-time"><?php _e('Horário', 'quilombo-lab'); ?></label>
                                <input type="time" id="event-time" name="event_time">
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-group">
                                <label for="event-end-date"><?php _e('Data Final', 'quilombo-lab'); ?></label>
                                <input type="date" id="event-end-date" name="end_date">
                            </div>
                            
                            <div class="ql-form-group">
                                <label for="event-end-time"><?php _e('Horário Final', 'quilombo-lab'); ?></label>
                                <input type="time" id="event-end-time" name="end_time">
                            </div>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-location"><?php _e('Local', 'quilombo-lab'); ?></label>
                            <input type="text" id="event-location" name="location">
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="event-project"><?php _e('Projeto', 'quilombo-lab'); ?></label>
                            <select id="event-project" name="project_id">
                                <option value=""><?php _e('Nenhum projeto específico', 'quilombo-lab'); ?></option>
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
                    <button type="button" class="button ql-modal-close"><?php _e('Cancelar', 'quilombo-lab'); ?></button>
                    <button type="button" id="ql-delete-event-btn" class="button button-secondary hidden"><?php _e('Excluir', 'quilombo-lab'); ?></button>
                    <button type="button" id="ql-save-event-btn" class="button button-primary"><?php _e('Salvar', 'quilombo-lab'); ?></button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Configurar integração com Moodle Calendar
     */
    public function setup_moodle_calendar_integration($config_data) {
        // Salvar configuração do Moodle Calendar
        $moodle_config = [
            'sync_enabled' => true,
            'last_sync' => null,
            'sync_interval' => 'hourly', // Pode ser: hourly, daily, weekly
            'course_filter' => $config_data['course_filter'] ?? [], // IDs dos cursos para sincronizar
            'event_types' => $config_data['event_types'] ?? ['course', 'user', 'site'] // Tipos de eventos
        ];
        
        update_option('ql_moodle_calendar_config', $moodle_config);
        
        // Agendar sincronização automática
        if (!wp_next_scheduled('ql_sync_moodle_calendar_cron')) {
            wp_schedule_event(time(), $moodle_config['sync_interval'], 'ql_sync_moodle_calendar_cron');
        }
        
        return true;
    }
    
    /**
     * Sincronizar eventos do Moodle Calendar
     */
    public function sync_moodle_calendar_events() {
        $moodle_config = get_option('ql_moodle_calendar_config');
        
        if (!$moodle_config || !$moodle_config['sync_enabled']) {
            error_log('QL Calendar: Moodle Calendar sync not enabled');
            return false;
        }
        
        if (!class_exists('QL_Moodle_Integration')) {
            error_log('QL Calendar: Moodle integration not available');
            return false;
        }
        
        try {
            $moodle = QL_Moodle_Integration::get_instance();
            $moodle_events = $this->fetch_moodle_calendar_events($moodle, $moodle_config);
            
            if (is_wp_error($moodle_events)) {
                error_log('QL Calendar: Error fetching Moodle events - ' . $moodle_events->get_error_message());
                return false;
            }
            
            $synced_count = 0;
            foreach ($moodle_events as $moodle_event) {
                $result = $this->import_moodle_event($moodle_event);
                if (!is_wp_error($result)) {
                    $synced_count++;
                    
                    // Mapear evento para instâncias baseado no título/descrição
                    $this->map_event_to_instances($result, $moodle_event);
                }
            }
            
            // Sincronizar com eventos do WordPress (The Events Calendar, etc.)
            $wp_synced = $this->sync_wordpress_calendar_events();
            if (!is_wp_error($wp_synced)) {
                $synced_count += $wp_synced;
            }
            
            // Atualizar último sync
            $moodle_config['last_sync'] = current_time('mysql');
            update_option('ql_moodle_calendar_config', $moodle_config);
            
            error_log("QL Calendar: Synchronized {$synced_count} events from Moodle/WordPress Calendar");
            
            return $synced_count;
            
        } catch (Exception $e) {
            error_log('QL Calendar: Moodle sync exception - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Buscar eventos do Moodle Calendar
     */
    private function fetch_moodle_calendar_events($moodle_integration, $config) {
        // Buscar eventos dos cursos configurados
        $events = [];
        
        // Se nenhum curso específico, buscar todos
        $course_filter = $config['course_filter'] ?? [];
        
        // Usar API do Moodle para buscar eventos
        $moodle_events = $moodle_integration->call_moodle_api('core_calendar_get_calendar_events', [
            'options' => [
                'userevents' => in_array('user', $config['event_types']),
                'siteevents' => in_array('site', $config['event_types']),
                'timestart' => time() - (30 * 24 * 60 * 60), // Últimos 30 dias
                'timeend' => time() + (90 * 24 * 60 * 60)     // Próximos 90 dias
            ]
        ]);
        
        if (is_wp_error($moodle_events)) {
            return $moodle_events;
        }
        
        // Processar eventos recebidos
        if (isset($moodle_events['events'])) {
            foreach ($moodle_events['events'] as $event) {
                // Filtrar por curso se especificado
                if (!empty($course_filter) && !in_array($event['courseid'] ?? 0, $course_filter)) {
                    continue;
                }
                
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    /**
     * Importar evento do Moodle Calendar
     */
    private function import_moodle_event($moodle_event) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        // Verificar se evento já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $events_table WHERE JSON_EXTRACT(metadata, '$.moodle_event_id') = %s",
            $moodle_event['id']
        ));
        
        if ($existing) {
            return $existing; // Já existe
        }
        
        // Converter evento do Moodle para formato QL
        $start_datetime = $this->parse_moodle_datetime($moodle_event);
        $end_datetime = $this->parse_moodle_end_datetime($moodle_event);
        
        $event_data = [
            'title' => sanitize_text_field($moodle_event['name']),
            'description' => sanitize_textarea_field($moodle_event['description'] ?? ''),
            'event_type' => $this->detect_event_type_from_title($moodle_event['name']),
            'event_date' => $start_datetime['date'],
            'event_time' => $start_datetime['time'],
            'end_date' => $end_datetime['date'],
            'end_time' => $end_datetime['time'],
            'location' => sanitize_text_field($moodle_event['location'] ?? ''),
            'color' => $this->get_color_for_event_type($this->detect_event_type_from_title($moodle_event['name'])),
            'project_id' => $this->detect_project_from_course($moodle_event['courseid'] ?? null),
            'created_by' => 1, // Sistema
            'metadata' => json_encode([
                'source' => 'moodle_calendar',
                'moodle_event_id' => $moodle_event['id'],
                'moodle_course_id' => $moodle_event['courseid'] ?? null,
                'moodle_category' => $moodle_event['categoryid'] ?? null,
                'event_type' => $moodle_event['eventtype'] ?? 'unknown',
                'imported_at' => current_time('mysql')
            ])
        ];
        
        $result = $wpdb->insert($events_table, $event_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao importar evento do Moodle');
        }
        
        $event_id = $wpdb->insert_id;
        
        // Disparar hook
        do_action('ql_calendar_event_created', $event_id, $moodle_event);
        
        return $event_id;
    }
    
    /**
     * Converter datetime do Moodle para formato QL
     */
    private function parse_moodle_datetime($moodle_event) {
        $timestamp = $moodle_event['timestart'] ?? time();
        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone(get_option('timezone_string', 'America/Sao_Paulo')));
        
        // Verificar se é evento de dia inteiro
        $duration = $moodle_event['timeduration'] ?? 0;
        $is_all_day = ($duration == 0 || $duration >= 86400); // 24 horas
        
        return [
            'date' => $dt->format('Y-m-d'),
            'time' => $is_all_day ? null : $dt->format('H:i:s')
        ];
    }
    
    /**
     * Converter end datetime do Moodle para formato QL
     */
    private function parse_moodle_end_datetime($moodle_event) {
        $start_timestamp = $moodle_event['timestart'] ?? time();
        $duration = $moodle_event['timeduration'] ?? 0;
        
        if ($duration > 0) {
            $end_timestamp = $start_timestamp + $duration;
            $dt = new DateTime('@' . $end_timestamp);
            $dt->setTimezone(new DateTimeZone(get_option('timezone_string', 'America/Sao_Paulo')));
            
            $is_all_day = ($duration >= 86400); // 24 horas
            
            return [
                'date' => $dt->format('Y-m-d'),
                'time' => $is_all_day ? null : $dt->format('H:i:s')
            ];
        }
        
        // Se não tem duração, usar mesma data/hora do início
        return $this->parse_moodle_datetime($moodle_event);
    }
    
    /**
     * Detectar tipo de evento baseado no título
     */
    private function detect_event_type_from_title($title) {
        $title = strtolower($title);
        
        if (strpos($title, 'reunião') !== false || strpos($title, 'meeting') !== false) {
            return 'meeting';
        } elseif (strpos($title, 'assembleia') !== false) {
            return 'assembleia';
        } elseif (strpos($title, 'workshop') !== false || strpos($title, 'oficina') !== false) {
            return 'workshop';
        } elseif (strpos($title, 'prazo') !== false || strpos($title, 'deadline') !== false) {
            return 'deadline';
        }
        
        return 'meeting'; // Default
    }
    
    /**
     * Obter cor baseada no tipo de evento
     */
    private function get_color_for_event_type($type) {
        $colors = [
            'meeting' => '#007bff',
            'assembleia' => '#dc3545',
            'workshop' => '#28a745',
            'deadline' => '#ffc107'
        ];
        
        return $colors[$type] ?? '#6c757d';
    }
    
    /**
     * Detectar projeto baseado no curso do Moodle
     */
    private function detect_project_from_course($course_id) {
        if (!$course_id) {
            return null;
        }
        
        // Buscar projeto mapeado para este curso
        global $wpdb;
        $projects_table = $wpdb->prefix . 'ql_projects';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $projects_table 
             WHERE JSON_EXTRACT(metadata, '$.moodle_course_id') = %d",
            $course_id
        ));
    }
    
    /**
     * Sincronizar eventos do WordPress (The Events Calendar, etc.)
     */
    public function sync_wordpress_calendar_events() {
        $synced_count = 0;
        
        // Integração com The Events Calendar plugin
        if (class_exists('Tribe__Events__Main')) {
            $synced_count += $this->sync_tribe_events();
        }
        
        // Integração com Event Organiser plugin
        if (function_exists('eo_get_events')) {
            $synced_count += $this->sync_event_organiser_events();
        }
        
        // Integração com eventos nativos do WordPress (posts customizados)
        $synced_count += $this->sync_custom_wp_events();
        
        return $synced_count;
    }
    
    /**
     * Sincronizar com The Events Calendar
     */
    private function sync_tribe_events() {
        if (!class_exists('Tribe__Events__Main')) {
            return 0;
        }
        
        $events = tribe_get_events([
            'posts_per_page' => 100,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+3 months'))
        ]);
        
        $synced_count = 0;
        
        foreach ($events as $event) {
            $result = $this->import_tribe_event($event);
            if (!is_wp_error($result)) {
                $synced_count++;
            }
        }
        
        return $synced_count;
    }
    
    /**
     * Importar evento do The Events Calendar
     */
    private function import_tribe_event($tribe_event) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        // Verificar se evento já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $events_table WHERE JSON_EXTRACT(metadata, '$.wp_event_id') = %s",
            $tribe_event->ID
        ));
        
        if ($existing) {
            return $existing;
        }
        
        $start_date = tribe_get_start_date($tribe_event, false, 'Y-m-d');
        $start_time = tribe_get_start_date($tribe_event, false, 'H:i:s');
        $end_date = tribe_get_end_date($tribe_event, false, 'Y-m-d');
        $end_time = tribe_get_end_date($tribe_event, false, 'H:i:s');
        
        $event_data = [
            'title' => sanitize_text_field($tribe_event->post_title),
            'description' => sanitize_textarea_field($tribe_event->post_content),
            'event_type' => $this->detect_event_type_from_title($tribe_event->post_title),
            'event_date' => $start_date,
            'event_time' => tribe_event_is_all_day($tribe_event) ? null : $start_time,
            'end_date' => $end_date,
            'end_time' => tribe_event_is_all_day($tribe_event) ? null : $end_time,
            'location' => sanitize_text_field(tribe_get_venue($tribe_event)),
            'color' => $this->get_color_for_event_type($this->detect_event_type_from_title($tribe_event->post_title)),
            'project_id' => null,
            'created_by' => 1,
            'metadata' => json_encode([
                'source' => 'wordpress_tribe_events',
                'wp_event_id' => $tribe_event->ID,
                'imported_at' => current_time('mysql')
            ])
        ];
        
        $result = $wpdb->insert($events_table, $event_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao importar evento do WordPress');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Sincronizar eventos customizados do WordPress
     */
    private function sync_custom_wp_events() {
        // Buscar posts do tipo 'event' ou similares
        $events = get_posts([
            'post_type' => ['event', 'ql_event'],
            'posts_per_page' => 100,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'event_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>=',
                    'type' => 'DATE'
                ]
            ]
        ]);
        
        $synced_count = 0;
        
        foreach ($events as $event) {
            $result = $this->import_custom_wp_event($event);
            if (!is_wp_error($result)) {
                $synced_count++;
            }
        }
        
        return $synced_count;
    }
    
    /**
     * Importar evento customizado do WordPress
     */
    private function import_custom_wp_event($wp_event) {
        global $wpdb;
        
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        
        // Verificar se evento já existe
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $events_table WHERE JSON_EXTRACT(metadata, '$.wp_event_id') = %s",
            $wp_event->ID
        ));
        
        if ($existing) {
            return $existing;
        }
        
        $event_date = get_post_meta($wp_event->ID, 'event_date', true) ?: date('Y-m-d');
        $event_time = get_post_meta($wp_event->ID, 'event_time', true);
        $end_date = get_post_meta($wp_event->ID, 'end_date', true) ?: $event_date;
        $end_time = get_post_meta($wp_event->ID, 'end_time', true);
        
        $event_data = [
            'title' => sanitize_text_field($wp_event->post_title),
            'description' => sanitize_textarea_field($wp_event->post_content),
            'event_type' => $this->detect_event_type_from_title($wp_event->post_title),
            'event_date' => $event_date,
            'event_time' => $event_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'location' => sanitize_text_field(get_post_meta($wp_event->ID, 'event_location', true)),
            'color' => $this->get_color_for_event_type($this->detect_event_type_from_title($wp_event->post_title)),
            'project_id' => get_post_meta($wp_event->ID, 'project_id', true),
            'created_by' => $wp_event->post_author,
            'metadata' => json_encode([
                'source' => 'wordpress_custom',
                'wp_event_id' => $wp_event->ID,
                'imported_at' => current_time('mysql')
            ])
        ];
        
        $result = $wpdb->insert($events_table, $event_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao importar evento customizado');
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Encontrar projeto por nome
     */
    private function find_project_by_name($name) {
        global $wpdb;
        
        $projects_table = $wpdb->prefix . 'ql_projects';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $projects_table WHERE name LIKE %s",
            '%' . $wpdb->esc_like($name) . '%'
        ));
    }
    
    /**
     * Encontrar projeto por slug
     */
    private function find_project_by_slug($slug) {
        global $wpdb;
        
        $projects_table = $wpdb->prefix . 'ql_projects';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $projects_table WHERE slug = %s",
            $slug
        ));
    }
    
    /**
     * Mapear evento para instâncias organizacionais
     */
    public function map_event_to_instances($event_id, $google_event = null) {
        if (!class_exists('QL_Instances')) {
            return;
        }
        
        $instances = QL_Instances::get_instance();
        
        // Obter evento do banco
        global $wpdb;
        $events_table = $wpdb->prefix . 'ql_calendar_events';
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $events_table WHERE id = %d",
            $event_id
        ));
        
        if (!$event) return;
        
        // Mapear baseado no tipo de evento e conteúdo
        $title = strtolower($event->title);
        $description = strtolower($event->description);
        
        // Detectar assembleias
        if (strpos($title, 'assembleia') !== false) {
            $this->create_assembleia_instance($event);
        }
        
        // Detectar reuniões de círculo
        if (strpos($title, 'círculo') !== false || strpos($description, 'círculo') !== false) {
            $this->map_event_to_circle($event);
        }
        
        // Detectar eventos de núcleo
        if (strpos($title, 'núcleo') !== false || strpos($description, 'núcleo') !== false) {
            $this->map_event_to_nucleus($event);
        }
        
        error_log("QL Calendar: Event {$event_id} mapped to instances");
    }
    
    /**
     * Criar instância de assembleia baseada em evento
     */
    private function create_assembleia_instance($event) {
        if (!class_exists('QL_Instances')) {
            return;
        }
        
        $instances = QL_Instances::get_instance();
        
        // Verificar se assembleia já existe para esta data
        global $wpdb;
        $instances_table = $wpdb->prefix . 'ql_instances';
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $instances_table 
             WHERE type = 'assembleia' 
             AND JSON_EXTRACT(metadata, '$.event_date') = %s",
            $event->event_date
        ));
        
        if ($existing) return $existing;
        
        // Criar instância de assembleia
        $assembleia_data = [
            'description' => "Assembleia convocada para {$event->event_date}",
            'metadata' => [
                'event_id' => $event->id,
                'event_date' => $event->event_date,
                'event_time' => $event->event_time,
                'location' => $event->location,
                'agenda' => $event->description,
                'source' => 'google_calendar_import'
            ]
        ];
        
        $result = $instances->create_instance(
            'assembleia',
            $event->title,
            1, // Sistema
            $assembleia_data
        );
        
        if (!is_wp_error($result)) {
            error_log("QL Calendar: Assembleia instance created from event {$event->id}");
        }
        
        return $result;
    }
    
    /**
     * Mapear evento para círculo existente
     */
    private function map_event_to_circle($event) {
        // Tentar identificar qual círculo baseado no título/descrição
        // Por enquanto apenas registra o evento
        error_log("QL Calendar: Event {$event->id} identified as circle meeting");
    }
    
    /**
     * Mapear evento para núcleo existente
     */
    private function map_event_to_nucleus($event) {
        // Tentar identificar qual núcleo baseado no local ou descrição
        // Por enquanto apenas registra o evento
        error_log("QL Calendar: Event {$event->id} identified as nucleus event");
    }
    
    /**
     * AJAX - Configurar Moodle Calendar
     */
    public function ajax_setup_moodle_calendar() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $config_data = [
            'course_filter' => array_map('intval', $_POST['course_filter'] ?? []),
            'event_types' => $_POST['event_types'] ?? ['course', 'user', 'site']
        ];
        
        $result = $this->setup_moodle_calendar_integration($config_data);
        
        if ($result) {
            wp_send_json_success(['message' => 'Moodle Calendar configurado com sucesso']);
        } else {
            wp_send_json_error(['message' => 'Erro ao configurar Moodle Calendar']);
        }
    }
    
    /**
     * AJAX - Sincronizar Moodle Calendar
     */
    public function ajax_sync_moodle_calendar() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissões insuficientes');
        }
        
        $synced_count = $this->sync_moodle_calendar_events();
        
        if ($synced_count !== false) {
            wp_send_json_success([
                'message' => "Sincronizados {$synced_count} eventos do Moodle/WordPress",
                'count' => $synced_count
            ]);
        } else {
            wp_send_json_error(['message' => 'Erro na sincronização']);
        }
    }
    
    /**
     * AJAX - Sincronizar WordPress Calendar
     */
    public function ajax_sync_wp_calendar() {
        if (!wp_verify_nonce($_POST['nonce'], 'ql_admin_nonce')) {
            wp_die('Nonce inválido');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_die('Permissões insuficientes');
        }
        
        $synced_count = $this->sync_wordpress_calendar_events();
        
        if ($synced_count !== false) {
            wp_send_json_success([
                'message' => "Sincronizados {$synced_count} eventos do WordPress",
                'count' => $synced_count
            ]);
        } else {
            wp_send_json_error(['message' => 'Erro na sincronização']);
        }
    }
}