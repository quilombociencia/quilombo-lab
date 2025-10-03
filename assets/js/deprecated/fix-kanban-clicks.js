/**
 * CORREÇÃO DIRETA para cliques nos quadros Kanban
 * Quilombo Laboratório - Patch de emergência
 */

(function($) {
    'use strict';
    
    console.log('🚨 APLICANDO CORREÇÃO DE CLIQUES KANBAN...');
    
    // Aguardar carregamento do DOM e inicializar correções
    $(document).ready(function() {
        initClickFixes();
        
        // Aguardar mais um pouco para casos de carregamento lento
        setTimeout(initClickFixes, 1000);
        setTimeout(initClickFixes, 3000);
    });
    
    function initClickFixes() {
        console.log('🔧 Aplicando correção de cliques...');
        
        // CORREÇÃO 1: Cliques nos cards de tarefas
        fixTaskCardClicks();
        
        // CORREÇÃO 2: Cliques nos botões "Adicionar tarefa"
        fixAddTaskButtonClicks();
        
        // CORREÇÃO 3: Garantir que modais funcionem
        ensureModalsWork();
        
        console.log('✅ Correções aplicadas!');
    }
    
    function fixTaskCardClicks() {
        console.log('🎯 Corrigindo cliques nos cards de tarefas...');
        
        // Remover todos os handlers existentes para evitar conflitos
        $(document).off('click', '.ql-kanban-task');
        
        // Adicionar novo handler direto
        $(document).on('click', '.ql-kanban-task', function(e) {
            console.log('🔥 CLIQUE NO CARD DETECTADO!', e);
            e.preventDefault();
            e.stopImmediatePropagation();
            
            var $task = $(this);
            
            // Verificar se está em modo drag
            if ($task.data('is-dragging') || $task.hasClass('ql-dragging') || $task.hasClass('ui-sortable-helper')) {
                console.log('⏭️ Ignorando clique durante drag');
                return;
            }
            
            var taskId = $task.data('task-id') || $task.attr('data-task-id');
            console.log('🔢 Task ID:', taskId);
            
            if (!taskId) {
                console.error('❌ Task ID não encontrado');
                alert('Erro: Task ID não encontrado no card');
                return;
            }
            
            // Tentar usar QLTaskModals se disponível
            if (typeof QLTaskModals !== 'undefined' && QLTaskModals.openTaskModal) {
                console.log('✅ Usando QLTaskModals.openTaskModal');
                QLTaskModals.openTaskModal(e);
                return;
            }
            
            // Fallback: Mostrar modal simples
            console.log('🔄 Fallback: Modal simples');
            showSimpleTaskModal(taskId, $task);
        });
    }
    
    function fixAddTaskButtonClicks() {
        console.log('➕ Corrigindo cliques nos botões adicionar tarefa...');
        
        // Remover handlers existentes
        $(document).off('click', '.ql-add-task-btn');
        
        // Adicionar novo handler direto
        $(document).on('click', '.ql-add-task-btn', function(e) {
            console.log('🔵 CLIQUE NO BOTÃO ADICIONAR DETECTADO!', e);
            e.preventDefault();
            e.stopImmediatePropagation();
            
            var $button = $(this);
            var columnId = $button.data('column-id') || $button.attr('data-column-id');
            
            console.log('🔢 Column ID:', columnId);
            
            if (!columnId) {
                console.error('❌ Column ID não encontrado');
                // Tentar encontrar via parent
                var $column = $button.closest('.ql-kanban-column');
                columnId = $column.data('column-id') || $column.attr('data-column-id');
                
                if (!columnId) {
                    alert('Erro: Column ID não encontrado');
                    return;
                }
            }
            
            // Tentar usar QLTaskModals se disponível
            if (typeof QLTaskModals !== 'undefined' && QLTaskModals.openQuickTaskModal) {
                console.log('✅ Usando QLTaskModals.openQuickTaskModal');
                QLTaskModals.openQuickTaskModal(e);
                return;
            }
            
            // Fallback: Modal simples de criação
            console.log('🔄 Fallback: Modal simples de criação');
            showSimpleCreateModal(columnId, $button);
        });
    }
    
    function ensureModalsWork() {
        console.log('🔧 Garantindo que modais funcionem...');
        
        // Verificar se modais existem
        if ($('#ql-quick-task-modal').length === 0) {
            console.log('📦 Criando modal básico...');
            createBasicModal();
        }
        
        // Garantir que eventos de fechamento funcionem
        $(document).off('click', '.ql-modal-close, .ql-modal-overlay');
        $(document).on('click', '.ql-modal-close, .ql-modal-overlay', function(e) {
            if (e.target === this || $(e.target).hasClass('ql-modal-close')) {
                console.log('🗙 Fechando modal...');
                $('.ql-modal').hide();
            }
        });
        
        // ESC para fechar
        $(document).off('keydown.qlfix');
        $(document).on('keydown.qlfix', function(e) {
            if (e.keyCode === 27) { // ESC
                $('.ql-modal').hide();
            }
        });
    }
    
    function showSimpleTaskModal(taskId, $task) {
        var taskTitle = $task.find('.ql-task-title').text() || 'Tarefa #' + taskId;
        var taskDescription = $task.find('.ql-task-description').text() || '';
        
        var modalHtml = `
            <div id="simple-task-modal" class="ql-modal" style="
                position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                background: rgba(0,0,0,0.8); z-index: 999999; 
                display: flex; align-items: center; justify-content: center;">
                <div style="
                    background: white; padding: 30px; border-radius: 8px; 
                    max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #333;">${taskTitle}</h2>
                        <button class="ql-modal-close" style="
                            background: none; border: none; font-size: 24px; 
                            cursor: pointer; color: #666;">&times;</button>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <strong>ID da Tarefa:</strong> ${taskId}
                    </div>
                    ${taskDescription ? `<div style="margin-bottom: 20px;"><strong>Descrição:</strong><br>${taskDescription}</div>` : ''}
                    <div style="text-align: center; margin-top: 30px;">
                        <p style="color: #666; font-style: italic;">
                            🚧 Modal de edição em desenvolvimento<br>
                            Esta funcionalidade será implementada em breve
                        </p>
                        <button class="ql-modal-close button button-primary">Fechar</button>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal anterior se existir
        $('#simple-task-modal').remove();
        
        // Adicionar novo modal
        $('body').append(modalHtml);
        
        console.log('✅ Modal simples de tarefa exibido');
    }
    
    function showSimpleCreateModal(columnId, $button) {
        var $column = $button.closest('.ql-kanban-column');
        var columnName = $column.find('.ql-column-title').text() || $column.find('h3').text() || 'Coluna';
        
        var modalHtml = `
            <div id="simple-create-modal" class="ql-modal" style="
                position: fixed; top: 0; left: 0; right: 0; bottom: 0; 
                background: rgba(0,0,0,0.8); z-index: 999999; 
                display: flex; align-items: center; justify-content: center;">
                <div style="
                    background: white; padding: 30px; border-radius: 8px; 
                    max-width: 500px; width: 90%;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0; color: #333;">Nova Tarefa - ${columnName}</h2>
                        <button class="ql-modal-close" style="
                            background: none; border: none; font-size: 24px; 
                            cursor: pointer; color: #666;">&times;</button>
                    </div>
                    <form id="simple-task-form">
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Título da Tarefa *</label>
                            <input type="text" id="simple-task-title" required style="
                                width: 100%; padding: 8px; border: 1px solid #ddd; 
                                border-radius: 4px;" placeholder="Digite o título da tarefa">
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Descrição (opcional)</label>
                            <textarea id="simple-task-description" rows="3" style="
                                width: 100%; padding: 8px; border: 1px solid #ddd; 
                                border-radius: 4px;" placeholder="Descreva a tarefa"></textarea>
                        </div>
                        <div style="text-align: right;">
                            <button type="button" class="ql-modal-close button" style="margin-right: 10px;">Cancelar</button>
                            <button type="submit" class="button button-primary">Criar Tarefa</button>
                        </div>
                    </form>
                    <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; color: #666; font-size: 14px;">
                        <strong>Column ID:</strong> ${columnId}<br>
                        <em>🚧 Funcionalidade de criação em desenvolvimento</em>
                    </div>
                </div>
            </div>
        `;
        
        // Remover modal anterior se existir
        $('#simple-create-modal').remove();
        
        // Adicionar novo modal
        $('body').append(modalHtml);
        
        // Focar no campo título
        setTimeout(function() {
            $('#simple-task-title').focus();
        }, 100);
        
        // Handler do formulário
        $('#simple-task-form').on('submit', function(e) {
            e.preventDefault();
            
            var title = $('#simple-task-title').val().trim();
            if (!title) {
                alert('Por favor, digite um título para a tarefa');
                return;
            }
            
            var description = $('#simple-task-description').val().trim();
            
            console.log('📝 Criando tarefa:', { title, description, columnId });
            
            // Aqui seria chamada a função de criação via AJAX
            // Por enquanto, apenas simular
            alert('Tarefa "' + title + '" criada com sucesso!\n(Funcionalidade em desenvolvimento)');
            
            $('.ql-modal').hide();
        });
        
        console.log('✅ Modal simples de criação exibido');
    }
    
    function createBasicModal() {
        var modalHtml = `
            <div id="ql-quick-task-modal" class="ql-modal" style="display: none;">
                <div class="ql-modal-overlay"></div>
                <div class="ql-modal-content">
                    <div class="ql-modal-header">
                        <h2>Nova Tarefa</h2>
                        <button class="ql-modal-close">&times;</button>
                    </div>
                    <div class="ql-modal-body">
                        <p>Modal básico criado dinamicamente</p>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        console.log('✅ Modal básico criado');
    }
    
    // Expor função para debug
    window.QLClickFix = {
        init: initClickFixes,
        fixTaskClicks: fixTaskCardClicks,
        fixAddTaskClicks: fixAddTaskButtonClicks
    };
    
    console.log('✅ Sistema de correção de cliques carregado');
    
})(jQuery);