<?php
/**
 * Templates para modais de tarefas
 * Quilombo Laboratório
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Modal Principal de Tarefa -->
<div id="ql-task-modal" class="ql-modal ql-task-modal" style="display: none;">
    <div class="ql-modal-overlay">
        <div class="ql-modal-container ql-task-modal-container">
            <!-- Cabeçalho do Modal -->
            <div class="ql-modal-header">
                <div class="ql-task-header-info">
                    <span class="ql-task-number">#QL-000</span>
                    <div class="ql-task-status-badge">
                        <span class="ql-badge ql-badge-open">Aberta</span>
                    </div>
                    <div class="ql-task-priority-badge">
                        <span class="ql-badge ql-badge-priority-normal">Normal</span>
                    </div>
                </div>
                <div class="ql-modal-actions">
                    <button type="button" class="ql-btn ql-btn-icon" id="ql-task-expand">
                        <i class="dashicons dashicons-editor-expand"></i>
                    </button>
                    <button type="button" class="ql-btn ql-btn-icon ql-modal-close">
                        <i class="dashicons dashicons-no-alt"></i>
                    </button>
                </div>
            </div>
            
            <!-- Corpo do Modal -->
            <div class="ql-modal-body">
                <div class="ql-task-modal-content">
                    <!-- Coluna Principal -->
                    <div class="ql-task-main-column">
                        <!-- Título da Tarefa -->
                        <div class="ql-task-title-section">
                            <h2 class="ql-task-title" contenteditable="true" data-placeholder="Digite o título da tarefa...">
                                <!-- Preenchido dinamicamente -->
                            </h2>
                            <div class="ql-task-breadcrumb">
                                <span class="ql-breadcrumb-project">Projeto</span>
                                <i class="dashicons dashicons-arrow-right-alt2"></i>
                                <span class="ql-breadcrumb-board">Quadro</span>
                                <i class="dashicons dashicons-arrow-right-alt2"></i>
                                <span class="ql-breadcrumb-column">Coluna</span>
                            </div>
                        </div>
                        
                        <!-- Descrição -->
                        <div class="ql-task-section">
                            <h3 class="ql-task-section-title">
                                <i class="dashicons dashicons-text-page"></i>
                                Descrição
                            </h3>
                            <div class="ql-task-description-container">
                                <div class="ql-task-description" contenteditable="true" data-placeholder="Adicione uma descrição para esta tarefa...">
                                    <!-- Preenchido dinamicamente -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subtarefas -->
                        <div class="ql-task-section ql-subtasks-section">
                            <h3 class="ql-task-section-title">
                                <i class="dashicons dashicons-list-view"></i>
                                Subtarefas
                                <span class="ql-subtasks-counter">(<span id="ql-subtasks-count">0</span>)</span>
                                <button type="button" class="ql-btn ql-btn-sm ql-btn-secondary ql-add-subtask-btn">
                                    <i class="dashicons dashicons-plus-alt"></i>
                                    Nova Subtarefa
                                </button>
                            </h3>
                            <div class="ql-subtasks-list">
                                <!-- Preenchido dinamicamente -->
                            </div>
                        </div>
                        
                        <!-- Atividades e Comentários -->
                        <div class="ql-task-section ql-activities-section">
                            <h3 class="ql-task-section-title">
                                <i class="dashicons dashicons-format-chat"></i>
                                Atividades
                            </h3>
                            
                            <!-- Formulário de Novo Comentário -->
                            <div class="ql-new-comment-form">
                                <div class="ql-comment-input-container">
                                    <textarea class="ql-comment-textarea" placeholder="Adicionar um comentário..."></textarea>
                                    <div class="ql-comment-actions">
                                        <label class="ql-comment-private">
                                            <input type="checkbox" id="ql-comment-private-check">
                                            Comentário privado
                                        </label>
                                        <div class="ql-comment-buttons">
                                            <button type="button" class="ql-btn ql-btn-sm ql-btn-secondary ql-cancel-comment">Cancelar</button>
                                            <button type="button" class="ql-btn ql-btn-sm ql-btn-primary ql-save-comment">Comentar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Lista de Atividades -->
                            <div class="ql-activities-list">
                                <!-- Preenchido dinamicamente -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Barra Lateral -->
                    <div class="ql-task-sidebar">
                        <!-- Assignee -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Responsável</h4>
                            <div class="ql-assignee-selector">
                                <select id="ql-task-assignee" class="ql-select">
                                    <option value="">Sem responsável</option>
                                    <!-- Preenchido dinamicamente com usuários -->
                                </select>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Status</h4>
                            <div class="ql-status-selector">
                                <select id="ql-task-status" class="ql-select">
                                    <option value="open">Aberta</option>
                                    <option value="in_progress">Em Progresso</option>
                                    <option value="review">Em Revisão</option>
                                    <option value="completed">Concluída</option>
                                    <option value="cancelled">Cancelada</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Prioridade -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Prioridade</h4>
                            <div class="ql-priority-selector">
                                <select id="ql-task-priority" class="ql-select">
                                    <option value="low">Baixa</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">Alta</option>
                                    <option value="urgent">Urgente</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Tipo de Tarefa -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Tipo</h4>
                            <div class="ql-type-selector">
                                <select id="ql-task-type" class="ql-select">
                                    <option value="feature">Funcionalidade</option>
                                    <option value="bug">Bug</option>
                                    <option value="improvement">Melhoria</option>
                                    <option value="research">Pesquisa</option>
                                    <option value="documentation">Documentação</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Datas -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Datas</h4>
                            <div class="ql-dates-container">
                                <div class="ql-date-field">
                                    <label for="ql-task-start-date">Data de Início</label>
                                    <input type="date" id="ql-task-start-date" class="ql-input">
                                </div>
                                <div class="ql-date-field">
                                    <label for="ql-task-due-date">Data Limite</label>
                                    <input type="date" id="ql-task-due-date" class="ql-input">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estimativas -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Estimativas</h4>
                            <div class="ql-estimates-container">
                                <div class="ql-estimate-field">
                                    <label for="ql-task-estimated-hours">Horas Estimadas</label>
                                    <input type="number" id="ql-task-estimated-hours" class="ql-input" min="0" step="0.5">
                                </div>
                                <div class="ql-estimate-field">
                                    <label for="ql-task-actual-hours">Horas Gastas</label>
                                    <input type="number" id="ql-task-actual-hours" class="ql-input" min="0" step="0.5">
                                </div>
                                <div class="ql-estimate-field">
                                    <label for="ql-task-story-points">Story Points</label>
                                    <input type="number" id="ql-task-story-points" class="ql-input" min="0" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Tags -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Tags</h4>
                            <div class="ql-tags-container">
                                <input type="text" id="ql-task-tags" class="ql-input" placeholder="tag1, tag2, tag3">
                                <div class="ql-tags-display"></div>
                            </div>
                        </div>
                        
                        <!-- Cor -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Cor</h4>
                            <div class="ql-color-picker-container">
                                <input type="color" id="ql-task-color" class="ql-color-input" value="#ffffff">
                                <div class="ql-color-presets">
                                    <button type="button" class="ql-color-preset" data-color="#e74c3c" style="background-color: #e74c3c;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#f39c12" style="background-color: #f39c12;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#f1c40f" style="background-color: #f1c40f;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#27ae60" style="background-color: #27ae60;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#3498db" style="background-color: #3498db;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#9b59b6" style="background-color: #9b59b6;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#34495e" style="background-color: #34495e;"></button>
                                    <button type="button" class="ql-color-preset" data-color="#ffffff" style="background-color: #ffffff; border: 1px solid #ddd;"></button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Anexos -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Anexos</h4>
                            <div class="ql-attachments-container">
                                <div class="ql-upload-area">
                                    <input type="file" id="ql-task-file-upload" class="ql-file-input" multiple>
                                    <label for="ql-task-file-upload" class="ql-upload-label">
                                        <i class="dashicons dashicons-cloud-upload"></i>
                                        Arrastar arquivos ou clique para selecionar
                                    </label>
                                </div>
                                <div class="ql-attachments-list">
                                    <!-- Preenchido dinamicamente -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informações da Tarefa -->
                        <div class="ql-task-sidebar-section">
                            <h4 class="ql-sidebar-title">Informações</h4>
                            <div class="ql-task-info">
                                <div class="ql-info-item">
                                    <span class="ql-info-label">Criada por:</span>
                                    <span class="ql-info-value" id="ql-task-creator">-</span>
                                </div>
                                <div class="ql-info-item">
                                    <span class="ql-info-label">Criada em:</span>
                                    <span class="ql-info-value" id="ql-task-created-at">-</span>
                                </div>
                                <div class="ql-info-item">
                                    <span class="ql-info-label">Atualizada em:</span>
                                    <span class="ql-info-value" id="ql-task-updated-at">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Rodapé do Modal -->
            <div class="ql-modal-footer">
                <div class="ql-modal-footer-left">
                    <button type="button" class="ql-btn ql-btn-danger ql-delete-task-btn">
                        <i class="dashicons dashicons-trash"></i>
                        Excluir Tarefa
                    </button>
                </div>
                <div class="ql-modal-footer-right">
                    <button type="button" class="ql-btn ql-btn-secondary ql-modal-close">Fechar</button>
                    <button type="button" class="ql-btn ql-btn-primary ql-save-task-btn">
                        <i class="dashicons dashicons-saved"></i>
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Criação Rápida de Tarefa -->
<div id="ql-quick-task-modal" class="ql-modal ql-quick-task-modal" style="display: none;">
    <div class="ql-modal-overlay">
        <div class="ql-modal-container ql-quick-modal-container">
            <div class="ql-modal-header">
                <h3 class="ql-modal-title">Nova Tarefa</h3>
                <button type="button" class="ql-btn ql-btn-icon ql-modal-close">
                    <i class="dashicons dashicons-no-alt"></i>
                </button>
            </div>
            
            <div class="ql-modal-body">
                <form id="ql-quick-task-form">
                    <div class="ql-form-group">
                        <label for="ql-quick-title">Título da Tarefa *</label>
                        <input type="text" id="ql-quick-title" class="ql-input" required placeholder="Digite o título da tarefa">
                    </div>
                    
                    <div class="ql-form-group">
                        <label for="ql-quick-description">Descrição (opcional)</label>
                        <textarea id="ql-quick-description" class="ql-textarea" rows="3" placeholder="Descreva brevemente a tarefa"></textarea>
                    </div>
                    
                    <div class="ql-form-row">
                        <div class="ql-form-group">
                            <label for="ql-quick-assignee">Responsável</label>
                            <select id="ql-quick-assignee" class="ql-select">
                                <option value="">Sem responsável</option>
                                <!-- Preenchido dinamicamente -->
                            </select>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="ql-quick-priority">Prioridade</label>
                            <select id="ql-quick-priority" class="ql-select">
                                <option value="low">Baixa</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">Alta</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ql-form-group">
                        <label for="ql-quick-due-date">Data Limite</label>
                        <input type="date" id="ql-quick-due-date" class="ql-input">
                    </div>
                </form>
            </div>
            
            <div class="ql-modal-footer">
                <button type="button" class="ql-btn ql-btn-secondary ql-modal-close">Cancelar</button>
                <button type="button" class="ql-btn ql-btn-primary ql-create-quick-task-btn">
                    <i class="dashicons dashicons-plus"></i>
                    Criar Tarefa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Subtarefa -->
<div id="ql-subtask-modal" class="ql-modal ql-subtask-modal" style="display: none;">
    <div class="ql-modal-overlay">
        <div class="ql-modal-container ql-subtask-modal-container">
            <div class="ql-modal-header">
                <h3 class="ql-modal-title">Nova Subtarefa</h3>
                <button type="button" class="ql-btn ql-btn-icon ql-modal-close">
                    <i class="dashicons dashicons-no-alt"></i>
                </button>
            </div>
            
            <div class="ql-modal-body">
                <form id="ql-subtask-form">
                    <div class="ql-subtask-parent-info">
                        <span class="ql-subtask-parent-label">Subtarefa de:</span>
                        <span class="ql-subtask-parent-title" id="ql-subtask-parent-title">Tarefa Pai</span>
                    </div>
                    
                    <div class="ql-form-group">
                        <label for="ql-subtask-title">Título da Subtarefa *</label>
                        <input type="text" id="ql-subtask-title" class="ql-input" required placeholder="Digite o título da subtarefa">
                    </div>
                    
                    <div class="ql-form-group">
                        <label for="ql-subtask-description">Descrição</label>
                        <textarea id="ql-subtask-description" class="ql-textarea" rows="3" placeholder="Descreva a subtarefa"></textarea>
                    </div>
                    
                    <div class="ql-form-row">
                        <div class="ql-form-group">
                            <label for="ql-subtask-assignee">Responsável</label>
                            <select id="ql-subtask-assignee" class="ql-select">
                                <option value="">Herdar da tarefa pai</option>
                                <!-- Preenchido dinamicamente -->
                            </select>
                        </div>
                        
                        <div class="ql-form-group">
                            <label for="ql-subtask-column">Coluna</label>
                            <select id="ql-subtask-column" class="ql-select">
                                <!-- Preenchido dinamicamente -->
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="ql-modal-footer">
                <button type="button" class="ql-btn ql-btn-secondary ql-modal-close">Cancelar</button>
                <button type="button" class="ql-btn ql-btn-primary ql-create-subtask-btn">
                    <i class="dashicons dashicons-plus"></i>
                    Criar Subtarefa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Templates para elementos dinâmicos -->
<script type="text/template" id="ql-subtask-item-template">
    <div class="ql-subtask-item" data-subtask-id="{{id}}">
        <div class="ql-subtask-status">
            <input type="checkbox" class="ql-subtask-checkbox" {{#if completed}}checked{{/if}}>
        </div>
        <div class="ql-subtask-content">
            <div class="ql-subtask-title">{{title}}</div>
            <div class="ql-subtask-meta">
                {{#if assigned_user_name}}<span class="ql-subtask-assignee">{{assigned_user_name}}</span>{{/if}}
                {{#if column_name}}<span class="ql-subtask-column">{{column_name}}</span>{{/if}}
            </div>
        </div>
        <div class="ql-subtask-actions">
            <button type="button" class="ql-btn ql-btn-icon ql-btn-sm ql-edit-subtask" title="Editar">
                <i class="dashicons dashicons-edit"></i>
            </button>
            <button type="button" class="ql-btn ql-btn-icon ql-btn-sm ql-delete-subtask" title="Excluir">
                <i class="dashicons dashicons-trash"></i>
            </button>
        </div>
    </div>
</script>

<script type="text/template" id="ql-activity-item-template">
    <div class="ql-activity-item ql-activity-{{activity_type}}" data-activity-id="{{id}}">
        <div class="ql-activity-avatar">
            <div class="ql-user-avatar">{{user_name_initial}}</div>
        </div>
        <div class="ql-activity-content">
            <div class="ql-activity-header">
                <span class="ql-activity-user">{{user_name}}</span>
                <span class="ql-activity-action">{{action_text}}</span>
                <span class="ql-activity-time">{{time_ago}}</span>
            </div>
            <div class="ql-activity-body">{{content}}</div>
        </div>
    </div>
</script>

<script type="text/template" id="ql-attachment-item-template">
    <div class="ql-attachment-item" data-attachment-id="{{id}}">
        <div class="ql-attachment-icon">
            <i class="dashicons dashicons-media-default"></i>
        </div>
        <div class="ql-attachment-info">
            <div class="ql-attachment-name">{{filename}}</div>
            <div class="ql-attachment-meta">
                <span class="ql-attachment-size">{{file_size}}</span>
                <span class="ql-attachment-uploader">por {{uploader_name}}</span>
            </div>
        </div>
        <div class="ql-attachment-actions">
            <a href="{{file_url}}" class="ql-btn ql-btn-icon ql-btn-sm" target="_blank" title="Baixar">
                <i class="dashicons dashicons-download"></i>
            </a>
            <button type="button" class="ql-btn ql-btn-icon ql-btn-sm ql-delete-attachment" title="Excluir">
                <i class="dashicons dashicons-trash"></i>
            </button>
        </div>
    </div>
</script>