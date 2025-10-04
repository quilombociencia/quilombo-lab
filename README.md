# Quilombo Lab de Projetos

Sistema completo de gestão de projetos integrado ao Plugin Gestão Coletiva. Gestão de projetos nativa do WordPress baseada em trilhas do Moodle.

## Versão Atual: 1.0.3-production

## Descrição

O **Quilombo Lab de Projetos** é um plugin WordPress que implementa um sistema robusto de gestão de projetos com integração nativa ao Moodle através do Plugin Gestão Coletiva. Oferece interface Kanban, gestão de tarefas, sincronização de usuários e trilhas educacionais.

## Principais Funcionalidades

### 🎯 Gestão de Projetos
- **Interface Visual Consistente**: Cards padronizados com altura uniforme e design responsivo
- **Descrições Limpas**: Remoção automática de HTML e truncamento de texto longo
- **Status Inteligente**: Correção automática de status de projetos
- **Vinculação Moodle**: Integração completa com trilhas e cursos

### 🔐 Sistema de Permissões Robusto
- **Auto-configuração**: Capabilities configuradas automaticamente na ativação
- **Níveis de Acesso**: Administrator, Editor, Author com permissões específicas
- **Correção Automática**: Sistema detecta e corrige problemas de permissão
- **Segurança**: Verificações contínuas de integridade

### 📋 Quadros Kanban
- **Gestão Visual**: Interface Kanban para acompanhamento de tarefas
- **Múltiplos Quadros**: Suporte a vários quadros por projeto
- **Colaboração**: Sistema de membros e papéis por projeto

### 🔄 Sincronização Inteligente
- **Cache Otimizado**: Limpeza automática de cache problemático
- **Detecção de Problemas**: Sistema identifica e corrige issues conhecidos
- **Logs Detalhados**: Monitoramento completo de operações

## Requisitos do Sistema

- **WordPress**: 5.0 ou superior
- **PHP**: 7.4 ou superior  
- **MySQL**: 5.7 ou superior
- **Plugin Gestão Coletiva**: Recomendado para integração completa
- **Moodle**: Para sincronização de trilhas (opcional)

## Instalação

### Instalação Padrão
1. Faça upload do plugin para `/wp-content/plugins/quilombo-lab/`
2. Ative o plugin no painel administrativo
3. Configure as permissões (feito automaticamente)
4. Acesse o menu "Quilombo Lab" no admin

### Instalação com Gestão Coletiva
1. Instale primeiro o Plugin Gestão Coletiva
2. Configure a integração Moodle no GC
3. Instale o Quilombo Laboratório
4. A sincronização será automática

## Configuração

### Permissões Automáticas
O plugin configura automaticamente as seguintes capabilities:

**Administradores:**
- `manage_laboratory` - Gestão geral do laboratório
- `view_all_projects` - Ver todos os projetos
- `manage_projects` - Gerenciar projetos
- `edit_projects` - Editar projetos
- `delete_projects` - Deletar projetos
- `manage_boards` - Gerenciar quadros Kanban
- `manage_tasks` - Gerenciar tarefas
- `view_reports` - Ver relatórios

**Editores:**
- `view_all_projects`, `manage_projects`, `edit_projects`
- `manage_boards`, `manage_tasks`, `view_reports`

**Autores:**
- `view_all_projects`, `manage_tasks`

### Páginas Automáticas
O plugin cria automaticamente:
- Página `/lab` com shortcode `[ql_dashboard]`

## Uso

### Dashboard Principal
Acesse `wp-admin -> Quilombo Lab -> Dashboard` para:
- Visão geral de todos os projetos
- Estatísticas de quadros e tarefas
- Links rápidos para gestão

### Gestão de Projetos
Acesse `wp-admin -> Quilombo Lab -> Projetos` para:
- Visualizar todos os projetos em cards padronizados
- Acessar quadros Kanban de cada projeto
- Criar novos quadros quando necessário

### Integração com Moodle
Se o Plugin Gestão Coletiva estiver ativo:
- Projetos são criados automaticamente das trilhas Moodle
- Sincronização de usuários e matrículas
- Vinculação bidirecional de dados

## Estrutura de Banco de Dados

### Tabelas Principais
- `wp_ql_projects` - Projetos principais
- `wp_ql_boards` - Quadros Kanban
- `wp_ql_tasks` - Tarefas individuais
- `wp_ql_project_members` - Membros dos projetos
- `wp_ql_moodle_mappings` - Mapeamentos Moodle

### Sistema de Cache
- Transients para consultas otimizadas
- Object cache para dados frequentes
- Limpeza automática de cache problemático

## Solução de Problemas

### Problemas Comuns Resolvidos Automaticamente

**1. Projetos Não Aparecem**
- ✅ **Causa**: Falta de capability `view_all_projects`
- ✅ **Solução**: Plugin adiciona automaticamente na ativação

**2. Status de Projetos Inválido**
- ✅ **Causa**: Status NULL ou vazio no banco
- ✅ **Solução**: Correção automática para 'active'

**3. Cache Desatualizado**
- ✅ **Causa**: Transients antigos interferindo
- ✅ **Solução**: Limpeza automática de cache problemático

**4. HTML nos Resumos**
- ✅ **Causa**: Tags HTML nas descrições
- ✅ **Solução**: `wp_strip_all_tags()` automático com truncamento

### Logs e Monitoramento
Verifique o log de erros do WordPress para:
```
QL: Banco atualizado para versão X.X.X
QL: Corrigido status de N projetos automaticamente  
QL: Capabilities adicionadas automaticamente para usuário X
```

## API e Extensibilidade

### Funções Globais
```php
// Obter projeto por ID
$project = ql_get_project($project_id);

// Obter projetos do usuário atual
$projects = ql_get_current_user_projects();
```

### Hooks Disponíveis
```php
// Após criação de projeto
do_action('ql_project_created', $project_id);

// Antes de renderizar dashboard
apply_filters('ql_dashboard_data', $data);
```

## Changelog

### Versão 1.0.3-production (2025-01-23)
**🎨 Melhorias de Interface**
- Cards de projetos com altura padronizada (350px)
- Remoção automática de HTML das descrições
- Truncamento inteligente de texto longo (120 caracteres)
- CSS separado para melhor manutenção
- Hover effects sutis nos cards

**🔧 Correções Automáticas**
- Sistema de auto-correção de problemas conhecidos
- Correção automática de status de projetos
- Limpeza automática de cache problemático
- Verificação contínua de capabilities

**⚡ Otimizações**
- Capabilities configuradas automaticamente na ativação
- Logs detalhados para monitoramento
- Verificação de integridade do banco aprimorada

**🧹 Limpeza**
- Remoção de scripts de correção desnecessários
- Documentação atualizada e limpa
- Estrutura de arquivos organizada

### Versão 1.0.2-production
- Integração estabilizada com Plugin Gestão Coletiva
- Sistema de sincronização melhorado

### Versão 1.0.0-beta
- Versão inicial do plugin
- Funcionalidades básicas de gestão de projetos

## Suporte

Para questões técnicas:
1. Verifique os logs do WordPress
2. Confirme se as capabilities estão configuradas
3. Teste a conectividade com Moodle (se aplicável)

## Desenvolvimento

### Estrutura de Arquivos
```
quilombo-lab/
├── quilombo-lab.php    # Arquivo principal
├── includes/                   # Classes principais
│   ├── class-ql-admin.php     # Interface administrativa
│   ├── class-ql-project.php   # Gestão de projetos
│   └── class-ql-database.php  # Banco de dados
├── assets/                     # Recursos estáticos
│   └── css/
│       └── project-cards.css  # Estilos dos cards
└── README.md                  # Esta documentação
```

### Padrões de Código
- PSR-4 para autoloading
- WordPress Coding Standards
- Singleton pattern para classes principais
- Sanitização rigorosa de dados

---

**Quilombo Ciência** • Sistema de Gestão de Projetos Educacionais  
**Website**: https://quilombociencia.org  
**Versão**: 1.0.3-production  
**Licença**: GPL v3 or later