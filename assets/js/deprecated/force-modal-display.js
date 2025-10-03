/**
 * CORREÇÃO FORÇADA para exibição de modais
 * Quilombo Laboratório - Solução para modais invisíveis
 */

(function($) {
    'use strict';
    
    console.log('🚀 CARREGANDO CORREÇÃO FORÇADA DE MODAIS...');
    
    // Prevenir múltiplas inicializações
    if (window.QLForceModalDisplay) {
        console.log('⚠️ Correção de modais já carregada');
        return;
    }
    
    window.QLForceModalDisplay = {
        initialized: false,
        modalCounter: 0,
        
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            console.log('🔧 Inicializando correção forçada de modais...');
            this.waitAndFix();
        },
        
        waitAndFix: function() {
            var self = this;
            
            // Aplicar imediatamente
            if (document.readyState === 'complete') {
                self.applyFixes();
            } else {
                $(document).ready(function() {
                    self.applyFixes();
                });
            }
            
            // Aplicar novamente com delays
            setTimeout(function() { self.applyFixes(); }, 500);
            setTimeout(function() { self.applyFixes(); }, 1500);
        },
        
        applyFixes: function() {
            console.log('🎯 Aplicando correção forçada de modais...');
            
            this.addModalCSS();
            this.setupClickHandlers();
            
            console.log('✅ Correção forçada aplicada!');
        },
        
        addModalCSS: function() {
            // Remover CSS anterior se existir
            $('#ql-force-modal-css').remove();
            
            var css = `
                <style id="ql-force-modal-css">
                    /* FORÇA MÁXIMA para exibição de modais */
                    .ql-force-modal {
                        position: fixed !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        background: rgba(0, 0, 0, 0.8) !important;
                        z-index: 2147483647 !important; /* Z-index máximo */
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        pointer-events: auto !important;
                    }
                    
                    .ql-force-modal-content {
                        background: white !important;
                        padding: 30px !important;
                        border-radius: 8px !important;
                        max-width: 90vw !important;
                        max-height: 90vh !important;
                        overflow-y: auto !important;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
                        position: relative !important;
                        z-index: 2147483647 !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                        display: block !important;
                    }
                    
                    .ql-force-modal-header {
                        display: flex !important;
                        justify-content: space-between !important;
                        align-items: center !important;
                        margin-bottom: 20px !important;
                        padding-bottom: 15px !important;
                        border-bottom: 2px solid #f0f0f0 !important;
                    }
                    
                    .ql-force-modal-close {
                        background: none !important;
                        border: none !important;
                        font-size: 28px !important;
                        cursor: pointer !important;
                        color: #666 !important;
                        padding: 5px !important;
                        line-height: 1 !important;
                        width: auto !important;
                        height: auto !important;
                    }
                    
                    .ql-force-modal-close:hover {
                        color: #333 !important;
                        background: #f0f0f0 !important;
                        border-radius: 4px !important;
                    }
                    
                    .ql-force-modal-title {
                        margin: 0 !important;
                        color: #333 !important;
                        font-size: 24px !important;
                        font-weight: bold !important;
                    }
                    
                    .ql-force-modal-body {
                        line-height: 1.5 !important;
                    }
                    
                    .ql-force-modal-section {
                        margin-bottom: 20px !important;
                        padding: 15px !important;
                        border-radius: 6px !important;
                        border-left: 4px solid #2196F3 !important;
                    }
                    
                    .ql-force-modal-success {
                        background: #e8f5e8 !important;
                        border-left-color: #4CAF50 !important;
                    }
                    
                    .ql-force-modal-info {
                        background: #f8f9fa !important;
                        border-left-color: #2196F3 !important;
                    }
                    
                    .ql-force-modal-warning {
                        background: #fff3e0 !important;
                        border-left-color: #FF9800 !important;
                    }
                    
                    .ql-force-modal-button {
                        background: #2196F3 !important;
                        color: white !important;
                        border: none !important;
                        padding: 12px 24px !important;
                        border-radius: 6px !important;
                        cursor: pointer !important;
                        font-size: 16px !important;
                        font-weight: bold !important;
                        margin: 5px !important;
                    }
                    
                    .ql-force-modal-button:hover {
                        background: #1976D2 !important;
                    }
                    
                    .ql-force-modal-input {
                        width: 100% !important;
                        padding: 12px !important;
                        border: 2px solid #ddd !important;
                        border-radius: 6px !important;
                        font-size: 16px !important;
                        box-sizing: border-box !important;
                        margin-bottom: 15px !important;
                    }
                    
                    .ql-force-modal-textarea {
                        width: 100% !important;
                        padding: 12px !important;
                        border: 2px solid #ddd !important;
                        border-radius: 6px !important;
                        font-size: 14px !important;
                        resize: vertical !important;
                        box-sizing: border-box !important;
                        margin-bottom: 15px !important;
                        min-height: 80px !important;
                    }
                    
                    .ql-force-modal-label {
                        display: block !important;
                        margin-bottom: 5px !important;
                        font-weight: bold !important;
                        color: #555 !important;
                    }
                    
                    /* Esconder outros modais que possam estar interferindo */
                    .ql-modal:not(.ql-force-modal) {
                        display: none !important;
                    }
                </style>
            `;
            
            $('head').append(css);
            console.log('🎨 CSS forçado adicionado');
        },
        
        setupClickHandlers: function() {
            var self = this;
            
            // Limpar handlers anteriores
            $(document).off('click.qlforce');
            
            // Handler para cards de tarefas
            $(document).on('click.qlforce', '.ql-kanban-task', function(e) {
                console.log('🔥 CLIQUE FORÇADO NO CARD!', this);
                
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                
                var $task = $(this);
                
                // Verificar drag
                if ($task.data('is-dragging') || $task.hasClass('ui-sortable-helper')) {
                    console.log('⏭️ Ignorando - em drag');
                    return false;
                }
                
                var taskId = $task.data('task-id') || $task.attr('data-task-id');
                console.log('🔍 Task ID (forçado):', taskId);
                
                if (!taskId) {
                    self.showErrorModal('Task ID não encontrado');
                    return false;
                }
                
                self.showTaskModal(taskId, $task);
                return false;
            });
            
            // Handler para botões adicionar
            $(document).on('click.qlforce', '.ql-add-task-btn', function(e) {
                console.log('🔵 CLIQUE FORÇADO NO BOTÃO ADICIONAR!', this);
                
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                
                var $button = $(this);
                var $column = $button.closest('.ql-kanban-column');
                var columnId = $button.data('column-id') || $button.attr('data-column-id') || 
                              $column.data('column-id') || $column.attr('data-column-id');
                
                console.log('🔍 Column ID (forçado):', columnId);
                
                if (!columnId) {
                    self.showErrorModal('Column ID não encontrado');
                    return false;
                }
                
                self.showCreateModal(columnId, $button);
                return false;
            });
            
            console.log('🎯 Handlers forçados configurados');
        },
        
        showTaskModal: function(taskId, $task) {
            console.log('📖 CRIANDO MODAL FORÇADO PARA TAREFA:', taskId);
            
            this.modalCounter++;
            var modalId = 'ql-force-task-modal-' + this.modalCounter;
            
            var taskTitle = $task.find('.ql-task-title').text() || 
                          $task.find('.ql-task-content').text() || 
                          $task.text().trim() || 
                          'Tarefa #' + taskId;
            
            var modalHtml = `
                <div id="${modalId}" class="ql-force-modal">
                    <div class="ql-force-modal-content" style="width: 700px;">
                        <div class="ql-force-modal-header">
                            <h2 class="ql-force-modal-title">${this.escapeHtml(taskTitle)}</h2>
                            <button class="ql-force-modal-close" onclick="QLForceModalDisplay.closeModal('${modalId}')">&times;</button>
                        </div>
                        <div class="ql-force-modal-body">
                            <div class="ql-force-modal-section ql-force-modal-success">
                                <h3 style="margin: 0 0 10px 0; color: #4CAF50;">✅ SISTEMA FUNCIONANDO!</h3>
                                <p style="margin: 0;">Clique detectado e modal aberto com sucesso!</p>
                            </div>
                            
                            <div class="ql-force-modal-section ql-force-modal-info">
                                <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações da Tarefa</h3>
                                <p><strong>ID:</strong> ${taskId}</p>
                                <p><strong>Título:</strong> ${this.escapeHtml(taskTitle)}</p>
                                <p><strong>Modal ID:</strong> ${modalId}</p>
                                <p style="margin: 0;"><strong>Status:</strong> Aberto com força máxima 💪</p>
                            </div>
                            
                            <div class="ql-force-modal-section ql-force-modal-warning">
                                <h3 style="margin: 0 0 10px 0; color: #FF9800;">🚧 Em Desenvolvimento</h3>
                                <p style="margin: 0;">Esta é uma versão de teste que força a exibição do modal. As funcionalidades de edição serão implementadas em breve.</p>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <button class="ql-force-modal-button" onclick="QLForceModalDisplay.closeModal('${modalId}')">
                                    Fechar Modal
                                </button>
                                <button class="ql-force-modal-button" onclick="QLForceModalDisplay.testAlert()" style="background: #4CAF50 !important;">
                                    Testar Clique
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modais anteriores
            $('.ql-force-modal').remove();
            
            // Adicionar ao body
            $('body').append(modalHtml);
            
            console.log('✅ MODAL FORÇADO CRIADO E ADICIONADO AO DOM!');
            console.log('🔍 Modal ID:', modalId);
            console.log('🔍 Elemento criado:', $('#' + modalId).length ? 'SIM' : 'NÃO');
            
            // Forçar foco no modal após um breve delay
            setTimeout(function() {
                $('#' + modalId).focus();
                console.log('🎯 Foco forçado no modal');
            }, 100);
        },
        
        showCreateModal: function(columnId, $button) {
            console.log('📝 CRIANDO MODAL FORÇADO DE CRIAÇÃO:', columnId);
            
            this.modalCounter++;
            var modalId = 'ql-force-create-modal-' + this.modalCounter;
            
            var $column = $button.closest('.ql-kanban-column');
            var columnName = $column.find('.ql-column-title').text() || 
                           $column.find('h3').text() || 
                           'Coluna #' + columnId;
            
            var modalHtml = `
                <div id="${modalId}" class="ql-force-modal">
                    <div class="ql-force-modal-content" style="width: 500px;">
                        <div class="ql-force-modal-header">
                            <h2 class="ql-force-modal-title">Nova Tarefa</h2>
                            <button class="ql-force-modal-close" onclick="QLForceModalDisplay.closeModal('${modalId}')">&times;</button>
                        </div>
                        <div class="ql-force-modal-body">
                            <div class="ql-force-modal-section ql-force-modal-success">
                                <h3 style="margin: 0 0 10px 0; color: #4CAF50;">✅ BOTÃO FUNCIONANDO!</h3>
                                <p style="margin: 0;">Clique no botão "+" detectado com sucesso!</p>
                            </div>
                            
                            <div class="ql-force-modal-section ql-force-modal-info">
                                <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações</h3>
                                <p><strong>Coluna:</strong> ${this.escapeHtml(columnName)}</p>
                                <p><strong>Column ID:</strong> ${columnId}</p>
                                <p style="margin: 0;"><strong>Modal ID:</strong> ${modalId}</p>
                            </div>
                            
                            <form onsubmit="QLForceModalDisplay.handleCreateTask(event, '${modalId}', '${columnId}')">
                                <label class="ql-force-modal-label">Título da Tarefa *</label>
                                <input type="text" class="ql-force-modal-input" id="task-title-${modalId}" required placeholder="Digite o título da tarefa">
                                
                                <label class="ql-force-modal-label">Descrição (opcional)</label>
                                <textarea class="ql-force-modal-textarea" id="task-desc-${modalId}" placeholder="Descreva a tarefa"></textarea>
                                
                                <div style="text-align: right;">
                                    <button type="button" class="ql-force-modal-button" onclick="QLForceModalDisplay.closeModal('${modalId}')" style="background: #ccc !important; color: #666 !important;">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="ql-force-modal-button" style="background: #4CAF50 !important;">
                                        Criar Tarefa
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modais anteriores
            $('.ql-force-modal').remove();
            
            // Adicionar ao body
            $('body').append(modalHtml);
            
            console.log('✅ MODAL DE CRIAÇÃO FORÇADO CRIADO!');
            
            // Focar no campo título
            setTimeout(function() {
                $('#task-title-' + modalId).focus();
            }, 100);
        },
        
        showErrorModal: function(message) {
            console.log('❌ CRIANDO MODAL DE ERRO:', message);
            
            this.modalCounter++;
            var modalId = 'ql-force-error-modal-' + this.modalCounter;
            
            var modalHtml = `
                <div id="${modalId}" class="ql-force-modal">
                    <div class="ql-force-modal-content" style="width: 400px;">
                        <div class="ql-force-modal-header">
                            <h2 class="ql-force-modal-title" style="color: #f44336;">⚠️ Erro</h2>
                            <button class="ql-force-modal-close" onclick="QLForceModalDisplay.closeModal('${modalId}')">&times;</button>
                        </div>
                        <div class="ql-force-modal-body">
                            <div class="ql-force-modal-section" style="background: #ffebee !important; border-left-color: #f44336 !important;">
                                <p style="margin: 0; color: #c62828;"><strong>${this.escapeHtml(message)}</strong></p>
                            </div>
                            <div style="text-align: center; margin-top: 20px;">
                                <button class="ql-force-modal-button" onclick="QLForceModalDisplay.closeModal('${modalId}')" style="background: #f44336 !important;">
                                    Fechar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('.ql-force-modal').remove();
            $('body').append(modalHtml);
        },
        
        closeModal: function(modalId) {
            console.log('🗙 Fechando modal forçado:', modalId);
            if (modalId) {
                $('#' + modalId).remove();
            } else {
                $('.ql-force-modal').remove();
            }
        },
        
        handleCreateTask: function(event, modalId, columnId) {
            event.preventDefault();
            
            var title = $('#task-title-' + modalId).val().trim();
            var description = $('#task-desc-' + modalId).val().trim();
            
            if (!title) {
                alert('Por favor, digite um título para a tarefa');
                return;
            }
            
            console.log('📝 Dados da nova tarefa:', {
                title: title,
                description: description,
                columnId: columnId
            });
            
            alert('Tarefa "' + title + '" criada com sucesso!\n\n(Dados enviados para o console)\n\nColumn ID: ' + columnId);
            
            this.closeModal(modalId);
        },
        
        testAlert: function() {
            alert('🎉 Sistema de cliques funcionando perfeitamente!\n\nOs modais estão sendo exibidos com força máxima.');
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };
    
    // Inicializar quando jQuery estiver disponível
    if (typeof $ !== 'undefined') {
        window.QLForceModalDisplay.init();
    } else {
        var checkJQuery = setInterval(function() {
            if (typeof $ !== 'undefined') {
                clearInterval(checkJQuery);
                window.QLForceModalDisplay.init();
            }
        }, 100);
    }
    
    console.log('✅ CORREÇÃO FORÇADA DE MODAIS CARREGADA!');
    
})(typeof jQuery !== 'undefined' ? jQuery : undefined);