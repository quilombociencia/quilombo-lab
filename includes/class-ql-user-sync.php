<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para sincronização unificada de usuários entre WordPress e Moodle
 * A gestão de projetos é feita através do Quilombo Laboratório
 */
class QL_User_Sync {
    
    private static $instance = null;
    
    // Configurações de conexão
    private $moodle_db_config = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('user_register', [$this, 'on_user_register'], 10, 1);
        add_action('profile_update', [$this, 'on_user_update'], 10, 2);
        add_action('wp_ajax_ql_sync_all_users', [$this, 'ajax_sync_all_users']);
        add_action('wp_ajax_ql_force_user_sync', [$this, 'ajax_force_user_sync']);
        add_action('wp_ajax_ql_import_moodle_users', [$this, 'ajax_import_moodle_users']);
        
        // Hooks para login/logout unificado
        add_action('wp_login', [$this, 'on_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'on_user_logout']);
        
        // Integração com WP SAML Auth
        add_filter('wp_saml_auth_insert_user', [$this, 'filter_saml_user_creation'], 10, 2);
        add_action('wp_saml_auth_new_user_authenticated', [$this, 'on_saml_user_authenticated'], 10, 2);
        
        // Cron job para sincronização automática
        add_action('ql_user_sync_cron', [$this, 'scheduled_user_sync']);
    }
    
    public function init() {
        // Carregar configurações de conexão
        $this->load_db_configs();
        
        // Registrar cron job se não existir
        if (!wp_next_scheduled('ql_user_sync_cron')) {
            wp_schedule_event(time(), 'hourly', 'ql_user_sync_cron');
        }
        
        // Criar tabela de mapeamento de usuários se não existir
        $this->ensure_user_mapping_table();
    }
    
    /**
     * Carregar configurações de banco de dados do Moodle
     */
    private function load_db_configs() {
        // Configuração do Moodle
        $this->moodle_db_config = [
            'host' => DB_HOST,
            'database' => 'moodle_escola',
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'charset' => 'utf8mb4'
        ];
    }
    
    /**
     * Criar/verificar tabela de mapeamento de usuários
     */
    private function ensure_user_mapping_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_mappings';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) NOT NULL,
            moodle_user_id bigint(20) NULL,
            gc_user_id bigint(20) NULL,
            email varchar(255) NOT NULL,
            sync_status varchar(50) DEFAULT 'active',
            last_sync datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY wp_user_id (wp_user_id),
            UNIQUE KEY email (email),
            KEY moodle_user_id (moodle_user_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Sincronizar usuário quando criado no WordPress
     */
    public function on_user_register($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $this->sync_user_to_all_systems($user);
        }
    }
    
    /**
     * Sincronizar usuário quando atualizado no WordPress
     */
    public function on_user_update($user_id, $old_user_data) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $this->sync_user_to_all_systems($user, true);
        }
    }
    
    /**
     * Sincronizar usuário entre WordPress e Moodle
     */
    public function sync_user_to_all_systems($wp_user, $is_update = false) {
        $results = [
            'wp' => true,
            'moodle' => false,
            'mapping' => false
        ];
        
        try {
            // Verificar se já existe mapeamento
            $mapping = $this->get_user_mapping($wp_user->ID);
            
            // Sincronizar com Moodle
            $moodle_user_id = $this->sync_to_moodle($wp_user, $mapping);
            if ($moodle_user_id) {
                $results['moodle'] = $moodle_user_id;
            }
            
            // Atualizar/criar mapeamento
            $mapping_result = $this->update_user_mapping($wp_user->ID, [
                'moodle_user_id' => $results['moodle'],
                'email' => $wp_user->user_email,
                'sync_status' => 'synced'
            ]);
            
            $results['mapping'] = $mapping_result;
            
            error_log("User Sync Success - WP User {$wp_user->ID}: " . json_encode($results));
            
        } catch (Exception $e) {
            error_log("User Sync Error - WP User {$wp_user->ID}: " . $e->getMessage());
            
            // Marcar como erro no mapeamento
            $this->update_user_mapping($wp_user->ID, [
                'email' => $wp_user->user_email,
                'sync_status' => 'error'
            ]);
        }
        
        return $results;
    }
    
    /**
     * Sincronizar usuário com Moodle
     */
    private function sync_to_moodle($wp_user, $existing_mapping = null) {
        try {
            $moodle_db = new PDO(
                "mysql:host={$this->moodle_db_config['host']};dbname={$this->moodle_db_config['database']};charset={$this->moodle_db_config['charset']}",
                $this->moodle_db_config['username'],
                $this->moodle_db_config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Verificar se usuário já existe no Moodle (por email)
            $stmt = $moodle_db->prepare("SELECT id FROM mdl_user WHERE email = ? AND deleted = 0");
            $stmt->execute([$wp_user->user_email]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_user) {
                // Atualizar usuário existente
                $stmt = $moodle_db->prepare("
                    UPDATE mdl_user 
                    SET username = ?, firstname = ?, lastname = ?, timemodified = UNIX_TIMESTAMP()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $wp_user->user_login,
                    $wp_user->first_name ?: $wp_user->display_name,
                    $wp_user->last_name ?: '',
                    $existing_user['id']
                ]);
                
                return $existing_user['id'];
                
            } else {
                // Criar novo usuário
                $username = $this->generate_unique_moodle_username($wp_user->user_login, $moodle_db);
                
                $stmt = $moodle_db->prepare("
                    INSERT INTO mdl_user (
                        username, firstname, lastname, email, 
                        timecreated, timemodified, confirmed, 
                        lang, calendartype, theme
                    ) VALUES (?, ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 1, 'pt_br', 'gregorian', 'boost')
                ");
                
                $stmt->execute([
                    $username,
                    $wp_user->first_name ?: $wp_user->display_name,
                    $wp_user->last_name ?: '',
                    $wp_user->user_email
                ]);
                
                return $moodle_db->lastInsertId();
            }
            
        } catch (Exception $e) {
            error_log("Moodle Sync Error: " . $e->getMessage());
            return false;
        }
    }
    
    
    /**
     * Gerar username único no Moodle
     */
    private function generate_unique_moodle_username($base_username, $moodle_db) {
        $username = $base_username;
        $counter = 1;
        
        while (true) {
            $stmt = $moodle_db->prepare("SELECT id FROM mdl_user WHERE username = ? AND deleted = 0");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                break;
            }
            
            $username = $base_username . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    
    /**
     * Obter mapeamento de usuário
     */
    public function get_user_mapping($wp_user_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ql_user_mappings WHERE wp_user_id = %d",
            $wp_user_id
        ));
    }
    
    /**
     * Atualizar mapeamento de usuário
     */
    public function update_user_mapping($wp_user_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ql_user_mappings';
        
        // Verificar se já existe
        $existing = $this->get_user_mapping($wp_user_id);
        
        $data['wp_user_id'] = $wp_user_id;
        
        if ($existing) {
            // Atualizar
            $where = ['wp_user_id' => $wp_user_id];
            return $wpdb->update($table_name, $data, $where);
        } else {
            // Inserir
            return $wpdb->insert($table_name, $data);
        }
    }
    
    /**
     * Sincronização em lote de todos os usuários
     */
    public function sync_all_users() {
        $users = get_users(['number' => -1]);
        $results = [
            'total' => count($users),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($users as $user) {
            $sync_result = $this->sync_user_to_all_systems($user);
            
            if ($sync_result['moodle'] && $sync_result['mapping']) {
                $results['success']++;
            } else {
                $results['errors']++;
            }
            
            $results['details'][] = [
                'user_id' => $user->ID,
                'email' => $user->user_email,
                'result' => $sync_result
            ];
        }
        
        return $results;
    }
    
    /**
     * Função principal para carregar participantes do Moodle
     * Implementação melhorada para o ambiente Quilombo Ciência
     */
    public function import_users_from_moodle() {
        try {
            $moodle_db = new PDO(
                "mysql:host={$this->moodle_db_config['host']};dbname={$this->moodle_db_config['database']};charset={$this->moodle_db_config['charset']}",
                $this->moodle_db_config['username'],
                $this->moodle_db_config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Buscar usuários do Moodle que não estão no WordPress
            // Incluir usuários não confirmados (confirmed = 0) pois podem ter sido criados via SAML/SSO
            $stmt = $moodle_db->prepare("
                SELECT id, username, firstname, lastname, email, confirmed, deleted, suspended, timecreated, timemodified
                FROM mdl_user
                WHERE deleted = 0
                  AND suspended = 0
                  AND email != ''
                  AND email IS NOT NULL
                  AND username != 'guest'
                  AND id > 1
                ORDER BY timecreated DESC
            ");

            // Log para diagnóstico
            $stmt->execute();
            $moodle_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log de diagnóstico
            error_log("QL User Import: Encontrados " . count($moodle_users) . " usuários no Moodle para importação");
            foreach ($moodle_users as $idx => $u) {
                error_log("QL User Import: [{$idx}] ID={$u['id']}, username={$u['username']}, email={$u['email']}, confirmed={$u['confirmed']}");
            }

            $imported = 0;
            $skipped = 0;

            foreach ($moodle_users as $moodle_user) {
                // Verificar se já existe no WordPress
                $wp_user = get_user_by('email', $moodle_user['email']);
                
                if (!$wp_user) {
                    // Criar usuário no WordPress
                    $username = $this->generate_unique_wp_username($moodle_user['username']);
                    $password = wp_generate_password(12, true);
                    
                    $new_user_id = wp_create_user($username, $password, $moodle_user['email']);
                    
                    if (!is_wp_error($new_user_id)) {
                        // Definir nome completo
                        $display_name = trim($moodle_user['firstname'] . ' ' . $moodle_user['lastname']);
                        if (empty($display_name)) {
                            $display_name = $username;
                        }
                        
                        // Atualizar informações do usuário
                        wp_update_user([
                            'ID' => $new_user_id,
                            'first_name' => $moodle_user['firstname'],
                            'last_name' => $moodle_user['lastname'],
                            'display_name' => $display_name,
                            'role' => 'subscriber' // Papel padrão para participantes
                        ]);
                        
                        // Adicionar meta dados do Moodle
                        update_user_meta($new_user_id, 'imported_from_moodle', true);
                        update_user_meta($new_user_id, 'moodle_user_id', $moodle_user['id']);
                        update_user_meta($new_user_id, 'moodle_import_date', current_time('mysql'));
                        
                        // Criar mapeamento
                        $this->update_user_mapping($new_user_id, [
                            'moodle_user_id' => $moodle_user['id'],
                            'email' => $moodle_user['email'],
                            'sync_status' => 'imported_from_moodle'
                        ]);
                        
                        $imported++;
                        
                        // Log da importação
                        error_log("QL User Import: Usuário {$moodle_user['email']} importado do Moodle (ID: {$moodle_user['id']}) para WP (ID: {$new_user_id})");
                    } else {
                        error_log("QL User Import: Erro ao criar usuário {$moodle_user['email']}: " . $new_user_id->get_error_message());
                    }
                } else {
                    // Atualizar mapeamento se necessário
                    $mapping = $this->get_user_mapping($wp_user->ID);
                    if (!$mapping || !$mapping->moodle_user_id) {
                        $this->update_user_mapping($wp_user->ID, [
                            'moodle_user_id' => $moodle_user['id'],
                            'email' => $moodle_user['email'],
                            'sync_status' => 'mapped_existing'
                        ]);
                    }
                    $skipped++;
                }
            }
            
            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'total_moodle_users' => count($moodle_users)
            ];
            
        } catch (Exception $e) {
            error_log("Import from Moodle Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar username único no WordPress
     */
    private function generate_unique_wp_username($base_username) {
        $username = sanitize_user($base_username);
        $counter = 1;
        
        while (username_exists($username)) {
            $username = sanitize_user($base_username . $counter);
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Login unificado - sincronizar sessão
     */
    public function on_user_login($user_login, $user) {
        // Atualizar último login no mapeamento
        $this->update_user_mapping($user->ID, [
            'sync_status' => 'active'
        ]);
        
        // Aqui pode implementar SSO com outras ferramentas se necessário
        do_action('ql_user_unified_login', $user);
    }
    
    /**
     * Logout unificado
     */
    public function on_user_logout() {
        // Aqui pode implementar logout unificado se necessário
        do_action('ql_user_unified_logout');
    }
    
    /**
     * Sincronização automática via cron
     */
    public function scheduled_user_sync() {
        // Sincronizar apenas usuários modificados recentemente
        $recent_users = get_users([
            'meta_query' => [
                [
                    'key' => 'last_update',
                    'value' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'compare' => '>='
                ]
            ]
        ]);
        
        foreach ($recent_users as $user) {
            $this->sync_user_to_all_systems($user, true);
        }
        
        // Log da sincronização
        error_log("QL User Sync Cron: Synchronized " . count($recent_users) . " users");
    }
    
    /**
     * AJAX: Sincronizar todos os usuários
     */
    public function ajax_sync_all_users() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão insuficiente');
        }
        
        $results = $this->sync_all_users();
        wp_send_json_success($results);
    }
    
    /**
     * AJAX: Forçar sincronização de usuário específico
     */
    public function ajax_force_user_sync() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão insuficiente');
        }
        
        $user_id = intval($_POST['user_id'] ?? 0);
        
        if (!$user_id) {
            wp_send_json_error('ID do usuário inválido');
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            wp_send_json_error('Usuário não encontrado');
        }
        
        $result = $this->sync_user_to_all_systems($user, true);
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Importar usuários do Moodle
     */
    public function ajax_import_moodle_users() {
        check_ajax_referer('ql_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão insuficiente');
        }
        
        try {
            $result = $this->import_users_from_moodle();
            
            if ($result !== false) {
                $message = sprintf(
                    'Importação concluída! %d usuários importados, %d existentes ignorados. Total de usuários no Moodle: %d',
                    $result['imported'],
                    $result['skipped'],
                    $result['total_moodle_users']
                );
                
                wp_send_json_success([
                    'message' => $message,
                    'data' => $result
                ]);
            } else {
                wp_send_json_error('Erro na importação. Verifique os logs para mais detalhes.');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Erro na importação: ' . $e->getMessage());
        }
    }
    
    /**
     * Obter estatísticas de sincronização
     */
    public function get_sync_stats() {
        global $wpdb;
        
        $stats = [
            'total_wp_users' => count_users()['total_users'],
            'total_mappings' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_user_mappings"),
            'synced_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_user_mappings WHERE sync_status = 'synced'"),
            'imported_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_user_mappings WHERE sync_status = 'imported_from_moodle'"),
            'error_users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ql_user_mappings WHERE sync_status = 'error'"),
            'last_sync' => $wpdb->get_var("SELECT MAX(last_sync) FROM {$wpdb->prefix}ql_user_mappings")
        ];
        
        $stats['sync_percentage'] = $stats['total_wp_users'] > 0 ? 
            round(($stats['synced_users'] / $stats['total_wp_users']) * 100, 2) : 0;
            
        return $stats;
    }
    
    /**
     * Funcionalidade especial para SAML: Buscar usuário pelo email e sincronizar se necessário
     * Esta função é chamada quando um usuário faz login via SAML
     */
    public function handle_saml_user_login($email, $user_attributes = []) {
        $wp_user = get_user_by('email', $email);
        
        if (!$wp_user) {
            // Tentar encontrar no Moodle e importar
            try {
                $moodle_db = new PDO(
                    "mysql:host={$this->moodle_db_config['host']};dbname={$this->moodle_db_config['database']};charset={$this->moodle_db_config['charset']}",
                    $this->moodle_db_config['username'],
                    $this->moodle_db_config['password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                
                $stmt = $moodle_db->prepare("
                    SELECT id, username, firstname, lastname, email 
                    FROM mdl_user 
                    WHERE email = ? AND deleted = 0 AND confirmed = 1
                ");
                $stmt->execute([$email]);
                $moodle_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($moodle_user) {
                    // Importar usuário automaticamente
                    $username = $this->generate_unique_wp_username($moodle_user['username']);
                    $password = wp_generate_password(12, true);
                    
                    $new_user_id = wp_create_user($username, $password, $email);
                    
                    if (!is_wp_error($new_user_id)) {
                        // Usar atributos SAML se disponíveis, senão usar dados do Moodle
                        $first_name = $user_attributes['first_name'] ?? $moodle_user['firstname'];
                        $last_name = $user_attributes['last_name'] ?? $moodle_user['lastname'];
                        $display_name = trim($first_name . ' ' . $last_name);
                        
                        if (empty($display_name)) {
                            $display_name = $user_attributes['display_name'] ?? $username;
                        }
                        
                        wp_update_user([
                            'ID' => $new_user_id,
                            'first_name' => $first_name,
                            'last_name' => $last_name,
                            'display_name' => $display_name,
                            'role' => 'subscriber'
                        ]);
                        
                        // Adicionar meta dados
                        update_user_meta($new_user_id, 'imported_from_moodle', true);
                        update_user_meta($new_user_id, 'moodle_user_id', $moodle_user['id']);
                        update_user_meta($new_user_id, 'saml_auto_import', true);
                        update_user_meta($new_user_id, 'moodle_import_date', current_time('mysql'));
                        
                        // Criar mapeamento
                        $this->update_user_mapping($new_user_id, [
                            'moodle_user_id' => $moodle_user['id'],
                            'email' => $email,
                            'sync_status' => 'saml_auto_imported'
                        ]);
                        
                        error_log("SAML Auto Import: Usuário $email importado automaticamente do Moodle via SAML");
                        
                        return get_user_by('id', $new_user_id);
                    }
                }
            } catch (Exception $e) {
                error_log("SAML Auto Import Error: " . $e->getMessage());
            }
        }
        
        return $wp_user;
    }
    
    /**
     * Função auxiliar para obter informações de usuários importados do Moodle
     */
    public function get_imported_users_stats() {
        global $wpdb;
        
        return [
            'total_imported' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}usermeta 
                WHERE meta_key = 'imported_from_moodle' AND meta_value = '1'
            "),
            'saml_imported' => $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}usermeta 
                WHERE meta_key = 'saml_auto_import' AND meta_value = '1'
            "),
            'recent_imports' => $wpdb->get_results("
                SELECT u.ID, u.user_email, u.display_name, um.meta_value as import_date
                FROM {$wpdb->prefix}users u
                INNER JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id
                WHERE um.meta_key = 'moodle_import_date'
                ORDER BY um.meta_value DESC
                LIMIT 10
            ")
        ];
    }
    
    /**
     * Filter para modificar criação de usuários via SAML
     */
    public function filter_saml_user_creation($user_data, $saml_attributes) {
        $email = $user_data['user_email'];
        
        // Tentar encontrar dados no Moodle para enriquecer o usuário
        try {
            $moodle_db = new PDO(
                "mysql:host={$this->moodle_db_config['host']};dbname={$this->moodle_db_config['database']};charset={$this->moodle_db_config['charset']}",
                $this->moodle_db_config['username'],
                $this->moodle_db_config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $stmt = $moodle_db->prepare("
                SELECT id, username, firstname, lastname, email 
                FROM mdl_user 
                WHERE email = ? AND deleted = 0 AND confirmed = 1
            ");
            $stmt->execute([$email]);
            $moodle_user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($moodle_user) {
                // Enriquecer dados do usuário com informações do Moodle
                if (empty($user_data['first_name']) && !empty($moodle_user['firstname'])) {
                    $user_data['first_name'] = $moodle_user['firstname'];
                }
                
                if (empty($user_data['last_name']) && !empty($moodle_user['lastname'])) {
                    $user_data['last_name'] = $moodle_user['lastname'];
                }
                
                if (empty($user_data['display_name'])) {
                    $display_name = trim($moodle_user['firstname'] . ' ' . $moodle_user['lastname']);
                    if (!empty($display_name)) {
                        $user_data['display_name'] = $display_name;
                    }
                }
                
                // Adicionar metadados para posterior mapeamento
                $user_data['moodle_user_id'] = $moodle_user['id'];
                $user_data['moodle_username'] = $moodle_user['username'];
            }
            
        } catch (Exception $e) {
            error_log("SAML User Creation - Moodle lookup error: " . $e->getMessage());
        }
        
        return $user_data;
    }
    
    /**
     * Action executada quando um novo usuário é autenticado via SAML
     */
    public function on_saml_user_authenticated($user, $saml_attributes) {
        // Verificar se temos dados do Moodle para criar mapeamento
        $moodle_user_id = get_user_meta($user->ID, 'moodle_user_id', true);
        
        if (!$moodle_user_id && isset($saml_attributes['moodle_user_id'])) {
            $moodle_user_id = $saml_attributes['moodle_user_id'];
        }
        
        if ($moodle_user_id) {
            // Adicionar meta dados
            update_user_meta($user->ID, 'imported_from_moodle', true);
            update_user_meta($user->ID, 'moodle_user_id', $moodle_user_id);
            update_user_meta($user->ID, 'saml_auto_import', true);
            update_user_meta($user->ID, 'moodle_import_date', current_time('mysql'));
            
            // Criar mapeamento
            $this->update_user_mapping($user->ID, [
                'moodle_user_id' => $moodle_user_id,
                'email' => $user->user_email,
                'sync_status' => 'saml_authenticated'
            ]);
            
            error_log("SAML Authentication: Mapeamento criado para usuário {$user->user_email} (WP: {$user->ID}, Moodle: {$moodle_user_id})");
        }
        
        // Sincronizar com Moodle se necessário
        $this->sync_user_to_all_systems($user, false);
    }
}