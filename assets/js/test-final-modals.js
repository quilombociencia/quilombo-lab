/**
 * TESTE DOS MODAIS FINAIS
 * Script para testar se o sistema final de modais está funcionando
 */

console.log('🧪 TESTE DOS MODAIS FINAIS CARREGADO');

// Aguardar jQuery e DOM
jQuery(document).ready(function($) {
    console.log('🧪 Testando se QLFinalModals está disponível...');
    
    // Verificar se o sistema final está carregado
    setTimeout(function() {
        console.log('🔍 Verificando QLFinalModals...', typeof window.QLFinalModals);
        
        if (typeof window.QLFinalModals !== 'undefined') {
            console.log('✅ QLFinalModals CARREGADO COM SUCESSO!');
            console.log('📋 Funções disponíveis:', Object.keys(window.QLFinalModals));
            
            // Forçar inicialização se não foi inicializado
            if (!window.QLFinalModals.initialized) {
                console.log('🔧 Forçando inicialização...');
                window.QLFinalModals.init();
            }
            
            // Testar se ql_admin está definido
            if (typeof ql_admin !== 'undefined') {
                console.log('✅ ql_admin disponível:', ql_admin);
            } else {
                console.log('❌ ql_admin não está definido');
            }
            
            // Contar elementos do Kanban
            var taskCards = $('.ql-kanban-task').length;
            var addButtons = $('.ql-add-task-btn').length;
            
            console.log('📊 ELEMENTOS ENCONTRADOS:');
            console.log('- Cards de tarefa:', taskCards);
            console.log('- Botões adicionar:', addButtons);
            
            if (taskCards > 0) {
                console.log('✅ Quadro Kanban detectado!');
                console.log('🎯 Clique em um card para testar o modal de visualização');
            }
            
            if (addButtons > 0) {
                console.log('✅ Botões de adição detectados!');
                console.log('🎯 Clique em um botão "+" para testar o modal de criação');
            }
            
        } else {
            console.log('❌ QLFinalModals NÃO ESTÁ CARREGADO');
            console.log('🔍 Verificando se outros sistemas estão ativos...');
            
            if (typeof window.QLSimpleModals !== 'undefined') {
                console.log('⚠️ QLSimpleModals ainda ativo - pode haver conflito');
            }
            
            if (typeof window.QLDebugSystem !== 'undefined') {
                console.log('🔧 QLDebugSystem ativo');
            }
        }
    }, 1000);
    
    // Debug apenas - remover botões de teste desnecessários pois agora há botões nos cards
    setTimeout(function() {
        if (typeof window.QLFinalModals !== 'undefined' && window.location.href.indexOf('debug') !== -1) {
            console.log('🧪 Modo debug ativado - botões de teste disponíveis');
            
            var debugButton = $('<button>')
                .text('🔧 Debug Modals')
                .css({
                    'position': 'fixed',
                    'top': '50px',
                    'right': '20px',
                    'z-index': '9999',
                    'background': '#666',
                    'color': 'white',
                    'border': 'none',
                    'padding': '5px 10px',
                    'border-radius': '3px',
                    'cursor': 'pointer',
                    'font-size': '11px'
                })
                .click(function() {
                    console.log('🔧 Status do sistema de modais:');
                    console.log('- QLFinalModals:', typeof window.QLFinalModals);
                    console.log('- Funções:', Object.keys(window.QLFinalModals));
                    console.log('- Cards encontrados:', $('.ql-kanban-task').length);
                    console.log('- Botões editar:', $('.ql-task-edit-btn').length);
                    console.log('- Botões adicionar:', $('.ql-add-task-btn').length);
                });
            
            $('body').append(debugButton);
        }
    }, 2000);
});