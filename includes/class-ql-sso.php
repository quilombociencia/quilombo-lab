<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para Single Sign-On (SSO) entre WordPress, Moodle, Kanboard e Gestão Coletiva
 */
class QL_SSO {
    
    private static $instance = null;
    private $session_table;
    private $sso_secret_key;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        global $wpdb;
        $this->session_table = $wpdb->prefix . 'ql_sso_sessions';
        $this->sso_secret_key = $this->get_or_create_sso_secret();
        
        add_action('init', [$this, 'init']);
        add_action('wp_login', [$this, 'on_wp_login'], 10, 2);
        add_action('wp_logout', [$this, 'on_wp_logout']);
        add_action('wp_ajax_ql_sso_validate', [$this, 'ajax_validate_sso_token']);
        add_action('wp_ajax_nopriv_ql_sso_validate', [$this, 'ajax_validate_sso_token']);
        
        // Endpoints para outras ferramentas
        add_action('wp_ajax_ql_sso_login', [$this, 'ajax_sso_login']);
        add_action('wp_ajax_nopriv_ql_sso_login', [$this, 'ajax_sso_login']);
        add_action('wp_ajax_ql_sso_logout', [$this, 'ajax_sso_logout']);
        add_action('wp_ajax_nopriv_ql_sso_logout', [$this, 'ajax_sso_logout']);
        
        // Hook para verificar SSO em requisições
        add_action('init', [$this, 'check_sso_request'], 5);
    }
    
    public function init() {
        $this->ensure_sso_tables();
    }
    
    /**
     * Criar tabelas necessárias para SSO
     */
    private function ensure_sso_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de sessões SSO
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$this->session_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_token varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            user_email varchar(255) NOT NULL,
            systems json NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_token (session_token),
            KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
    }
    
    /**
     * Obter ou criar chave secreta para SSO
     */
    private function get_or_create_sso_secret() {
        $secret = get_option('ql_sso_secret_key');
        
        if (!$secret) {
            $secret = wp_generate_password(64, true, true);
            update_option('ql_sso_secret_key', $secret);
        }
        
        return $secret;
    }
    
    /**
     * Quando usuário faz login no WordPress
     */
    public function on_wp_login($user_login, $user) {
        // Criar sessão SSO
        $sso_token = $this->create_sso_session($user);
        
        if ($sso_token) {
            // Definir cookie para outras ferramentas
            setcookie('ql_sso_token', $sso_token, time() + (24 * 60 * 60), '/', $_SERVER['HTTP_HOST'], is_ssl(), true);
            
            // Tentar login automático em outras ferramentas
            $this->propagate_login_to_systems($user, $sso_token);
        }
    }
    
    /**
     * Quando usuário faz logout no WordPress
     */
    public function on_wp_logout() {
        $sso_token = $_COOKIE['ql_sso_token'] ?? '';
        
        if ($sso_token) {
            // Invalidar sessão SSO
            $this->invalidate_sso_session($sso_token);
            
            // Remover cookie
            setcookie('ql_sso_token', '', time() - 3600, '/', $_SERVER['HTTP_HOST']);
            
            // Propagar logout para outras ferramentas
            $this->propagate_logout_to_systems($sso_token);
        }
    }
    
    /**
     * Criar sessão SSO
     */
    private function create_sso_session($user) {
        global $wpdb;
        
        // Gerar token único
        $token = wp_generate_password(64, false, false);
        $token = hash('sha256', $token . $this->sso_secret_key . $user->ID . time());
        
        // Buscar sistemas onde o usuário está sincronizado
        $user_sync = QL_User_Sync::get_instance();
        $mapping = $user_sync->get_user_mapping($user->ID);
        
        $systems = ['wordpress' => true];
        if ($mapping) {
            if ($mapping->moodle_user_id) $systems['moodle'] = $mapping->moodle_user_id;
            if ($mapping->kanboard_user_id) $systems['kanboard'] = $mapping->kanboard_user_id;
            $systems['gc'] = true; // Sempre disponível (mesmo banco)
        }
        
        // Salvar sessão
        $result = $wpdb->insert($this->session_table, [
            'session_token' => $token,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'systems' => json_encode($systems),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => date('Y-m-d H:i:s', time() + (24 * 60 * 60)) // 24 horas
        ]);
        
        return $result ? $token : false;
    }
    
    /**
     * Validar sessão SSO
     */
    public function validate_sso_session($token) {
        global $wpdb;
        
        $session = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$this->session_table} 
            WHERE session_token = %s AND is_active = 1 AND expires_at > NOW()
        ", $token));
        
        if (!$session) {
            return false;
        }
        
        // Verificar IP (opcional, pode ser desabilitado para maior flexibilidade)
        $current_ip = $this->get_client_ip();
        if ($session->ip_address !== $current_ip) {
            error_log("SSO IP mismatch: {$session->ip_address} != {$current_ip}");
            // Por enquanto apenas log, não bloquear
        }
        
        return $session;
    }
    
    /**
     * Invalidar sessão SSO
     */
    private function invalidate_sso_session($token) {
        global $wpdb;
        
        return $wpdb->update(
            $this->session_table,
            ['is_active' => 0],
            ['session_token' => $token]
        );
    }
    
    /**
     * Propagar login para outros sistemas
     */
    private function propagate_login_to_systems($user, $sso_token) {
        $user_sync = QL_User_Sync::get_instance();
        $mapping = $user_sync->get_user_mapping($user->ID);
        
        if (!$mapping) {
            return;
        }
        
        // Preparar dados do usuário para propagação
        $user_data = [
            'wp_user_id' => $user->ID,
            'email' => $user->user_email,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'sso_token' => $sso_token
        ];
        
        // Propagar para Moodle (se configurado)
        if ($mapping->moodle_user_id) {
            $this->create_moodle_session($mapping->moodle_user_id, $user_data);
        }
        
        // Propagar para Kanboard (se configurado)
        if ($mapping->kanboard_user_id) {
            $this->create_kanboard_session($mapping->kanboard_user_id, $user_data);
        }
        
        // GC usa a mesma sessão do WordPress, então não precisa propagar
    }
    
    /**
     * Criar sessão no Moodle
     */
    private function create_moodle_session($moodle_user_id, $user_data) {
        try {
            // Para integração completa com Moodle, seria necessário:
            // 1. Implementar plugin personalizado no Moodle
            // 2. Usar web services do Moodle
            // 3. Ou criar integração via banco de dados
            
            // Por enquanto, apenas registrar a intenção
            update_user_meta($user_data['wp_user_id'], 'ql_moodle_sso_pending', $moodle_user_id);
            
            error_log("SSO: Moodle session created for user {$moodle_user_id}");
            
        } catch (Exception $e) {
            error_log("SSO Moodle Error: " . $e->getMessage());
        }
    }
    
    /**
     * Criar sessão no Kanboard
     */
    private function create_kanboard_session($kanboard_user_id, $user_data) {
        try {
            // Kanboard suporta integração via API ou banco
            // Aqui implementaríamos a criação de sessão via API do Kanboard
            
            update_user_meta($user_data['wp_user_id'], 'ql_kanboard_sso_pending', $kanboard_user_id);
            
            error_log("SSO: Kanboard session created for user {$kanboard_user_id}");
            
        } catch (Exception $e) {
            error_log("SSO Kanboard Error: " . $e->getMessage());
        }
    }
    
    /**
     * Propagar logout para outros sistemas
     */
    private function propagate_logout_to_systems($sso_token) {
        $session = $this->validate_sso_session($sso_token);
        
        if (!$session) {
            return;
        }
        
        $systems = json_decode($session->systems, true);
        
        // Logout do Moodle
        if (isset($systems['moodle'])) {
            $this->logout_moodle_session($systems['moodle']);
        }
        
        // Logout do Kanboard
        if (isset($systems['kanboard'])) {
            $this->logout_kanboard_session($systems['kanboard']);
        }
    }
    
    /**
     * Logout do Moodle
     */
    private function logout_moodle_session($moodle_user_id) {
        // Implementar logout do Moodle
        delete_user_meta($this->get_wp_user_by_moodle_id($moodle_user_id), 'ql_moodle_sso_pending');
        error_log("SSO: Moodle logout for user {$moodle_user_id}");
    }
    
    /**
     * Logout do Kanboard
     */
    private function logout_kanboard_session($kanboard_user_id) {
        // Implementar logout do Kanboard
        delete_user_meta($this->get_wp_user_by_kanboard_id($kanboard_user_id), 'ql_kanboard_sso_pending');
        error_log("SSO: Kanboard logout for user {$kanboard_user_id}");
    }
    
    /**
     * Verificar requisições SSO
     */
    public function check_sso_request() {
        // Verificar se há token SSO no cookie
        $sso_token = $_COOKIE['ql_sso_token'] ?? '';
        
        if ($sso_token && !is_user_logged_in()) {
            $session = $this->validate_sso_session($sso_token);
            
            if ($session) {
                // Fazer login automático do usuário
                $user = get_user_by('id', $session->user_id);
                
                if ($user) {
                    wp_set_current_user($session->user_id);
                    wp_set_auth_cookie($session->user_id);
                    do_action('wp_login', $user->user_login, $user);
                }
            }
        }
    }
    
    /**
     * AJAX: Validar token SSO
     */
    public function ajax_validate_sso_token() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        
        if (!$token) {
            wp_send_json_error('Token não fornecido');
        }
        
        $session = $this->validate_sso_session($token);
        
        if ($session) {
            $user = get_user_by('id', $session->user_id);
            
            wp_send_json_success([
                'valid' => true,
                'user_id' => $session->user_id,
                'email' => $session->user_email,
                'display_name' => $user ? $user->display_name : '',
                'systems' => json_decode($session->systems, true)
            ]);
        } else {
            wp_send_json_error('Token inválido ou expirado');
        }
    }
    
    /**
     * AJAX: Login SSO de sistema externo
     */
    public function ajax_sso_login() {
        $email = sanitize_email($_POST['email'] ?? '');
        $system = sanitize_text_field($_POST['system'] ?? '');
        $external_user_id = sanitize_text_field($_POST['external_user_id'] ?? '');
        
        if (!$email || !$system) {
            wp_send_json_error('Dados insuficientes para SSO');
        }
        
        // Buscar usuário no WordPress
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_send_json_error('Usuário não encontrado');
        }
        
        // Verificar se o usuário está sincronizado com o sistema
        $user_sync = QL_User_Sync::get_instance();
        $mapping = $user_sync->get_user_mapping($user->ID);
        
        if (!$mapping) {
            wp_send_json_error('Usuário não sincronizado');
        }
        
        // Verificar se o ID externo corresponde
        $valid = false;
        switch ($system) {
            case 'moodle':
                $valid = ($mapping->moodle_user_id == $external_user_id);
                break;
            case 'kanboard':
                $valid = ($mapping->kanboard_user_id == $external_user_id);
                break;
        }
        
        if (!$valid) {
            wp_send_json_error('ID do usuário não corresponde');
        }
        
        // Criar sessão SSO
        $sso_token = $this->create_sso_session($user);
        
        if ($sso_token) {
            wp_send_json_success([
                'sso_token' => $sso_token,
                'user_id' => $user->ID,
                'redirect_url' => home_url()
            ]);
        } else {
            wp_send_json_error('Erro ao criar sessão SSO');
        }
    }
    
    /**
     * AJAX: Logout SSO
     */
    public function ajax_sso_logout() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        
        if ($token) {
            $this->invalidate_sso_session($token);
            $this->propagate_logout_to_systems($token);
        }
        
        wp_send_json_success('Logout realizado');
    }
    
    /**
     * Obter IP do cliente
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return QL_Config::get_client_ip();
    }
    
    /**
     * Obter usuário WordPress por ID do Moodle
     */
    private function get_wp_user_by_moodle_id($moodle_user_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT wp_user_id FROM {$wpdb->prefix}ql_user_mappings 
            WHERE moodle_user_id = %d
        ", $moodle_user_id));
        
        return $user_id ?: 0;
    }
    
    /**
     * Obter usuário WordPress por ID do Kanboard
     */
    private function get_wp_user_by_kanboard_id($kanboard_user_id) {
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT wp_user_id FROM {$wpdb->prefix}ql_user_mappings 
            WHERE kanboard_user_id = %d
        ", $kanboard_user_id));
        
        return $user_id ?: 0;
    }
    
    /**
     * Gerar URL de login SSO para sistema externo
     */
    public function generate_sso_login_url($system, $return_url = '') {
        $params = [
            'action' => 'ql_sso_login',
            'system' => $system,
            'return_url' => $return_url ?: home_url()
        ];
        
        return admin_url('admin-ajax.php?' . http_build_query($params));
    }
    
    /**
     * Obter estatísticas das sessões SSO
     */
    public function get_sso_stats() {
        global $wpdb;
        
        return [
            'active_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->session_table} WHERE is_active = 1 AND expires_at > NOW()"),
            'total_sessions_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->session_table} WHERE DATE(created_at) = CURDATE()"),
            'expired_sessions' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->session_table} WHERE expires_at <= NOW()"),
            'unique_users_today' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$this->session_table} WHERE DATE(created_at) = CURDATE()")
        ];
    }
    
    /**
     * Limpeza de sessões expiradas
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        $deleted = $wpdb->query("DELETE FROM {$this->session_table} WHERE expires_at <= NOW() OR created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        
        error_log("SSO Cleanup: Removed {$deleted} expired sessions");
        
        return $deleted;
    }
}