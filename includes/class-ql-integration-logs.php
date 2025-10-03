<?php

if (!defined('ABSPATH')) {
    exit;
}

class QL_Integration_Logs {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'create_log_table']);
    }
    
    public function create_log_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            source_system varchar(50) NOT NULL,
            target_system varchar(50) DEFAULT NULL,
            operation varchar(100) NOT NULL,
            entity_type varchar(50) NOT NULL,
            entity_id varchar(100) NOT NULL,
            status enum('success', 'error', 'warning', 'info') NOT NULL,
            message text NOT NULL,
            data longtext DEFAULT NULL,
            user_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_log_type (log_type),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_entity (entity_type, entity_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public static function log($type, $source, $target, $operation, $entity_type, $entity_id, $status, $message, $data = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $log_data = [
            'log_type' => $type,
            'source_system' => $source,
            'target_system' => $target,
            'operation' => $operation,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'status' => $status,
            'message' => $message,
            'data' => is_array($data) || is_object($data) ? json_encode($data) : $data,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table_name, $log_data);
        
        // Log crítico no error_log do WordPress
        if ($status === 'error') {
            error_log("QL Integration Error [{$type}]: {$message} - {$source} -> {$target}");
        }
    }
    
    public static function log_user_sync($source, $target, $user_id, $status, $message, $data = null) {
        self::log('user_sync', $source, $target, 'sync_user', 'user', $user_id, $status, $message, $data);
    }
    
    public static function log_project_sync($source, $target, $project_id, $status, $message, $data = null) {
        self::log('project_sync', $source, $target, 'sync_project', 'project', $project_id, $status, $message, $data);
    }
    
    public static function log_permission_check($system, $user_id, $permission, $status, $message) {
        self::log('permission', $system, null, 'check_permission', 'permission', $permission, $status, $message, ['user_id' => $user_id]);
    }
    
    public static function log_sso_action($operation, $user_id, $status, $message, $data = null) {
        self::log('sso', 'wordpress', 'all_systems', $operation, 'session', $user_id, $status, $message, $data);
    }
    
    public static function log_moodle_integration($operation, $entity_id, $status, $message, $data = null) {
        self::log('moodle_integration', 'wordpress', 'moodle', $operation, 'course', $entity_id, $status, $message, $data);
    }
    
    public static function get_recent_logs($limit = 50, $type = null, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $where_conditions = [];
        $where_values = [];
        
        if ($type) {
            $where_conditions[] = 'log_type = %s';
            $where_values[] = $type;
        }
        
        if ($status) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d";
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($sql, $where_values));
    }
    
    public static function get_error_summary($since_hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $sql = "SELECT 
                    log_type,
                    source_system,
                    target_system,
                    COUNT(*) as error_count,
                    GROUP_CONCAT(DISTINCT message SEPARATOR '; ') as error_messages
                FROM $table_name 
                WHERE status = 'error' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
                GROUP BY log_type, source_system, target_system
                ORDER BY error_count DESC";
        
        return $wpdb->get_results($wpdb->prepare($sql, $since_hours));
    }
    
    public static function get_integration_stats($since_days = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $sql = "SELECT 
                    DATE(created_at) as log_date,
                    log_type,
                    status,
                    COUNT(*) as operation_count
                FROM $table_name 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at), log_type, status
                ORDER BY log_date DESC, log_type";
        
        return $wpdb->get_results($wpdb->prepare($sql, $since_days));
    }
    
    public static function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        self::log('maintenance', 'system', null, 'cleanup_logs', 'logs', 'cleanup', 'info', "Removidos {$deleted} logs antigos");
        
        return $deleted;
    }
    
    public static function get_system_health() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_integration_logs';
        
        // Verificar erros nas últimas 24 horas
        $recent_errors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        ));
        
        // Verificar última sincronização de cada tipo
        $last_syncs = $wpdb->get_results(
            "SELECT log_type, MAX(created_at) as last_sync 
             FROM $table_name 
             WHERE status = 'success' 
             GROUP BY log_type"
        );
        
        // Verificar sistemas que não estão respondendo (sem logs nas últimas 6 horas)
        $inactive_systems = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT source_system 
             FROM $table_name 
             WHERE source_system NOT IN (
                 SELECT DISTINCT source_system 
                 FROM $table_name 
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
             )"
        ));
        
        return [
            'recent_errors' => $recent_errors,
            'last_syncs' => $last_syncs,
            'inactive_systems' => $inactive_systems,
            'health_score' => self::calculate_health_score($recent_errors, count($last_syncs))
        ];
    }
    
    private static function calculate_health_score($errors, $active_integrations) {
        $base_score = 100;
        
        // Reduzir pontuação por erros
        $error_penalty = min($errors * 5, 50);
        
        // Bonificar por integrações ativas
        $integration_bonus = min($active_integrations * 2, 20);
        
        return max(0, $base_score - $error_penalty + $integration_bonus);
    }
}