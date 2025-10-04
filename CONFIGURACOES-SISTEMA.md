# 🎯 Sistema de Configurações - Quilombo Lab

## 📋 Visão Geral

O sistema de configurações foi completamente reorganizado para eliminar valores hardcoded e tornar o plugin totalmente portável entre diferentes ambientes.

## 🔧 Nova Estrutura de Configurações

### **📍 Acesso**
`Laboratório → ⚙️ Configurações`

### **🗂️ Submenus Organizados**

#### **1. 🎯 Configurações Gerais**
- **Projetos**: Visibilidade padrão, administrador padrão
- **Tarefas**: Prefixo de numeração, status e prioridade padrão
- **Quadros**: Tipo de board padrão
- **Notificações**: Sistema de notificações e email

#### **2. 🔗 Integrações**
- **Moodle**: URL, token da API, curso padrão
- **Bancos Externos**: Nomes de bancos do Moodle e Kanboard
- **Gestão Coletiva**: Integração e auto-criação de lançamentos

#### **3. ⚙️ Sistema**
- **Uploads**: Tamanho máximo, tipos permitidos, diretório
- **Paths**: Banco Kanboard, diretório de logs
- **Permissões**: Boards públicos, autenticação

#### **4. 🛠️ Avançado**
- **Debug**: Logs e níveis de debug
- **Otimização**: Cache e performance
- **CDNs**: URLs de recursos externos
- **Migração**: Ferramentas de backup e migração

## 🚀 Principais Melhorias

### **❌ Antes (Hardcoded)**
```php
// IDs de usuário fixos
'owner_id' => 1,
'created_by' => 1,

// Paths absolutos
$this->kanboard_db_path = '/var/www/html/lab/data/db.sqlite';

// Bancos de dados fixos
"mysql:host=" . DB_HOST . ";dbname=moodle_escola;charset=utf8mb4"
"mysql:host=" . DB_HOST . ";dbname=kanboard;charset=utf8mb4"

// CDNs fixos
'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'
'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'

// Configurações fixas
'task_number_prefix' => 'QL',
'max_file_upload_size' => 10,
'default_task_status' => 'open',
'default_project_visibility' => 'team'
```

### **✅ Depois (Configurável)**
```php
// IDs dinâmicos
'owner_id' => QL_Config::get_default_admin_id(),
'created_by' => QL_Config::get_current_or_default_user_id(),

// Paths configuráveis
$this->kanboard_db_path = QL_Config::get_kanboard_db_path();

// Bancos dinâmicos
$database_name = QL_Config::get_moodle_database_name();
$dsn = QL_Config::get_external_db_dsn($database_name);

// CDNs configuráveis
$cdn_settings = QL_Config::get_cdn_settings();
wp_enqueue_script('fullcalendar', $cdn_settings['fullcalendar']);

// Configurações dinâmicas
QL_Config::get_task_number_prefix(),
QL_Config::get_upload_settings()['max_size'],
QL_Config::get_default_task_status(),
QL_Config::get_default_project_visibility()
```

## 🔄 Migração Automática

### **Executar Migração**
1. Acesse: `/wp-content/plugins/quilombo-lab/migration-hardcoded-to-settings.php`
2. Execute o script como administrador
3. Revise as configurações migradas

### **O que é Migrado**
- ✅ Configurações antigas do plugin
- ✅ Detecção automática de integrações
- ✅ Criação de diretórios necessários
- ✅ Mapeamento de valores hardcoded
- ✅ Backup das configurações antigas

## 📚 Classes Principais

### **QL_Config**
Classe helper para acesso centralizado às configurações
```php
// Exemplos de uso
$admin_id = QL_Config::get_default_admin_id();
$moodle_settings = QL_Config::get_moodle_settings();
$upload_settings = QL_Config::get_upload_settings();
$is_debug = QL_Config::is_debug_mode();
```

### **QL_Settings**
Interface administrativa para gerenciamento de configurações
- Submenus organizados
- Validação e sanitização
- Testes de conexão
- Reset para padrões

## 🔧 Configurações por Categoria

### **Geral (ql_general_settings)**
```php
[
    'default_project_visibility' => 'team',
    'default_admin_user_id' => 1,
    'task_number_prefix' => 'QL',
    'default_task_status' => 'open',
    'default_task_priority' => 'normal',
    'default_board_type' => 'kanban',
    'enable_notifications' => true,
    'notification_email' => 'admin@example.com'
]
```

### **Integração (ql_integration_settings)**
```php
[
    'moodle_url' => 'https://escola.exemplo.org',
    'moodle_token' => 'token_secreto',
    'moodle_default_course_id' => 1,
    'moodle_database_name' => 'moodle_escola',
    'kanboard_database_name' => 'kanboard',
    'gc_integration_enabled' => true,
    'auto_create_gc_expenses' => true
]
```

### **Sistema (ql_system_settings)**
```php
[
    'max_file_upload_size' => 10,
    'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip',
    'upload_directory' => 'quilombo-lab',
    'kanboard_db_path' => '/wp-content/kanboard/db.sqlite',
    'logs_directory' => '/wp-content/logs/quilombo-lab',
    'enable_public_boards' => false,
    'require_login_for_view' => true
]
```

### **Avançado (ql_advanced_settings)**
```php
[
    'debug_mode' => false,
    'log_level' => 'error',
    'enable_data_cache' => true,
    'cache_timeout' => 60,
    'fullcalendar_cdn' => 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
    'fontawesome_cdn' => 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'enable_kanboard_migration' => true,
    'auto_backup_before_migration' => true
]
```

## 🛡️ Segurança e Validação

### **Sanitização Automática**
- URLs validadas com `esc_url_raw()`
- Emails validados com `sanitize_email()`
- Textos sanitizados com `sanitize_text_field()`
- Números validados com `intval()`

### **Nonces e Permissões**
- Todas as configurações protegidas por nonces
- Acesso restrito a `manage_options`
- Validação de dados antes de salvar

### **Fallbacks Seguros**
- Valores padrão para todas as configurações
- Verificação de existência de usuários
- Validação de paths e diretórios

## 🔍 Debugging

### **Verificar Configurações**
```php
// Debug completo (apenas em modo debug)
$all_settings = QL_Config::get_all_settings_for_debug();
var_dump($all_settings);

// Configurações específicas
$moodle = QL_Config::get_moodle_settings();
$upload = QL_Config::get_upload_settings();
```

### **Logs**
```php
// Logs são salvos em: QL_Config::get_logs_directory()
// Level configurável: QL_Config::get_log_level()
```

## 📈 Benefícios

### **🚀 Portabilidade**
- ✅ Funciona em qualquer ambiente
- ✅ Sem paths absolutos hardcoded
- ✅ Bancos de dados configuráveis
- ✅ CDNs personalizáveis

### **🔧 Manutenibilidade**
- ✅ Configurações centralizadas
- ✅ Interface intuitiva
- ✅ Validação automática
- ✅ Backup automático

### **🛡️ Segurança**
- ✅ Validação de entrada
- ✅ Sanitização automática
- ✅ Controle de permissões
- ✅ Fallbacks seguros

### **📊 Monitoramento**
- ✅ Status do sistema em tempo real
- ✅ Testes de conexão
- ✅ Logs configuráveis
- ✅ Relatórios de migração

## 🆘 Solução de Problemas

### **Configurações Não Aparecem**
1. Verificar se classes estão carregadas
2. Limpar cache: `QL_Config::clear_cache()`
3. Verificar permissões de usuário

### **Erro de Migração**
1. Verificar permissões de escrita
2. Executar migração manualmente
3. Restaurar backup se necessário

### **Integrações Falhando**
1. Testar conexões na interface
2. Verificar URLs e tokens
3. Revisar logs de erro

## 🎯 Próximos Passos

1. **Configurar Integrações**: Moodle, GC, etc.
2. **Testar Funcionalidades**: Criar projetos e tarefas
3. **Configurar Backup**: Estratégia de backup regular
4. **Monitorar Logs**: Acompanhar funcionamento
5. **Treinar Usuários**: Interface administrativa

---

**📅 Implementado em**: Dezembro 2024  
**🔄 Versão**: 1.0.3-production+settings  
**👨‍💻 Status**: Pronto para produção