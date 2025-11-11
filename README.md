# Quilombo Laboratório

Plugin WordPress para gestão de projetos colaborativos com integração Moodle e sistema de trilhas de aprendizagem.

## Versão Atual: 1.1.1-production

## Descrição

O Quilombo Laboratório oferece um sistema completo de gestão de projetos baseado em metodologias ágeis, com foco em trilhas de aprendizagem, pesquisa e criação. Integra-se nativamente com Moodle e oferece quadros Kanban personalizáveis por tipo de projeto com sistema de modais robusto para criação e edição de tarefas.

## Principais Funcionalidades

- **Gestão de Projetos**: Sistema Kanban com quadros personalizáveis por trilha
- **Sistema de Modais Robusto**: Interface fluida para criação/edição de tarefas com event delegation
- **Trilhas de Aprendizagem**: Templates especializados para diferentes tipos de projetos
- **Integração Moodle**: Sincronização automática de cursos e usuários
- **Sistema de Tarefas**: Gestão completa com prioridades, prazos e anexos
- **Colaboração**: Gerenciamento de membros e permissões por projeto
- **Relatórios**: Dashboard com métricas e acompanhamento de progresso
- **Interface Aprimorada**: Botões circulares minimalistas e design responsivo

## Tipos de Trilhas

### Trilhas de Aprendizagem
- Planejamento → Estudando → Praticando → Revisão → Concluído

### Trilhas de Pesquisa  
- Questão de Pesquisa → Coleta de Dados → Análise → Validação → Publicação

### Trilhas de Criação
- Problema → Ideação → Planejamento → Desenvolvimento → Avaliação → Concluída

## 🎉 Últimas Implementações (v1.1.0-beta-stable)

### ✨ Sistema de Modais Revolucionário
- **Event Delegation Robusto**: Sistema final com captura automática de eventos
- **Auto-recuperação**: Monitora e religa eventos automaticamente se necessário
- **Performance Otimizada**: Um único listener global no body resolve todos os conflitos

### 🎨 Interface Completamente Renovada
- **Botão Circular Minimalista**: Design elegante com símbolo "+" centralizado
- **Estados Interativos**: Hover, focus e active com animações suaves
- **Responsivo**: Ajustes automáticos para mobile (40px → 36px)
- **Acessibilidade**: Navegação por teclado com outline adequado

### 🔧 Correções Críticas
- **Modal Reabertura**: Resolvido problema onde modais só abriam uma vez
- **DOM Dinâmico**: Sistema resistente a recriação de elementos
- **Event Conflicts**: Eliminados conflitos entre scripts de modal

### 🧹 Limpeza de Código
- **Scripts Legacy**: Movidos para `assets/js/deprecated/`
- **Debug Reduzido**: Logs apenas quando necessário
- **Performance**: Remoção de código desnecessário

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

### 1.1.0-beta-stable (Atual)
- ✨ **Sistema de Modais Robusto**: Event delegation final com auto-recuperação
- 🎨 **Botão Circular Minimalista**: Design "+' centralizado e responsivo
- 🔧 **Correção Modal Reabertura**: Resolvido problema crítico de modais
- 🧹 **Limpeza Legacy**: Scripts antigos movidos para deprecated/
- ✅ **100% Funcional**: Criação/edição de tarefas working perfeitamente

### 1.0.3-production
- ✨ Sistema de auto-correção de problemas conhecidos
- 🎨 Interface dos cards de projetos aprimorada (350px altura padrão)
- 🔧 Corrigidos problemas de capability e cache
- 📚 README completamente reescrito
- 🧹 Removidos scripts de correção desnecessários

### 1.0.2-production  
- 🔧 Integração estabilizada com Plugin Gestão Coletiva
- ⚡ Performance geral melhorada
- 🔧 Correções de bugs menores na interface

### 1.0.0-beta
- ✨ Lançamento inicial do plugin
- 📋 Funcionalidades básicas de gestão de projetos
- 🔗 Integração com Plugin Gestão Coletiva
- 📊 Sistema básico de quadros Kanban

---

**Desenvolvido por Quilombo Ciência** | [quilombociencia.org](https://quilombociencia.org)