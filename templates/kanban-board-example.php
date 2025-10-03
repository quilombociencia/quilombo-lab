<?php
/**
 * Template de exemplo para Quadro Kanban com Drag and Drop
 * 
 * Este arquivo demonstra como implementar um quadro Kanban completo
 * com funcionalidade de arrastar e soltar (drag and drop).
 * 
 * @package QuilomboLaboratorio
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Exemplo de dados do quadro (normalmente viria do banco de dados)
$board_data = [
    'id' => 1,
    'name' => 'Desenvolvimento do Site',
    'columns' => [
        [
            'id' => 1,
            'name' => 'Backlog',
            'position' => 0,
            'tasks' => [
                [
                    'id' => 1,
                    'title' => 'Criar página inicial',
                    'description' => 'Desenvolver layout e conteúdo da página inicial',
                    'assigned_user' => 'João Silva',
                    'priority' => 'high',
                    'color' => 'blue'
                ],
                [
                    'id' => 2,
                    'title' => 'Configurar SEO',
                    'description' => 'Otimizar meta tags e estrutura para SEO',
                    'assigned_user' => 'Maria Santos',
                    'priority' => 'normal',
                    'color' => 'green'
                ]
            ]
        ],
        [
            'id' => 2,
            'name' => 'Em Progresso',
            'position' => 1,
            'tasks' => [
                [
                    'id' => 3,
                    'title' => 'Implementar sistema de login',
                    'description' => 'Criar autenticação de usuários',
                    'assigned_user' => 'Pedro Costa',
                    'priority' => 'urgent',
                    'color' => 'red'
                ]
            ]
        ],
        [
            'id' => 3,
            'name' => 'Revisão',
            'position' => 2,
            'tasks' => [
                [
                    'id' => 4,
                    'title' => 'Testar responsividade',
                    'description' => 'Verificar funcionamento em dispositivos móveis',
                    'assigned_user' => 'Ana Lima',
                    'priority' => 'normal',
                    'color' => 'orange'
                ]
            ]
        ],
        [
            'id' => 4,
            'name' => 'Concluído',
            'position' => 3,
            'tasks' => [
                [
                    'id' => 5,
                    'title' => 'Configurar servidor',
                    'description' => 'Configuração inicial do servidor de produção',
                    'assigned_user' => 'João Silva',
                    'priority' => 'normal',
                    'color' => 'green'
                ]
            ]
        ]
    ]
];

// Função auxiliar para obter classe CSS da prioridade
function get_priority_class($priority) {
    switch ($priority) {
        case 'urgent': return 'ql-priority-urgent';
        case 'high': return 'ql-priority-high';
        case 'normal': return 'ql-priority-normal';
        case 'low': return 'ql-priority-low';
        default: return 'ql-priority-normal';
    }
}

// Função auxiliar para obter emoji da prioridade
function get_priority_emoji($priority) {
    switch ($priority) {
        case 'urgent': return '🚨';
        case 'high': return '🔼';
        case 'normal': return '➖';
        case 'low': return '🔽';
        default: return '➖';
    }
}

// Função auxiliar para obter classe CSS da cor
function get_color_class($color) {
    return 'ql-task-color-' . $color;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        🚀 <?php echo esc_html($board_data['name']); ?>
        <span class="ql-board-subtitle">Exemplo de Quadro Kanban com Drag & Drop</span>
    </h1>
    
    <div class="ql-board-actions">
        <button class="button button-primary ql-add-task-btn" data-column-id="1">
            ➕ Nova Tarefa
        </button>
        <button class="button button-secondary" onclick="location.reload()">
            🔄 Atualizar
        </button>
    </div>

    <!-- Quadro Kanban Principal -->
    <div class="ql-kanban-board" data-board-id="<?php echo esc_attr($board_data['id']); ?>">
        
        <?php foreach ($board_data['columns'] as $column): ?>
        <div class="ql-kanban-column" data-column-id="<?php echo esc_attr($column['id']); ?>">
            
            <!-- Cabeçalho da Coluna -->
            <div class="ql-column-header">
                <h3 class="ql-column-title">
                    <?php echo esc_html($column['name']); ?>
                    <span class="ql-task-count">(<?php echo count($column['tasks']); ?>)</span>
                </h3>
                <div class="ql-column-actions">
                    <button class="ql-add-task-btn" data-column-id="<?php echo esc_attr($column['id']); ?>" title="Adicionar Tarefa">
                        ➕
                    </button>
                </div>
            </div>
            
            <!-- Lista de Tarefas (Sortable) -->
            <div class="ql-tasks-list" data-column-id="<?php echo esc_attr($column['id']); ?>">
                
                <?php foreach ($column['tasks'] as $task): ?>
                <div class="ql-kanban-task <?php echo esc_attr(get_priority_class($task['priority']) . ' ' . get_color_class($task['color'])); ?>" 
                     data-task-id="<?php echo esc_attr($task['id']); ?>"
                     data-priority="<?php echo esc_attr($task['priority']); ?>">
                    
                    <!-- Cabeçalho da Tarefa -->
                    <div class="ql-task-header">
                        <span class="ql-task-priority" title="Prioridade: <?php echo esc_attr(ucfirst($task['priority'])); ?>">
                            <?php echo get_priority_emoji($task['priority']); ?>
                        </span>
                        <span class="ql-task-id">#<?php echo esc_html($task['id']); ?></span>
                    </div>
                    
                    <!-- Título da Tarefa -->
                    <h4 class="ql-task-title">
                        <?php echo esc_html($task['title']); ?>
                    </h4>
                    
                    <!-- Descrição da Tarefa -->
                    <?php if (!empty($task['description'])): ?>
                    <p class="ql-task-description">
                        <?php echo esc_html(wp_trim_words($task['description'], 15)); ?>
                    </p>
                    <?php endif; ?>
                    
                    <!-- Responsável -->
                    <?php if (!empty($task['assigned_user'])): ?>
                    <div class="ql-task-assignee">
                        <span class="ql-task-assignee-icon">👤</span>
                        <span class="ql-task-assignee-name"><?php echo esc_html($task['assigned_user']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Indicador de Cor -->
                    <div class="ql-task-color-indicator ql-color-<?php echo esc_attr($task['color']); ?>"></div>
                    
                </div>
                <?php endforeach; ?>
                
                <!-- Mensagem quando não há tarefas -->
                <?php if (empty($column['tasks'])): ?>
                <div class="ql-no-tasks">
                    <p>📋 Nenhuma tarefa nesta coluna</p>
                    <button class="ql-add-task-btn button-link" data-column-id="<?php echo esc_attr($column['id']); ?>">
                        Adicionar primeira tarefa
                    </button>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endforeach; ?>
        
    </div>
    
    <!-- Estatísticas do Quadro -->
    <div class="ql-board-stats">
        <div class="ql-stats-grid">
            <div class="ql-stat-item">
                <span class="ql-stat-number"><?php echo array_sum(array_map(function($col) { return count($col['tasks']); }, $board_data['columns'])); ?></span>
                <span class="ql-stat-label">Total de Tarefas</span>
            </div>
            <div class="ql-stat-item">
                <span class="ql-stat-number"><?php echo count($board_data['columns'][1]['tasks']); ?></span>
                <span class="ql-stat-label">Em Progresso</span>
            </div>
            <div class="ql-stat-item">
                <span class="ql-stat-number"><?php echo count($board_data['columns'][3]['tasks']); ?></span>
                <span class="ql-stat-label">Concluídas</span>
            </div>
            <div class="ql-stat-item">
                <span class="ql-stat-number"><?php echo count($board_data['columns']); ?></span>
                <span class="ql-stat-label">Colunas</span>
            </div>
        </div>
    </div>
</div>

<!-- Estilos Específicos do Exemplo -->
<style>
.ql-board-subtitle {
    font-size: 14px;
    color: #666;
    font-weight: normal;
    display: block;
    margin-top: 5px;
}

.ql-board-actions {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 5px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.ql-column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.ql-column-title {
    margin: 0;
    font-size: 16px;
    color: #23282d;
}

.ql-task-count {
    font-size: 12px;
    color: #666;
    font-weight: normal;
}

.ql-column-actions button {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    border-radius: 3px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.ql-column-actions button:hover {
    opacity: 1;
    background: rgba(0,0,0,0.05);
}

.ql-task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.ql-task-id {
    font-size: 11px;
    color: #666;
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 10px;
}

.ql-task-title {
    margin: 0 0 8px 0;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
}

.ql-task-description {
    font-size: 12px;
    color: #666;
    margin: 0 0 10px 0;
    line-height: 1.4;
}

.ql-task-assignee {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #555;
    margin-bottom: 8px;
}

.ql-task-color-indicator {
    height: 3px;
    border-radius: 2px;
    margin-top: 10px;
}

.ql-color-blue { background: #0073aa; }
.ql-color-green { background: #00a32a; }
.ql-color-red { background: #d63638; }
.ql-color-orange { background: #dba617; }
.ql-color-purple { background: #8b5cf6; }

.ql-priority-urgent { border-left: 3px solid #d63638; }
.ql-priority-high { border-left: 3px solid #dba617; }
.ql-priority-normal { border-left: 3px solid #646970; }
.ql-priority-low { border-left: 3px solid #00a32a; }

.ql-no-tasks {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.ql-no-tasks p {
    margin: 0 0 10px 0;
    font-size: 14px;
}

.button-link {
    background: none !important;
    border: none !important;
    padding: 0 !important;
    color: #0073aa !important;
    text-decoration: underline !important;
    cursor: pointer !important;
}

.ql-board-stats {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 5px;
}

.ql-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
}

.ql-stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 5px;
}

.ql-stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.ql-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>

<!-- Scripts Necessários -->
<script>
// Variáveis globais para o plugin
window.ql_admin = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('ql_admin_nonce'); ?>',
    rest_url: '<?php echo rest_url('quilombo-laboratorio/v1/'); ?>',
    rest_nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    strings: {
        task_moved: 'Tarefa movida com sucesso!',
        error_moving: 'Erro ao mover tarefa',
        task_created: 'Tarefa criada com sucesso!',
        task_updated: 'Tarefa atualizada com sucesso!'
    }
};

// Inicializar quando o documento estiver pronto
jQuery(document).ready(function($) {
    // Verificar se QLKanban está disponível
    if (typeof QLKanban !== 'undefined') {
        // Reinicializar o Kanban para este quadro específico
        QLKanban.init();
        console.log('✅ Quadro Kanban inicializado com drag & drop');
    } else {
        console.warn('⚠️ QLKanban não encontrado. Certifique-se de que kanban.js foi carregado.');
        
        // Fallback: mostrar instruções
        $('<div class="notice notice-warning"><p><strong>Aviso:</strong> Para funcionalidade completa de drag & drop, certifique-se de que o arquivo kanban.js foi carregado.</p></div>')
            .insertAfter('.wp-heading-inline');
    }
});
</script>

<!-- Instruções de Implementação -->
<div class="ql-implementation-guide" style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #0073aa;">
    <h3>📖 Como Implementar Este Exemplo</h3>
    <ol>
        <li><strong>Incluir Scripts:</strong> Certifique-se de que jQuery UI e kanban.js estão carregados</li>
        <li><strong>Incluir Estilos:</strong> O arquivo admin.css deve estar carregado</li>
        <li><strong>Configurar AJAX:</strong> Os endpoints ql_move_task devem estar configurados</li>
        <li><strong>Personalizar:</strong> Adapte as cores, prioridades e campos conforme necessário</li>
    </ol>
    
    <h4>🔧 Arquivos Necessários:</h4>
    <ul>
        <li><code>assets/js/kanban.js</code> - Lógica do drag & drop</li>
        <li><code>assets/css/admin.css</code> - Estilos do quadro Kanban</li>
        <li><code>includes/class-ql-ajax.php</code> - Endpoints AJAX</li>
        <li><code>includes/class-ql-task.php</code> - Modelo de dados das tarefas</li>
    </ul>
    
    <p><strong>💡 Dica:</strong> Este exemplo demonstra todas as funcionalidades implementadas. Para usar em produção, substitua os dados estáticos por consultas ao banco de dados.</p>
</div>