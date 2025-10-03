<?php
/**
 * Classe para gestão de colunas dos boards
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Column {
    
    private static $instance = null;
    private $table_name;
    
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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ql_columns';
    }
    
    /**
     * Criar coluna
     */
    public function create($data) {
        global $wpdb;
        
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['board_id'])) {
            return new WP_Error('missing_data', 'Nome e ID do board são obrigatórios');
        }
        
        // Se posição não foi especificada, usar próxima disponível
        if (!isset($data['position'])) {
            $data['position'] = $this->get_next_position($data['board_id']);
        }
        
        $column_data = [
            'name' => sanitize_text_field($data['name']),
            'description' => !empty($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'board_id' => intval($data['board_id']),
            'position' => intval($data['position']),
            'task_limit' => !empty($data['task_limit']) ? intval($data['task_limit']) : 0,
            'color' => !empty($data['color']) ? sanitize_hex_color($data['color']) : '#f4f4f4',
            'hide_in_dashboard' => !empty($data['hide_in_dashboard']) ? 1 : 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($this->table_name, $column_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao criar coluna: ' . $wpdb->last_error);
        }
        
        $column_id = $wpdb->insert_id;
        
        // Hook para extensibilidade
        do_action('ql_column_created', $column_id, $column_data);
        
        return $column_id;
    }
    
    /**
     * Obter próxima posição disponível
     */
    private function get_next_position($board_id) {
        global $wpdb;
        
        $max_position = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) FROM {$this->table_name} WHERE board_id = %d",
            $board_id
        ));
        
        return ($max_position !== null) ? $max_position + 1 : 0;
    }
    
    /**
     * Obter coluna por ID
     */
    public function get_by_id($column_id) {
        global $wpdb;
        
        $column = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $column_id
        ), ARRAY_A);
        
        if (!$column) {
            return null;
        }
        
        // Adicionar tarefas da coluna
        $column['tasks'] = $this->get_column_tasks($column_id);
        $column['tasks_count'] = count($column['tasks']);
        
        return $column;
    }
    
    /**
     * Obter colunas por board
     */
    public function get_by_board($board_id, $include_tasks = true) {
        global $wpdb;
        
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE board_id = %d ORDER BY position ASC",
            $board_id
        ), ARRAY_A);
        
        if ($include_tasks) {
            foreach ($columns as &$column) {
                $column['tasks'] = $this->get_column_tasks($column['id']);
                $column['tasks_count'] = count($column['tasks']);
            }
        }
        
        return $columns;
    }
    
    /**
     * Obter tarefas da coluna
     */
    private function get_column_tasks($column_id) {
        if (!class_exists('QL_Task')) {
            return [];
        }
        
        $task_model = QL_Task::get_instance();
        return $task_model->get_by_column($column_id);
    }
    
    /**
     * Atualizar coluna
     */
    public function update($column_id, $data) {
        global $wpdb;
        
        $update_data = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['position'])) {
            $update_data['position'] = intval($data['position']);
        }
        
        if (isset($data['task_limit'])) {
            $update_data['task_limit'] = intval($data['task_limit']);
        }
        
        if (isset($data['color'])) {
            $update_data['color'] = sanitize_hex_color($data['color']);
        }
        
        if (isset($data['hide_in_dashboard'])) {
            $update_data['hide_in_dashboard'] = !empty($data['hide_in_dashboard']) ? 1 : 0;
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $column_id],
            null,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao atualizar coluna: ' . $wpdb->last_error);
        }
        
        // Hook para extensibilidade
        do_action('ql_column_updated', $column_id, $update_data);
        
        return true;
    }
    
    /**
     * Excluir coluna
     */
    public function delete($column_id) {
        global $wpdb;
        
        // Verificar se coluna existe
        $column = $this->get_by_id($column_id);
        if (!$column) {
            return new WP_Error('not_found', 'Coluna não encontrada');
        }
        
        // Mover tarefas para primeira coluna do board (se houver)
        $this->move_tasks_to_first_column($column_id, $column['board_id']);
        
        // Excluir coluna
        $result = $wpdb->delete(
            $this->table_name,
            ['id' => $column_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Erro ao excluir coluna: ' . $wpdb->last_error);
        }
        
        // Reordenar colunas restantes
        $this->reorder_columns($column['board_id']);
        
        // Hook para extensibilidade
        do_action('ql_column_deleted', $column_id);
        
        return true;
    }
    
    /**
     * Mover tarefas para primeira coluna do board
     */
    private function move_tasks_to_first_column($column_id, $board_id) {
        global $wpdb;
        
        // Obter primeira coluna do board
        $first_column = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} 
             WHERE board_id = %d AND id != %d 
             ORDER BY position ASC 
             LIMIT 1",
            $board_id, $column_id
        ));
        
        if ($first_column && class_exists('QL_Task')) {
            $task_table = $wpdb->prefix . 'ql_tasks';
            
            $wpdb->update(
                $task_table,
                ['column_id' => $first_column],
                ['column_id' => $column_id],
                ['%d'],
                ['%d']
            );
        }
    }
    
    /**
     * Reordenar colunas após exclusão
     */
    private function reorder_columns($board_id) {
        global $wpdb;
        
        $columns = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE board_id = %d ORDER BY position ASC",
            $board_id
        ), ARRAY_A);
        
        foreach ($columns as $index => $column) {
            $wpdb->update(
                $this->table_name,
                ['position' => $index],
                ['id' => $column['id']],
                ['%d'],
                ['%d']
            );
        }
    }
    
    /**
     * Reordenar colunas
     */
    public function reorder($column_positions) {
        global $wpdb;
        
        foreach ($column_positions as $column_id => $position) {
            $wpdb->update(
                $this->table_name,
                ['position' => intval($position), 'updated_at' => current_time('mysql')],
                ['id' => intval($column_id)],
                ['%d', '%s'],
                ['%d']
            );
        }
        
        // Hook para extensibilidade
        do_action('ql_columns_reordered', $column_positions);
        
        return true;
    }
    
    /**
     * Excluir colunas por board
     */
    public function delete_by_board($board_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            ['board_id' => $board_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Duplicar colunas de um board para outro
     */
    public function duplicate_board_columns($source_board_id, $target_board_id) {
        $source_columns = $this->get_by_board($source_board_id, false);
        
        foreach ($source_columns as $column) {
            $new_column_data = [
                'name' => $column['name'],
                'description' => $column['description'],
                'board_id' => $target_board_id,
                'position' => $column['position'],
                'task_limit' => $column['task_limit'],
                'color' => $column['color'],
                'hide_in_dashboard' => $column['hide_in_dashboard']
            ];
            
            $this->create($new_column_data);
        }
        
        return true;
    }
    
    /**
     * Verificar limite de tarefas na coluna
     */
    public function check_task_limit($column_id) {
        $column = $this->get_by_id($column_id);
        
        if (!$column || $column['task_limit'] <= 0) {
            return true; // Sem limite ou coluna não encontrada
        }
        
        return $column['tasks_count'] < $column['task_limit'];
    }
    
    /**
     * Obter estatísticas da coluna
     */
    public function get_column_stats($column_id) {
        if (!class_exists('QL_Task')) {
            return [
                'total_tasks' => 0,
                'completed_tasks' => 0,
                'pending_tasks' => 0,
                'overdue_tasks' => 0
            ];
        }
        
        global $wpdb;
        $task_table = $wpdb->prefix . 'ql_tasks';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN due_date < NOW() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
             FROM {$task_table} 
             WHERE column_id = %d",
            $column_id
        ), ARRAY_A);
        
        return [
            'total_tasks' => intval($stats['total_tasks']),
            'completed_tasks' => intval($stats['completed_tasks']),
            'pending_tasks' => intval($stats['pending_tasks']),
            'overdue_tasks' => intval($stats['overdue_tasks'])
        ];
    }
    
    /**
     * Buscar colunas
     */
    public function search($search_term, $board_id = null) {
        global $wpdb;
        
        $where_clause = "WHERE (name LIKE %s OR description LIKE %s)";
        $params = ["%{$search_term}%", "%{$search_term}%"];
        
        if ($board_id) {
            $where_clause .= " AND board_id = %d";
            $params[] = $board_id;
        }
        
        $sql = "SELECT * FROM {$this->table_name}
                {$where_clause}
                ORDER BY board_id, position ASC";
        
        $columns = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        
        // Adicionar dados adicionais
        foreach ($columns as &$column) {
            $column['tasks_count'] = count($this->get_column_tasks($column['id']));
        }
        
        return $columns;
    }
}