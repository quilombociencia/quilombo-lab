/**
 * Quilombo Laboratório - Admin JavaScript
 */

(function($) {
    'use strict';

    // Objeto principal
    window.QLAdmin = {
        
        // Configurações
        config: {
            apiEndpoint: ql_admin?.rest_url || '/wp-json/quilombo-lab/v1/',
            nonce: ql_admin?.rest_nonce || ''
        },
        
        // Inicializar
        init: function() {
            this.bindEvents();
            this.initTooltips();
            console.log('QL Admin inicializado');
        },

        // Vincular eventos
        bindEvents: function() {
            var self = this;
            
            // Refresh da página de status
            $(document).on('click', '.ql-refresh-status', function(e) {
                e.preventDefault();
                location.reload();
            });
            
            // Confirmação de ações destrutivas
            $(document).on('click', '.ql-danger-action', function(e) {
                if (!confirm('Tem certeza que deseja executar esta ação?')) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Sincronizar trilhas do Moodle
            $(document).on('click', '#sync-trilhas-moodle', function(e) {
                e.preventDefault();
                self.syncTrilhasMoodle($(this));
            });
            
            // Limpar tarefas de exemplo
            $(document).on('click', '#clear-example-tasks', function(e) {
                e.preventDefault();
                self.clearExampleTasks($(this));
            });
        },
        
        // Inicializar tooltips
        initTooltips: function() {
            // Adicionar tooltips simples onde necessário
            $('[data-tooltip]').each(function() {
                var $this = $(this);
                var tooltip = $this.data('tooltip');
                
                $this.attr('title', tooltip);
            });
        },
        
        // Mostrar notificação
        showNotification: function(message, type) {
            type = type || 'info';
            
            var notification = $('<div class="ql-notification ql-notification-' + type + '">' + message + '</div>');
            
            $('body').append(notification);
            
            setTimeout(function() {
                notification.addClass('ql-notification-show');
            }, 100);
            
            setTimeout(function() {
                notification.removeClass('ql-notification-show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        },

        // Mostrar loading
        showLoading: function() {
            if ($('.ql-loading-overlay').length === 0) {
                $('body').append('<div class="ql-loading-overlay"><div class="ql-loading-spinner"></div></div>');
            }
        },

        // Esconder loading
        hideLoading: function() {
            $('.ql-loading-overlay').remove();
        },

        // Fazer requisição AJAX
        ajaxRequest: function(url, data, method, callback) {
            var self = this;
            method = method || 'POST';
            
            $.ajax({
                url: url,
                method: method,
                data: data,
                headers: {
                    'X-WP-Nonce': this.config.nonce
                },
                beforeSend: function() {
                    self.showLoading();
                },
                success: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        self.showNotification(response.message || 'Operação realizada com sucesso', 'success');
                        if (callback) callback(response.data);
                    } else {
                        self.showNotification(response.message || 'Erro na operação', 'error');
                    }
                },
                error: function(xhr) {
                    self.hideLoading();
                    var error = xhr.responseJSON?.message || 'Erro na requisição';
                    self.showNotification(error, 'error');
                }
            });
        },

        // Confirmar ação
        confirmAction: function(message, callback) {
            if (confirm(message || 'Tem certeza?')) {
                if (callback) callback();
            }
        },

        // Sincronizar trilhas do Moodle
        syncTrilhasMoodle: function($button) {
            var self = this;
            
            this.confirmAction('Sincronizar trilhas do Moodle? Isso pode demorar alguns minutos.', function() {
                // Desabilitar botão durante a sincronização
                $button.prop('disabled', true).text('Sincronizando...');
                
                self.ajaxRequest(ajaxurl, {
                    action: 'ql_sync_trilhas_moodle',
                    nonce: ql_admin.nonce
                }, 'POST', function(data) {
                    // Restaurar botão
                    $button.prop('disabled', false).text('Sincronizar Trilhas');
                    
                    if (data.synchronized_count !== undefined) {
                        self.showNotification(
                            data.synchronized_count + ' trilhas sincronizadas com sucesso!', 
                            'success'
                        );
                        
                        // Recarregar a página após 2 segundos para mostrar as novas trilhas
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else if (data.message) {
                        self.showNotification(data.message, 'success');
                    }
                });
            });
        }
    };
    
    // Funções globais para uso nos templates
    window.qlViewProjectDetails = function(projectId) {
        QLAdmin.showNotification('Carregando detalhes do projeto...', 'info');
        
        // Fazer requisição para API
        $.ajax({
            url: QLAdmin.config.apiEndpoint + 'projects/' + projectId,
            method: 'GET',
            headers: {
                'X-WP-Nonce': QLAdmin.config.nonce
            },
            success: function(project) {
                // Mostrar modal com detalhes
                var modalHtml = `
                    <div id="ql-project-details-modal" class="ql-modal ql-modal-open">
                        <div class="ql-modal-backdrop"></div>
                        <div class="ql-modal-content">
                            <div class="ql-modal-header">
                                <h3 class="ql-modal-title">Detalhes do Projeto</h3>
                                <button class="ql-modal-close">&times;</button>
                            </div>
                            <div class="ql-modal-body">
                                <h4>${project.name}</h4>
                                <p><strong>Status:</strong> ${project.status}</p>
                                <p><strong>Descrição:</strong> ${project.description || 'Sem descrição'}</p>
                                <p><strong>Criado em:</strong> ${new Date(project.created_at).toLocaleDateString('pt-BR')}</p>
                                ${project.start_date ? '<p><strong>Data de início:</strong> ' + new Date(project.start_date).toLocaleDateString('pt-BR') + '</p>' : ''}
                                ${project.end_date ? '<p><strong>Data de fim:</strong> ' + new Date(project.end_date).toLocaleDateString('pt-BR') + '</p>' : ''}
                            </div>
                            <div class="ql-modal-footer">
                                <button type="button" class="button ql-modal-close">Fechar</button>
                                <a href="${window.location.href.split('?')[0]}?page=quilombo-lab-project-boards&project_id=${projectId}" class="button button-primary">Ver Quadros</a>
                            </div>
                        </div>
                    </div>
                `;
                
                $('body').append(modalHtml);
                
                // Bind close events
                $(document).on('click', '#ql-project-details-modal .ql-modal-close, #ql-project-details-modal .ql-modal-backdrop', function() {
                    $('#ql-project-details-modal').remove();
                });
            },
            error: function(xhr) {
                var error = xhr.responseJSON?.message || 'Erro ao carregar detalhes do projeto';
                QLAdmin.showNotification(error, 'error');
            }
        });
    };
    
    // Função qlOpenQuadro removida daqui - está definida na página PHP para evitar conflitos

    // Funções AJAX para tarefas
    window.qlCreateTask = function(columnId, boardId) {
        var title = prompt('Título da tarefa:');
        if (!title || !title.trim()) return;
        
        QLAdmin.ajaxRequest(ajaxurl, {
            action: 'ql_create_task',
            title: title.trim(),
            column_id: columnId,
            board_id: boardId,
            nonce: ql_admin.nonce
        }, 'POST', function(data) {
            if (data.task) {
                location.reload(); // Recarregar para mostrar nova tarefa
            }
        });
    };

    window.qlDeleteTask = function(taskId) {
        QLAdmin.confirmAction('Tem certeza que deseja excluir esta tarefa?', function() {
            QLAdmin.ajaxRequest(ajaxurl, {
                action: 'ql_delete_task',
                task_id: taskId,
                nonce: ql_admin.nonce
            }, 'POST', function() {
                location.reload();
            });
        });
    };

    window.qlUpdateTaskStatus = function(taskId, status) {
        QLAdmin.ajaxRequest(ajaxurl, {
            action: 'ql_update_task',
            task_id: taskId,
            status: status,
            nonce: ql_admin.nonce
        }, 'POST', function() {
            QLAdmin.showNotification('Status da tarefa atualizado', 'success');
        });
    };

    // Funções AJAX para projetos
    window.qlCreateProject = function() {
        var name = prompt('Nome do projeto:');
        if (!name || !name.trim()) return;
        
        var description = prompt('Descrição do projeto (opcional):') || '';
        
        QLAdmin.ajaxRequest(ajaxurl, {
            action: 'ql_create_project',
            name: name.trim(),
            description: description.trim(),
            nonce: ql_admin.nonce
        }, 'POST', function(data) {
            if (data.project_id) {
                location.reload();
            }
        });
    };

    window.qlDeleteProject = function(projectId) {
        QLAdmin.confirmAction('ATENÇÃO: Excluir um projeto irá remover todas as suas tarefas e quadros. Esta ação não pode ser desfeita. Continuar?', function() {
            QLAdmin.ajaxRequest(ajaxurl, {
                action: 'ql_delete_project',
                project_id: projectId,
                nonce: ql_admin.nonce
            }, 'POST', function() {
                location.reload();
            });
        });
    };

    // Função para buscar usuários
    window.qlSearchUsers = function(searchTerm, callback) {
        if (searchTerm.length < 2) {
            if (callback) callback([]);
            return;
        }
        
        QLAdmin.ajaxRequest(ajaxurl, {
            action: 'ql_search_users',
            search: searchTerm,
            nonce: ql_admin.nonce
        }, 'POST', function(data) {
            if (callback) callback(data.users || []);
        });
    };

    // Função para sincronização com Moodle
    window.qlSyncMoodle = function() {
        QLAdmin.confirmAction('Sincronizar projetos com Moodle? Isso pode demorar alguns minutos.', function() {
            QLAdmin.ajaxRequest(ajaxurl, {
                action: 'ql_sync_moodle',
                nonce: ql_admin.nonce
            }, 'POST', function(data) {
                if (data.synchronized_count) {
                    QLAdmin.showNotification(
                        data.synchronized_count + ' trilhas sincronizadas com sucesso!', 
                        'success'
                    );
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                }
            });
        });
    };

    // Função para obter dados do quadro
    window.qlGetQuadroData = function(boardId, callback) {
        QLAdmin.ajaxRequest(ajaxurl, {
            action: 'ql_get_quadro_data',
            board_id: boardId,
            nonce: ql_admin.nonce
        }, 'POST', function(data) {
            if (callback) callback(data.board);
        });
    };

    // Funcionalidades avançadas de drag & drop
    window.qlInitAdvancedKanban = function() {
        if (typeof Sortable !== 'undefined') {
            $('.ql-tasks-list').each(function() {
                var list = this;
                new Sortable(list, {
                    group: 'kanban-tasks',
                    animation: 150,
                    ghostClass: 'ql-task-ghost',
                    chosenClass: 'ql-task-chosen',
                    dragClass: 'ql-task-drag',
                    onEnd: function(evt) {
                        var taskId = $(evt.item).data('task-id');
                        var newColumnId = $(evt.to).data('column-id');
                        var newPosition = evt.newIndex;
                        
                        QLAdmin.ajaxRequest(ajaxurl, {
                            action: 'ql_move_task',
                            task_id: taskId,
                            new_column_id: newColumnId,
                            new_position: newPosition,
                            nonce: ql_admin.nonce
                        }, 'POST', function() {
                            QLAdmin.showNotification('Tarefa movida com sucesso', 'success');
                        });
                    }
                });
            });
        },
        
        // Limpar tarefas de exemplo
        clearExampleTasks: function(button) {
            var self = this;
            
            if (!confirm('⚠️ Isso irá remover todas as tarefas de exemplo do sistema. Tem certeza que deseja continuar?')) {
                return;
            }
            
            button.prop('disabled', true).text('Removendo...');
            
            $.ajax({
                url: ql_admin?.ajax_url || '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_clear_example_tasks',
                    nonce: ql_admin?.nonce || this.config.nonce
                },
                success: function(response) {
                    button.prop('disabled', false).text('🗑️ Limpar Tarefas de Exemplo');
                    
                    if (response.success) {
                        var count = response.data?.deleted_count || 0;
                        self.showNotification(
                            count > 0 ? count + ' tarefas de exemplo removidas!' : 'Nenhuma tarefa de exemplo encontrada.',
                            'success'
                        );
                        
                        // Recarregar página se estivermos na página do quadro
                        if ($('.ql-kanban-board').length > 0) {
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        self.showNotification(response.message || 'Erro ao remover tarefas', 'error');
                    }
                },
                error: function(xhr) {
                    button.prop('disabled', false).text('🗑️ Limpar Tarefas de Exemplo');
                    var error = xhr.responseJSON?.message || 'Erro ao remover tarefas';
                    self.showNotification(error, 'error');
                }
            });
        }
    };

    // Função para abrir o Kanban Board
    window.qlOpenKanbanBoard = function(boardId) {
        // Obter URL base do admin
        var adminUrl = window.location.href.split('/wp-admin/')[0] + '/wp-admin/admin.php';
        var kanbanUrl = adminUrl + '?page=quilombo-lab-kanban&board_id=' + boardId;
        
        // Redirecionar para a página do Kanban
        window.location.href = kanbanUrl;
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        QLAdmin.init();
        
        // Kanban drag & drop é gerenciado pelo kanban.js (jQuery UI Sortable)
        // qlInitAdvancedKanban desabilitado para evitar conflito
        console.log('✅ QLAdmin: Inicializado sem conflitos de drag & drop');
    });

})(jQuery);