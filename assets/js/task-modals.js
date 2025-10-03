/**
 * Quilombo Laboratório - JavaScript para Modais de Tarefas
 * Sistema completo de gestão de tarefas com hierarquia e funcionalidades avançadas
 */

(function($) {
    'use strict';
    
    // Garantir que $ está disponível mesmo com conflitos
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        $ = jQuery;
    }

    // Classe principal para gerenciamento de modais de tarefas
    window.QLTaskModals = {
        
        // Configurações
        config: {
            modalSelector: '.ql-modal',
            taskModal: '#ql-task-modal',
            quickTaskModal: '#ql-quick-task-modal',
            subtaskModal: '#ql-subtask-modal',
            currentTask: null,
            currentColumn: null,
            currentBoard: null,
            users: []
        },

        // Inicializar
        init: function() {
            console.log('QLTaskModals: Inicializando sistema de modais...');
            
            this.bindEvents();
            this.initModals();
            this.loadUsers();
            this.setupTemplates();
            
            console.log('QLTaskModals: Sistema inicializado com sucesso');
        },

        // Inicializar modais 
        initModals: function() {
            // Verificar se modais já existem, se não criar dinamicamente
            if ($('#ql-task-modal').length === 0) {
                this.loadModalTemplates();
            }
        },

        // Carregar templates de modais via AJAX
        loadModalTemplates: function() {
            var self = this;
            
            // Primeiro tentar carregar modais diretamente (se já existem no DOM)
            if ($('#ql-task-modal').length > 0) {
                console.log('✅ Modais já existem no DOM');
                return;
            }
            
            // Criar modais diretamente como fallback principal
            this.createFallbackModals();
            console.log('✅ Modais criados diretamente');
        },

        // Criar modais básicos como fallback
        createFallbackModals: function() {
            var basicModalHTML = `
                <div id="ql-quick-task-modal" class="ql-modal" style="display: none;">
                    <div class="ql-modal-overlay"></div>
                    <div class="ql-modal-content" style="width: 500px; max-width: 90vw;">
                        <div class="ql-modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd;">
                            <h2 class="ql-modal-title" style="margin: 0;">Nova Tarefa</h2>
                            <button class="ql-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                        </div>
                        <div class="ql-modal-body" style="padding: 20px;">
                            <form id="ql-quick-task-form">
                                <div style="margin-bottom: 15px;">
                                    <label for="ql-quick-title" style="display: block; margin-bottom: 5px; font-weight: bold;">Título da Tarefa *</label>
                                    <input type="text" id="ql-quick-title" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" required placeholder="Digite o título da tarefa">
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label for="ql-quick-description" style="display: block; margin-bottom: 5px; font-weight: bold;">Descrição (opcional)</label>
                                    <textarea id="ql-quick-description" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" rows="3" placeholder="Descreva brevemente a tarefa"></textarea>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                    <div style="flex: 1;">
                                        <label for="ql-quick-assignee" style="display: block; margin-bottom: 5px; font-weight: bold;">Responsável</label>
                                        <select id="ql-quick-assignee" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                            <option value="">Sem responsável</option>
                                        </select>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <label for="ql-quick-priority" style="display: block; margin-bottom: 5px; font-weight: bold;">Prioridade</label>
                                        <select id="ql-quick-priority" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                            <option value="low">Baixa</option>
                                            <option value="normal" selected>Normal</option>
                                            <option value="high">Alta</option>
                                            <option value="urgent">Urgente</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label for="ql-quick-due-date" style="display: block; margin-bottom: 5px; font-weight: bold;">Data Limite</label>
                                    <input type="date" id="ql-quick-due-date" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                            </form>
                        </div>
                        <div class="ql-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #ddd;">
                            <button type="button" class="ql-modal-close" style="padding: 8px 16px; border: 1px solid #ddd; background: #f7f7f7; border-radius: 4px; cursor: pointer;">Cancelar</button>
                            <button type="button" class="ql-create-quick-task-btn" style="padding: 8px 16px; border: none; background: #0073aa; color: white; border-radius: 4px; cursor: pointer;">Criar Tarefa</button>
                        </div>
                    </div>
                </div>
                
                <div id="ql-task-modal" class="ql-modal" style="display: none;">
                    <div class="ql-modal-overlay"></div>
                    <div class="ql-modal-content" style="width: 800px; max-width: 90vw; max-height: 90vh;">
                        <div class="ql-modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd;">
                            <h2 class="ql-modal-title" style="margin: 0;">Visualizar Tarefa</h2>
                            <button class="ql-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                        </div>
                        <div class="ql-modal-body" style="padding: 20px; text-align: center;">
                            <p>Carregando dados da tarefa...</p>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(basicModalHTML);
            console.log('✅ Modais básicos criados como fallback');
        },

        // Vincular eventos
        bindEvents: function() {
            var self = this;
            
            // Eventos de abertura de modais
            $(document).on('click', '.ql-add-task-btn', this.openQuickTaskModal.bind(this));
            $(document).on('click', '.ql-kanban-task', this.openTaskModal.bind(this));
            $(document).on('click', '.ql-add-subtask-btn', this.openSubtaskModal.bind(this));
            
            // Eventos de fechamento de modais
            $(document).on('click', '.ql-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.ql-modal-overlay', this.closeModal.bind(this));
            $(document).on('keydown', this.handleKeydown.bind(this));
            
            // Eventos de formulários
            $(document).on('click', '.ql-create-quick-task-btn', this.createQuickTask.bind(this));
            $(document).on('click', '.ql-save-task-btn', function(e) {
                console.log('🔵 Botão salvar clicado!', e);
                self.saveTask.call(self, e);
            });
            $(document).on('click', '.ql-delete-task-btn', function(e) {
                console.log('🔴 Botão excluir clicado!', e);
                e.preventDefault();
                if (typeof self.deleteTask === 'function') {
                    self.deleteTask.call(self, e);
                } else {
                    console.warn('deleteTask method not found');
                }
            });
            $(document).on('click', '.ql-create-subtask-btn', this.createSubtask.bind(this));
            
            // Eventos de comentários
            $(document).on('click', '.ql-save-comment', this.saveComment.bind(this));
            $(document).on('click', '.ql-cancel-comment', function(e) {
                e.preventDefault();
                if (typeof self.cancelComment === 'function') {
                    self.cancelComment.call(self, e);
                } else {
                    console.warn('cancelComment method not found');
                }
            });
            
            // Eventos de subtarefas
            $(document).on('change', '.ql-subtask-checkbox', this.toggleSubtask.bind(this));
            $(document).on('click', '.ql-edit-subtask', this.editSubtask.bind(this));
            $(document).on('click', '.ql-delete-subtask', this.deleteSubtask.bind(this));
            
            // Eventos de anexos
            $(document).on('change', '#ql-task-file-upload', this.handleFileUpload.bind(this));
            $(document).on('click', '.ql-delete-attachment', this.deleteAttachment.bind(this));
            
            // Eventos de cores
            $(document).on('click', '.ql-color-preset', this.selectColorPreset.bind(this));
            
            // Auto-save em campos editáveis
            $(document).on('input', '.ql-task-title[contenteditable]', this.debounce(this.autoSave.bind(this), 2000));
            $(document).on('input', '.ql-task-description[contenteditable]', this.debounce(this.autoSave.bind(this), 2000));
            
            // Eventos de mudança nos selects
            $(document).on('change', '.ql-task-sidebar select', this.onFieldChange.bind(this));
            $(document).on('change', '.ql-task-sidebar input', this.onFieldChange.bind(this));
        },

        // Abrir modal de criação rápida de tarefa
        openQuickTaskModal: function(e) {
            console.log('🔵 QLTaskModals: openQuickTaskModal chamado!', e);
            e.preventDefault();
            e.stopImmediatePropagation();
            
            var $target = $(e.target);
            var $column = $target.closest('.ql-kanban-column');
            var columnId = $target.data('column-id') || $column.data('column-id');
            var columnName = $column.find('.ql-column-title').text() || $column.find('h3').text() || 'Coluna';
            
            console.log('🔍 Column ID encontrado:', columnId);
            console.log('🔍 Column Name encontrado:', columnName);
            
            if (!columnId) {
                console.error('❌ Column ID não encontrado. Target:', $target, 'Column:', $column);
                // Tentar encontrar o column ID de outra forma
                columnId = $column.attr('data-column-id') || $target.attr('data-column-id');
                if (!columnId) {
                    alert('Erro: Column ID não encontrado. Verifique a estrutura do quadro.');
                    return;
                }
            }
            
            this.config.currentColumn = columnId;
            
            // Limpar formulário
            var $form = $('#ql-quick-task-form');
            if ($form.length > 0) {
                $form[0].reset();
            }
            
            // Atualizar título do modal
            $('#ql-quick-task-modal .ql-modal-title').text('Nova Tarefa - ' + columnName);
            
            // Carregar usuários no select
            this.populateUserSelect('#ql-quick-assignee');
            
            // Abrir modal
            console.log('✅ Abrindo modal de nova tarefa...');
            this.openModal('#ql-quick-task-modal');
        },

        // Abrir modal principal de tarefa
        openTaskModal: function(e) {
            console.log('🔥 QLTaskModals: openTaskModal CHAMADO!', e);
            
            e.preventDefault();
            e.stopImmediatePropagation(); // Evitar conflito com drag & drop e outros handlers
            
            var $task = $(e.currentTarget);
            console.log('📋 Task element:', $task);
            
            // Verificar se está em modo drag
            if ($task.data('is-dragging') || $task.hasClass('ql-dragging') || $task.hasClass('ui-sortable-helper')) {
                console.log('⏭️ QLTaskModals: Ignorando clique durante drag');
                return;
            }
            
            var taskId = $task.data('task-id') || $task.attr('data-task-id');
            console.log('🔢 Task ID encontrado:', taskId);
            
            if (!taskId) {
                console.error('❌ Task ID não encontrado. Element:', $task);
                // Tentar encontrar task ID de forma alternativa
                taskId = $task.find('[data-task-id]').first().data('task-id');
                if (!taskId) {
                    alert('Erro: Task ID não encontrado. Verifique a estrutura do card.');
                    return;
                }
            }
            
            console.log('✅ QLTaskModals: Abrindo modal para tarefa', taskId);
            this.loadTaskData(taskId);
        },

        // Abrir modal de subtarefa
        openSubtaskModal: function(e) {
            e.preventDefault();
            
            if (!this.config.currentTask) {
                console.error('Nenhuma tarefa pai selecionada');
                return;
            }
            
            // Limpar formulário
            $('#ql-subtask-form')[0].reset();
            
            // Definir tarefa pai
            $('#ql-subtask-parent-title').text(this.config.currentTask.title);
            
            // Carregar usuários e colunas
            this.populateUserSelect('#ql-subtask-assignee');
            this.populateColumnSelect('#ql-subtask-column');
            
            // Pré-selecionar responsável da tarefa pai se existir
            if (this.config.currentTask.assignee_id) {
                $('#ql-subtask-assignee').val(this.config.currentTask.assignee_id);
            }
            
            // Pré-selecionar coluna da tarefa pai
            $('#ql-subtask-column').val(this.config.currentTask.column_id);
            
            this.openModal('#ql-subtask-modal');
        },

        // Carregar dados da tarefa
        loadTaskData: function(taskId) {
            var self = this;
            
            // Mostrar loading no modal
            var $modal = $(this.config.taskModal);
            $modal.find('.ql-modal-body').addClass('ql-loading');
            
            this.openModal(this.config.taskModal);
            
            // Fazer requisição AJAX
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_get_task',
                    task_id: taskId,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.populateTaskModal(response.data);
                        self.config.currentTask = response.data;
                    } else {
                        self.showError('Erro ao carregar dados da tarefa: ' + (response.data || 'Erro desconhecido'));
                        self.closeModal();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao carregar tarefa:', error);
                    self.showError('Erro de conexão ao carregar tarefa');
                    self.closeModal();
                },
                complete: function() {
                    $modal.find('.ql-modal-body').removeClass('ql-loading');
                }
            });
        },

        // Popular modal da tarefa com dados
        populateTaskModal: function(taskData) {
            var $modal = $(this.config.taskModal);
            
            // Cabeçalho
            $modal.find('.ql-task-number').text('#' + (taskData.task_number || 'QL-' + taskData.id));
            $modal.find('.ql-badge-open').removeClass('ql-badge-open ql-badge-in-progress ql-badge-review ql-badge-completed ql-badge-cancelled')
                  .addClass('ql-badge-' + (taskData.status || 'open')).text(this.getStatusLabel(taskData.status));
            $modal.find('.ql-badge-priority-normal').removeClass('ql-badge-priority-low ql-badge-priority-normal ql-badge-priority-high ql-badge-priority-urgent')
                  .addClass('ql-badge-priority-' + (taskData.priority || 'normal')).text(this.getPriorityLabel(taskData.priority));
            
            // Breadcrumb
            $modal.find('.ql-breadcrumb-project').text(taskData.project_name || 'Projeto');
            $modal.find('.ql-breadcrumb-board').text(taskData.board_name || 'Quadro');
            $modal.find('.ql-breadcrumb-column').text(taskData.column_name || 'Coluna');
            
            // Título e descrição
            $modal.find('.ql-task-title').text(taskData.title || '');
            $modal.find('.ql-task-description').html(taskData.description || '');
            
            // Sidebar
            this.populateUserSelect('#ql-task-assignee', taskData.assignee_id);
            $('#ql-task-status').val(taskData.status || 'open');
            $('#ql-task-priority').val(taskData.priority || 'normal');
            $('#ql-task-type').val(taskData.task_type || 'feature');
            $('#ql-task-start-date').val(taskData.start_date ? taskData.start_date.split(' ')[0] : '');
            $('#ql-task-due-date').val(taskData.due_date ? taskData.due_date.split(' ')[0] : '');
            $('#ql-task-estimated-hours').val(taskData.estimated_hours || '');
            $('#ql-task-actual-hours').val(taskData.actual_hours || '');
            $('#ql-task-story-points').val(taskData.story_points || '');
            $('#ql-task-tags').val(taskData.tags || '');
            $('#ql-task-color').val(taskData.color || '#ffffff');
            
            // Informações
            $('#ql-task-creator').text(taskData.creator_name || '-');
            $('#ql-task-created-at').text(this.formatDate(taskData.created_at));
            $('#ql-task-updated-at').text(this.formatDate(taskData.updated_at));
            
            // Subtarefas
            this.populateSubtasks(taskData.subtasks || []);
            
            // Atividades
            this.populateActivities(taskData.activities || []);
            
            // Anexos
            this.populateAttachments(taskData.attachments || []);
        },

        // Popular subtarefas
        populateSubtasks: function(subtasks) {
            var $container = $('.ql-subtasks-list');
            var $counter = $('#ql-subtasks-count');
            
            $container.empty();
            $counter.text(subtasks.length);
            
            if (subtasks.length === 0) {
                $container.html('<p class="ql-empty-state">Nenhuma subtarefa encontrada. <a href="#" class="ql-add-subtask-btn">Criar primeira subtarefa</a></p>');
                return;
            }
            
            var template = $('#ql-subtask-item-template').html();
            
            subtasks.forEach(function(subtask) {
                var html = template.replace(/\{\{(\w+)\}\}/g, function(match, key) {
                    return subtask[key] || '';
                });
                
                html = html.replace('{{#if completed}}checked{{/if}}', subtask.status === 'completed' ? 'checked' : '');
                html = html.replace('{{#if assigned_user_name}}', subtask.assigned_user_name ? '' : '<!--');
                html = html.replace('{{/if}}', subtask.assigned_user_name ? '' : '-->');
                
                $container.append(html);
            });
        },

        // Popular atividades
        populateActivities: function(activities) {
            var $container = $('.ql-activities-list');
            
            $container.empty();
            
            if (activities.length === 0) {
                $container.html('<p class="ql-empty-state">Nenhuma atividade registrada.</p>');
                return;
            }
            
            var self = this;
            var template = $('#ql-activity-item-template').html();
            
            activities.forEach(function(activity) {
                var html = template.replace(/\{\{(\w+)\}\}/g, function(match, key) {
                    switch(key) {
                        case 'user_name_initial':
                            return (activity.user_name || 'U').charAt(0).toUpperCase();
                        case 'action_text':
                            return self.getActivityActionText(activity.activity_type);
                        case 'time_ago':
                            return self.timeAgo(activity.created_at);
                        default:
                            return activity[key] || '';
                    }
                });
                
                $container.append(html);
            });
        },

        // Popular anexos
        populateAttachments: function(attachments) {
            var $container = $('.ql-attachments-list');
            
            $container.empty();
            
            if (attachments.length === 0) {
                return;
            }
            
            var template = $('#ql-attachment-item-template').html();
            
            attachments.forEach(function(attachment) {
                var html = template.replace(/\{\{(\w+)\}\}/g, function(match, key) {
                    switch(key) {
                        case 'file_size':
                            return attachment.file_size || '0 KB';
                        case 'file_url':
                            return attachment.file_url || '#';
                        default:
                            return attachment[key] || '';
                    }
                });
                
                $container.append(html);
            });
        },

        // Criar tarefa rápida
        createQuickTask: function(e) {
            e.preventDefault();
            
            var self = this;
            var $form = $('#ql-quick-task-form');
            var $btn = $('.ql-create-quick-task-btn');
            
            // Validar formulário
            var title = $('#ql-quick-title').val().trim();
            if (!title) {
                this.showError('Título da tarefa é obrigatório');
                $('#ql-quick-title').focus();
                return;
            }
            
            // Coletar dados
            var taskData = {
                title: title,
                description: $('#ql-quick-description').val().trim(),
                assignee_id: $('#ql-quick-assignee').val(),
                priority: $('#ql-quick-priority').val(),
                due_date: $('#ql-quick-due-date').val(),
                column_id: this.config.currentColumn,
                board_id: this.getCurrentBoardId()
            };
            
            // Loading
            $btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> Criando...');
            
            // AJAX
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_create_task',
                    title: taskData.title,
                    description: taskData.description,
                    assignee_id: taskData.assignee_id,
                    priority: taskData.priority,
                    due_date: taskData.due_date,
                    column_id: taskData.column_id,
                    board_id: taskData.board_id,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Tarefa criada com sucesso!');
                        self.closeModal();
                        
                        // Recarregar board
                        if (window.QLKanban && window.QLKanban.loadBoardData) {
                            window.QLKanban.loadBoardData();
                        }
                    } else {
                        self.showError('Erro ao criar tarefa: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao criar tarefa:', error);
                    self.showError('Erro de conexão ao criar tarefa');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="dashicons dashicons-plus"></i> Criar Tarefa');
                }
            });
        },

        // Salvar tarefa
        saveTask: function(e) {
            console.log('🔄 QLTaskModals: saveTask chamado', e);
            e.preventDefault();
            
            if (!this.config.currentTask) {
                console.error('❌ Nenhuma tarefa carregada para salvar');
                alert('Erro: Nenhuma tarefa carregada para salvar');
                return;
            }
            
            console.log('📋 Tarefa atual:', this.config.currentTask);
            
            var self = this;
            var $btn = $('.ql-save-task-btn');
            
            if ($btn.length === 0) {
                console.error('❌ Botão salvar não encontrado');
                alert('Erro: Botão salvar não encontrado');
                return;
            }
            
            // Coletar dados do formulário
            var taskData = this.collectTaskData();
            console.log('📝 Dados coletados:', taskData);
            
            // Loading
            $btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> Salvando...');
            
            // AJAX
            console.log('🌐 Iniciando AJAX para salvar tarefa...');
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_update_task',
                    task_id: this.config.currentTask.id,
                    task_data: taskData,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Tarefa salva com sucesso!');
                        
                        // Atualizar dados locais
                        Object.assign(self.config.currentTask, taskData);
                        
                        // Recarregar board
                        if (window.QLKanban && window.QLKanban.loadBoardData) {
                            window.QLKanban.loadBoardData();
                        }
                    } else {
                        self.showError('Erro ao salvar tarefa: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao salvar tarefa:', error);
                    self.showError('Erro de conexão ao salvar tarefa');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="dashicons dashicons-saved"></i> Salvar Alterações');
                }
            });
        },

        // Excluir tarefa
        deleteTask: function(e) {
            console.log('🗑️ QLTaskModals: deleteTask chamado', e);
            e.preventDefault();
            
            if (!this.config.currentTask) {
                console.error('❌ Nenhuma tarefa carregada para excluir');
                alert('Erro: Nenhuma tarefa carregada para excluir');
                return;
            }
            
            console.log('📋 Tarefa a excluir:', this.config.currentTask);
            
            if (!confirm('Tem certeza que deseja excluir esta tarefa?')) {
                console.log('🚫 Exclusão cancelada pelo usuário');
                return;
            }
            
            console.log('✅ Usuário confirmou exclusão, prosseguindo...');
            
            var self = this;
            var taskId = this.config.currentTask.id;
            var $btn = $('.ql-delete-task-btn');
            
            console.log('🔍 Task ID para excluir:', taskId);
            
            if (!$btn.length) {
                console.error('❌ Botão excluir não encontrado');
                alert('Erro: Botão excluir não encontrado');
                return;
            }
            
            // Loading
            $btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> Excluindo...');
            
            // AJAX
            console.log('🌐 Iniciando AJAX para excluir tarefa...');
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_delete_task',
                    task_id: taskId,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Tarefa excluída com sucesso!');
                        self.closeModal();
                        
                        // Remover card da interface
                        $('.ql-kanban-task[data-task-id="' + taskId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                        
                        // Recarregar board
                        if (window.QLKanban && window.QLKanban.loadBoardData) {
                            window.QLKanban.loadBoardData();
                        }
                    } else {
                        self.showError('Erro ao excluir tarefa: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao excluir tarefa:', error);
                    self.showError('Erro de conexão ao excluir tarefa');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="dashicons dashicons-trash"></i> Excluir Tarefa');
                }
            });
        },

        // Coletar dados da tarefa do formulário
        collectTaskData: function() {
            console.log('📋 Coletando dados da tarefa...');
            
            // Verificar se há campos de input ou usar elementos de exibição
            var titleEl = $('#ql-task-title-input').length ? $('#ql-task-title-input') : $('.ql-task-title');
            var descEl = $('#ql-task-description-input').length ? $('#ql-task-description-input') : $('.ql-task-description');
            
            var data = {
                title: titleEl.is('input') || titleEl.is('textarea') ? titleEl.val().trim() : titleEl.text().trim(),
                description: descEl.is('textarea') ? descEl.val() : descEl.html(),
                assignee_id: $('#ql-task-assignee').val() || null,
                status: $('#ql-task-status').val() || 'open',
                priority: $('#ql-task-priority').val() || 'normal',
                task_type: $('#ql-task-type').val() || null,
                start_date: $('#ql-task-start-date').val() || null,
                due_date: $('#ql-task-due-date').val() || null,
                estimated_hours: $('#ql-task-estimated-hours').val() || null,
                actual_hours: $('#ql-task-actual-hours').val() || null,
                story_points: $('#ql-task-story-points').val() || null,
                tags: $('#ql-task-tags').val() || null,
                color: $('#ql-task-color').val() || null
            };
            
            console.log('📝 Dados coletados do formulário:', data);
            return data;
        },

        // Auto-save
        autoSave: function() {
            if (!this.config.currentTask) return;
            
            var taskData = this.collectTaskData();
            
            // Salvar silenciosamente
            $.ajax({
                url: ql_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'ql_update_task',
                    task_id: this.config.currentTask.id,
                    task_data: taskData,
                    nonce: ql_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Auto-save realizado com sucesso');
                    }
                }
            });
        },

        // Criar subtarefa
        createSubtask: function(e) {
            e.preventDefault();
            
            if (!this.config.currentTask) {
                console.error('Nenhuma tarefa pai selecionada');
                return;
            }
            
            var self = this;
            var $btn = $('.ql-create-subtask-btn');
            
            // Validar
            var title = $('#ql-subtask-title').val().trim();
            if (!title) {
                this.showError('Título da subtarefa é obrigatório');
                $('#ql-subtask-title').focus();
                return;
            }
            
            // Coletar dados
            var subtaskData = {
                title: title,
                description: $('#ql-subtask-description').val().trim(),
                assignee_id: $('#ql-subtask-assignee').val(),
                column_id: $('#ql-subtask-column').val(),
                parent_task_id: this.config.currentTask.id
            };
            
            // Loading
            $btn.prop('disabled', true).html('<i class="dashicons dashicons-update spin"></i> Criando...');
            
            // AJAX
            $.ajax({
                url: ql_admin.ajax_url,
                method: 'POST',
                data: {
                    action: 'ql_create_subtask',
                    subtask_data: subtaskData,
                    nonce: ql_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess('Subtarefa criada com sucesso!');
                        self.closeModal('#ql-subtask-modal');
                        
                        // Recarregar subtarefas
                        self.loadTaskData(self.config.currentTask.id);
                    } else {
                        self.showError('Erro ao criar subtarefa: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao criar subtarefa:', error);
                    self.showError('Erro de conexão ao criar subtarefa');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="dashicons dashicons-plus"></i> Criar Subtarefa');
                }
            });
        },

        // Salvar comentário
        saveComment: function(e) {
            e.preventDefault();
            
            if (!this.config.currentTask) return;
            
            var self = this;
            var $textarea = $('.ql-comment-textarea');
            var $btn = $('.ql-save-comment');
            var content = $textarea.val().trim();
            var isPrivate = $('#ql-comment-private-check').is(':checked');
            
            if (!content) {
                this.showError('Digite um comentário');
                $textarea.focus();
                return;
            }
            
            // Loading
            $btn.prop('disabled', true).text('Salvando...');
            
            // AJAX
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_add_comment',
                    task_id: this.config.currentTask.id,
                    content: content,
                    is_private: isPrivate,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        $textarea.val('');
                        $('#ql-comment-private-check').prop('checked', false);
                        
                        // Recarregar atividades
                        self.loadTaskActivities();
                        
                        self.showSuccess('Comentário adicionado!');
                    } else {
                        self.showError('Erro ao adicionar comentário: ' + (response.data || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX ao salvar comentário:', error);
                    self.showError('Erro de conexão ao salvar comentário');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Comentar');
                }
            });
        },

        // Cancelar comentário
        cancelComment: function(e) {
            e.preventDefault();
            
            var $container = $(e.target).closest('.ql-comment-form');
            var $textarea = $container.find('.ql-comment-textarea');
            
            $textarea.val('');
            $('#ql-comment-private-check').prop('checked', false);
        },

        // Utilitários
        openModal: function(selector) {
            var $modal = $(selector);
            
            // Adicionar estilos inline se não existir CSS do plugin
            if (!$modal.hasClass('ql-modal-styled')) {
                $modal.css({
                    'position': 'fixed',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%',
                    'background': 'rgba(0, 0, 0, 0.5)',
                    'z-index': '999999',
                    'display': 'flex',
                    'align-items': 'center',
                    'justify-content': 'center'
                });
                
                $modal.find('.ql-modal-overlay').css({
                    'position': 'absolute',
                    'top': '0',
                    'left': '0',
                    'width': '100%',
                    'height': '100%'
                });
                
                $modal.find('.ql-modal-content').css({
                    'position': 'relative',
                    'background': 'white',
                    'border-radius': '8px',
                    'box-shadow': '0 10px 30px rgba(0, 0, 0, 0.3)'
                });
                
                $modal.addClass('ql-modal-styled');
            }
            
            $modal.show();
            $('body').css('overflow', 'hidden');
            
            // Focus no primeiro campo editável
            setTimeout(function() {
                $modal.find('input, textarea, [contenteditable="true"]').first().focus();
            }, 100);
        },

        closeModal: function(selector) {
            if (selector) {
                $(selector).hide();
            } else {
                $('.ql-modal').hide();
                this.config.currentTask = null;
            }
            
            if (!$('.ql-modal:visible').length) {
                $('body').css('overflow', '');
            }
        },

        handleKeydown: function(e) {
            if (e.keyCode === 27) { // ESC
                this.closeModal();
            }
        },

        populateUserSelect: function(selector, selectedValue) {
            var $select = $(selector);
            $select.empty();
            
            $select.append('<option value="">Sem responsável</option>');
            
            this.config.users.forEach(function(user) {
                var selected = selectedValue == user.ID ? 'selected' : '';
                $select.append('<option value="' + user.ID + '" ' + selected + '>' + user.display_name + '</option>');
            });
        },

        populateColumnSelect: function(selector) {
            var $select = $(selector);
            $select.empty();
            
            // Assumindo que temos acesso às colunas do board atual
            if (window.QLKanban && window.QLKanban.getCurrentBoard) {
                var board = window.QLKanban.getCurrentBoard();
                if (board && board.columns) {
                    board.columns.forEach(function(column) {
                        $select.append('<option value="' + column.id + '">' + column.name + '</option>');
                    });
                }
            }
        },

        loadUsers: function() {
            var self = this;
            
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_get_users',
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.config.users = response.data;
                    }
                }
            });
        },

        // Utilitários de formatação
        formatDate: function(dateString) {
            if (!dateString) return '-';
            var date = new Date(dateString);
            return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
        },

        timeAgo: function(dateString) {
            if (!dateString) return '';
            
            var date = new Date(dateString);
            var now = new Date();
            var diffMs = now - date;
            var diffMins = Math.floor(diffMs / 60000);
            var diffHours = Math.floor(diffMins / 60);
            var diffDays = Math.floor(diffHours / 24);
            
            if (diffMins < 1) return 'agora';
            if (diffMins < 60) return diffMins + 'm atrás';
            if (diffHours < 24) return diffHours + 'h atrás';
            if (diffDays < 7) return diffDays + 'd atrás';
            
            return this.formatDate(dateString);
        },

        getStatusLabel: function(status) {
            var labels = {
                'open': 'Aberta',
                'in_progress': 'Em Progresso',
                'review': 'Em Revisão',
                'completed': 'Concluída',
                'cancelled': 'Cancelada'
            };
            return labels[status] || 'Aberta';
        },

        getPriorityLabel: function(priority) {
            var labels = {
                'low': 'Baixa',
                'normal': 'Normal',
                'high': 'Alta',
                'urgent': 'Urgente'
            };
            return labels[priority] || 'Normal';
        },

        getActivityActionText: function(type) {
            var actions = {
                'comment': 'comentou',
                'field_change': 'alterou',
                'moved': 'moveu a tarefa',
                'status_change': 'alterou o status',
                'assignment': 'alterou responsável'
            };
            return actions[type] || 'fez uma ação';
        },

        // Debounce para auto-save
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Mostrar mensagens
        showSuccess: function(message) {
            this.showNotification(message, 'success');
        },

        showError: function(message) {
            this.showNotification(message, 'error');
        },

        showNotification: function(message, type) {
            // Criar notificação temporária
            var $notification = $('<div class="ql-notification ql-notification-' + type + '">' + message + '</div>');
            $('body').append($notification);
            
            // Animar entrada
            setTimeout(function() {
                $notification.addClass('ql-notification-show');
            }, 100);
            
            // Remover após 4 segundos
            setTimeout(function() {
                $notification.removeClass('ql-notification-show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 4000);
        },

        // Eventos específicos
        onFieldChange: function(e) {
            // Marcar como alterado para indicar ao usuário que há mudanças não salvas
            $(e.target).addClass('ql-changed');
        },

        selectColorPreset: function(e) {
            var color = $(e.target).data('color');
            $('#ql-task-color').val(color);
        },

        toggleSubtask: function(e) {
            var $checkbox = $(e.target);
            var subtaskId = $checkbox.closest('.ql-subtask-item').data('subtask-id');
            var isCompleted = $checkbox.is(':checked');
            
            // AJAX para atualizar status da subtarefa
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_toggle_subtask',
                    subtask_id: subtaskId,
                    completed: isCompleted,
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.nonce) ? ql_admin.nonce : ''
                },
                success: function(response) {
                    if (!response.success) {
                        // Reverter checkbox em caso de erro
                        $checkbox.prop('checked', !isCompleted);
                    }
                }
            });
        },

        setupTemplates: function() {
            // Templates já estão definidos no HTML
            // Esta função pode ser expandida para templates mais complexos se necessário
        },

        // Obter ID do board atual
        getCurrentBoardId: function() {
            // Método 1: ql_admin (prioridade) - com verificação de null
            if (typeof ql_admin !== 'undefined' && ql_admin && ql_admin.board_id && ql_admin.board_id > 0) {
                return ql_admin.board_id;
            }
            
            // Método 1.1: window.ql_admin como fallback
            if (typeof window.ql_admin !== 'undefined' && window.ql_admin && window.ql_admin.board_id && window.ql_admin.board_id > 0) {
                return window.ql_admin.board_id;
            }
            
            // Método 2: data attribute no container
            var boardContainer = $('.ql-kanban-board');
            if (boardContainer.length > 0) {
                var boardId = boardContainer.data('board-id');
                if (boardId && boardId > 0) return boardId;
            }
            
            // Método 3: URL parameter
            var urlParams = new URLSearchParams(window.location.search);
            var boardIdFromUrl = urlParams.get('board_id');
            if (boardIdFromUrl && boardIdFromUrl > 0) {
                return parseInt(boardIdFromUrl);
            }
            
            // Método 4: configuração padrão do admin (se disponível)
            if (typeof ql_admin !== 'undefined' && ql_admin.default_board_id && ql_admin.default_board_id > 0) {
                return ql_admin.default_board_id;
            }
            
            console.error('Board ID não encontrado para task-modals');
            return null;
        }
    };

    // Inicializar quando o documento estiver pronto
    $(document).ready(function() {
        QLTaskModals.init();
    });

})(jQuery);