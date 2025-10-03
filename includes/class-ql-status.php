<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para verificação de status do plugin
 */
class QL_Status {
    
    /**
     * Verificar se o plugin pode ser ativado
     */
    public static function can_activate() {
        $checks = self::run_checks();
        
        foreach ($checks as $check) {
            if ($check['type'] === 'error') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Executar verificações de sistema
     */
    public static function run_checks() {
        $checks = [];
        
        // Verificar versão PHP
        $php_version = PHP_VERSION;
        if (version_compare($php_version, '7.4', '>=')) {
            $checks[] = [
                'name' => 'Versão PHP',
                'type' => 'success',
                'message' => "PHP {$php_version} ✅"
            ];
        } else {
            $checks[] = [
                'name' => 'Versão PHP',
                'type' => 'error',
                'message' => "PHP {$php_version} - Requer 7.4+ ❌"
            ];
        }
        
        // Verificar WordPress
        if (defined('ABSPATH')) {
            global $wp_version;
            if (version_compare($wp_version, '5.0', '>=')) {
                $checks[] = [
                    'name' => 'Versão WordPress',
                    'type' => 'success',
                    'message' => "WordPress {$wp_version} ✅"
                ];
            } else {
                $checks[] = [
                    'name' => 'Versão WordPress',
                    'type' => 'warning',
                    'message' => "WordPress {$wp_version} - Recomendado 5.0+ ⚠️"
                ];
            }
        }
        
        // Verificar Plugin Gestão Coletiva
        if (class_exists('GC_Projeto')) {
            $checks[] = [
                'name' => 'Plugin Gestão Coletiva',
                'type' => 'success',
                'message' => 'Plugin Gestão Coletiva ativo ✅'
            ];
            
            // Verificar tabelas do Gestão Coletiva
            global $wpdb;
            $gc_tables = ['gc_projetos', 'gc_lancamentos', 'gc_projeto_membros'];
            $missing_gc_tables = [];
            
            foreach ($gc_tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'") != $wpdb->prefix . $table) {
                    $missing_gc_tables[] = $table;
                }
            }
            
            if (empty($missing_gc_tables)) {
                $checks[] = [
                    'name' => 'Tabelas Gestão Coletiva',
                    'type' => 'success',
                    'message' => 'Tabelas do Gestão Coletiva encontradas ✅'
                ];
            } else {
                $checks[] = [
                    'name' => 'Tabelas Gestão Coletiva',
                    'type' => 'error',
                    'message' => 'Tabelas do Gestão Coletiva não encontradas: ' . implode(', ', $missing_gc_tables) . ' ❌'
                ];
            }
        } else {
            $checks[] = [
                'name' => 'Plugin Gestão Coletiva',
                'type' => 'warning',
                'message' => 'Plugin Gestão Coletiva não detectado ⚠️'
            ];
        }
        
        // Verificar arquivos do plugin
        $required_files = [
            'class-ql-database.php',
            'class-ql-project.php',
            'class-ql-gc-integration.php'
        ];
        
        $missing_files = [];
        foreach ($required_files as $file) {
            if (!file_exists(QL_PLUGIN_PATH . 'includes/' . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (empty($missing_files)) {
            $checks[] = [
                'name' => 'Arquivos do Plugin',
                'type' => 'success',
                'message' => 'Todos os arquivos essenciais presentes ✅'
            ];
        } else {
            $checks[] = [
                'name' => 'Arquivos do Plugin',
                'type' => 'error',
                'message' => 'Arquivos faltando: ' . implode(', ', $missing_files) . ' ❌'
            ];
        }
        
        // Verificar permissões de banco
        if (defined('DB_NAME')) {
            try {
                global $wpdb;
                $wpdb->get_var("SELECT 1");
                $checks[] = [
                    'name' => 'Conexão Banco de Dados',
                    'type' => 'success',
                    'message' => 'Conexão com banco de dados ativa ✅'
                ];
            } catch (Exception $e) {
                $checks[] = [
                    'name' => 'Conexão Banco de Dados',
                    'type' => 'error',
                    'message' => 'Erro de conexão: ' . $e->getMessage() . ' ❌'
                ];
            }
        }
        
        return $checks;
    }
    
    /**
     * Obter status resumido
     */
    public static function get_summary() {
        $checks = self::run_checks();
        
        $summary = [
            'total' => count($checks),
            'success' => 0,
            'warning' => 0,
            'error' => 0,
            'can_activate' => true
        ];
        
        foreach ($checks as $check) {
            $summary[$check['type']]++;
            
            if ($check['type'] === 'error') {
                $summary['can_activate'] = false;
            }
        }
        
        return $summary;
    }
    
    /**
     * Exibir relatório de status
     */
    public static function display_report() {
        $checks = self::run_checks();
        $summary = self::get_summary();
        
        echo "<div class='ql-status-report'>\n";
        echo "<h3>Status do Quilombo Laboratório</h3>\n";
        
        foreach ($checks as $check) {
            $class = 'ql-status-' . $check['type'];
            echo "<div class='{$class}'>{$check['name']}: {$check['message']}</div>\n";
        }
        
        echo "<div class='ql-status-summary'>\n";
        if ($summary['can_activate']) {
            echo "<strong style='color: green;'>✅ Plugin pronto para uso!</strong>\n";
        } else {
            echo "<strong style='color: red;'>❌ Plugin não pode ser ativado. Corrija os erros acima.</strong>\n";
        }
        echo "</div>\n";
        echo "</div>\n";
        
        return $summary['can_activate'];
    }
}