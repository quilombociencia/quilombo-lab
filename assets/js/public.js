/**
 * Quilombo Lab - JavaScript Público
 */

(function($) {
    'use strict';

    // Objeto principal para funcionalidades públicas
    window.QLPublic = {
        
        // Configurações
        config: {
            apiEndpoint: ql_public?.rest_url || '/wp-json/quilombo-lab/v1/',
            nonce: ql_public?.rest_nonce || '',
            currentProject: ql_public?.current_project || null,
            currentBoard: ql_public?.current_board || null,
            refreshInterval: 30000 // 30 segundos
        },
        
        // Timers
        timers: {
            autoRefresh: null,
            progressUpdate: null
        },

        // Inicializar
        init: function() {
            this.bindEvents();
            this.initProgressBars();
            this.initAutoRefresh();
            this.initTooltips();
            this.initFilterSearch();
            console.log('QL Public inicializado');
        },

        // Vincular eventos
        bindEvents: function() {
            var self = this;
            
            // Clique em tarefa para mostrar detalhes
            $(document).on('click', '.ql-public-task', function(e) {
                e.preventDefault();
                var taskId = $(this).data('task-id');
                if (taskId) {
                    self.showTaskDetails(taskId);
                }
            });
            
            // Navegação entre projetos
            $(document).on('click', '.ql-project-nav-link', function(e) {
                e.preventDefault();
                var projectSlug = $(this).data('project-slug');
                if (projectSlug) {
                    self.loadProject(projectSlug);
                }
            });
            
            // Filtros de status
            $(document).on('change', '.ql-status-filter', function() {
                self.filterTasks();
            });
            
            // Busca de tarefas
            $(document).on('input', '.ql-task-search', function() {
                self.searchTasks($(this).val());
            });
            
            // Expandir/colapsar colunas
            $(document).on('click', '.ql-column-toggle', function() {
                var column = $(this).closest('.ql-public-kanban-column');
                column.toggleClass('collapsed');
                $(this).text(column.hasClass('collapsed') ? '+' : '−');
            });
            
            // Refresh manual
            $(document).on('click', '.ql-refresh-btn', function(e) {
                e.preventDefault();
                self.refreshBoard();
            });
        },

        // Mostrar detalhes da tarefa
        showTaskDetails: function(taskId) {
            var self = this;
            
            // Criar modal se não existir
            if ($('#ql-task-details-modal').length === 0) {
                $('body').append(this.createTaskDetailsModal());
            }
            
            var modal = $('#ql-task-details-modal');
            var modalBody = modal.find('.ql-modal-body');
            
            // Mostrar loading
            modalBody.html('<div class="ql-loading-state"><p>Carregando detalhes da tarefa...</p></div>');
            modal.addClass('ql-modal-open');
            
            // Fazer requisição
            $.ajax({
                url: this.config.apiEndpoint + 'tasks/' + taskId,
                method: 'GET',
                success: function(task) {
                    self.populateTaskDetails(task);
                },
                error: function(xhr) {
                    var error = xhr.responseJSON?.message || 'Erro ao carregar detalhes da tarefa';
                    modalBody.html('<div class="ql-error-state"><p>' + error + '</p></div>');
                }
            });
        },

        // Preencher detalhes da tarefa no modal
        populateTaskDetails: function(task) {
            var modal = $('#ql-task-details-modal');
            var modalBody = modal.find('.ql-modal-body');
            
            var priorityLabels = {
                1: 'Baixa',
                2: 'Normal', 
                3: 'Alta',
                4: 'Urgente'
            };
            
            var statusLabels = {
                'open': 'Aberta',
                'in_progress': 'Em Progresso',
                'review': 'Em Revisão',
                'completed': 'Concluída'
            };
            
            var assigneeHtml = '';
            if (task.assigned_user_name) {
                assigneeHtml = `
                    <div class="ql-task-detail-assignee">
                        <div class="ql-task-avatar">${task.assigned_user_name.charAt(0).toUpperCase()}</div>
                        <span>${task.assigned_user_name}</span>
                    </div>
                `;
            } else {
                assigneeHtml = '<span class="ql-not-assigned">Não atribuída</span>';
            }
            
            var dueDateHtml = '';
            if (task.due_date) {
                var dueDate = new Date(task.due_date);
                var isOverdue = dueDate < new Date();
                dueDateHtml = `
                    <span class="ql-due-date ${isOverdue ? 'overdue' : ''}">
                        ${dueDate.toLocaleDateString('pt-BR')}
                        ${isOverdue ? ' (Atrasada)' : ''}
                    </span>
                `;
            } else {
                dueDateHtml = '<span class="ql-no-due-date">Sem prazo definido</span>';
            }
            
            var detailsHtml = `
                <div class="ql-task-details">
                    <div class="ql-task-header">
                        <h3 class="ql-task-title">${task.title}</h3>
                        <span class="ql-task-priority priority-${task.priority}">
                            ${priorityLabels[task.priority] || 'Normal'}
                        </span>
                    </div>
                    
                    <div class="ql-task-description">
                        <h4>Descrição</h4>
                        <p>${task.description || 'Sem descrição disponível'}</p>
                    </div>
                    
                    <div class="ql-task-meta-details">
                        <div class="ql-meta-item">
                            <label>Status:</label>
                            <span class="ql-task-status status-${task.status}">
                                ${statusLabels[task.status] || task.status}
                            </span>
                        </div>
                        
                        <div class="ql-meta-item">
                            <label>Responsável:</label>
                            ${assigneeHtml}
                        </div>
                        
                        <div class="ql-meta-item">
                            <label>Data de Vencimento:</label>
                            ${dueDateHtml}
                        </div>
                        
                        <div class="ql-meta-item">
                            <label>Criada em:</label>
                            <span>${new Date(task.created_at).toLocaleString('pt-BR')}</span>
                        </div>
                        
                        ${task.updated_at ? `
                            <div class="ql-meta-item">
                                <label>Última atualização:</label>
                                <span>${new Date(task.updated_at).toLocaleString('pt-BR')}</span>
                            </div>
                        ` : ''}
                    </div>
                    
                    ${task.time_estimate ? `
                        <div class="ql-task-estimate">
                            <label>Estimativa de tempo:</label>
                            <span>${task.time_estimate} hora(s)</span>
                        </div>
                    ` : ''}
                </div>
            `;
            
            modalBody.html(detailsHtml);
            modal.find('.ql-modal-title').text('Detalhes da Tarefa');
        },

        // Carregar projeto
        loadProject: function(projectSlug) {
            this.showLoading();
            window.location.href = ql_public?.project_base_url?.replace('%slug%', projectSlug) || 
                                   '/projeto/' + projectSlug;
        },

        // Filtrar tarefas por status
        filterTasks: function() {
            var selectedStatuses = [];
            $('.ql-status-filter:checked').each(function() {
                selectedStatuses.push($(this).val());
            });
            
            $('.ql-public-task').each(function() {
                var taskStatus = $(this).data('status');
                if (selectedStatuses.length === 0 || selectedStatuses.includes(taskStatus)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            this.updateColumnCounts();
        },

        // Buscar tarefas
        searchTasks: function(searchTerm) {
            searchTerm = searchTerm.toLowerCase().trim();
            
            $('.ql-public-task').each(function() {
                var task = $(this);
                var title = task.find('.ql-public-task-title').text().toLowerCase();
                var description = task.find('.ql-public-task-description').text().toLowerCase();
                
                if (!searchTerm || title.includes(searchTerm) || description.includes(searchTerm)) {
                    task.show();
                } else {
                    task.hide();
                }
            });
            
            this.updateColumnCounts();
        },

        // Atualizar contadores das colunas
        updateColumnCounts: function() {
            $('.ql-public-kanban-column').each(function() {
                var column = $(this);
                var visibleTasks = column.find('.ql-public-task:visible').length;
                column.find('.ql-public-column-count').text(visibleTasks);
            });
        },

        // Refresh do board
        refreshBoard: function() {
            if (this.config.currentBoard) {
                this.showLoading();
                location.reload();
            }
        },

        // Auto refresh (para boards dinâmicos)
        initAutoRefresh: function() {
            var self = this;
            
            // Só ativar auto refresh se estiver em uma página de board
            if (this.config.currentBoard && ql_public?.auto_refresh) {
                this.timers.autoRefresh = setInterval(function() {
                    self.silentRefresh();
                }, this.config.refreshInterval);
            }
        },

        // Refresh silencioso (sem recarregar página)
        silentRefresh: function() {
            var self = this;
            
            if (!this.config.currentBoard) return;
            
            $.ajax({
                url: this.config.apiEndpoint + 'boards/' + this.config.currentBoard + '/tasks',
                method: 'GET',
                success: function(response) {
                    // Atualizar tarefas silenciosamente
                    self.updateTasksData(response.tasks);
                },
                error: function() {
                    // Falhou silenciosamente, não mostrar erro
                    console.log('Falha no refresh automático');
                }
            });
        },

        // Atualizar dados das tarefas
        updateTasksData: function(tasks) {
            // Implementar atualização dinâmica sem recarregar página
            // Por enquanto apenas log
            console.log('Tarefas atualizadas:', tasks.length);
        },

        // Inicializar barras de progresso
        initProgressBars: function() {
            $('.ql-progress-bar').each(function() {
                var progressBar = $(this);
                var percentage = progressBar.data('percentage') || 0;
                var progressFill = progressBar.find('.ql-progress-fill');
                
                // Animar progresso
                setTimeout(function() {
                    progressFill.css('width', percentage + '%');
                }, 200);
            });
        },

        // Inicializar tooltips simples
        initTooltips: function() {
            $('[data-tooltip]').each(function() {
                var element = $(this);
                var tooltip = element.data('tooltip');
                
                element.attr('title', tooltip);
                
                // Tooltip customizado (opcional)
                element.hover(
                    function() {
                        var tooltipEl = $('<div class="ql-tooltip">' + tooltip + '</div>');
                        $('body').append(tooltipEl);
                        
                        var offset = element.offset();
                        tooltipEl.css({
                            position: 'absolute',
                            top: offset.top - tooltipEl.height() - 10,
                            left: offset.left + (element.width() / 2) - (tooltipEl.width() / 2),
                            zIndex: 10000
                        });
                    },
                    function() {
                        $('.ql-tooltip').remove();
                    }
                );
            });
        },

        // Inicializar filtro de busca
        initFilterSearch: function() {
            // Implementar busca avançada se necessário
            var searchInput = $('.ql-task-search');
            if (searchInput.length) {
                // Debounce para busca
                var timeout;
                searchInput.on('input', function() {
                    clearTimeout(timeout);
                    var term = $(this).val();
                    timeout = setTimeout(function() {
                        QLPublic.searchTasks(term);
                    }, 300);
                });
            }
        },

        // Mostrar loading
        showLoading: function() {
            if ($('.ql-public-loading').length === 0) {
                $('body').append('<div class="ql-public-loading"><div class="ql-spinner"></div><p>Carregando...</p></div>');
            }
        },

        // Esconder loading
        hideLoading: function() {
            $('.ql-public-loading').remove();
        },

        // Criar modal de detalhes da tarefa
        createTaskDetailsModal: function() {
            return `
                <div id="ql-task-details-modal" class="ql-modal">
                    <div class="ql-modal-backdrop"></div>
                    <div class="ql-modal-content">
                        <div class="ql-modal-header">
                            <h3 class="ql-modal-title">Detalhes da Tarefa</h3>
                            <button class="ql-modal-close">&times;</button>
                        </div>
                        <div class="ql-modal-body">
                            <!-- Conteúdo será preenchido dinamicamente -->
                        </div>
                        <div class="ql-modal-footer">
                            <button type="button" class="ql-btn ql-modal-close">Fechar</button>
                        </div>
                    </div>
                </div>
                
                <style>
                .ql-modal {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 100000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                }
                
                .ql-modal.ql-modal-open {
                    opacity: 1;
                    pointer-events: all;
                }
                
                .ql-modal-backdrop {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    cursor: pointer;
                }
                
                .ql-modal-content {
                    background: var(--ql-public-bg-white, #fff);
                    border-radius: var(--ql-public-radius, 8px);
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
                    width: 90%;
                    max-width: 600px;
                    max-height: 80vh;
                    overflow-y: auto;
                    position: relative;
                    transform: scale(0.9);
                    transition: transform 0.3s ease;
                }
                
                .ql-modal.ql-modal-open .ql-modal-content {
                    transform: scale(1);
                }
                
                .ql-modal-header {
                    padding: 20px;
                    border-bottom: 1px solid var(--ql-public-border, #ddd);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .ql-modal-title {
                    margin: 0;
                    color: var(--ql-public-primary, #2c3e50);
                    font-size: 1.5em;
                }
                
                .ql-modal-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    color: var(--ql-public-text-light, #999);
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    transition: all 0.2s ease;
                }
                
                .ql-modal-close:hover {
                    background: var(--ql-public-bg, #f5f5f5);
                    color: var(--ql-public-primary, #2c3e50);
                }
                
                .ql-modal-body {
                    padding: 20px;
                }
                
                .ql-modal-footer {
                    padding: 20px;
                    border-top: 1px solid var(--ql-public-border, #ddd);
                    text-align: right;
                }
                
                .ql-task-details .ql-task-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 20px;
                }
                
                .ql-task-details .ql-task-title {
                    margin: 0;
                    color: var(--ql-public-primary, #2c3e50);
                    font-size: 1.3em;
                    flex: 1;
                    margin-right: 15px;
                }
                
                .ql-task-meta-details {
                    display: grid;
                    gap: 15px;
                    margin-top: 20px;
                }
                
                .ql-meta-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 10px 0;
                    border-bottom: 1px solid var(--ql-public-border, #eee);
                }
                
                .ql-meta-item label {
                    font-weight: 600;
                    color: var(--ql-public-text, #333);
                }
                
                .ql-loading-state, .ql-error-state {
                    text-align: center;
                    padding: 40px 20px;
                    color: var(--ql-public-text-light, #999);
                }
                
                .ql-public-loading {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(255, 255, 255, 0.9);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    z-index: 999999;
                }
                
                .ql-spinner {
                    width: 40px;
                    height: 40px;
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid var(--ql-public-secondary, #3498db);
                    border-radius: 50%;
                    animation: ql-spin 1s linear infinite;
                    margin-bottom: 15px;
                }
                
                @keyframes ql-spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                </style>
            `;
        },

        // Destruir (limpar timers ao sair da página)
        destroy: function() {
            if (this.timers.autoRefresh) {
                clearInterval(this.timers.autoRefresh);
            }
            if (this.timers.progressUpdate) {
                clearInterval(this.timers.progressUpdate);
            }
        }
    };

    // Fechar modal ao clicar no backdrop ou botão fechar
    $(document).on('click', '.ql-modal-close, .ql-modal-backdrop', function(e) {
        $(this).closest('.ql-modal').removeClass('ql-modal-open');
    });

    // Fechar modal com tecla ESC
    $(document).on('keyup', function(e) {
        if (e.keyCode === 27) {
            $('.ql-modal').removeClass('ql-modal-open');
        }
    });

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        QLPublic.init();
    });

    // Limpar ao sair da página
    $(window).on('beforeunload', function() {
        QLPublic.destroy();
    });

})(jQuery);