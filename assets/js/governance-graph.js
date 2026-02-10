/**
 * Sistema de Visualização de Grafos de Governança
 * 
 * Implementa visualizações interativas para os três modos de governança:
 * - Assembleia: Cluster centralizado
 * - Descentralizado: Clusters distribuídos por proximidade
 * - Inativo: Distribuição aleatória
 * 
 * Usa Vis.js para renderização do grafo
 */

class QLGovernanceGraph {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        this.network = null;
        this.nodes = null;
        this.edges = null;
        this.currentMode = 'inactive';
        this.coletivo_id = options.coletivo_id || 1;
        
        // Configurações padrão
        this.defaultOptions = {
            physics: {
                enabled: true,
                stabilization: { iterations: 100 }
            },
            interaction: {
                hover: true,
                tooltipDelay: 200,
                hideEdgesOnDrag: false,
                hideNodesOnDrag: false
            },
            nodes: {
                borderWidth: 2,
                borderWidthSelected: 4,
                font: {
                    size: 12,
                    color: '#333333'
                },
                chosen: true
            },
            edges: {
                width: 1,
                color: { inherit: 'both' },
                smooth: {
                    type: 'continuous',
                    roundness: 0.3
                }
            },
            groups: this.defineNodeGroups()
        };
        
        this.init();
    }
    
    init() {
        if (!this.container) {
            console.error('Container not found:', this.containerId);
            return;
        }
        
        // Inicializar datasets
        this.nodes = new vis.DataSet([]);
        this.edges = new vis.DataSet([]);
        
        // Criar controles de interface
        this.createControls();
        
        // Carregar dados iniciais
        this.loadCurrentState();
        
        // Event listeners
        this.bindEvents();
    }
    
    defineNodeGroups() {
        return {
            circulo: {
                color: { 
                    background: '#3498db', 
                    border: '#2980b9',
                    highlight: { background: '#5dade2', border: '#2980b9' }
                },
                shape: 'circle'
            },
            nucleo: {
                color: { 
                    background: '#9b59b6', 
                    border: '#8e44ad',
                    highlight: { background: '#bb8fce', border: '#8e44ad' }
                },
                shape: 'triangle'
            },
            comunidade: {
                color: { 
                    background: '#27ae60', 
                    border: '#229954',
                    highlight: { background: '#58d68d', border: '#229954' }
                },
                shape: 'square'
            },
            coletivo: {
                color: { 
                    background: '#e74c3c', 
                    border: '#c0392b',
                    highlight: { background: '#f1948a', border: '#c0392b' }
                },
                shape: 'diamond'
            },
            assembleia: {
                color: { 
                    background: '#f39c12', 
                    border: '#e67e22',
                    highlight: { background: '#f7dc6f', border: '#e67e22' }
                },
                shape: 'star',
                size: 50
            },
            person: {
                color: { 
                    background: '#34495e', 
                    border: '#2c3e50',
                    highlight: { background: '#5d6d7e', border: '#2c3e50' }
                },
                shape: 'dot',
                size: 10
            }
        };
    }
    
    createControls() {
        const controlsHtml = `
            <div class="ql-governance-controls" style="margin-bottom: 15px;">
                <div class="ql-mode-selector">
                    <h4>Modo de Governança:</h4>
                    <div class="ql-mode-buttons">
                        <button class="ql-mode-btn" data-mode="inactive" title="Coletivo Inativo">
                            <span class="dashicons dashicons-groups"></span>
                            Inativo
                        </button>
                        <button class="ql-mode-btn" data-mode="decentralized" title="Modo Descentralizado">
                            <span class="dashicons dashicons-networking"></span>
                            Descentralizado
                        </button>
                        <button class="ql-mode-btn" data-mode="assembly" title="Modo Assembleia">
                            <span class="dashicons dashicons-megaphone"></span>
                            Assembleia
                        </button>
                    </div>
                </div>
                
                <div class="ql-graph-controls">
                    <h4>Controles do Grafo:</h4>
                    <button id="ql-refresh-graph" class="button">
                        <span class="dashicons dashicons-update"></span>
                        Atualizar
                    </button>
                    <button id="ql-center-graph" class="button">
                        <span class="dashicons dashicons-admin-site"></span>
                        Centralizar
                    </button>
                    <button id="ql-toggle-physics" class="button">
                        <span class="dashicons dashicons-admin-tools"></span>
                        Física On/Off
                    </button>
                    <button id="ql-export-graph" class="button">
                        <span class="dashicons dashicons-download"></span>
                        Exportar
                    </button>
                </div>
                
                <div class="ql-assembly-controls" style="display: none;">
                    <h4>Controles da Assembleia:</h4>
                    <select id="ql-assembly-select">
                        <option value="">Selecione uma assembleia...</option>
                    </select>
                    <button id="ql-start-assembly" class="button button-primary">
                        Iniciar Assembleia
                    </button>
                    <button id="ql-end-assembly" class="button" style="display: none;">
                        Finalizar Assembleia
                    </button>
                </div>
            </div>
            
            <div class="ql-graph-info" style="margin-bottom: 15px;">
                <div class="ql-stats-panel">
                    <div class="ql-stat-item">
                        <label>Modo Atual:</label>
                        <span id="ql-current-mode">Carregando...</span>
                    </div>
                    <div class="ql-stat-item">
                        <label>Total de Nós:</label>
                        <span id="ql-total-nodes">0</span>
                    </div>
                    <div class="ql-stat-item">
                        <label>Total de Conexões:</label>
                        <span id="ql-total-edges">0</span>
                    </div>
                    <div class="ql-stat-item" id="ql-assembly-stats" style="display: none;">
                        <label>Participação na Assembleia:</label>
                        <span id="ql-participation-rate">0%</span>
                    </div>
                </div>
            </div>
        `;
        
        this.container.insertAdjacentHTML('beforebegin', controlsHtml);
    }
    
    bindEvents() {
        // Event listeners para controles de modo
        document.querySelectorAll('.ql-mode-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const mode = e.target.closest('.ql-mode-btn').dataset.mode;
                this.changeMode(mode);
            });
        });
        
        // Event listeners para controles do grafo
        document.getElementById('ql-refresh-graph').addEventListener('click', () => {
            this.refreshGraph();
        });
        
        document.getElementById('ql-center-graph').addEventListener('click', () => {
            if (this.network) {
                this.network.fit();
            }
        });
        
        document.getElementById('ql-toggle-physics').addEventListener('click', () => {
            this.togglePhysics();
        });
        
        document.getElementById('ql-export-graph').addEventListener('click', () => {
            this.exportGraph();
        });
        
        // Event listeners para controles de assembleia
        document.getElementById('ql-start-assembly').addEventListener('click', () => {
            this.startAssembly();
        });
        
        document.getElementById('ql-end-assembly').addEventListener('click', () => {
            this.endAssembly();
        });
        
        // Carregar assembleias disponíveis quando modo assembly é selecionado
        this.loadAvailableAssemblies();
    }
    
    async loadCurrentState() {
        try {
            this.showLoading(true);
            
            const response = await this.makeRequest('ql_get_governance_graph', {
                coletivo_id: this.coletivo_id
            });
            
            if (response.success) {
                this.currentMode = response.data.metadata.mode;
                this.updateUI(response.data);
                this.renderGraph(response.data);
            } else {
                this.showError('Erro ao carregar estado: ' + response.data.message);
            }
        } catch (error) {
            this.showError('Erro de conexão: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    async changeMode(newMode) {
        if (newMode === this.currentMode) return;
        
        try {
            this.showLoading(true);
            
            // Primeiro mudar o modo
            const modeResponse = await this.makeRequest('ql_set_governance_mode', {
                coletivo_id: this.coletivo_id,
                mode: newMode
            });
            
            if (!modeResponse.success) {
                this.showError('Erro ao alterar modo: ' + modeResponse.data.message);
                return;
            }
            
            // Depois carregar o novo grafo
            const graphResponse = await this.makeRequest('ql_get_governance_graph', {
                coletivo_id: this.coletivo_id,
                mode: newMode
            });
            
            if (graphResponse.success) {
                this.currentMode = newMode;
                this.updateUI(graphResponse.data);
                this.renderGraph(graphResponse.data);
                this.showSuccess('Modo de governança alterado para: ' + this.getModeLabel(newMode));
            } else {
                this.showError('Erro ao carregar grafo: ' + graphResponse.data.message);
            }
            
        } catch (error) {
            this.showError('Erro de conexão: ' + error.message);
        } finally {
            this.showLoading(false);
        }
    }
    
    renderGraph(data) {
        if (!data.nodes || !data.edges) {
            console.error('Dados do grafo inválidos:', data);
            return;
        }
        
        // Limpar dados existentes
        this.nodes.clear();
        this.edges.clear();
        
        // Adicionar novos dados
        this.nodes.add(data.nodes.map(node => ({
            ...node,
            group: node.type,
            title: this.generateNodeTooltip(node)
        })));
        
        this.edges.add(data.edges);
        
        // Configurar opções específicas do modo
        const options = { ...this.defaultOptions };
        this.configureLayoutOptions(options, data);
        
        // Criar ou atualizar rede
        if (this.network) {
            this.network.setData({ nodes: this.nodes, edges: this.edges });
            this.network.setOptions(options);
        } else {
            this.network = new vis.Network(this.container, 
                { nodes: this.nodes, edges: this.edges }, 
                options
            );
            
            this.setupNetworkEvents();
        }
        
        // Aplicar layout específico se necessário
        this.applyModeSpecificLayout(data);
        
        // Atualizar estatísticas
        this.updateStats(data);
    }
    
    configureLayoutOptions(options, data) {
        switch (data.metadata.mode) {
            case 'assembly':
                options.physics = {
                    enabled: false // Posições fixas para modo assembleia
                };
                break;
                
            case 'decentralized':
                options.physics = {
                    enabled: true,
                    barnesHut: {
                        gravitationalConstant: -2000,
                        centralGravity: 0.3,
                        springLength: 95,
                        springConstant: 0.04,
                        damping: 0.09,
                        avoidOverlap: 1
                    },
                    stabilization: { iterations: 150 }
                };
                break;
                
            case 'inactive':
                options.physics = {
                    enabled: true,
                    barnesHut: {
                        gravitationalConstant: -1000,
                        centralGravity: 0.1,
                        springLength: 200,
                        springConstant: 0.02,
                        damping: 0.15,
                        avoidOverlap: 0.5
                    },
                    stabilization: { iterations: 100 }
                };
                break;
        }
    }
    
    applyModeSpecificLayout(data) {
        if (data.metadata.mode === 'assembly' && this.network) {
            // Para modo assembleia, posicionar nós conforme coordenadas pré-definidas
            setTimeout(() => {
                const positions = {};
                data.nodes.forEach(node => {
                    if (node.x !== undefined && node.y !== undefined) {
                        positions[node.id] = { x: node.x, y: node.y };
                    }
                });
                this.network.setPositions(positions);
                this.network.fit();
            }, 500);
        } else if (data.metadata.mode === 'decentralized' && this.network) {
            // Para modo descentralizado, aplicar clustering
            setTimeout(() => {
                this.network.clustering.cluster({
                    joinCondition: (nodeOptions) => {
                        return nodeOptions.cluster && nodeOptions.cluster > 0;
                    },
                    processProperties: (clusterOptions, childNodes, childEdges) => {
                        const cluster = childNodes[0].cluster;
                        return {
                            id: 'cluster_' + cluster,
                            label: 'Cluster ' + cluster,
                            color: '#85929e',
                            size: Math.min(30 + childNodes.length * 5, 80)
                        };
                    }
                });
            }, 1000);
        }
    }
    
    setupNetworkEvents() {
        if (!this.network) return;
        
        // Hover para mostrar informações
        this.network.on('hoverNode', (params) => {
            const nodeId = params.node;
            const node = this.nodes.get(nodeId);
            this.showNodeInfo(node);
        });
        
        // Clique para seleção
        this.network.on('click', (params) => {
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                const node = this.nodes.get(nodeId);
                this.onNodeClick(node);
            }
        });
        
        // Duplo clique para focar
        this.network.on('doubleClick', (params) => {
            if (params.nodes.length > 0) {
                this.network.focus(params.nodes[0], {
                    scale: 2.0,
                    animation: true
                });
            }
        });
    }
    
    generateNodeTooltip(node) {
        let tooltip = `<strong>${node.label}</strong><br>`;
        tooltip += `Tipo: ${this.getTypeLabel(node.type)}<br>`;
        
        if (node.metadata) {
            if (node.metadata.member_count !== undefined) {
                tooltip += `Membros: ${node.metadata.member_count}<br>`;
            }
            if (node.metadata.status) {
                tooltip += `Status: ${node.metadata.status}<br>`;
            }
            if (node.metadata.cluster_name) {
                tooltip += `Cluster: ${node.metadata.cluster_name}<br>`;
            }
        }
        
        return tooltip;
    }
    
    updateUI(data) {
        // Atualizar botões de modo
        document.querySelectorAll('.ql-mode-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.mode === data.metadata.mode) {
                btn.classList.add('active');
            }
        });
        
        // Mostrar/esconder controles específicos
        const assemblyControls = document.querySelector('.ql-assembly-controls');
        const assemblyStats = document.getElementById('ql-assembly-stats');
        
        if (data.metadata.mode === 'assembly') {
            assemblyControls.style.display = 'block';
            assemblyStats.style.display = 'block';
            
            // Atualizar informações da assembleia
            if (data.metadata.assembly_name) {
                document.getElementById('ql-assembly-select').innerHTML = 
                    `<option value="${data.metadata.assembly_id}" selected>${data.metadata.assembly_name}</option>`;
                document.getElementById('ql-start-assembly').style.display = 'none';
                document.getElementById('ql-end-assembly').style.display = 'inline-block';
            }
        } else {
            assemblyControls.style.display = 'none';
            assemblyStats.style.display = 'none';
            document.getElementById('ql-start-assembly').style.display = 'inline-block';
            document.getElementById('ql-end-assembly').style.display = 'none';
        }
        
        // Atualizar modo atual
        document.getElementById('ql-current-mode').textContent = this.getModeLabel(data.metadata.mode);
    }
    
    updateStats(data) {
        document.getElementById('ql-total-nodes').textContent = data.metadata.total_nodes || 0;
        document.getElementById('ql-total-edges').textContent = data.metadata.total_edges || 0;
        
        if (data.metadata.participation_stats) {
            document.getElementById('ql-participation-rate').textContent = 
                Math.round(data.metadata.participation_stats.participation_rate || 0) + '%';
        }
    }
    
    async startAssembly() {
        const assemblyId = document.getElementById('ql-assembly-select').value;
        if (!assemblyId) {
            this.showError('Selecione uma assembleia para iniciar');
            return;
        }
        
        try {
            const response = await this.makeRequest('ql_toggle_assembly_mode', {
                coletivo_id: this.coletivo_id,
                assembly_id: parseInt(assemblyId),
                action: 'start'
            });
            
            if (response.success) {
                this.showSuccess('Modo assembleia iniciado');
                await this.loadCurrentState(); // Recarregar estado
            } else {
                this.showError('Erro ao iniciar assembleia: ' + response.data.message);
            }
        } catch (error) {
            this.showError('Erro de conexão: ' + error.message);
        }
    }
    
    async endAssembly() {
        const currentState = await this.makeRequest('ql_get_governance_graph', {
            coletivo_id: this.coletivo_id
        });
        
        if (!currentState.success || !currentState.data.metadata.assembly_id) {
            this.showError('Nenhuma assembleia ativa encontrada');
            return;
        }
        
        try {
            const response = await this.makeRequest('ql_toggle_assembly_mode', {
                coletivo_id: this.coletivo_id,
                assembly_id: currentState.data.metadata.assembly_id,
                action: 'end'
            });
            
            if (response.success) {
                this.showSuccess('Modo assembleia finalizado');
                await this.loadCurrentState(); // Recarregar estado
            } else {
                this.showError('Erro ao finalizar assembleia: ' + response.data.message);
            }
        } catch (error) {
            this.showError('Erro de conexão: ' + error.message);
        }
    }
    
    async loadAvailableAssemblies() {
        // Implementar carregamento de assembleias disponíveis
        // Por enquanto usar dados mockados
        const select = document.getElementById('ql-assembly-select');
        select.innerHTML = '<option value="">Selecione uma assembleia...</option>';
        // TODO: Carregar assembleias reais via AJAX
    }
    
    refreshGraph() {
        this.loadCurrentState();
    }
    
    togglePhysics() {
        if (!this.network) return;
        
        const physics = this.network.physics.physicsEnabled;
        this.network.setOptions({ physics: { enabled: !physics } });
        
        const btn = document.getElementById('ql-toggle-physics');
        btn.textContent = physics ? 'Física On' : 'Física Off';
    }
    
    exportGraph() {
        if (!this.network) return;
        
        // Exportar como PNG
        const canvas = this.network.canvas.getCanvas();
        const link = document.createElement('a');
        link.download = `governance-graph-${this.currentMode}-${Date.now()}.png`;
        link.href = canvas.toDataURL();
        link.click();
    }
    
    onNodeClick(node) {
        console.log('Node clicked:', node);
        // Implementar ações específicas por tipo de nó
    }
    
    showNodeInfo(node) {
        // Implementar painel de informações do nó
        console.log('Node info:', node);
    }
    
    // Métodos auxiliares
    
    getModeLabel(mode) {
        const modes = {
            'assembly': 'Assembleia',
            'decentralized': 'Descentralizado',
            'inactive': 'Inativo'
        };
        return modes[mode] || mode;
    }
    
    getTypeLabel(type) {
        const types = {
            'circulo': 'Círculo',
            'nucleo': 'Núcleo',
            'comunidade': 'Comunidade',
            'coletivo': 'Coletivo',
            'assembleia': 'Assembleia',
            'person': 'Pessoa'
        };
        return types[type] || type;
    }
    
    async makeRequest(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', ql_admin_ajax.nonce);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });
        
        const response = await fetch(ql_admin_ajax.ajax_url, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    showLoading(show) {
        // Implementar indicador de loading
        if (show) {
            this.container.style.opacity = '0.6';
            this.container.style.pointerEvents = 'none';
        } else {
            this.container.style.opacity = '1';
            this.container.style.pointerEvents = 'auto';
        }
    }
    
    showError(message) {
        console.error('QL Governance Graph Error:', message);
        // Implementar notificação de erro
        this.showNotification(message, 'error');
    }
    
    showSuccess(message) {
        console.log('QL Governance Graph Success:', message);
        // Implementar notificação de sucesso
        this.showNotification(message, 'success');
    }
    
    showNotification(message, type = 'info') {
        // Implementar sistema de notificações
        const notification = document.createElement('div');
        notification.className = `ql-notification ql-notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
}

// CSS para os controles (será adicionado via PHP)
const governanceGraphCSS = `
.ql-governance-controls {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 15px;
}

.ql-governance-controls h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
}

.ql-mode-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.ql-mode-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 15px;
    border: 2px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.3s;
}

.ql-mode-btn:hover {
    border-color: #007cba;
}

.ql-mode-btn.active {
    background: #007cba;
    color: white;
    border-color: #005a87;
}

.ql-graph-controls {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.ql-graph-controls h4 {
    margin-right: 15px;
}

.ql-stats-panel {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    background: white;
    padding: 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.ql-stat-item {
    display: flex;
    flex-direction: column;
}

.ql-stat-item label {
    font-size: 11px;
    color: #666;
    font-weight: 600;
    margin-bottom: 2px;
}

.ql-stat-item span {
    font-size: 14px;
    font-weight: bold;
}

.ql-assembly-controls {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.ql-assembly-controls select {
    margin-right: 10px;
    min-width: 200px;
}

.ql-notification {
    position: fixed;
    top: 32px;
    right: 20px;
    padding: 10px 15px;
    border-radius: 4px;
    color: white;
    z-index: 10000;
    font-weight: 500;
}

.ql-notification-success {
    background: #27ae60;
}

.ql-notification-error {
    background: #e74c3c;
}

.ql-notification-info {
    background: #3498db;
}
`;

// Inicialização global
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar CSS
    const style = document.createElement('style');
    style.textContent = governanceGraphCSS;
    document.head.appendChild(style);
    
    // Inicializar grafo se container existir
    if (document.getElementById('ql-governance-graph')) {
        window.qlGovernanceGraph = new QLGovernanceGraph('ql-governance-graph');
    }
});