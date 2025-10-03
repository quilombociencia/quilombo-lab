/**
 * MODAIS FUNCIONAIS com integração backend
 * Quilombo Laboratório - Sistema completo de tarefas
 */

(function($) {
    'use strict';
    
    console.log('🚀 CARREGANDO MODAIS FUNCIONAIS...');
    
    if (window.QLFunctionalModals) {
        console.log('⚠️ Modais funcionais já carregados');
        return;
    }
    
    window.QLFunctionalModals = {
        initialized: false,
        modalCounter: 0,
        currentTaskData: null,
        
        init: function() {
            if (this.initialized) return;
            this.initialized = true;
            
            console.log('🔧 Inicializando modais funcionais...');
            this.waitAndSetup();
        },
        
        waitAndSetup: function() {
            var self = this;
            
            if (document.readyState === 'complete') {
                self.setup();
            } else {
                $(document).ready(function() {
                    self.setup();
                });
            }
            
            setTimeout(function() { self.setup(); }, 500);
        },
        
        setup: function() {
            console.log('🎯 Configurando modais funcionais...');
            
            this.addModalCSS();
            this.setupEventHandlers();
            
            console.log('✅ Modais funcionais configurados!');
        },
        
        addModalCSS: function() {
            $('#ql-functional-modal-css').remove();
            
            var css = `
                <style id="ql-functional-modal-css">
                    .ql-functional-modal {
                        position: fixed !important;
                        top: 0 !important;
                        left: 0 !important;
                        width: 100vw !important;
                        height: 100vh !important;
                        background: rgba(0, 0, 0, 0.8) !important;
                        z-index: 2147483647 !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }
                    
                    .ql-functional-content {
                        background: white !important;
                        border-radius: 8px !important;
                        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
                        max-height: 90vh !important;
                        overflow-y: auto !important;
                        position: relative !important;
                        visibility: visible !important;
                        opacity: 1 !important;
                    }
                    
                    .ql-functional-header {
                        display: flex !important;
                        justify-content: space-between !important;
                        align-items: center !important;
                        padding: 20px 30px !important;
                        border-bottom: 2px solid #f0f0f0 !important;
                        background: #f8f9fa !important;
                        border-radius: 8px 8px 0 0 !important;
                    }
                    
                    .ql-functional-body {
                        padding: 30px !important;
                        max-height: 70vh !important;
                        overflow-y: auto !important;
                    }
                    
                    .ql-functional-footer {
                        padding: 20px 30px !important;
                        border-top: 1px solid #eee !important;
                        background: #f8f9fa !important;
                        border-radius: 0 0 8px 8px !important;
                        text-align: right !important;
                    }
                    
                    .ql-functional-title {
                        margin: 0 !important;
                        color: #333 !important;
                        font-size: 24px !important;
                        font-weight: bold !important;
                    }
                    
                    .ql-functional-close {
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
                    
                    .ql-functional-close:hover {
                        background: #f0f0f0 !important;
                        color: #333 !important;
                    }
                    
                    .ql-functional-section {
                        margin-bottom: 25px !important;
                        padding: 20px !important;
                        border-radius: 6px !important;
                        border-left: 4px solid #2196F3 !important;
                        background: #f8f9fa !important;
                    }
                    
                    .ql-functional-section h3 {
                        margin: 0 0 15px 0 !important;
                        color: #2196F3 !important;
                        font-size: 18px !important;
                    }
                    
                    .ql-functional-field {
                        margin-bottom: 20px !important;
                    }
                    
                    .ql-functional-label {
                        display: block !important;
                        margin-bottom: 8px !important;
                        font-weight: bold !important;
                        color: #555 !important;
                        font-size: 14px !important;
                    }
                    
                    .ql-functional-input {
                        width: 100% !important;
                        padding: 12px !important;
                        border: 2px solid #ddd !important;
                        border-radius: 6px !important;
                        font-size: 16px !important;
                        box-sizing: border-box !important;
                        transition: border-color 0.3s !important;
                    }
                    
                    .ql-functional-input:focus {
                        border-color: #2196F3 !important;
                        outline: none !important;
                        box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1) !important;
                    }
                    
                    .ql-functional-textarea {
                        width: 100% !important;
                        padding: 12px !important;
                        border: 2px solid #ddd !important;
                        border-radius: 6px !important;
                        font-size: 14px !important;
                        min-height: 100px !important;
                        resize: vertical !important;
                        box-sizing: border-box !important;
                        font-family: inherit !important;
                    }
                    
                    .ql-functional-select {
                        width: 100% !important;
                        padding: 12px !important;
                        border: 2px solid #ddd !important;
                        border-radius: 6px !important;
                        font-size: 16px !important;
                        box-sizing: border-box !important;
                        background: white !important;
                    }
                    
                    .ql-functional-button {
                        background: #2196F3 !important;
                        color: white !important;
                        border: none !important;
                        padding: 12px 24px !important;
                        border-radius: 6px !important;
                        cursor: pointer !important;
                        font-size: 16px !important;
                        font-weight: bold !important;
                        margin-left: 10px !important;
                        transition: background-color 0.3s !important;
                    }
                    
                    .ql-functional-button:hover {
                        background: #1976D2 !important;
                    }
                    
                    .ql-functional-button.secondary {
                        background: #6c757d !important;
                    }
                    
                    .ql-functional-button.secondary:hover {
                        background: #5a6268 !important;
                    }
                    
                    .ql-functional-button.success {
                        background: #28a745 !important;
                    }
                    
                    .ql-functional-button.success:hover {
                        background: #218838 !important;
                    }
                    
                    .ql-functional-button.danger {
                        background: #dc3545 !important;
                    }
                    
                    .ql-functional-button.danger:hover {
                        background: #c82333 !important;
                    }
                    
                    .ql-functional-loading {
                        display: inline-block !important;
                        width: 20px !important;
                        height: 20px !important;
                        border: 3px solid #f3f3f3 !important;
                        border-top: 3px solid #2196F3 !important;
                        border-radius: 50% !important;
                        animation: ql-spin 1s linear infinite !important;
                        margin-right: 10px !important;
                    }
                    
                    @keyframes ql-spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                    
                    .ql-functional-row {
                        display: flex !important;
                        gap: 15px !important;
                        margin-bottom: 20px !important;
                    }
                    
                    .ql-functional-col {
                        flex: 1 !important;
                    }
                    
                    .ql-functional-alert {
                        padding: 15px !important;
                        border-radius: 6px !important;
                        margin-bottom: 20px !important;
                    }
                    
                    .ql-functional-alert.success {
                        background: #d4edda !important;
                        border: 1px solid #c3e6cb !important;
                        color: #155724 !important;
                    }
                    
                    .ql-functional-alert.error {
                        background: #f8d7da !important;
                        border: 1px solid #f5c6cb !important;
                        color: #721c24 !important;
                    }
                    
                    .ql-functional-meta {
                        font-size: 12px !important;
                        color: #666 !important;
                        background: #f8f9fa !important;
                        padding: 10px !important;
                        border-radius: 4px !important;
                        margin-top: 10px !important;
                    }
                </style>
            `;
            
            $('head').append(css);
            console.log('🎨 CSS funcional adicionado');
        },
        
        setupEventHandlers: function() {
            var self = this;
            
            $(document).off('click.qlfunctional');
            
            // Handler para cards de tarefas
            $(document).on('click.qlfunctional', '.ql-kanban-task', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                var $task = $(this);
                if ($task.data('is-dragging') || $task.hasClass('ui-sortable-helper')) {
                    return false;
                }
                
                var taskId = $task.data('task-id') || $task.attr('data-task-id');
                if (!taskId) {
                    self.showAlert('Erro: Task ID não encontrado', 'error');
                    return false;
                }
                
                console.log('📖 Carregando dados da tarefa:', taskId);
                self.loadAndShowTask(taskId);
                return false;
            });
            
            // Handler para botões adicionar
            $(document).on('click.qlfunctional', '.ql-add-task-btn', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                var $button = $(this);
                var $column = $button.closest('.ql-kanban-column');
                var columnId = $button.data('column-id') || $button.attr('data-column-id') || 
                              $column.data('column-id') || $column.attr('data-column-id');
                
                if (!columnId) {
                    self.showAlert('Erro: Column ID não encontrado', 'error');
                    return false;
                }
                
                console.log('📝 Abrindo criação de tarefa para coluna:', columnId);
                self.showCreateTaskModal(columnId, $column);
                return false;
            });
            
            console.log('🎯 Event handlers funcionais configurados');
        },
        
        loadAndShowTask: function(taskId) {
            var self = this;
            
            // Mostrar modal de loading
            this.showLoadingModal('Carregando dados da tarefa...');
            
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
                    console.log('📦 Resposta do servidor:', response);
                    
                    if (response && response.success && response.data) {
                        self.currentTaskData = response.data;
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
            console.log('✏️ Criando modal de edição para:', taskData);
            
            this.modalCounter++;
            var modalId = 'ql-func-edit-modal-' + this.modalCounter;
            
            var modalHtml = `
                <div id="${modalId}" class="ql-functional-modal">
                    <div class="ql-functional-content" style="width: 800px;">
                        <div class="ql-functional-header">
                            <h2 class="ql-functional-title">✏️ Editar Tarefa #${taskData.id}</h2>
                            <button class="ql-functional-close" onclick="QLFunctionalModals.closeModal('${modalId}')">&times;</button>
                        </div>
                        <div class="ql-functional-body">
                            <form id="edit-task-form-${modalId}">
                                <div class="ql-functional-section">
                                    <h3>📋 Informações Básicas</h3>
                                    <div class="ql-functional-field">
                                        <label class="ql-functional-label">Título *</label>
                                        <input type="text" class="ql-functional-input" name="title" 
                                               value="${this.escapeHtml(taskData.title || '')}" required>
                                    </div>
                                    <div class="ql-functional-field">
                                        <label class="ql-functional-label">Descrição</label>
                                        <textarea class="ql-functional-textarea" name="description">${this.escapeHtml(taskData.description || '')}</textarea>
                                    </div>
                                </div>
                                
                                <div class="ql-functional-section">
                                    <h3>⚙️ Configurações</h3>
                                    <div class="ql-functional-row">
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Status</label>
                                            <select class="ql-functional-select" name="status">
                                                <option value="open" ${taskData.status === 'open' ? 'selected' : ''}>Aberta</option>
                                                <option value="in_progress" ${taskData.status === 'in_progress' ? 'selected' : ''}>Em Progresso</option>
                                                <option value="completed" ${taskData.status === 'completed' ? 'selected' : ''}>Concluída</option>
                                                <option value="closed" ${taskData.status === 'closed' ? 'selected' : ''}>Fechada</option>
                                            </select>
                                        </div>
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Prioridade</label>
                                            <select class="ql-functional-select" name="priority">
                                                <option value="low" ${taskData.priority === 'low' ? 'selected' : ''}>Baixa</option>
                                                <option value="normal" ${taskData.priority === 'normal' ? 'selected' : ''}>Normal</option>
                                                <option value="high" ${taskData.priority === 'high' ? 'selected' : ''}>Alta</option>
                                                <option value="urgent" ${taskData.priority === 'urgent' ? 'selected' : ''}>Urgente</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ql-functional-row">
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Data de Início</label>
                                            <input type="date" class="ql-functional-input" name="start_date" 
                                                   value="${taskData.start_date || ''}">
                                        </div>
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Data Limite</label>
                                            <input type="date" class="ql-functional-input" name="due_date" 
                                                   value="${taskData.due_date || ''}">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ql-functional-section">
                                    <h3>🏷️ Tags e Estimativas</h3>
                                    <div class="ql-functional-row">
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Tags (separadas por vírgula)</label>
                                            <input type="text" class="ql-functional-input" name="tags" 
                                                   value="${this.escapeHtml(taskData.tags || '')}" 
                                                   placeholder="frontend, urgente, bug">
                                        </div>
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Horas Estimadas</label>
                                            <input type="number" class="ql-functional-input" name="estimated_hours" 
                                                   value="${taskData.estimated_hours || ''}" 
                                                   step="0.5" min="0" placeholder="8">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="ql-functional-meta">
                                    <strong>Criada por:</strong> ${this.escapeHtml(taskData.creator_name || 'N/A')} | 
                                    <strong>Criada em:</strong> ${this.formatDate(taskData.created_at)} | 
                                    <strong>Última atualização:</strong> ${this.formatDate(taskData.updated_at)}
                                </div>
                            </form>
                        </div>
                        <div class="ql-functional-footer">
                            <button type="button" class="ql-functional-button danger" 
                                    onclick="QLFunctionalModals.deleteTask('${taskData.id}', '${modalId}')">
                                🗑️ Excluir
                            </button>
                            <button type="button" class="ql-functional-button secondary" 
                                    onclick="QLFunctionalModals.closeModal('${modalId}')">
                                Cancelar
                            </button>
                            <button type="button" class="ql-functional-button success" 
                                    onclick="QLFunctionalModals.saveTask('${taskData.id}', '${modalId}')">
                                💾 Salvar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('.ql-functional-modal').remove();
            $('body').append(modalHtml);
            
            // Focar no campo título
            setTimeout(function() {
                $(`#${modalId} input[name="title"]`).focus();
            }, 100);
            
            console.log('✅ Modal de edição criado');
        },
        
        showCreateTaskModal: function(columnId, $column) {
            console.log('📝 Criando modal de criação para coluna:', columnId);
            
            this.modalCounter++;
            var modalId = 'ql-func-create-modal-' + this.modalCounter;
            
            var columnName = $column.find('.ql-column-title').text() || 
                           $column.find('h3').text() || 
                           'Coluna #' + columnId;
            
            var modalHtml = `
                <div id="${modalId}" class="ql-functional-modal">
                    <div class="ql-functional-content" style="width: 600px;">
                        <div class="ql-functional-header">
                            <h2 class="ql-functional-title">➕ Nova Tarefa</h2>
                            <button class="ql-functional-close" onclick="QLFunctionalModals.closeModal('${modalId}')">&times;</button>
                        </div>
                        <div class="ql-functional-body">
                            <div class="ql-functional-alert success">
                                <strong>📍 Destino:</strong> ${this.escapeHtml(columnName)} (ID: ${columnId})
                            </div>
                            
                            <form id="create-task-form-${modalId}">
                                <input type="hidden" name="column_id" value="${columnId}">
                                
                                <div class="ql-functional-section">
                                    <h3>📋 Informações da Tarefa</h3>
                                    <div class="ql-functional-field">
                                        <label class="ql-functional-label">Título da Tarefa *</label>
                                        <input type="text" class="ql-functional-input" name="title" 
                                               required placeholder="Digite um título descritivo">
                                    </div>
                                    <div class="ql-functional-field">
                                        <label class="ql-functional-label">Descrição</label>
                                        <textarea class="ql-functional-textarea" name="description"
                                                  placeholder="Descreva os detalhes da tarefa..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="ql-functional-section">
                                    <h3>⚙️ Configurações Iniciais</h3>
                                    <div class="ql-functional-row">
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Prioridade</label>
                                            <select class="ql-functional-select" name="priority">
                                                <option value="low">Baixa</option>
                                                <option value="normal" selected>Normal</option>
                                                <option value="high">Alta</option>
                                                <option value="urgent">Urgente</option>
                                            </select>
                                        </div>
                                        <div class="ql-functional-col">
                                            <label class="ql-functional-label">Status Inicial</label>
                                            <select class="ql-functional-select" name="status">
                                                <option value="open" selected>Aberta</option>
                                                <option value="in_progress">Em Progresso</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ql-functional-field">
                                        <label class="ql-functional-label">Data Limite (opcional)</label>
                                        <input type="date" class="ql-functional-input" name="due_date">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="ql-functional-footer">
                            <button type="button" class="ql-functional-button secondary" 
                                    onclick="QLFunctionalModals.closeModal('${modalId}')">
                                Cancelar
                            </button>
                            <button type="button" class="ql-functional-button success" 
                                    onclick="QLFunctionalModals.createTask('${modalId}')">
                                ➕ Criar Tarefa
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('.ql-functional-modal').remove();
            $('body').append(modalHtml);
            
            // Focar no campo título
            setTimeout(function() {
                $(`#${modalId} input[name="title"]`).focus();
            }, 100);
            
            console.log('✅ Modal de criação criado');
        },
        
        showLoadingModal: function(message) {
            var modalHtml = `
                <div id="ql-loading-modal" class="ql-functional-modal">
                    <div class="ql-functional-content" style="width: 300px; text-align: center;">
                        <div class="ql-functional-body">
                            <div class="ql-functional-loading"></div>
                            <p style="margin: 0; font-size: 16px; color: #666;">${message}</p>
                        </div>
                    </div>
                </div>
            `;
            
            $('.ql-functional-modal').remove();
            $('body').append(modalHtml);
        },
        
        saveTask: function(taskId, modalId) {
            console.log('💾 Salvando tarefa:', taskId);
            
            var formData = this.getFormData(`#edit-task-form-${modalId}`);
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
        
        createTask: function(modalId) {
            console.log('➕ Criando nova tarefa');
            
            var formData = this.getFormData(`#create-task-form-${modalId}`);
            
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
        
        deleteTask: function(taskId, modalId) {
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
        
        closeModal: function(modalId) {
            if (modalId) {
                $('#' + modalId).remove();
            } else {
                $('.ql-functional-modal').remove();
            }
        },
        
        showAlert: function(message, type) {
            type = type || 'info';
            
            var alertHtml = `
                <div class="ql-functional-alert ${type}" style="
                    position: fixed; top: 20px; right: 20px; z-index: 2147483647;
                    min-width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    border-radius: 6px;">
                    ${this.escapeHtml(message)}
                    <button onclick="$(this).parent().remove()" style="
                        float: right; background: none; border: none; 
                        font-size: 18px; cursor: pointer; margin-left: 10px;">&times;</button>
                </div>
            `;
            
            $('body').append(alertHtml);
            
            // Auto-remover após 5 segundos
            setTimeout(function() {
                $('.ql-functional-alert').fadeOut(function() {
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
        }
    };
    
    // Inicializar quando jQuery estiver disponível
    if (typeof $ !== 'undefined') {
        window.QLFunctionalModals.init();
    } else {
        var checkJQuery = setInterval(function() {
            if (typeof $ !== 'undefined') {
                clearInterval(checkJQuery);
                window.QLFunctionalModals.init();
            }
        }, 100);
    }
    
    console.log('✅ MODAIS FUNCIONAIS CARREGADOS!');
    
})(typeof jQuery !== 'undefined' ? jQuery : undefined);