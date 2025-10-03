/**
 * SISTEMA FINAL DE MODAIS FUNCIONAIS
 * Quilombo Laboratório - Versão definitiva com backend
 */

(function($) {
    'use strict';
    
    console.log('🚀 CARREGANDO SISTEMA FINAL DE MODAIS...');
    
    if (window.QLFinalModals) {
        console.log('⚠️ Sistema final já carregado');
        return;
    }
    
    window.QLFinalModals = {
        initialized: false,
        
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            console.log('🔧 Inicializando sistema final de modais...');
            this.setupInterception();
        },
        
        setupInterception: function() {
            var self = this;
            
            // Usar uma abordagem mais robusta com body delegation
            console.log('🔧 Configurando interceptação robusta...');
            
            // Remover eventos anteriores para evitar duplicação
            $('body').off('click.qlfinal-robust');
            
            // Event delegation no body - mais robusto contra mudanças no DOM
            $('body').on('click.qlfinal-robust', function(e) {
                var $target = $(e.target);
                var $closest;
                
                // Verificar se clicou em um botão de adicionar tarefa ou seus filhos
                $closest = $target.closest('.ql-add-task-btn');
                if ($closest.length > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var columnId = $closest.data('column-id');
                    if (columnId) {
                        self.showCreateTaskModal(columnId, $closest);
                    }
                    return false;
                }
                
                // Verificar se clicou em um botão de editar tarefa
                $closest = $target.closest('.ql-task-edit-btn');
                if ($closest.length > 0) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var taskId = $closest.data('task-id');
                    if (taskId) {
                        self.loadAndShowTask(taskId);
                    }
                    return false;
                }
                
                // Verificar se clicou em um card de tarefa (fallback)
                $closest = $target.closest('.ql-kanban-task');
                if ($closest.length > 0 && !$target.closest('.ql-task-edit-btn').length) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var taskId = $closest.data('task-id');
                    if (taskId) {
                        self.loadAndShowTask(taskId);
                    }
                    return false;
                }
            });
            
            console.log('✅ Sistema de interceptação robusta configurado');
        },
        
        loadAndShowTask: function(taskId) {
            var self = this;
            
            console.log('📖 Carregando dados da tarefa:', taskId);
            
            // Mostrar loading
            this.showLoadingModal('Carregando tarefa...');
            
            var ajaxUrl = (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? 
                         ql_admin.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? 
                       ql_admin.nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ql_get_task_data',
                    task_id: taskId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('📦 Dados da tarefa recebidos:', response);
                    
                    if (response && response.success && response.data) {
                        self.showTaskEditModal(response.data);
                    } else {
                        self.closeModal();
                        self.showAlert('Erro ao carregar tarefa: ' + (response.message || 'Erro desconhecido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro AJAX:', error);
                    self.closeModal();
                    self.showAlert('Erro de conexão: ' + error, 'error');
                },
                dataType: 'json'
            });
        },
        
        showTaskEditModal: function(taskData) {
            console.log('✏️ Criando modal de edição:', taskData);
            
            var modalHtml = `
                <div id="ql-final-edit-modal" class="ql-final-modal">
                    <div class="ql-final-content" style="width: 800px;">
                        <div class="ql-final-header">
                            <h2 class="ql-final-title">✏️ Editar Tarefa #${taskData.id}</h2>
                            <button class="ql-final-close" onclick="QLFinalModals.closeModal()">&times;</button>
                        </div>
                        <div class="ql-final-body">
                            <form id="ql-final-edit-form">
                                <div class="ql-final-section">
                                    <h3>📋 Informações Básicas</h3>
                                    <div class="ql-final-field">
                                        <label>Título *</label>
                                        <input type="text" name="title" value="${this.escapeHtml(taskData.title || '')}" required>
                                    </div>
                                    <div class="ql-final-field">
                                        <label>Descrição</label>
                                        <textarea name="description">${this.escapeHtml(taskData.description || '')}</textarea>
                                    </div>
                                </div>
                                
                                <div class="ql-final-section">
                                    <h3>⚙️ Configurações</h3>
                                    <div class="ql-final-row">
                                        <div class="ql-final-col">
                                            <label>Status</label>
                                            <select name="status">
                                                <option value="open" ${taskData.status === 'open' ? 'selected' : ''}>Aberta</option>
                                                <option value="in_progress" ${taskData.status === 'in_progress' ? 'selected' : ''}>Em Progresso</option>
                                                <option value="completed" ${taskData.status === 'completed' ? 'selected' : ''}>Concluída</option>
                                                <option value="closed" ${taskData.status === 'closed' ? 'selected' : ''}>Fechada</option>
                                            </select>
                                        </div>
                                        <div class="ql-final-col">
                                            <label>Prioridade</label>
                                            <select name="priority">
                                                <option value="low" ${taskData.priority === 'low' ? 'selected' : ''}>Baixa</option>
                                                <option value="normal" ${taskData.priority === 'normal' ? 'selected' : ''}>Normal</option>
                                                <option value="high" ${taskData.priority === 'high' ? 'selected' : ''}>Alta</option>
                                                <option value="urgent" ${taskData.priority === 'urgent' ? 'selected' : ''}>Urgente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ql-final-row">
                                        <div class="ql-final-col">
                                            <label>Data Limite</label>
                                            <input type="date" name="due_date" value="${taskData.due_date || ''}">
                                        </div>
                                        <div class="ql-final-col">
                                            <label>Horas Estimadas</label>
                                            <input type="number" name="estimated_hours" value="${taskData.estimated_hours || ''}" step="0.5" min="0">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ql-final-meta">
                                    <strong>Criada por:</strong> ${this.escapeHtml(taskData.creator_name || 'N/A')} | 
                                    <strong>Criada em:</strong> ${this.formatDate(taskData.created_at)} | 
                                    <strong>Atualizada em:</strong> ${this.formatDate(taskData.updated_at)}
                                </div>
                            </form>
                        </div>
                        <div class="ql-final-footer">
                            <button type="button" class="ql-final-btn danger" onclick="QLFinalModals.deleteTask('${taskData.id}')">
                                🗑️ Excluir
                            </button>
                            <button type="button" class="ql-final-btn secondary" onclick="QLFinalModals.closeModal()">
                                Cancelar
                            </button>
                            <button type="button" class="ql-final-btn primary" onclick="QLFinalModals.saveTask('${taskData.id}')">
                                💾 Salvar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.closeModal();
            $('body').append(modalHtml);
            
            console.log('✏️ Modal HTML adicionado ao DOM');
            console.log('✏️ Verificando se modal existe:', $('#ql-final-edit-modal').length);
            
            // Adicionar eventos do modal
            this.attachModalEvents();
            
            // Focar no campo título
            setTimeout(function() {
                $('#ql-final-edit-form input[name="title"]').focus();
            }, 100);
            
            console.log('✅ Modal de edição criado');
        },
        
        showCreateTaskModal: function(columnId, $button) {
            console.log('📝 Criando modal de criação para coluna:', columnId);
            
            var columnName = 'Coluna #' + columnId;
            if ($button && $button.length > 0) {
                var $column = $button.closest('.ql-kanban-column');
                if ($column.length > 0) {
                    columnName = $column.find('.ql-column-title').text() || 
                               $column.find('h3').text() || 
                               'Coluna #' + columnId;
                }
            }
            
            var modalHtml = `
                <div id="ql-final-create-modal" class="ql-final-modal">
                    <div class="ql-final-content" style="width: 600px;">
                        <div class="ql-final-header">
                            <h2 class="ql-final-title">➕ Nova Tarefa</h2>
                            <button class="ql-final-close" onclick="QLFinalModals.closeModal()">&times;</button>
                        </div>
                        <div class="ql-final-body">
                            <div class="ql-final-alert success">
                                <strong>📍 Destino:</strong> ${this.escapeHtml(columnName)} (ID: ${columnId})
                            </div>
                            
                            <form id="ql-final-create-form">
                                <input type="hidden" name="column_id" value="${columnId}">
                                
                                <div class="ql-final-section">
                                    <h3>📋 Informações da Tarefa</h3>
                                    <div class="ql-final-field">
                                        <label>Título da Tarefa *</label>
                                        <input type="text" name="title" required placeholder="Digite um título descritivo">
                                    </div>
                                    <div class="ql-final-field">
                                        <label>Descrição</label>
                                        <textarea name="description" placeholder="Descreva os detalhes da tarefa..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="ql-final-section">
                                    <h3>⚙️ Configurações Iniciais</h3>
                                    <div class="ql-final-row">
                                        <div class="ql-final-col">
                                            <label>Prioridade</label>
                                            <select name="priority">
                                                <option value="low">Baixa</option>
                                                <option value="normal" selected>Normal</option>
                                                <option value="high">Alta</option>
                                                <option value="urgent">Urgente</option>
                                            </select>
                                        </div>
                                        <div class="ql-final-col">
                                            <label>Status Inicial</label>
                                            <select name="status">
                                                <option value="open" selected>Aberta</option>
                                                <option value="in_progress">Em Progresso</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ql-final-field">
                                        <label>Data Limite (opcional)</label>
                                        <input type="date" name="due_date">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="ql-final-footer">
                            <button type="button" class="ql-final-btn secondary" onclick="QLFinalModals.closeModal()">
                                Cancelar
                            </button>
                            <button type="button" class="ql-final-btn primary" onclick="QLFinalModals.createTask()">
                                ➕ Criar Tarefa
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            this.closeModal();
            $('body').append(modalHtml);
            
            console.log('📝 Modal HTML adicionado ao DOM');
            console.log('📝 Verificando se modal existe:', $('#ql-final-create-modal').length);
            
            // Adicionar eventos do modal
            this.attachModalEvents();
            
            // Focar no campo título
            setTimeout(function() {
                $('#ql-final-create-form input[name="title"]').focus();
            }, 100);
            
            console.log('✅ Modal de criação criado');
        },
        
        showLoadingModal: function(message) {
            var modalHtml = `
                <div id="ql-final-loading-modal" class="ql-final-modal">
                    <div class="ql-final-content" style="width: 300px; text-align: center;">
                        <div class="ql-final-body">
                            <div class="ql-final-loading"></div>
                            <p>${message}</p>
                        </div>
                    </div>
                </div>
            `;
            
            this.closeModal();
            $('body').append(modalHtml);
        },
        
        saveTask: function(taskId) {
            console.log('💾 Salvando tarefa:', taskId);
            
            var formData = this.getFormData('#ql-final-edit-form');
            formData.task_id = taskId;
            
            this.showLoadingModal('Salvando alterações...');
            
            var ajaxUrl = (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? 
                         ql_admin.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? 
                       ql_admin.nonce : '';
            
            formData.action = 'ql_update_task';
            formData.nonce = nonce;
            
            var self = this;
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    console.log('💾 Resposta salvar:', response);
                    
                    if (response && response.success) {
                        self.closeModal();
                        self.showAlert('Tarefa atualizada com sucesso!', 'success');
                        self.refreshBoard();
                    } else {
                        self.closeModal();
                        self.showAlert('Erro ao salvar: ' + (response.message || 'Erro desconhecido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro ao salvar:', error);
                    self.closeModal();
                    self.showAlert('Erro de conexão: ' + error, 'error');
                },
                dataType: 'json'
            });
        },
        
        createTask: function() {
            console.log('➕ Criando nova tarefa');
            
            var formData = this.getFormData('#ql-final-create-form');
            
            if (!formData.title || !formData.title.trim()) {
                this.showAlert('Por favor, digite um título para a tarefa', 'error');
                return;
            }
            
            this.showLoadingModal('Criando tarefa...');
            
            var ajaxUrl = (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? 
                         ql_admin.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? 
                       ql_admin.nonce : '';
            
            formData.action = 'ql_create_task';
            formData.nonce = nonce;
            
            var self = this;
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: formData,
                success: function(response) {
                    console.log('➕ Resposta criar:', response);
                    
                    if (response && response.success) {
                        self.closeModal();
                        self.showAlert('Tarefa criada com sucesso!', 'success');
                        self.refreshBoard();
                    } else {
                        self.closeModal();
                        self.showAlert('Erro ao criar tarefa: ' + (response.message || 'Erro desconhecido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro ao criar:', error);
                    self.closeModal();
                    self.showAlert('Erro de conexão: ' + error, 'error');
                },
                dataType: 'json'
            });
        },
        
        deleteTask: function(taskId) {
            if (!confirm('Tem certeza que deseja excluir esta tarefa? Esta ação não pode ser desfeita.')) {
                return;
            }
            
            console.log('🗑️ Excluindo tarefa:', taskId);
            
            this.showLoadingModal('Excluindo tarefa...');
            
            var ajaxUrl = (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? 
                         ql_admin.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? 
                       ql_admin.nonce : '';
            
            var self = this;
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ql_delete_task',
                    task_id: taskId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('🗑️ Resposta excluir:', response);
                    
                    if (response && response.success) {
                        self.closeModal();
                        self.showAlert('Tarefa excluída com sucesso!', 'success');
                        self.refreshBoard();
                    } else {
                        self.closeModal();
                        self.showAlert('Erro ao excluir: ' + (response.message || 'Erro desconhecido'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Erro ao excluir:', error);
                    self.closeModal();
                    self.showAlert('Erro de conexão: ' + error, 'error');
                },
                dataType: 'json'
            });
        },
        
        refreshBoard: function() {
            console.log('🔄 Atualizando quadro...');
            
            // Tentar usar QLKanban se disponível
            if (typeof QLKanban !== 'undefined' && QLKanban.loadBoardData) {
                QLKanban.loadBoardData();
                console.log('🔄 Board atualizado - eventos robustos devem continuar funcionando');
            } else {
                // Fallback: recarregar página
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        },
        
        getFormData: function(formSelector) {
            var data = {};
            $(formSelector + ' input, ' + formSelector + ' select, ' + formSelector + ' textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if (name && value !== null) {
                    data[name] = value;
                }
            });
            return data;
        },
        
        attachModalEvents: function() {
            var self = this;
            
            // Eventos ESC para fechar modal
            $(document).off('keydown.ql-modal').on('keydown.ql-modal', function(e) {
                if (e.keyCode === 27) { // ESC
                    console.log('⌨️ ESC pressionado - fechando modal');
                    self.closeModal();
                }
            });
            
            // Clique fora do modal para fechar
            $('.ql-final-modal').off('click.ql-modal').on('click.ql-modal', function(e) {
                if (e.target === this) {
                    console.log('🖱️ Clique fora do modal - fechando');
                    self.closeModal();
                }
            });
            
            console.log('🎮 Eventos do modal anexados');
        },
        
        closeModal: function() {
            $('.ql-final-modal').remove();
            $(document).off('keydown.ql-modal');
        },
        
        showAlert: function(message, type) {
            type = type || 'info';
            
            var alertClass = type === 'success' ? 'ql-final-alert-success' : 'ql-final-alert-error';
            
            var alertHtml = `
                <div class="ql-final-alert-fixed ${alertClass}">
                    ${this.escapeHtml(message)}
                    <button onclick="$(this).parent().remove()">×</button>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            // Auto-remover após 5 segundos
            setTimeout(function() {
                $('.ql-final-alert-fixed').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        formatDate: function(dateString) {
            if (!dateString) return 'N/A';
            
            try {
                var date = new Date(dateString);
                return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR');
            } catch (e) {
                return dateString;
            }
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        },
        
        // Aliases para compatibilidade
        openCreate: function(columnId) {
            console.log('🔄 Usando alias openCreate para columnId:', columnId);
            var fakeButton = $('<div>').data('column-id', columnId);
            this.showCreateTaskModal(columnId, fakeButton);
        },
        
        openEdit: function(taskId) {
            console.log('🔄 Usando alias openEdit para taskId:', taskId);
            this.loadAndShowTask(taskId);
        },
        
        // Método para testar manualmente
        testAddButton: function() {
            console.log('🧪 TESTE MANUAL: Simulando clique em botão adicionar...');
            var $firstBtn = $('.ql-add-task-btn').first();
            if ($firstBtn.length > 0) {
                console.log('🧪 Botão encontrado:', $firstBtn);
                console.log('🧪 Column ID:', $firstBtn.data('column-id'));
                this.showCreateTaskModal($firstBtn.data('column-id'), $firstBtn);
            } else {
                console.log('❌ Nenhum botão encontrado!');
            }
        },
        
        // Método para religar eventos
        rebindEvents: function() {
            console.log('🔄 Religando eventos...');
            this.setupInterception();
        },
        
        // Verificar se eventos estão funcionando
        checkEvents: function() {
            var self = this;
            console.log('🔍 Verificando se eventos estão funcionando...');
            
            // Testar se há listeners ativos
            var events = $._data(document, 'events');
            if (events && events.click) {
                var qlFinalEvents = events.click.filter(function(e) {
                    return e.namespace === 'qlfinal';
                });
                console.log('🔍 Eventos qlfinal ativos:', qlFinalEvents.length);
                
                if (qlFinalEvents.length === 0) {
                    console.log('⚠️ Eventos perdidos, religando...');
                    this.rebindEvents();
                }
            } else {
                console.log('⚠️ Nenhum evento encontrado, religando...');
                this.rebindEvents();
            }
        }
    };
    
    // CSS para os modais finais
    var css = `
        <style id="ql-final-modal-css">
            .ql-final-modal {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(0, 0, 0, 0.8) !important;
                z-index: 999999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                opacity: 1 !important;
                visibility: visible !important;
                pointer-events: auto !important;
            }
            
            .ql-final-content {
                background: white !important;
                border-radius: 8px !important;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
                max-height: 90vh !important;
                overflow-y: auto !important;
            }
            
            .ql-final-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                padding: 20px 30px !important;
                border-bottom: 2px solid #f0f0f0 !important;
                background: #f8f9fa !important;
                border-radius: 8px 8px 0 0 !important;
            }
            
            .ql-final-title {
                margin: 0 !important;
                color: #333 !important;
                font-size: 24px !important;
                font-weight: bold !important;
            }
            
            .ql-final-close {
                background: none !important;
                border: none !important;
                font-size: 28px !important;
                cursor: pointer !important;
                color: #666 !important;
                padding: 5px !important;
                width: 40px !important;
                height: 40px !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            .ql-final-close:hover {
                background: #f0f0f0 !important;
                color: #333 !important;
            }
            
            .ql-final-body {
                padding: 30px !important;
            }
            
            .ql-final-footer {
                padding: 20px 30px !important;
                border-top: 1px solid #eee !important;
                background: #f8f9fa !important;
                border-radius: 0 0 8px 8px !important;
                text-align: right !important;
            }
            
            .ql-final-section {
                margin-bottom: 25px !important;
                padding: 20px !important;
                border-radius: 6px !important;
                border-left: 4px solid #2196F3 !important;
                background: #f8f9fa !important;
            }
            
            .ql-final-section h3 {
                margin: 0 0 15px 0 !important;
                color: #2196F3 !important;
                font-size: 18px !important;
            }
            
            .ql-final-field {
                margin-bottom: 20px !important;
            }
            
            .ql-final-field label {
                display: block !important;
                margin-bottom: 8px !important;
                font-weight: bold !important;
                color: #555 !important;
            }
            
            .ql-final-field input,
            .ql-final-field select,
            .ql-final-field textarea {
                width: 100% !important;
                padding: 12px !important;
                border: 2px solid #ddd !important;
                border-radius: 6px !important;
                font-size: 16px !important;
                box-sizing: border-box !important;
            }
            
            .ql-final-field textarea {
                min-height: 100px !important;
                resize: vertical !important;
                font-family: inherit !important;
            }
            
            .ql-final-row {
                display: flex !important;
                gap: 15px !important;
                margin-bottom: 20px !important;
            }
            
            .ql-final-col {
                flex: 1 !important;
            }
            
            .ql-final-btn {
                background: #2196F3 !important;
                color: white !important;
                border: none !important;
                padding: 12px 24px !important;
                border-radius: 6px !important;
                cursor: pointer !important;
                font-size: 16px !important;
                font-weight: bold !important;
                margin-left: 10px !important;
            }
            
            .ql-final-btn.secondary {
                background: #6c757d !important;
            }
            
            .ql-final-btn.danger {
                background: #dc3545 !important;
            }
            
            .ql-final-btn.primary {
                background: #28a745 !important;
            }
            
            .ql-final-loading {
                display: inline-block !important;
                width: 40px !important;
                height: 40px !important;
                border: 4px solid #f3f3f3 !important;
                border-top: 4px solid #2196F3 !important;
                border-radius: 50% !important;
                animation: ql-final-spin 1s linear infinite !important;
                margin-bottom: 20px !important;
            }
            
            @keyframes ql-final-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .ql-final-alert {
                padding: 15px !important;
                border-radius: 6px !important;
                margin-bottom: 20px !important;
            }
            
            .ql-final-alert.success {
                background: #d4edda !important;
                border: 1px solid #c3e6cb !important;
                color: #155724 !important;
            }
            
            .ql-final-alert-fixed {
                position: fixed !important;
                top: 20px !important;
                right: 20px !important;
                z-index: 9999999 !important;
                padding: 15px 20px !important;
                border-radius: 6px !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
                min-width: 300px !important;
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
            }
            
            .ql-final-alert-success {
                background: #d4edda !important;
                border: 1px solid #c3e6cb !important;
                color: #155724 !important;
            }
            
            .ql-final-alert-error {
                background: #f8d7da !important;
                border: 1px solid #f5c6cb !important;
                color: #721c24 !important;
            }
            
            .ql-final-alert-fixed button {
                background: none !important;
                border: none !important;
                font-size: 18px !important;
                cursor: pointer !important;
                margin-left: 10px !important;
            }
            
            .ql-final-meta {
                font-size: 12px !important;
                color: #666 !important;
                background: #f8f9fa !important;
                padding: 10px !important;
                border-radius: 4px !important;
                margin-top: 20px !important;
            }
            
            /* Botão de edição nos cards */
            .ql-task-edit-btn {
                background: #007cba !important;
                color: white !important;
                border: none !important;
                padding: 4px 8px !important;
                border-radius: 3px !important;
                cursor: pointer !important;
                font-size: 12px !important;
                line-height: 1 !important;
                float: right !important;
                margin-left: 10px !important;
            }
            
            .ql-task-edit-btn:hover {
                background: #005a87 !important;
                transform: scale(1.1) !important;
            }
            
            .ql-task-header {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                margin-bottom: 8px !important;
            }
        </style>
    `;
    
    $('head').append(css);
    
    // Inicializar quando jQuery estiver disponível
    $(document).ready(function() {
        // Aguardar um pouco para garantir que o Kanban foi inicializado
        setTimeout(function() {
            window.QLFinalModals.init();
            console.log('✅ SISTEMA FINAL DE MODAIS INICIALIZADO!');
            
            // Debug adicional após inicialização
            setTimeout(function() {
                console.log('🔍 POST-INIT DEBUG:');
                console.log('- Botões encontrados:', $('.ql-add-task-btn').length);
                console.log('- QLKanban disponível:', typeof window.QLKanban);
                console.log('- QLFinalModals inicializado:', window.QLFinalModals.initialized);
                
                // Teste manual disponível no console
                window.testAddButton = function() {
                    window.QLFinalModals.testAddButton();
                };
                window.rebindEvents = function() {
                    window.QLFinalModals.rebindEvents();
                };
                window.checkEvents = function() {
                    window.QLFinalModals.checkEvents();
                };
                
                console.log('🧪 Funções de teste disponíveis: testAddButton(), rebindEvents(), checkEvents()');
                
                // Verificação automática inicial
                window.QLFinalModals.checkEvents();
                
                // Monitoramento periódico para detectar se eventos param de funcionar
                setInterval(function() {
                    // Verificar se ainda temos eventos robustos ativos
                    var events = $._data($('body')[0], 'events');
                    if (!events || !events.click || !events.click.some(function(e) {
                        return e.namespace === 'qlfinal-robust';
                    })) {
                        console.log('⚠️ EVENTOS ROBUSTOS PERDIDOS! Religando...');
                        window.QLFinalModals.setupInterception();
                    }
                }, 5000); // Verificar a cada 5 segundos
            }, 2000);
        }, 500);
    });
    
})(jQuery);