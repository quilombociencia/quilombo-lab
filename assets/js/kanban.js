/**
 * Quilombo Laboratório - Kanban Board JavaScript (VERSÃO FUNCIONAL)
 */

(function($) {
    'use strict';
    
    // Garantir que $ está disponível mesmo com conflitos
    if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
        $ = jQuery;
    }

    // Classe principal do Kanban
    window.QLKanban = {
        
        // Configurações
        config: {
            boardContainer: '.ql-kanban-board',
            columnClass: '.ql-kanban-column',
            taskClass: '.ql-kanban-task',
            addTaskButton: '.ql-add-task-btn',
            taskModal: '#ql-task-modal'
        },

        // Inicializar
        init: function() {
            console.log('QLKanban: Inicializando...');
            this.bindEvents();
            this.initModals();
            
            // Carregar dados do quadro se estivermos na página do quadro
            var currentBoardId = this.getCurrentBoardId();
            if (currentBoardId) {
                this.loadBoardData();
            } else {
                console.warn('Board ID não encontrado - não carregando dados');
                this.showBoardError('Board ID não configurado. Configure um Board ID padrão nas configurações do plugin.');
            }
            
            console.log('QL Kanban inicializado');
        },

        // Carregar dados do quadro
        loadBoardData: function() {
            var self = this;
            var boardId = this.getCurrentBoardId();
            
            if (!boardId) {
                console.error('Board ID não encontrado');
                return;
            }
            
            console.log('Carregando dados do quadro ID:', boardId);
            
            // Mostrar loading
            $(this.config.boardContainer).html('<div class="ql-loading-board">📊 Carregando quadro...</div>');
            
            var ajaxUrl = (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php';
            var nonce = (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? ql_admin.nonce : '';
            
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'ql_get_quadro_data',
                    board_id: boardId,
                    nonce: nonce
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    
                    if (response && response.success && response.data && response.data.board) {
                        console.log('Dados do quadro recebidos:', response.data.board);
                        self.renderBoard(response.data.board);
                    } else {
                        console.error('Erro na resposta AJAX:', response.message);
                        self.showBoardError('Erro ao carregar dados: ' + (response.message || 'Erro desconhecido'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Erro AJAX:', status, error);
                    self.showBoardError('Erro de conexão: ' + error);
                },
                dataType: 'json'
            });
        },

        // Renderizar quadro com dados (baseado no teste que funcionou)
        renderBoard: function(boardData) {
            var self = this;
            var boardContainer = $(this.config.boardContainer);
            
            if (!boardData || !boardData.columns || !Array.isArray(boardData.columns)) {
                console.error('Dados inválidos para renderizar quadro:', boardData);
                this.showBoardError('Estrutura de dados inválida');
                return;
            }
            
            console.log('Renderizando', boardData.columns.length, 'colunas');
            
            var html = '';
            var totalTasks = 0;
            
            boardData.columns.forEach(function(column) {
                var taskCount = column.tasks ? column.tasks.length : 0;
                totalTasks += taskCount;
                
                console.log('Coluna:', column.name, '(' + taskCount + ' tarefas)');
                
                html += '<div class="ql-kanban-column" data-column-id="' + column.id + '">';
                html += '  <div class="ql-column-header" style="border-left: 4px solid ' + (column.color || '#ccc') + '">';
                html += '    <h3 class="ql-column-title">' + self.escapeHtml(column.name || 'Sem nome') + '</h3>';
                html += '    <span class="ql-column-count">';
                html += '      <span class="ql-task-count">' + taskCount + '</span> tarefa' + (taskCount !== 1 ? 's' : '');
                html += '    </span>';
                html += '  </div>';
                html += '  <div class="ql-tasks-list">';
                
                if (column.tasks && Array.isArray(column.tasks) && column.tasks.length > 0) {
                    column.tasks.forEach(function(task) {
                        console.log('  Renderizando tarefa:', task.title);
                        html += self.renderTask(task);
                    });
                } else {
                    html += '<div class="ql-empty-column">📭 Nenhuma tarefa</div>';
                }
                
                html += '  </div>';
                html += '  <div class="ql-add-task-btn" data-column-id="' + column.id + '">';
                html += '    +';
                html += '  </div>';
                html += '</div>';
            });
            
            boardContainer.html(html);
            
            console.log('Quadro renderizado com sucesso! Total de tarefas:', totalTasks);
            
            // Inicializar drag & drop após renderizar
            setTimeout(function() {
                self.initSortable();
            }, 100);
        },

        // Renderizar uma tarefa
        renderTask: function(task) {
            var taskRef = task.reference || ('#' + task.id);
            var title = task.title || 'Sem título';
            var shortTitle = title.length > 50 ? title.substring(0, 50) + '...' : title;
            
            var html = '';
            html += '<div class="ql-kanban-task" data-task-id="' + task.id + '">';
            html += '  <div class="ql-task-header">';
            html += '    <span class="ql-task-ref">' + this.escapeHtml(taskRef) + '</span>';
            html += '    <button class="ql-task-edit-btn" data-task-id="' + task.id + '" title="Editar tarefa">✏️</button>';
            html += '  </div>';
            html += '  <h4 class="ql-task-title" title="' + this.escapeHtml(title) + '">' + this.escapeHtml(shortTitle) + '</h4>';
            
            if (task.description) {
                var shortDesc = task.description.length > 80 ? 
                    task.description.substring(0, 80) + '...' : task.description;
                html += '  <p class="ql-task-description" title="' + this.escapeHtml(task.description) + '">' + this.escapeHtml(shortDesc) + '</p>';
            }
            
            html += '  <div class="ql-task-footer">';
            if (task.assigned_user_name) {
                var initials = task.assigned_user_name.substring(0, 2).toUpperCase();
                html += '    <div class="ql-avatar" title="Responsável: ' + this.escapeHtml(task.assigned_user_name) + '">' + initials + '</div>';
            } else {
                html += '    <div class="ql-avatar ql-unassigned" title="Não atribuído">?</div>';
            }
            html += '  </div>';
            html += '</div>';
            
            return html;
        },

        // Função utilitária para escape de HTML
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Mostrar erro no quadro
        showBoardError: function(message) {
            $(this.config.boardContainer).html(
                '<div class="ql-board-error" style="text-align: center; padding: 50px; color: #d32f2f;">' +
                '<h3>❌ Erro ao carregar quadro</h3>' +
                '<p>' + this.escapeHtml(message) + '</p>' +
                '<button onclick="location.reload()" class="button" style="margin-top: 10px;">🔄 Recarregar página</button>' +
                '</div>'
            );
        },

        // Vincular eventos
        bindEvents: function() {
            var self = this;

            // Eventos relacionados apenas ao kanban e drag-and-drop
            // Eventos de modais são gerenciados pelo task-modals.js
            
            console.log('QLKanban: Eventos básicos vinculados');
        },

        // Inicializar modais
        initModals: function() {
            // Modais são gerenciados pelo task-modals.js
            console.log('QLKanban: Modais delegados ao task-modals.js');
        },

        // Inicializar drag & drop
        initSortable: function() {
            var self = this;
            
            // Verificar se jQuery UI sortable está disponível
            if (typeof $.fn.sortable === 'undefined') {
                console.warn('jQuery UI Sortable não disponível - drag & drop desabilitado');
                return;
            }
            
            var taskLists = $('.ql-tasks-list');
            
            if (taskLists.length === 0) {
                console.warn('Nenhuma lista de tarefas encontrada para sortable');
                return;
            }
            
            console.log('Inicializando sortable em', taskLists.length, 'listas');
            
            taskLists.sortable({
                connectWith: '.ql-tasks-list',
                items: '.ql-kanban-task',
                placeholder: 'ql-task-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                opacity: 0.8,
                distance: 10,
                delay: 200,
                scroll: true,
                cancel: false, // Permitir drag em todos os elementos
                handle: false, // Permitir drag em qualquer lugar do card
                
                start: function(event, ui) {
                    console.log('Sortable: Iniciando drag');
                    ui.placeholder.height(ui.item.height());
                    ui.item.addClass('ql-dragging');
                    // Marcar que está em drag para evitar conflito com clique
                    ui.item.data('is-dragging', true);
                },
                
                stop: function(event, ui) {
                    console.log('Sortable: Finalizando drag');
                    ui.item.removeClass('ql-dragging');
                    // Remover flag de drag após um pequeno delay para evitar clique imediato
                    setTimeout(function() {
                        ui.item.removeData('is-dragging');
                    }, 100);
                },
                
                receive: function(event, ui) {
                    var taskId = ui.item.data('task-id');
                    var newColumnId = $(this).closest('.ql-kanban-column').data('column-id');
                    var newPosition = ui.item.index();
                    
                    console.log('Tarefa movida:', taskId, 'para coluna:', newColumnId, 'posição:', newPosition);
                    self.moveTask(taskId, newColumnId, newPosition);
                },
                
                update: function(event, ui) {
                    // Só executar se a tarefa mudou de posição na mesma coluna
                    if (ui.sender === null) {
                        var taskId = ui.item.data('task-id');
                        var columnId = $(this).closest('.ql-kanban-column').data('column-id');
                        var newPosition = ui.item.index();
                        
                        console.log('Posição atualizada:', taskId, 'coluna:', columnId, 'posição:', newPosition);
                        self.updateTaskPosition(taskId, columnId, newPosition);
                    }
                }
            });
            
            console.log('Drag & drop inicializado com sucesso');
        },

        // Mover tarefa para nova coluna
        moveTask: function(taskId, newColumnId, newPosition) {
            var self = this;
            
            $.ajax({
                url: (typeof ql_admin !== 'undefined' && ql_admin.ajax_url) ? ql_admin.ajax_url : '/wp-admin/admin-ajax.php',
                method: 'POST',
                data: {
                    action: 'ql_move_task',
                    nonce: (typeof ql_admin !== 'undefined' && ql_admin.nonce) ? ql_admin.nonce : '',
                    task_id: taskId,
                    new_column_id: newColumnId,
                    new_position: newPosition
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('Tarefa movida com sucesso!', 'success');
                        self.updateColumnStats();
                    } else {
                        self.showNotification('Erro ao mover tarefa: ' + (response.message || 'Erro desconhecido'), 'error');
                        self.loadBoardData(); // Recarregar em caso de erro
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotification('Erro de conexão: ' + error, 'error');
                    self.loadBoardData(); // Recarregar em caso de erro
                },
                dataType: 'json'
            });
        },

        // Atualizar posição da tarefa na mesma coluna
        updateTaskPosition: function(taskId, columnId, newPosition) {
            // Usar o mesmo método move_task para atualizações de posição
            this.moveTask(taskId, columnId, newPosition);
        },

        // Funções de modal removidas - delegadas ao task-modals.js

        // Atualizar estatísticas das colunas
        updateColumnStats: function() {
            var self = this;
            $('.ql-kanban-column').each(function() {
                var column = $(this);
                var taskCount = column.find('.ql-kanban-task').length;
                var countElement = column.find('.ql-task-count');
                
                if (countElement.length > 0) {
                    countElement.text(taskCount);
                }
            });
        },

        // Mostrar notificação
        showNotification: function(message, type) {
            type = type || 'info';
            
            var notification = $('<div class="ql-notification ql-notification-' + type + '">' + this.escapeHtml(message) + '</div>');
            
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

        // HTML do modal removido - delegado ao task-modals.js

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
            var boardContainer = $(this.config.boardContainer);
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
            
            // Último recurso: mostrar erro
            console.error('Board ID não encontrado. Configure um Board ID padrão nas configurações do plugin.');
            return null;
        }
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        console.log('DOM ready, inicializando QLKanban...');
        
        // Aguardar um pouco para garantir que tudo está carregado
        setTimeout(function() {
            if (typeof QLKanban !== 'undefined') {
                QLKanban.init();
            } else {
                console.error('QLKanban não foi definido!');
            }
        }, 500);
    });

})(jQuery);