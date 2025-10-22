# Quilombo Laboratório

Plugin WordPress para gestão de projetos colaborativos com integração Moodle e sistema de trilhas de aprendizagem.

## Versão Atual: 2.1.0

## Descrição

O Quilombo Laboratório oferece um sistema completo de gestão de projetos baseado em metodologias ágeis, com foco em trilhas de aprendizagem, pesquisa e criação. Integra-se nativamente com Moodle e oferece quadros Kanban personalizáveis por tipo de projeto.

## Principais Funcionalidades

- **Gestão de Projetos**: Sistema Kanban com quadros personalizáveis
- **Trilhas de Aprendizagem**: Templates especializados para diferentes tipos de projetos
- **Integração Moodle**: Sincronização automática de cursos e usuários
- **Sistema de Tarefas**: Gestão completa com prioridades, prazos e anexos
- **Colaboração**: Gerenciamento de membros e permissões por projeto
- **Relatórios**: Dashboard com métricas e acompanhamento de progresso

## Tipos de Trilhas

### Trilhas de Aprendizagem
- Planejamento → Estudando → Praticando → Revisão → Concluído

### Trilhas de Pesquisa  
- Questão de Pesquisa → Coleta de Dados → Análise → Validação → Publicação

### Trilhas de Criação
- Problema → Ideação → Planejamento → Desenvolvimento → Avaliação → Concluída

## Requisitos

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- Plugin Gestão Coletiva (opcional, para funcionalidades financeiras)

## Instalação

1. Faça upload do plugin para `/wp-content/plugins/quilombo-lab/`
2. Ative o plugin no painel administrativo do WordPress
3. Configure as integrações em `Quilombo Lab > Configurações`

## Configuração

### Integração com Moodle

1. Acesse `Quilombo Lab > Moodle`
2. Configure a URL e token do Moodle
3. Execute a sincronização inicial

### Criação de Projetos

1. Acesse `Quilombo Lab > Projetos`
2. Clique em "Novo Projeto"
3. Selecione o tipo de trilha apropriado
4. Configure membros e permissões

## Shortcodes

```php
// Exibir quadro de projeto
[ql_projeto slug="meu-projeto"]

// Listar projetos do usuário
[ql_projetos_usuario]

// Calendário de tarefas
[ql_calendario]
```

## Hooks para Desenvolvedores

```php
// Quando projeto é criado
add_action('ql_project_created', 'minha_funcao', 10, 2);

// Quando tarefa é atualizada
add_action('ql_task_updated', 'minha_funcao', 10, 2);

// Filtrar tipos de templates
add_filter('ql_project_templates', 'meus_templates');
```

## Suporte

Para suporte e documentação técnica, consulte a [documentação oficial](https://quilombociencia.org/docs) ou entre em contato através do [GitHub](https://github.com/quilombociencia/quilombo-lab).

## Licença

GPL v2 ou superior

## Changelog

### 2.1.0
- Correção: Eliminados quadros duplicados ao alterar tipo de trilha
- Melhoria: Migração automática de tarefas entre templates
- Melhoria: Sistema de limpeza inteligente de duplicatas

### 2.0.0
- Nova: Integração completa com Moodle
- Nova: Sistema de trilhas especializadas
- Nova: Templates de quadros por tipo de projeto

---

**Desenvolvido por Quilombo Ciência** | [quilombociencia.org](https://quilombociencia.org)