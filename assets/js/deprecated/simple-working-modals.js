/**
 * MODAIS SUPER SIMPLES - Versão garantida que funciona
 * Quilombo Laboratório - Abordagem minimalista
 */

console.log('🚀 CARREGANDO MODAIS SUPER SIMPLES...');

// Aguardar jQuery
if (typeof jQuery === 'undefined') {
    console.log('⏳ Aguardando jQuery...');
    
    var jQueryWait = setInterval(function() {
        if (typeof jQuery !== 'undefined') {
            clearInterval(jQueryWait);
            initSimpleModals();
        }
    }, 100);
} else {
    initSimpleModals();
}

function initSimpleModals() {
    var $ = jQuery;
    
    console.log('✅ jQuery disponível, inicializando modais simples...');
    
    // Aguardar DOM
    $(document).ready(function() {
        console.log('📋 DOM pronto, configurando eventos...');
        setupSimpleEvents();
        
        // Tentar novamente após delays
        setTimeout(setupSimpleEvents, 1000);
        setTimeout(setupSimpleEvents, 3000);
    });
    
    function setupSimpleEvents() {
        console.log('🔧 Configurando eventos simples...');
        
        // Remover eventos anteriores para evitar duplicação
        $(document).off('click.simple');
        
        // Event handler super simples para cards
        $(document).on('click.simple', '.ql-kanban-task', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('🔥 CLIQUE SIMPLES NO CARD!', this);
            
            var taskId = $(this).data('task-id') || $(this).attr('data-task-id');
            console.log('🔍 Task ID:', taskId);
            
            showSimpleTaskModal(taskId, $(this));
        });
        
        // Event handler super simples para botões
        $(document).on('click.simple', '.ql-add-task-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('➕ CLIQUE SIMPLES NO BOTÃO!', this);
            
            var columnId = $(this).data('column-id') || $(this).attr('data-column-id');
            var $column = $(this).closest('.ql-kanban-column');
            if (!columnId) {
                columnId = $column.data('column-id') || $column.attr('data-column-id');
            }
            
            console.log('🔍 Column ID:', columnId);
            
            showSimpleCreateModal(columnId);
        });
        
        console.log('✅ Eventos simples configurados!');
        
        // Log elementos encontrados
        console.log('📊 ELEMENTOS ENCONTRADOS:');
        console.log('- Cards:', $('.ql-kanban-task').length);
        console.log('- Botões:', $('.ql-add-task-btn').length);
    }
    
    function showSimpleTaskModal(taskId, $task) {
        console.log('📖 Criando modal simples para tarefa:', taskId);
        
        var taskTitle = $task.find('.ql-task-title').text() || 
                       $task.text().trim().substring(0, 50) || 
                       'Tarefa #' + taskId;
        
        var modalHtml = `
            <div id="simple-task-modal" style="
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(0, 0, 0, 0.8) !important;
                z-index: 999999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;">
                <div style="
                    background: white !important;
                    padding: 30px !important;
                    border-radius: 8px !important;
                    max-width: 600px !important;
                    width: 90% !important;
                    max-height: 80vh !important;
                    overflow-y: auto !important;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                        <h2 style="margin: 0; color: #333; font-size: 24px;">${escapeHtml(taskTitle)}</h2>
                        <button onclick="closeSimpleModal()" style="
                            background: none; border: none; font-size: 28px; 
                            cursor: pointer; color: #666; padding: 5px;
                            width: 40px; height: 40px; border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;">&times;</button>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4CAF50; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 10px 0; color: #4CAF50;">✅ MODAL FUNCIONANDO!</h3>
                            <p style="margin: 0;">O clique foi detectado e o modal está sendo exibido corretamente!</p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                            <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações da Tarefa</h3>
                            <p><strong>ID da Tarefa:</strong> ${taskId}</p>
                            <p><strong>Título:</strong> ${escapeHtml(taskTitle)}</p>
                            <p style="margin: 0;"><strong>Status:</strong> Modal simples funcionando ✅</p>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <h3 style="color: #FF9800; margin-bottom: 10px;">🔧 Próximos Passos</h3>
                        <div style="background: #fff3e0; padding: 15px; border-radius: 6px; border-left: 4px solid #FF9800;">
                            <p style="margin: 0;">Agora que confirmamos que os modais funcionam, podemos implementar:</p>
                            <ul style="margin: 10px 0 0 20px; padding: 0;">
                                <li>Carregamento de dados reais via AJAX</li>
                                <li>Formulários de edição</li>
                                <li>Salvamento de alterações</li>
                                <li>Interface completa</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 30px;">
                        <button onclick="testEditTask('${taskId}')" style="
                            background: #2196F3; color: white; border: none; 
                            padding: 12px 24px; border-radius: 6px; cursor: pointer;
                            font-size: 16px; font-weight: bold; margin: 5px;">
                            ✏️ Testar Edição
                        </button>
                        <button onclick="closeSimpleModal()" style="
                            background: #6c757d; color: white; border: none; 
                            padding: 12px 24px; border-radius: 6px; cursor: pointer;
                            font-size: 16px; font-weight: bold; margin: 5px;">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal anterior
        $('#simple-task-modal').remove();
        
        // Adicionar novo modal
        $('body').append(modalHtml);
        
        console.log('✅ Modal simples de tarefa criado!');
    }
    
    function showSimpleCreateModal(columnId) {
        console.log('📝 Criando modal simples de criação:', columnId);
        
        var modalHtml = `
            <div id="simple-create-modal" style="
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(0, 0, 0, 0.8) !important;
                z-index: 999999 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;">
                <div style="
                    background: white !important;
                    padding: 30px !important;
                    border-radius: 8px !important;
                    max-width: 500px !important;
                    width: 90% !important;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">
                        <h2 style="margin: 0; color: #333; font-size: 24px;">➕ Nova Tarefa</h2>
                        <button onclick="closeSimpleModal()" style="
                            background: none; border: none; font-size: 28px; 
                            cursor: pointer; color: #666; padding: 5px;
                            width: 40px; height: 40px; border-radius: 50%;
                            display: flex; align-items: center; justify-content: center;">&times;</button>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; border-left: 4px solid #4CAF50; margin-bottom: 20px;">
                            <h3 style="margin: 0 0 10px 0; color: #4CAF50;">✅ BOTÃO FUNCIONANDO!</h3>
                            <p style="margin: 0;">O clique no botão "+" foi detectado corretamente!</p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #2196F3;">
                            <h3 style="margin: 0 0 10px 0; color: #2196F3;">📋 Informações</h3>
                            <p><strong>Column ID:</strong> ${columnId}</p>
                            <p style="margin: 0;"><strong>Status:</strong> Modal de criação funcionando ✅</p>
                        </div>
                    </div>
                    
                    <form onsubmit="testCreateTask(event, '${columnId}')">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #555;">
                                Título da Tarefa *
                            </label>
                            <input type="text" id="simple-task-title" required style="
                                width: 100%; padding: 12px; border: 2px solid #ddd; 
                                border-radius: 6px; font-size: 16px; box-sizing: border-box;" 
                                placeholder="Digite o título da tarefa">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold; color: #555;">
                                Descrição (opcional)
                            </label>
                            <textarea id="simple-task-description" rows="3" style="
                                width: 100%; padding: 12px; border: 2px solid #ddd; 
                                border-radius: 6px; font-size: 14px; resize: vertical; box-sizing: border-box;" 
                                placeholder="Descreva a tarefa"></textarea>
                        </div>
                        
                        <div style="background: #fff3e0; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                            <p style="margin: 0; font-size: 14px; color: #666;">
                                <strong>Status:</strong> Formulário funcionando ✅<br>
                                <strong>Column ID:</strong> ${columnId}<br>
                                <em>🚧 Este é um teste. A integração real será implementada em seguida.</em>
                            </p>
                        </div>
                        
                        <div style="text-align: right;">
                            <button type="button" onclick="closeSimpleModal()" style="
                                background: #6c757d; color: white; border: none; 
                                padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-right: 10px;">
                                Cancelar
                            </button>
                            <button type="submit" style="
                                background: #28a745; color: white; border: none; 
                                padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                Criar Tarefa (Teste)
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        // Remover modal anterior
        $('#simple-create-modal').remove();
        
        // Adicionar novo modal
        $('body').append(modalHtml);
        
        // Focar no campo título
        setTimeout(function() {
            $('#simple-task-title').focus();
        }, 100);
        
        console.log('✅ Modal simples de criação criado!');
    }
    
    // Funções globais para os botões
    window.closeSimpleModal = function() {
        console.log('🗙 Fechando modal simples...');
        $('#simple-task-modal, #simple-create-modal').remove();
    };
    
    // Expor funções para debug
    window.QLSimpleModals = {
        openTask: function(taskId, $task) {
            console.log('📖 Abrindo modal direto para tarefa:', taskId);
            showSimpleTaskModal(taskId, $task || $('<div>'));
        },
        openCreate: function(columnId) {
            console.log('📝 Abrindo modal direto de criação:', columnId);
            showSimpleCreateModal(columnId);
        },
        testClick: function() {
            console.log('🧪 Teste manual de modal...');
            showSimpleTaskModal('teste', $('<div>').text('Teste Manual'));
        }
    };
    
    window.testEditTask = function(taskId) {
        console.log('✏️ Testando edição da tarefa:', taskId);
        alert('🎉 Edição da tarefa ' + taskId + ' funcionaria aqui!\n\nAgora podemos implementar a integração real com o backend.');
    };
    
    window.testCreateTask = function(event, columnId) {
        event.preventDefault();
        
        var title = $('#simple-task-title').val().trim();
        var description = $('#simple-task-description').val().trim();
        
        console.log('📝 Testando criação de tarefa:', {
            title: title,
            description: description,
            columnId: columnId
        });
        
        if (!title) {
            alert('Por favor, digite um título para a tarefa');
            return;
        }
        
        alert('🎉 Tarefa "' + title + '" seria criada na coluna ' + columnId + '!\n\nDados enviados para o console.\n\nAgora podemos implementar a integração real.');
        
        closeSimpleModal();
    };
    
    // Setup de eventos ESC
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) { // ESC
            closeSimpleModal();
        }
    });
    
    // Click fora do modal
    $(document).on('click', '#simple-task-modal, #simple-create-modal', function(e) {
        if (e.target === this) {
            closeSimpleModal();
        }
    });
    
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
}

console.log('✅ MODAIS SUPER SIMPLES CARREGADOS!');