/**
 * SISTEMA DE DEBUG COMPLETO para modais
 * Quilombo Laboratório - Diagnóstico detalhado
 */

(function($) {
    'use strict';
    
    console.log('🔍 INICIANDO SISTEMA DE DEBUG PARA MODAIS...');
    
    // Verificar se jQuery está disponível
    if (typeof $ === 'undefined') {
        console.error('❌ jQuery não está disponível!');
        return;
    }
    
    window.QLModalDebug = {
        eventLog: [],
        
        init: function() {
            console.log('🚀 Inicializando debug de modais...');
            this.logEnvironment();
            this.setupEventMonitoring();
            this.setupClickDebug();
            this.addDebugButtons();
        },
        
        logEnvironment: function() {
            console.log('🌍 AMBIENTE DE DEBUG:');
            console.log('- jQuery disponível:', typeof $ !== 'undefined');
            console.log('- jQuery version:', $.fn.jquery);
            console.log('- Document ready:', document.readyState);
            console.log('- QLKanban disponível:', typeof QLKanban !== 'undefined');
            console.log('- QLTaskModals disponível:', typeof QLTaskModals !== 'undefined');
            console.log('- QLForceModalDisplay disponível:', typeof QLForceModalDisplay !== 'undefined');
            console.log('- QLFunctionalModals disponível:', typeof QLFunctionalModals !== 'undefined');
            console.log('- ql_admin disponível:', typeof ql_admin !== 'undefined');
            
            if (typeof ql_admin !== 'undefined') {
                console.log('- AJAX URL:', ql_admin.ajax_url);
                console.log('- Nonce:', ql_admin.nonce);
            }
            
            // Verificar elementos no DOM
            console.log('🔍 ELEMENTOS NO DOM:');
            console.log('- Cards de tarefas (.ql-kanban-task):', $('.ql-kanban-task').length);
            console.log('- Botões adicionar (.ql-add-task-btn):', $('.ql-add-task-btn').length);
            console.log('- Colunas (.ql-kanban-column):', $('.ql-kanban-column').length);
            console.log('- Modais existentes (.ql-modal):', $('.ql-modal').length);
            
            // Mostrar elementos encontrados
            if ($('.ql-kanban-task').length > 0) {
                console.log('📋 CARDS DE TAREFAS ENCONTRADOS:');
                $('.ql-kanban-task').each(function(index, el) {
                    var $el = $(el);
                    console.log(`  [${index}] ID: ${$el.data('task-id')}, Classes: ${el.className}`);
                });
            }
            
            if ($('.ql-add-task-btn').length > 0) {
                console.log('➕ BOTÕES ADICIONAR ENCONTRADOS:');
                $('.ql-add-task-btn').each(function(index, el) {
                    var $el = $(el);
                    console.log(`  [${index}] Column ID: ${$el.data('column-id')}, Classes: ${el.className}`);
                });
            }
        },
        
        setupEventMonitoring: function() {
            var self = this;
            
            console.log('👂 Configurando monitoramento de eventos...');
            
            // Monitorar TODOS os eventos de clique
            $(document).on('click', '*', function(e) {
                var $target = $(e.target);
                var $current = $(e.currentTarget);
                
                // Log apenas para elementos relevantes
                if ($target.hasClass('ql-kanban-task') || 
                    $target.closest('.ql-kanban-task').length ||
                    $target.hasClass('ql-add-task-btn') || 
                    $target.closest('.ql-add-task-btn').length) {
                    
                    var eventInfo = {
                        timestamp: new Date().toISOString(),
                        target: e.target.tagName + '.' + e.target.className,
                        currentTarget: e.currentTarget.tagName + '.' + e.currentTarget.className,
                        taskId: $target.data('task-id') || $target.closest('.ql-kanban-task').data('task-id'),
                        columnId: $target.data('column-id') || $target.closest('.ql-kanban-column').data('column-id'),
                        propagationStopped: e.isPropagationStopped(),
                        defaultPrevented: e.isDefaultPrevented()
                    };
                    
                    self.eventLog.push(eventInfo);
                    
                    console.log('🖱️ CLIQUE DETECTADO:', eventInfo);
                    
                    // INTERCEPETAR E PROCESSAR CLIQUES DIRETAMENTE
                    if (e.target === e.currentTarget) { // Clique direto no elemento
                        if ($target.hasClass('ql-kanban-task')) {
                            console.log('🔥 INTERCEPTANDO CLIQUE EM CARD!');
                            e.preventDefault();
                            e.stopPropagation();
                            self.handleTaskClick($target, eventInfo.taskId);
                        } else if ($target.hasClass('ql-add-task-btn')) {
                            console.log('➕ INTERCEPTANDO CLIQUE EM BOTÃO!');
                            e.preventDefault();
                            e.stopPropagation();
                            self.handleAddClick($target, eventInfo.columnId);
                        }
                    }
                }
            });
        },
        
        setupClickDebug: function() {
            var self = this;
            
            console.log('🎯 Configurando debug de cliques específicos...');
            
            // Debug para cards de tarefas
            $(document).on('click.debug', '.ql-kanban-task', function(e) {
                console.log('🔥 DEBUG: Clique em card de tarefa detectado!');
                console.log('  Target:', e.target);
                console.log('  Current target:', e.currentTarget);
                console.log('  Task ID:', $(this).data('task-id'));
                console.log('  Classes:', this.className);
                console.log('  Event propagation stopped:', e.isPropagationStopped());
                console.log('  Default prevented:', e.isDefaultPrevented());
                
                // NÃO prevenir o evento aqui - apenas observar
            });
            
            // Debug para botões adicionar
            $(document).on('click.debug', '.ql-add-task-btn', function(e) {
                console.log('➕ DEBUG: Clique em botão adicionar detectado!');
                console.log('  Target:', e.target);
                console.log('  Current target:', e.currentTarget);
                console.log('  Column ID:', $(this).data('column-id'));
                console.log('  Classes:', this.className);
                console.log('  Event propagation stopped:', e.isPropagationStopped());
                console.log('  Default prevented:', e.isDefaultPrevented());
                
                // NÃO prevenir o evento aqui - apenas observar
            });
        },
        
        addDebugButtons: function() {
            console.log('🔧 Adicionando botões de debug...');
            
            var debugPanel = `
                <div id="ql-debug-panel" style="
                    position: fixed; top: 10px; left: 10px; 
                    background: white; border: 2px solid #333; 
                    padding: 15px; border-radius: 5px; z-index: 999999;
                    font-family: monospace; font-size: 12px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    max-width: 300px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;">🔍 Debug Modais</h4>
                    <button onclick="QLModalDebug.testTaskClick()" style="
                        display: block; width: 100%; margin: 5px 0; 
                        padding: 8px; background: #007cba; color: white; 
                        border: none; border-radius: 3px; cursor: pointer;">
                        🔥 Testar Clique em Card
                    </button>
                    <button onclick="QLModalDebug.testAddClick()" style="
                        display: block; width: 100%; margin: 5px 0; 
                        padding: 8px; background: #00a32a; color: white; 
                        border: none; border-radius: 3px; cursor: pointer;">
                        ➕ Testar Botão Adicionar
                    </button>
                    <button onclick="QLModalDebug.showEventLog()" style="
                        display: block; width: 100%; margin: 5px 0; 
                        padding: 8px; background: #996633; color: white; 
                        border: none; border-radius: 3px; cursor: pointer;">
                        📜 Ver Log de Eventos
                    </button>
                    <button onclick="QLModalDebug.forceModal()" style="
                        display: block; width: 100%; margin: 5px 0; 
                        padding: 8px; background: #cc0000; color: white; 
                        border: none; border-radius: 3px; cursor: pointer;">
                        🚀 Forçar Modal
                    </button>
                    <button onclick="QLModalDebug.checkHandlers()" style="
                        display: block; width: 100%; margin: 5px 0; 
                        padding: 8px; background: #663399; color: white; 
                        border: none; border-radius: 3px; cursor: pointer;">
                        🔍 Ver Event Handlers
                    </button>
                    <div style="margin-top: 10px; font-size: 10px; color: #666;">
                        <div>Cards: <span id="debug-task-count">0</span></div>
                        <div>Botões: <span id="debug-btn-count">0</span></div>
                        <div>Eventos: <span id="debug-event-count">0</span></div>
                    </div>
                </div>
            `;
            
            $('body').append(debugPanel);
            
            // Atualizar contadores
            setInterval(function() {
                $('#debug-task-count').text($('.ql-kanban-task').length);
                $('#debug-btn-count').text($('.ql-add-task-btn').length);
                $('#debug-event-count').text(QLModalDebug.eventLog.length);
            }, 1000);
        },
        
        testTaskClick: function() {
            console.log('🧪 TESTE: Simulando clique em card de tarefa...');
            
            var $firstTask = $('.ql-kanban-task').first();
            if ($firstTask.length > 0) {
                console.log('  Clicando no primeiro card encontrado:', $firstTask[0]);
                console.log('  Task ID no card:', $firstTask.data('task-id'));
                console.log('  Classes do card:', $firstTask[0].className);
                $firstTask.trigger('click');
                
                // Tentar clique direto também
                setTimeout(function() {
                    console.log('🔄 Tentando clique manual...');
                    if (typeof window.QLSimpleModals !== 'undefined') {
                        window.QLSimpleModals.openTask($firstTask.data('task-id'), $firstTask);
                    } else {
                        console.log('❌ QLSimpleModals não encontrado');
                    }
                }, 100);
            } else {
                console.log('  ❌ Nenhum card de tarefa encontrado!');
                alert('Nenhum card de tarefa encontrado no DOM');
            }
        },
        
        testAddClick: function() {
            console.log('🧪 TESTE: Simulando clique em botão adicionar...');
            
            var $firstBtn = $('.ql-add-task-btn').first();
            if ($firstBtn.length > 0) {
                console.log('  Clicando no primeiro botão encontrado:', $firstBtn[0]);
                $firstBtn.trigger('click');
            } else {
                console.log('  ❌ Nenhum botão adicionar encontrado!');
                alert('Nenhum botão adicionar encontrado no DOM');
            }
        },
        
        showEventLog: function() {
            console.log('📜 LOG DE EVENTOS DOS ÚLTIMOS CLIQUES:');
            
            var recentEvents = this.eventLog.slice(-10);
            recentEvents.forEach(function(event, index) {
                console.log(`[${index}] ${event.timestamp}:`, event);
            });
            
            if (this.eventLog.length === 0) {
                console.log('  Nenhum evento registrado ainda');
                alert('Nenhum evento de clique registrado ainda');
            } else {
                alert(`${this.eventLog.length} eventos registrados. Veja o console para detalhes.`);
            }
        },
        
        forceModal: function() {
            console.log('🚀 FORÇANDO MODAL DE TESTE...');
            
            var modalHtml = `
                <div id="ql-debug-force-modal" style="
                    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                    background: rgba(0,0,0,0.8); z-index: 2147483647;
                    display: flex; align-items: center; justify-content: center;">
                    <div style="
                        background: white; padding: 30px; border-radius: 8px;
                        max-width: 500px; width: 90%;">
                        <h2 style="margin: 0 0 20px 0; color: #333;">🚀 Modal Forçado</h2>
                        <p>Este modal foi criado pelo sistema de debug para verificar se a exibição de modais funciona.</p>
                        <div style="margin: 20px 0; padding: 15px; background: #f0f0f0; border-radius: 4px;">
                            <strong>Status:</strong> Modal visível ✅<br>
                            <strong>Z-index:</strong> 2147483647<br>
                            <strong>Posicionamento:</strong> Fixed<br>
                            <strong>Display:</strong> Flex
                        </div>
                        <button onclick="QLModalDebug.closeForceModal()" style="
                            background: #007cba; color: white; border: none;
                            padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                            Fechar Modal
                        </button>
                    </div>
                </div>
            `;
            
            $('#ql-debug-force-modal').remove();
            $('body').append(modalHtml);
            
            console.log('✅ Modal forçado criado e deveria estar visível');
        },
        
        closeForceModal: function() {
            $('#ql-debug-force-modal').remove();
            console.log('🗙 Modal forçado fechado');
        },
        
        checkHandlers: function() {
            console.log('🔍 VERIFICANDO EVENT HANDLERS...');
            
            var taskHandlers = $._data(document, 'events');
            console.log('Handlers no document:', taskHandlers);
            
            // Verificar handlers específicos
            if (taskHandlers && taskHandlers.click) {
                console.log('Handlers de click encontrados:', taskHandlers.click.length);
                taskHandlers.click.forEach(function(handler, index) {
                    console.log(`  [${index}] Namespace: ${handler.namespace}, Selector: ${handler.selector}`);
                });
            } else {
                console.log('Nenhum handler de click encontrado no document');
            }
            
            // Verificar se elementos têm handlers diretos
            $('.ql-kanban-task').each(function(index, el) {
                var handlers = $._data(el, 'events');
                if (handlers) {
                    console.log(`Card ${index} tem handlers:`, handlers);
                }
            });
            
            alert('Verificação de handlers concluída. Veja o console para detalhes.');
        },
        
        handleTaskClick: function(taskElement, taskId) {
            console.log('📖 Processando clique em card via interceptor:', taskId);
            
            var taskTitle = taskElement.find('.ql-task-title').text() || 
                          taskElement.text().trim().substring(0, 50) || 
                          'Tarefa #' + taskId;
            
            this.showInterceptedTaskModal(taskId, taskTitle);
        },
        
        handleAddClick: function(buttonElement, columnId) {
            console.log('📝 Processando clique em botão via interceptor:', columnId);
            
            var columnElement = buttonElement.closest('.ql-kanban-column');
            var columnName = columnElement.find('.ql-column-title').text() || 
                           columnElement.find('h3').text() || 
                           'Coluna #' + columnId;
            
            this.showInterceptedCreateModal(columnId, columnName);
        },
        
        showInterceptedTaskModal: function(taskId, taskTitle) {
            console.log('📖 Criando modal interceptado para tarefa:', taskId);
            
            var modalHtml = `
                <div id="ql-intercepted-task-modal" style="
                    position: fixed !important; top: 0 !important; left: 0 !important;
                    width: 100vw !important; height: 100vh !important;
                    background: rgba(0, 0, 0, 0.8) !important; z-index: 999999 !important;
                    display: flex !important; align-items: center !important; justify-content: center !important;">
                    <div style="
                        background: white !important; padding: 30px !important; border-radius: 8px !important;
                        max-width: 600px !important; width: 90% !important; max-height: 80vh !important; overflow-y: auto !important;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <h2 style="margin: 0; color: #333; font-size: 24px;">${this.escapeHtml(taskTitle)}</h2>
                            <button onclick="document.getElementById('ql-intercepted-task-modal').remove()" style="
                                background: none; border: none; font-size: 28px; cursor: pointer; color: #666; padding: 5px;">&times;</button>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4CAF50; margin-bottom: 20px;">
                                <h3 style="margin: 0 0 10px 0; color: #4CAF50;">🎯 CLIQUE INTERCEPTADO!</h3>
                                <p style="margin: 0;">O clique foi interceptado pelo sistema de debug e o modal está funcionando!</p>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                                <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações da Tarefa</h3>
                                <p><strong>ID da Tarefa:</strong> ${taskId}</p>
                                <p><strong>Título:</strong> ${this.escapeHtml(taskTitle)}</p>
                                <p style="margin: 0;"><strong>Status:</strong> Modal interceptado funcionando ✅</p>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <h3 style="color: #2196F3; margin-bottom: 10px;">✅ Solução Encontrada!</h3>
                            <div style="background: #e3f2fd; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                                <p style="margin: 0;">O sistema de interceptação está funcionando! Agora sabemos como fazer os modais aparecerem corretamente.</p>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button onclick="alert('Funcionalidade de edição seria implementada aqui!')" style="
                                background: #2196F3; color: white; border: none; padding: 12px 24px; 
                                border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; margin: 5px;">
                                ✏️ Editar Tarefa
                            </button>
                            <button onclick="document.getElementById('ql-intercepted-task-modal').remove()" style="
                                background: #6c757d; color: white; border: none; padding: 12px 24px; 
                                border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; margin: 5px;">
                                Fechar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            $('#ql-intercepted-task-modal').remove();
            $('body').append(modalHtml);
            
            console.log('✅ Modal interceptado de tarefa criado!');
        },
        
        showInterceptedCreateModal: function(columnId, columnName) {
            console.log('📝 Criando modal interceptado de criação:', columnId);
            
            var modalHtml = `
                <div id="ql-intercepted-create-modal" style="
                    position: fixed !important; top: 0 !important; left: 0 !important;
                    width: 100vw !important; height: 100vh !important;
                    background: rgba(0, 0, 0, 0.8) !important; z-index: 999999 !important;
                    display: flex !important; align-items: center !important; justify-content: center !important;">
                    <div style="
                        background: white !important; padding: 30px !important; border-radius: 8px !important;
                        max-width: 500px !important; width: 90% !important;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                            <h2 style="margin: 0; color: #333; font-size: 24px;">➕ Nova Tarefa</h2>
                            <button onclick="document.getElementById('ql-intercepted-create-modal').remove()" style="
                                background: none; border: none; font-size: 28px; cursor: pointer; color: #666; padding: 5px;">&times;</button>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4CAF50; margin-bottom: 20px;">
                                <h3 style="margin: 0 0 10px 0; color: #4CAF50;">🎯 BOTÃO INTERCEPTADO!</h3>
                                <p style="margin: 0;">O clique no botão "+" foi interceptado e o modal está funcionando!</p>
                            </div>
                            
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                                <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações</h3>
                                <p><strong>Coluna:</strong> ${this.escapeHtml(columnName)}</p>
                                <p><strong>Column ID:</strong> ${columnId}</p>
                                <p style="margin: 0;"><strong>Status:</strong> Modal interceptado funcionando ✅</p>
                            </div>
                        </div>
                        
                        <form onsubmit="alert('Tarefa seria criada aqui! Column ID: ${columnId}'); document.getElementById('ql-intercepted-create-modal').remove(); return false;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #555;">Título da Tarefa *</label>
                                <input type="text" required style="
                                    width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; 
                                    font-size: 16px; box-sizing: border-box;" placeholder="Digite o título da tarefa">
                            </div>
                            
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #555;">Descrição (opcional)</label>
                                <textarea rows="3" style="
                                    width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; 
                                    font-size: 14px; resize: vertical; box-sizing: border-box;" placeholder="Descreva a tarefa"></textarea>
                            </div>
                            
                            <div style="text-align: right;">
                                <button type="button" onclick="document.getElementById('ql-intercepted-create-modal').remove()" style="
                                    background: #6c757d; color: white; border: none; padding: 10px 20px; 
                                    border-radius: 6px; cursor: pointer; margin-right: 10px;">Cancelar</button>
                                <button type="submit" style="
                                    background: #28a745; color: white; border: none; padding: 10px 20px; 
                                    border-radius: 6px; cursor: pointer; font-weight: bold;">Criar Tarefa</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            $('#ql-intercepted-create-modal').remove();
            $('body').append(modalHtml);
            
            console.log('✅ Modal interceptado de criação criado!');
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }
    };
    
    // Aguardar jQuery e DOM
    $(document).ready(function() {
        console.log('📋 DOM pronto, inicializando debug...');
        window.QLModalDebug.init();
        
        // Aguardar um pouco mais para garantir que outros scripts carregaram
        setTimeout(function() {
            console.log('🔄 Re-executando diagnóstico após delay...');
            window.QLModalDebug.logEnvironment();
        }, 2000);
    });
    
    console.log('✅ SISTEMA DE DEBUG CARREGADO!');
    
})(jQuery);