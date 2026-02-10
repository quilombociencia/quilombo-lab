<?php
/**
 * Template da página administrativa de Instâncias Organizacionais
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$instances_system = QL_Instances::get_instance();
$instance_types = QL_Instances::get_instance_types();
$hierarchy = $instances_system->get_instances_hierarchy();

// Processar formulário de criação de comunidade
if (isset($_POST['create_community']) && wp_verify_nonce($_POST['_wpnonce'], 'ql_create_community')) {
    $source_nucleo_id = intval($_POST['source_nucleo_id']);
    $community_name = sanitize_text_field($_POST['community_name']);
    $territory_scope = sanitize_textarea_field($_POST['territory_scope']);
    $description = sanitize_textarea_field($_POST['community_description']);
    
    if ($source_nucleo_id && !empty($community_name) && !empty($territory_scope)) {
        $options = [
            'description' => $description,
            'parent_id' => $source_nucleo_id,
            'territory_scope' => $territory_scope,
            'source_nucleo_id' => $source_nucleo_id
        ];
        
        $result = $instances_system->create_instance('comunidade', $community_name, get_current_user_id(), $options);
        
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . __('Erro ao criar comunidade:', 'quilombo-lab') . ' ' . $result->get_error_message() . '</p></div>';
        } else {
            // Vincular membros do núcleo à comunidade
            $nucleo_members = $instances_system->get_instance_members($source_nucleo_id);
            foreach ($nucleo_members as $member) {
                $instances_system->add_member_to_instance($result, $member->user_id, 'member');
            }
            
            echo '<div class="notice notice-success"><p>' . sprintf(__('Comunidade criada com sucesso! %d membros adicionados automaticamente.', 'quilombo-lab'), count($nucleo_members)) . '</p></div>';
            // Recarregar hierarquia
            $hierarchy = $instances_system->get_instances_hierarchy();
        }
    } else {
        echo '<div class="notice notice-error"><p>' . __('Por favor, preencha todos os campos obrigatórios.', 'quilombo-lab') . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-networking" style="color: #e74c3c;"></span>
        Instâncias Organizacionais
    </h1>
    
    <div class="ql-instances-admin">
        
        
        <!-- Criação de Comunidades -->
        <div class="ql-community-creation-section">
            <h2>
                <span class="dashicons dashicons-admin-multisite"></span>
                Criar Nova Comunidade
            </h2>
            
            <div class="ql-creation-instructions">
                <div class="notice notice-info inline">
                    <h3>Conforme Modelo Organizativo:</h3>
                    <ul>
                        <li><strong>Círculos:</strong> Criados automaticamente como <em>grupos</em> no Moodle</li>
                        <li><strong>Núcleos:</strong> Criados automaticamente como <em>agrupamentos</em> no Moodle</li>
                        <li><strong>Assembleias:</strong> Criadas a partir de trilhas no Moodle com categoria assembleia</li>
                        <li><strong>Comunidades:</strong> Criadas definindo abrangência territorial de núcleos existentes</li>
                    </ul>
                </div>
                
                <div class="ql-moodle-sync-status">
                    <h4>Sincronização com Moodle</h4>
                    <p class="description">Os dados do grafo de governança são obtidos em tempo real do Moodle e das instâncias criadas localmente.</p>
                    <button type="button" id="ql-sync-moodle-instances" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        Sincronizar Grupos/Agrupamentos do Moodle
                    </button>
                    <p class="description" style="font-size: 12px; color: #666; margin-top: 8px;">
                        <strong>Automático:</strong> Círculos e Núcleos são sincronizados automaticamente do Moodle. 
                        <strong>Manual:</strong> Comunidades, Coletivos e Assembleias são criados manualmente nesta página.
                    </p>
                </div>
            </div>
            
            <?php 
            $nucleos_disponiveis = array_filter($hierarchy, function($i) { return $i->type === 'nucleo'; });
            if (!empty($nucleos_disponiveis) && current_user_can('ql_manage_resources')): 
            ?>
                <div class="ql-community-form">
                    <h3>Selecione um Núcleo e Defina a Abrangência Territorial</h3>
                    <p>Para criar uma comunidade, selecione um núcleo existente e defina sua abrangência territorial.</p>
                    
                    <form method="post" action="" id="community-creation-form">
                        <?php wp_nonce_field('ql_create_community'); ?>
                        <input type="hidden" name="create_community" value="1">
                        
                        <div class="ql-form-row">
                            <div class="ql-form-col">
                                <label for="source_nucleo_id">Núcleo Base</label>
                                <select name="source_nucleo_id" id="source_nucleo_id" required onchange="updateCommunityForm()">
                                    <option value="">Selecione um núcleo...</option>
                                    <?php foreach ($nucleos_disponiveis as $nucleo): ?>
                                        <option value="<?php echo $nucleo->id; ?>" 
                                                data-name="<?php echo esc_attr($nucleo->name); ?>"
                                                data-address="<?php echo esc_attr($nucleo->physical_address ?? ''); ?>">
                                            <?php echo esc_html($nucleo->name); ?> 
                                            (<?php echo count($instances_system->get_instance_members($nucleo->id)); ?> membros)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="ql-form-col">
                                <label for="community_name">Nome da Comunidade</label>
                                <input type="text" name="community_name" id="community_name" required 
                                       placeholder="Ex: Comunidade Vila Madalena">
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-col-full">
                                <label for="territory_scope">Abrangência Territorial</label>
                                <textarea name="territory_scope" id="territory_scope" rows="3" required
                                          placeholder="Descreva a área geográfica de abrangência da comunidade. Ex: Bairro Vila Madalena, São Paulo, SP - Raio de 2km a partir do núcleo"></textarea>
                                <p class="description">Defina claramente a área geográfica onde a comunidade terá influência e participação.</p>
                            </div>
                        </div>
                        
                        <div class="ql-form-row">
                            <div class="ql-form-col-full">
                                <label for="community_description">Descrição da Comunidade</label>
                                <textarea name="community_description" id="community_description" rows="3"
                                          placeholder="Descreva o propósito, objetivos e características da comunidade..."></textarea>
                            </div>
                        </div>
                        
                        <div id="nucleo-info" style="display: none;" class="ql-nucleo-preview">
                            <h4>Informações do Núcleo Selecionado</h4>
                            <div id="nucleo-details"></div>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" class="button-primary" value="Criar Comunidade">
                            <button type="button" class="button button-secondary" onclick="resetCommunityForm();">
                                Cancelar
                            </button>
                        </p>
                    </form>
                </div>
            <?php elseif (empty($nucleos_disponiveis)): ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong>Nenhum núcleo disponível:</strong>
                        Para criar comunidades, é necessário ter núcleos sincronizados do Moodle. 
                        Use o botão "Sincronizar" acima para importar agrupamentos como núcleos.
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning inline">
                    <p>
                        <strong>Permissões limitadas:</strong>
                        Você não tem permissões para criar comunidades. Entre em contato com um administrador.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Hierarquia Atual -->
        <div class="ql-current-hierarchy">
            <h2>Hierarquia Organizacional Atual</h2>
            
            <?php if (empty($hierarchy)): ?>
                <div class="ql-no-instances">
                    <p>Ainda não há instâncias organizacionais sincronizadas.</p>
                    <p>Para começar:</p>
                    <ol>
                        <li>Crie <strong>grupos</strong> no Moodle (serão importados como Círculos)</li>
                        <li>Crie <strong>agrupamentos</strong> no Moodle (serão importados como Núcleos)</li>
                        <li>Use o botão "Sincronizar" acima para importar</li>
                        <li>Crie <strong>Comunidades/Coletivos/Assembleias</strong> diretamente no QL</li>
                    </ol>
                </div>
            <?php else: ?>
                <div class="ql-instances-tree">
                    <?php echo render_instances_tree($hierarchy, $instances_system); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Criação de Comunidades a partir de Núcleos -->
        <?php 
        $nucleos = array_filter($hierarchy, function($i) { return $i->type === 'nucleo'; });
        if (!empty($nucleos)):
        ?>
        <div class="ql-community-creation">
            <h2>
                <span class="dashicons dashicons-location-alt"></span>
                Criar Comunidades a partir de Núcleos
            </h2>
            <p class="description">
                Conforme o modelo organizativo, comunidades são criadas definindo a abrangência territorial dos núcleos existentes.
            </p>
            
            <div class="ql-nucleos-for-communities">
                <?php foreach ($nucleos as $nucleo): ?>
                    <div class="ql-nucleo-community-card" data-nucleo-id="<?php echo $nucleo->id; ?>">
                        <div class="ql-nucleo-header">
                            <div class="ql-nucleo-info">
                                <span class="dashicons dashicons-location"></span>
                                <strong><?php echo esc_html($nucleo->name); ?></strong>
                                <span class="ql-nucleo-status">(Núcleo)</span>
                            </div>
                            <button type="button" class="button button-secondary ql-define-territory-btn" 
                                    data-nucleo-id="<?php echo $nucleo->id; ?>">
                                <span class="dashicons dashicons-admin-site"></span>
                                Definir Território
                            </button>
                        </div>
                        
                        <div class="ql-nucleo-details">
                            <?php if ($nucleo->physical_address): ?>
                                <p><strong>Endereço:</strong> <?php echo esc_html($nucleo->physical_address); ?></p>
                            <?php endif; ?>
                            
                            <?php 
                            $members = $instances_system->get_instance_members($nucleo->id);
                            $circles = $instances_system->get_child_instances($nucleo->id);
                            $metadata = json_decode($nucleo->metadata ?? '{}', true);
                            $territory_scope = $metadata['territory_scope'] ?? null;
                            ?>
                            <p><strong>Círculos:</strong> <?php echo count($circles); ?> • <strong>Membros:</strong> <?php echo count($members); ?></p>
                            
                            <?php if (empty($territory_scope)): ?>
                                <div class="notice notice-warning inline">
                                    <p>Este núcleo ainda não tem abrangência territorial definida. É necessário definir para criar uma comunidade.</p>
                                </div>
                            <?php else: ?>
                                <div class="ql-territory-defined">
                                    <p><strong>Território:</strong> <?php echo esc_html($territory_scope); ?></p>
                                    <button type="button" class="button button-primary ql-create-community-btn" 
                                            data-nucleo-id="<?php echo $nucleo->id; ?>">
                                        <span class="dashicons dashicons-plus"></span>
                                        Criar Comunidade
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Visualização dos Modos de Governança -->
        <div class="ql-governance-visualization">
            <h2>
                <span class="dashicons dashicons-networking"></span>
                Modos de Governança Organizacional
            </h2>
            <p class="description">
                Visualize e controle os diferentes modos de organização do coletivo conforme o modelo organizativo.
            </p>
            
            <!-- Container para o grafo de visualização -->
            <div id="ql-governance-graph"></div>
            
            <!-- Informações dos modos -->
            <div class="ql-governance-modes-info">
                <div class="ql-modes-grid">
                    <div class="ql-mode-info-card mode-assembly">
                        <h4><span class="dashicons dashicons-megaphone"></span> Modo Assembleia</h4>
                        <p>Todas as instâncias convergem para assembleia centralizada</p>
                        <ul>
                            <li>Assembleia assume controle total</li>
                            <li>Transferência de prerrogativas</li>
                            <li>Decisões coletivas centralizadas</li>
                        </ul>
                    </div>
                    
                    <div class="ql-mode-info-card mode-decentralized">
                        <h4><span class="dashicons dashicons-networking"></span> Modo Descentralizado</h4>
                        <p>Distribuição por proximidade em 3 parâmetros</p>
                        <ul>
                            <li>Projetos compartilhados (40%)</li>
                            <li>Distribuição de responsabilidades (35%)</li>
                            <li>Consensos de participação (25%)</li>
                        </ul>
                    </div>
                    
                    <div class="ql-mode-info-card mode-inactive">
                        <h4><span class="dashicons dashicons-groups"></span> Coletivo Inativo</h4>
                        <p>Distribuição aleatória sem organização</p>
                        <ul>
                            <li>Participantes distribuídos aleatoriamente</li>
                            <li>Sem atividade coordenada</li>
                            <li>Estado de dormência organizacional</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Estatísticas por Tipo -->
        <div class="ql-instances-stats">
            <h2>Estatísticas das Instâncias</h2>
            
            <div class="ql-stats-grid">
                <?php foreach ($instance_types as $type_key => $type_data): 
                    $instances_of_type = array_filter($hierarchy, function($i) use ($type_key) { 
                        return $i->type === $type_key; 
                    });
                    $count = count($instances_of_type);
                    $total_members = array_sum(array_column($instances_of_type, 'member_count'));
                ?>
                    <div class="ql-stat-card" style="border-top: 3px solid <?php echo $type_data['color']; ?>">
                        <div class="ql-stat-header">
                            <span class="dashicons <?php echo $type_data['icon']; ?>"></span>
                            <h3><?php echo $type_data['label']; ?>s</h3>
                        </div>
                        <div class="ql-stat-numbers">
                            <div class="ql-stat-main"><?php echo $count; ?></div>
                            <div class="ql-stat-sub"><?php echo $total_members; ?> membros</div>
                        </div>
                        <?php if ($count > 0): ?>
                            <div class="ql-stat-details">
                                Média: <?php echo number_format($total_members / $count, 1); ?> membros/instância
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Controle de Modos de Governança -->
        <div class="ql-governance-control">
            <h2>
                <span class="dashicons dashicons-admin-settings"></span>
                Modos de Governança do Coletivo
            </h2>
            
            <?php
            // Calcular estado atual do coletivo
            $nucleos_ativos = count(array_filter($hierarchy, function($i) { 
                return $i->type === 'nucleo' && $i->status === 'active'; 
            }));
            $circulos_ativos = count(array_filter($hierarchy, function($i) { 
                return $i->type === 'circulo' && $i->status === 'active'; 
            }));
            $assembleias_ativas = array_filter($hierarchy, function($i) { 
                return $i->type === 'assembleia' && $i->status === 'active'; 
            });
            
            // Determinar modo atual baseado nas regras organizativas do Quilombo Ciência
            $current_mode = 'inactive'; // Modo padrão inicial
            
            // Identificar o coletivo através da trilha 01 (trilha do coletivo)
            $coletivo_instances = array_filter($hierarchy, function($i) { 
                return $i->type === 'coletivo' || (isset($i->trilha_id) && $i->trilha_id == 1); 
            });
            
            // Verificar círculos da trilha do coletivo (trilha 01)
            $circulos_coletivo = array_filter($hierarchy, function($i) { 
                return $i->type === 'circulo' && (isset($i->trilha_id) && $i->trilha_id == 1) && $i->status === 'active'; 
            });
            
            // Verificar se todas as responsabilidades estão atribuídas
            // No modelo: quando há 1+ círculo na trilha do coletivo, as responsabilidades podem ser atribuídas
            $responsibilities_assigned = count($circulos_coletivo) >= 1;
            
            // Verificar se existe assembleia ativa (prioridade máxima)
            if (!empty($assembleias_ativas)) {
                $current_mode = 'assembly';
            } 
            // Verificar se atende aos requisitos para modo descentralizado
            elseif ($nucleos_ativos >= 1 && $circulos_ativos >= 3 && $responsibilities_assigned) {
                $current_mode = 'decentralized';
            }
            // Caso contrário, permanece inativo
            ?>
            
            <div class="ql-current-status">
                <div class="ql-mode-indicator mode-<?php echo $current_mode; ?>">
                    <h3>
                        <?php if ($current_mode === 'assembly'): ?>
                            <span class="dashicons dashicons-megaphone"></span> Modo Assembleia
                        <?php elseif ($current_mode === 'decentralized'): ?>
                            <span class="dashicons dashicons-networking"></span> Modo Descentralizado  
                        <?php else: ?>
                            <span class="dashicons dashicons-groups"></span> Coletivo Inativo
                        <?php endif; ?>
                    </h3>
                    
                    <div class="ql-status-details">
                        <p><strong>Coletivo:</strong> <?php echo count($coletivo_instances) > 0 ? 'Quilombo Ciência identificado' : 'Não identificado'; ?></p>
                        <p><strong>Círculos na trilha do coletivo:</strong> <?php echo count($circulos_coletivo); ?></p>
                        <p><strong>Núcleos ativos:</strong> <?php echo $nucleos_ativos; ?></p>
                        <p><strong>Círculos ativos (total):</strong> <?php echo $circulos_ativos; ?></p>
                        <p><strong>Assembleias ativas:</strong> <?php echo count($assembleias_ativas); ?></p>
                    </div>
                </div>
                
                <div class="ql-mode-controls">
                    <div class="ql-visualization-controls">
                        <h4>Controles de Visualização:</h4>
                        <div class="ql-control-buttons">
                            <button type="button" class="button button-secondary ql-show-mode-inactive" data-mode="inactive">
                                <span class="dashicons dashicons-groups"></span>
                                Visualizar Modo Inativo
                            </button>
                            <button type="button" class="button button-secondary ql-show-mode-decentralized" data-mode="decentralized">
                                <span class="dashicons dashicons-networking"></span>
                                Visualizar Modo Descentralizado
                            </button>
                            <button type="button" class="button button-secondary ql-show-mode-assembly" data-mode="assembly">
                                <span class="dashicons dashicons-megaphone"></span>
                                Visualizar Modo Assembleia
                            </button>
                        </div>
                    </div>
                    
                    <div class="ql-mode-activation">
                        <?php if (!empty($assembleias_ativas)): ?>
                            <button type="button" class="button button-primary ql-activate-assembly-mode" 
                                    data-assembly-id="<?php echo $assembleias_ativas[0]->id; ?>">
                                <span class="dashicons dashicons-megaphone"></span>
                                Ativar Modo Assembleia (Permanente)
                            </button>
                            <p class="description">Este botão ativa permanentemente o modo assembleia. Use os botões de visualização acima para ver diferentes modos temporariamente.</p>
                        <?php else: ?>
                            <div class="notice notice-info inline">
                                <p><strong>Para ativar o Modo Assembleia:</strong> Crie uma trilha com categoria "assembleia" no Moodle e sincronize.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ql-mode-requirements">
                        <h4>Requisitos para Modo Descentralizado:</h4>
                        <p class="description" style="font-size: 12px; color: #666; margin-bottom: 10px;">
                            A transição para modo descentralizado acontece automaticamente quando todos os requisitos abaixo são atendidos conforme o modelo organizativo do Quilombo Ciência.
                        </p>
                        <ul>
                            <li class="<?php echo count($circulos_coletivo) >= 1 ? 'completed' : 'pending'; ?>">
                                <span class="dashicons dashicons-<?php echo count($circulos_coletivo) >= 1 ? 'yes' : 'marker'; ?>"></span>
                                Pelo menos 1 círculo na trilha do coletivo (atual: <?php echo count($circulos_coletivo); ?>)
                            </li>
                            <li class="<?php echo $nucleos_ativos >= 1 ? 'completed' : 'pending'; ?>">
                                <span class="dashicons dashicons-<?php echo $nucleos_ativos >= 1 ? 'yes' : 'marker'; ?>"></span>
                                Pelo menos 1 núcleo ativo (atual: <?php echo $nucleos_ativos; ?>)
                            </li>
                            <li class="<?php echo $circulos_ativos >= 3 ? 'completed' : 'pending'; ?>">
                                <span class="dashicons dashicons-<?php echo $circulos_ativos >= 3 ? 'yes' : 'marker'; ?>"></span>
                                Pelo menos 3 círculos ativos total (atual: <?php echo $circulos_ativos; ?>)
                            </li>
                            <li class="<?php echo $responsibilities_assigned ? 'completed' : 'pending'; ?>">
                                <span class="dashicons dashicons-<?php echo $responsibilities_assigned ? 'yes' : 'marker'; ?>"></span>
                                Responsabilidades atribuídas (círculo do coletivo criado)
                            </li>
                        </ul>
                        
                        <div class="ql-coletivo-info" style="background: #f0f8ff; border: 1px solid #4A90E2; border-radius: 4px; padding: 10px; margin-top: 15px;">
                            <h5 style="margin: 0 0 8px 0; color: #2c5282;">
                                <span class="dashicons dashicons-info"></span> 
                                Lógica do Coletivo Quilombo Ciência
                            </h5>
                            <p style="margin: 0; font-size: 12px; color: #4a5568;">
                                <strong>Coletivo identificado:</strong> Trilha 01 (trilha do coletivo)<br>
                                <strong>Responsabilidades:</strong> Quando criado 1 círculo na trilha do coletivo, todas as responsabilidades podem ser atribuídas a esse círculo<br>
                                <strong>Projetos:</strong> Toda trilha no Moodle é a trilha de um projeto no laboratório
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visualização do Grafo -->
            <div class="ql-governance-visualization">
                <h3>Visualização Organizacional</h3>
                <p class="description">Grafo representando o modo atual de organização do coletivo.</p>
                
                <div id="ql-governance-graph" data-current-mode="<?php echo $current_mode; ?>">
                    <div class="ql-graph-placeholder">
                        <p>📊 Carregando visualização do modo <?php echo ucfirst($current_mode === 'inactive' ? 'Inativo' : ($current_mode === 'assembly' ? 'Assembleia' : 'Descentralizado')); ?>...</p>
                    </div>
                </div>
                
                <div class="ql-graph-legend">
                    <h4>Legenda:</h4>
                    <div class="ql-legend-section">
                        <h5>Instâncias:</h5>
                        <div class="ql-legend-items">
                            <div class="ql-legend-item">
                                <span class="ql-legend-color" style="background: #3498db;"></span>
                                <span>Círculos (Grupos/Projetos)</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-color" style="background: #9b59b6;"></span>
                                <span>Núcleos (Agrupamentos)</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-color" style="background: #27ae60;"></span>
                                <span>Comunidades</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-color" style="background: #e67e22;"></span>
                                <span>Coletivos</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-color" style="background: #f39c12;"></span>
                                <span>Assembleias</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ql-legend-section" style="margin-top: 15px;">
                        <h5>Vínculos:</h5>
                        <div class="ql-legend-items">
                            <div class="ql-legend-item">
                                <span class="ql-legend-line" style="background: #e74c3c; height: 3px; width: 20px; display: inline-block;"></span>
                                <span>Coletivo (Trilha 01)</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-line" style="background: #3498db; height: 2px; width: 20px; display: inline-block;"></span>
                                <span>Projeto/Trilha</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-line" style="background: #27ae60; height: 2px; width: 20px; display: inline-block;"></span>
                                <span>Território</span>
                            </div>
                            <div class="ql-legend-item">
                                <span class="ql-legend-line" style="background: #f39c12; height: 2px; width: 20px; display: inline-block; border-top: 2px dashed #f39c12;"></span>
                                <span>Gestão</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ql-instances-admin {
    max-width: 1200px;
    margin: 20px 0;
}

.ql-hierarchy-diagram {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
}

.ql-hierarchy-level {
    width: 100%;
    max-width: 300px;
}

.ql-instance-type-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ql-type-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 10px;
}

.ql-type-header h3 {
    margin: 0;
    font-size: 18px;
}

.ql-level-badge {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}

.ql-hierarchy-arrow {
    font-size: 24px;
    color: #666;
    margin: 5px 0;
}

.ql-count {
    background: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    font-weight: bold;
    margin-top: 10px;
}

.ql-instance-creation-info {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ql-creation-restrictions {
    margin-bottom: 20px;
}

.ql-moodle-sync-status {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.ql-allowed-creation {
    border-top: 1px solid #ddd;
    padding-top: 20px;
    margin-top: 20px;
}

.ql-form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.ql-form-col {
    flex: 1;
}

.ql-form-col-full {
    width: 100%;
}

.ql-form-col label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
}

.ql-form-col input,
.ql-form-col select,
.ql-form-col textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ql-type-fields {
    display: none;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.ql-type-fields.active {
    display: block;
}

.ql-type-fields h3 {
    margin: 0 0 15px 0;
    color: #495057;
}

.ql-instances-tree {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ql-instance-item {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    margin: 10px 0;
    overflow: hidden;
}

.ql-instance-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px;
    background: #f8f9fa;
    cursor: pointer;
}

.ql-instance-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ql-instance-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.ql-instance-status.active { background: #28a745; }
.ql-instance-status.initial { background: #ffc107; color: #000; }
.ql-instance-status.forming { background: #17a2b8; }
.ql-instance-status.complete { background: #007bff; }

.ql-instance-members {
    padding: 15px;
    border-top: 1px solid #e9ecef;
    background: #fff;
}

.ql-member-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.ql-member-badge {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
}

.ql-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.ql-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.ql-stat-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 15px;
}

.ql-stat-header h3 {
    margin: 0;
    font-size: 16px;
}

.ql-stat-main {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.ql-stat-sub {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}

.ql-stat-details {
    color: #999;
    font-size: 12px;
    margin-top: 10px;
}

.ql-no-instances {
    text-align: center;
    padding: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    color: #6c757d;
}

/* Estilos para Criação de Comunidades */
.ql-community-creation {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ql-community-creation h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    margin-bottom: 15px;
}

.ql-nucleos-for-communities {
    display: grid;
    gap: 15px;
    margin-top: 20px;
}

.ql-nucleo-community-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-left: 4px solid #9b59b6;
    border-radius: 6px;
    padding: 15px;
    transition: box-shadow 0.2s ease;
}

.ql-nucleo-community-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.ql-nucleo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ql-nucleo-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.ql-nucleo-info strong {
    color: #333;
}

.ql-nucleo-status {
    color: #666;
    font-size: 14px;
    font-style: italic;
}

.ql-nucleo-details {
    margin-top: 15px;
}

.ql-nucleo-details p {
    margin: 8px 0;
    color: #555;
    font-size: 14px;
}

.ql-territory-defined {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    padding: 10px;
    margin-top: 10px;
}

.ql-define-territory-btn,
.ql-create-community-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    padding: 6px 12px;
    height: auto;
}

.ql-create-community-btn {
    background: #27ae60;
    border-color: #229954;
    color: white;
}

.ql-create-community-btn:hover {
    background: #229954;
    border-color: #1e7e34;
}

/* Estilos para Controle de Governança */
.ql-governance-control {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ql-governance-control h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    margin-bottom: 20px;
}

.ql-current-status {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.ql-mode-indicator {
    flex: 1;
    min-width: 300px;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #ccc;
}

.ql-mode-indicator.mode-assembly {
    background: #fff3cd;
    border-left-color: #f39c12;
    color: #856404;
}

.ql-mode-indicator.mode-decentralized {
    background: #d4edda;
    border-left-color: #27ae60;
    color: #155724;
}

.ql-mode-indicator.mode-inactive {
    background: #f8f9fa;
    border-left-color: #95a5a6;
    color: #6c757d;
}

.ql-mode-indicator h3 {
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 18px;
}

.ql-status-details p {
    margin: 5px 0;
    font-size: 14px;
}

.ql-mode-controls {
    flex: 1;
    min-width: 300px;
    padding: 20px;
}

.ql-visualization-controls {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.ql-visualization-controls h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 14px;
}

.ql-control-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.ql-control-buttons .button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    font-size: 13px;
    justify-content: flex-start;
    width: 100%;
}

.ql-control-buttons .button:hover {
    transform: translateX(2px);
    transition: transform 0.2s ease;
}

.ql-control-buttons .ql-show-mode-inactive:hover {
    background: #95a5a6;
    color: white;
    border-color: #7f8c8d;
}

.ql-control-buttons .ql-show-mode-decentralized:hover {
    background: #27ae60;
    color: white;
    border-color: #229954;
}

.ql-control-buttons .ql-show-mode-assembly:hover {
    background: #f39c12;
    color: white;
    border-color: #e67e22;
}

.ql-control-buttons .button.active {
    background: #0073aa;
    color: white;
    border-color: #006799;
    font-weight: bold;
}

.ql-control-buttons .button.active:hover {
    background: #005a87;
    border-color: #004a74;
    transform: none;
}

.ql-mode-activation {
    margin-top: 20px;
}

.ql-activate-assembly-mode {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    background: #f39c12;
    border-color: #e67e22;
    color: white;
    margin-bottom: 20px;
}

.ql-activate-assembly-mode:hover {
    background: #e67e22;
    border-color: #d35400;
}

.ql-mode-requirements h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.ql-mode-requirements ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ql-mode-requirements li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 13px;
}

.ql-mode-requirements li.completed {
    color: #155724;
}

.ql-mode-requirements li.pending {
    color: #856404;
}

.ql-governance-visualization {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 20px;
}

.ql-governance-visualization h3 {
    margin: 0 0 10px 0;
    color: #333;
}

#ql-governance-graph {
    width: 100%;
    height: 400px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #ffffff;
    margin: 15px 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ql-graph-placeholder {
    text-align: center;
    color: #666;
    font-size: 16px;
}

.ql-graph-legend {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    margin-top: 15px;
}

.ql-graph-legend h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 14px;
}

.ql-legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.ql-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}

.ql-legend-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 1px solid #ccc;
}

/* Modal para definição de território */
.ql-territory-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

.ql-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.ql-modal-content {
    background: white;
    border-radius: 8px;
    padding: 25px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.ql-modal-content h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 20px;
}

.ql-modal-content p {
    color: #666;
    margin: 0 0 20px 0;
    line-height: 1.5;
}

.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    font-weight: bold;
    margin-bottom: 8px;
    color: #333;
}

.form-field input,
.form-field textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-field small {
    display: block;
    color: #666;
    font-size: 12px;
    margin-top: 5px;
    font-style: italic;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    border-top: 1px solid #eee;
    padding-top: 20px;
}

/* Estilos para Visualização de Governança */
.ql-governance-visualization {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.ql-governance-visualization h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333;
    margin-bottom: 15px;
}

.ql-governance-visualization .description {
    color: #666;
    font-style: italic;
    margin-bottom: 20px;
}

#ql-governance-graph {
    width: 100%;
    height: 500px;
    border: 1px solid #ddd;
    border-radius: 6px;
    background: #ffffff;
    margin-bottom: 20px;
}

.ql-governance-modes-info {
    margin-top: 20px;
}

.ql-modes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.ql-mode-info-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.ql-mode-info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.ql-mode-info-card h4 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px 0;
    font-size: 16px;
}

.ql-mode-info-card.mode-assembly h4 {
    color: #f39c12;
}

.ql-mode-info-card.mode-assembly {
    border-left: 4px solid #f39c12;
}

.ql-mode-info-card.mode-decentralized h4 {
    color: #27ae60;
}

.ql-mode-info-card.mode-decentralized {
    border-left: 4px solid #27ae60;
}

.ql-mode-info-card.mode-inactive h4 {
    color: #95a5a6;
}

.ql-mode-info-card.mode-inactive {
    border-left: 4px solid #95a5a6;
}

.ql-mode-info-card p {
    color: #666;
    font-size: 14px;
    margin: 0 0 10px 0;
}

.ql-mode-info-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ql-mode-info-card li {
    padding: 3px 0;
    padding-left: 15px;
    position: relative;
    font-size: 13px;
    color: #555;
}

.ql-mode-info-card li::before {
    content: "•";
    position: absolute;
    left: 0;
    color: currentColor;
    font-weight: bold;
}

@media (max-width: 768px) {
    .ql-form-row {
        flex-direction: column;
        gap: 10px;
    }
    
    .ql-hierarchy-diagram {
        padding: 0 10px;
    }
    
    .ql-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Nonce para AJAX
const qlInstancesNonce = '<?php echo wp_create_nonce("ql_admin_nonce"); ?>';

// Dados das instâncias para o JavaScript usar
const qlInstancesData = <?php echo json_encode(array_values($hierarchy)); ?>;
const qlInstanceTypes = <?php echo json_encode($instance_types); ?>;
const qlCurrentMode = '<?php echo $current_mode; ?>';
const qlColetivoInfo = {
    instances: <?php echo json_encode(array_values($coletivo_instances)); ?>,
    circulos_coletivo: <?php echo json_encode(array_values($circulos_coletivo)); ?>,
    total_nucleos: <?php echo $nucleos_ativos; ?>,
    total_circulos: <?php echo $circulos_ativos; ?>,
    responsibilities_assigned: <?php echo $responsibilities_assigned ? 'true' : 'false'; ?>
};

function updateInstanceForm() {
    const typeSelect = document.getElementById('instance_type');
    const selectedType = typeSelect.value;
    const typeFields = document.getElementById('type-specific-fields');
    const allFields = typeFields.querySelectorAll('.ql-type-fields');
    
    // Ocultar todos os campos específicos
    allFields.forEach(field => field.classList.remove('active'));
    
    if (selectedType) {
        // Mostrar campos específicos para o tipo selecionado
        const specificField = typeFields.querySelector(`[data-type="${selectedType}"]`);
        if (specificField) {
            specificField.classList.add('active');
        }
        typeFields.style.display = 'block';
    } else {
        typeFields.style.display = 'none';
    }
}

// Toggle de exibição de membros das instâncias
document.addEventListener('DOMContentLoaded', function() {
    const instanceHeaders = document.querySelectorAll('.ql-instance-header');
    
    instanceHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const membersDiv = this.nextElementSibling;
            if (membersDiv && membersDiv.classList.contains('ql-instance-members')) {
                membersDiv.style.display = membersDiv.style.display === 'none' ? 'block' : 'none';
            }
        });
    });
    
    // Sincronização com Moodle
    const syncButton = document.getElementById('ql-sync-moodle-instances');
    if (syncButton) {
        syncButton.addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<span class="dashicons dashicons-update spin"></span> Sincronizando...';
            
            // AJAX call para sincronizar
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'ql_sync_instances_from_moodle',
                    nonce: qlInstancesNonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // Recarregar para mostrar novas instâncias
                } else {
                    alert('Erro na sincronização: ' + (data.data.message || 'Erro desconhecido'));
                    this.disabled = false;
                    this.innerHTML = '<span class="dashicons dashicons-update"></span> Sincronizar Grupos/Agrupamentos do Moodle';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro na sincronização');
                this.disabled = false;
                this.innerHTML = '<span class="dashicons dashicons-update"></span> Sincronizar Grupos/Agrupamentos do Moodle';
            });
        });
    }
    
    // Gestão de territórios e comunidades
    const defineTerritoryButtons = document.querySelectorAll('.ql-define-territory-btn');
    const createCommunityButtons = document.querySelectorAll('.ql-create-community-btn');
    
    defineTerritoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nucleoId = this.dataset.nucleoId;
            
            // Criar modal para definir território
            const modal = document.createElement('div');
            modal.className = 'ql-territory-modal';
            modal.innerHTML = `
                <div class="ql-modal-overlay">
                    <div class="ql-modal-content">
                        <h3>Definir Abrangência Territorial</h3>
                        <p>Defina a área geográfica de abrangência para este núcleo para criar uma comunidade.</p>
                        
                        <form id="territory-form">
                            <div class="form-field">
                                <label for="territory-scope">Descrição do Território:</label>
                                <textarea id="territory-scope" name="territory_scope" rows="4" 
                                          placeholder="Ex: Bairro Vila Madalena, São Paulo, SP - Raio de 2km a partir da sede do núcleo"
                                          required></textarea>
                                <small>Descreva a área geográfica que a comunidade irá abranger.</small>
                            </div>
                            
                            <div class="form-field">
                                <label for="community-name">Nome da Comunidade (opcional):</label>
                                <input type="text" id="community-name" name="community_name" 
                                       placeholder="Ex: Comunidade Vila Madalena">
                                <small>Se não especificado, será gerado automaticamente.</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="button button-primary">Definir Território</button>
                                <button type="button" class="button button-secondary cancel-btn">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Event listeners do modal
            modal.querySelector('.cancel-btn').addEventListener('click', () => {
                modal.remove();
            });
            
            modal.querySelector('.ql-modal-overlay').addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    modal.remove();
                }
            });
            
            modal.querySelector('#territory-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('action', 'ql_define_nucleo_territory');
                formData.append('nucleo_id', nucleoId);
                formData.append('territory_scope', document.getElementById('territory-scope').value);
                formData.append('community_name', document.getElementById('community-name').value);
                formData.append('nonce', qlInstancesNonce);
                
                button.disabled = true;
                button.innerHTML = '<span class="dashicons dashicons-update spin"></span> Definindo...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Território definido com sucesso!');
                        location.reload();
                    } else {
                        alert('Erro: ' + (data.data.message || 'Erro desconhecido'));
                        button.disabled = false;
                        button.innerHTML = '<span class="dashicons dashicons-admin-site"></span> Definir Território';
                    }
                    modal.remove();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao definir território');
                    button.disabled = false;
                    button.innerHTML = '<span class="dashicons dashicons-admin-site"></span> Definir Território';
                    modal.remove();
                });
            });
        });
    });
    
    createCommunityButtons.forEach(button => {
        button.addEventListener('click', function() {
            const nucleoId = this.dataset.nucleoId;
            
            if (!confirm('Confirma a criação de uma comunidade a partir deste núcleo?')) {
                return;
            }
            
            button.disabled = true;
            button.innerHTML = '<span class="dashicons dashicons-update spin"></span> Criando...';
            
            const formData = new FormData();
            formData.append('action', 'ql_create_community_from_nucleo');
            formData.append('nucleo_id', nucleoId);
            formData.append('nonce', qlInstancesNonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Comunidade criada com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (data.data.message || 'Erro desconhecido'));
                    button.disabled = false;
                    button.innerHTML = '<span class="dashicons dashicons-plus"></span> Criar Comunidade';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao criar comunidade');
                button.disabled = false;
                button.innerHTML = '<span class="dashicons dashicons-plus"></span> Criar Comunidade';
            });
        });
    });
    
    // Inicializar visualização do grafo de governança
    initializeGovernanceGraph();
    
    // Botões de visualização de modos
    const visualizationButtons = document.querySelectorAll('[data-mode]');
    
    // Marcar botão atual como ativo
    const currentModeButton = document.querySelector(`[data-mode="${qlCurrentMode}"]`);
    if (currentModeButton) {
        currentModeButton.classList.add('active');
    }
    
    visualizationButtons.forEach(button => {
        button.addEventListener('click', function() {
            const mode = this.dataset.mode;
            
            // Atualizar estado visual dos botões
            visualizationButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Buscar dados reais e renderizar grafo
            fetchRealDataAndRenderGraph(mode);
        });
    });
    
    // Ativação do Modo Assembleia
    const assemblyModeButton = document.querySelector('.ql-activate-assembly-mode');
    if (assemblyModeButton) {
        assemblyModeButton.addEventListener('click', function() {
            const assemblyId = this.dataset.assemblyId;
            
            if (!confirm('Confirma a ativação do Modo Assembleia? Todas as prerrogativas serão transferidas para a assembleia.')) {
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="dashicons dashicons-update spin"></span> Ativando Modo Assembleia...';
            
            const formData = new FormData();
            formData.append('action', 'ql_activate_assembly_mode');
            formData.append('assembly_id', assemblyId);
            formData.append('nonce', qlInstancesNonce);
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Modo Assembleia ativado com sucesso! Todas as prerrogativas foram transferidas para a assembleia.');
                    location.reload();
                } else {
                    alert('Erro ao ativar Modo Assembleia: ' + (data.data.message || 'Erro desconhecido'));
                    this.disabled = false;
                    this.innerHTML = '<span class="dashicons dashicons-megaphone"></span> Ativar Modo Assembleia';
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao ativar Modo Assembleia');
                this.disabled = false;
                this.innerHTML = '<span class="dashicons dashicons-megaphone"></span> Ativar Modo Assembleia';
            });
        });
    }
});

// Função para atualizar formulário de criação de comunidade
function updateCommunityForm() {
    const nucleoSelect = document.getElementById('source_nucleo_id');
    const selectedOption = nucleoSelect.options[nucleoSelect.selectedIndex];
    const nucleoInfo = document.getElementById('nucleo-info');
    const nucleoDetails = document.getElementById('nucleo-details');
    const communityName = document.getElementById('community_name');
    const territoryScope = document.getElementById('territory_scope');
    
    if (selectedOption.value) {
        const nucleoName = selectedOption.dataset.name;
        const nucleoAddress = selectedOption.dataset.address;
        
        // Auto-preencher nome da comunidade
        if (!communityName.value) {
            communityName.value = 'Comunidade ' + nucleoName;
        }
        
        // Mostrar informações do núcleo
        let detailsHTML = '<p><strong>Nome:</strong> ' + nucleoName + '</p>';
        if (nucleoAddress) {
            detailsHTML += '<p><strong>Endereço:</strong> ' + nucleoAddress + '</p>';
        }
        detailsHTML += '<p><strong>Membros:</strong> ' + selectedOption.text.match(/\((\d+) membros\)/)[1] + '</p>';
        
        nucleoDetails.innerHTML = detailsHTML;
        nucleoInfo.style.display = 'block';
        
        // Sugerir abrangência territorial baseada no endereço
        if (!territoryScope.value && nucleoAddress) {
            territoryScope.value = 'Região próxima ao endereço: ' + nucleoAddress + ' - Raio de atuação a ser definido pela comunidade';
        }
    } else {
        nucleoInfo.style.display = 'none';
        communityName.value = '';
        territoryScope.value = '';
    }
}

// Função para resetar formulário de comunidade
function resetCommunityForm() {
    document.getElementById('community-creation-form').reset();
    document.getElementById('nucleo-info').style.display = 'none';
}

// Função para inicializar o grafo de governança
function initializeGovernanceGraph() {
    const graphContainer = document.getElementById('ql-governance-graph');
    if (!graphContainer) return;
    
    const currentMode = graphContainer.dataset.currentMode;
    
    // Buscar dados reais e renderizar
    fetchRealDataAndRenderGraph(currentMode);
}

// Função para buscar dados reais do banco e renderizar grafo
function fetchRealDataAndRenderGraph(mode) {
    const graphContainer = document.getElementById('ql-governance-graph');
    if (!graphContainer) return;
    
    // Mostrar loading
    graphContainer.innerHTML = '<div class="ql-graph-loading" style="display: flex; align-items: center; justify-content: center; height: 100%; color: #666;"><span class="dashicons dashicons-update spin" style="font-size: 32px; margin-right: 10px;"></span>Carregando dados organizacionais...</div>';
    
    // Buscar dados reais via AJAX
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'ql_get_governance_graph_data',
            mode: mode,
            nonce: qlInstancesNonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Usar dados reais do servidor
            renderGovernanceGraph(graphContainer, data.data.graph_data, mode);
        } else {
            // Fallback para dados simulados se houver erro
            console.warn('Erro ao buscar dados reais, usando dados simulados:', data.data?.message);
            const fallbackData = generateGraphData(mode);
            renderGovernanceGraph(graphContainer, fallbackData, mode);
        }
    })
    .catch(error => {
        console.error('Erro ao buscar dados do grafo:', error);
        // Fallback para dados simulados
        const fallbackData = generateGraphData(mode);
        renderGovernanceGraph(graphContainer, fallbackData, mode);
    });
}

// Função para gerar dados do grafo baseado no modo atual (fallback com dados simulados)
function generateGraphData(mode) {
    // Usar dados reais se disponíveis no PHP
    const realInstances = typeof qlInstancesData !== 'undefined' ? qlInstancesData : null;
    const coletivoInfo = typeof qlColetivoInfo !== 'undefined' ? qlColetivoInfo : null;
    
    let nodes = [];
    
    if (realInstances && realInstances.length > 0) {
        // Usar dados reais das instâncias
        nodes = realInstances.map(instance => ({
            id: instance.id,
            label: instance.name,
            group: instance.type,
            level: getInstanceLevel(instance.type),
            members: instance.member_count || 0,
            status: instance.status,
            trilha_id: instance.trilha_id || null,
            is_coletivo_circle: instance.type === 'circulo' && instance.trilha_id === 1
        }));
        
        // Adicionar coletivo se identificado via trilha 01
        if (coletivoInfo && coletivoInfo.instances.length === 0 && coletivoInfo.circulos_coletivo.length > 0) {
            // Criar nó do coletivo baseado nos círculos da trilha 01
            const coletivoNode = {
                id: 'coletivo_qc',
                label: 'Quilombo Ciência',
                group: 'coletivo',
                level: 4,
                members: coletivoInfo.circulos_coletivo.reduce((total, c) => total + (c.member_count || 0), 0),
                status: 'active',
                trilha_id: 1
            };
            nodes.push(coletivoNode);
        }
    } else {
        // Dados básicos simulados como fallback - incluindo o "Teste coletivo"
        nodes = [
            { id: 1, label: 'Círculo Arte', group: 'circulo', level: 1, members: 8, status: 'active', trilha_id: 2 },
            { id: 2, label: 'Círculo Ciência', group: 'circulo', level: 1, members: 12, status: 'active', trilha_id: 1, is_coletivo_circle: true },
            { id: 3, label: 'Círculo Educação', group: 'circulo', level: 1, members: 6, status: 'active', trilha_id: 3 },
            { id: 4, label: 'Núcleo Central', group: 'nucleo', level: 2, members: 25, status: 'active' },
            { id: 5, label: 'Comunidade Vila Madalena', group: 'comunidade', level: 3, members: 45, status: 'active' },
            { id: 6, label: 'Teste coletivo', group: 'coletivo', level: 4, members: 50, status: 'active', trilha_id: 1 }
        ];
    }

// Função auxiliar para determinar nível da instância
function getInstanceLevel(type) {
    const levels = {
        'circulo': 1,
        'nucleo': 2, 
        'comunidade': 3,
        'coletivo': 4,
        'assembleia': 5
    };
    return levels[type] || 1;
}
    
    let edges = [];
    
    // Gerar conexões baseadas no modo e nos dados reais
    switch (mode) {
        case 'assembly':
            // No modo assembleia, todos convergem para uma assembleia central
            const assembleiaNodes = nodes.filter(n => n.group === 'assembleia');
            if (assembleiaNodes.length === 0) {
                // Criar assembleia simulada se não existir
                const assemblyNode = { 
                    id: Math.max(...nodes.map(n => n.id)) + 1, 
                    label: 'Assembleia Central', 
                    group: 'assembleia', 
                    level: 5,
                    members: nodes.reduce((total, n) => total + (n.members || 0), 0),
                    status: 'active'
                };
                nodes.push(assemblyNode);
            }
            
            // Conectar todas as outras instâncias à assembleia
            const assemblyId = assembleiaNodes.length > 0 ? assembleiaNodes[0].id : nodes[nodes.length - 1].id;
            nodes.forEach(node => {
                if (node.group !== 'assembleia') {
                    edges.push({ from: node.id, to: assemblyId, arrows: 'to' });
                }
            });
            break;
            
        case 'decentralized':
            // No modo descentralizado, conectar baseado no modelo organizativo
            const circulos = nodes.filter(n => n.group === 'circulo');
            const nucleos = nodes.filter(n => n.group === 'nucleo');
            const comunidades = nodes.filter(n => n.group === 'comunidade');
            const coletivos = nodes.filter(n => n.group === 'coletivo');
            const circulosColetivo = nodes.filter(n => n.is_coletivo_circle);
            
            // Conectar círculos do coletivo (trilha 01) ao coletivo
            if (coletivos.length > 0) {
                circulosColetivo.forEach(circulo => {
                    edges.push({ from: circulo.id, to: coletivos[0].id, style: 'coletivo' });
                });
            }
            
            // Conectar círculos de projetos (outras trilhas) aos núcleos
            circulos.filter(c => !c.is_coletivo_circle).forEach(circulo => {
                if (nucleos.length > 0) {
                    // Conectar por proximidade territorial ou por projeto
                    const targetNucleo = nucleos.find(n => n.trilha_id === circulo.trilha_id) || nucleos[0];
                    edges.push({ from: circulo.id, to: targetNucleo.id, style: 'projeto' });
                }
            });
            
            // Conectar núcleos às comunidades
            nucleos.forEach(nucleo => {
                comunidades.forEach(comunidade => {
                    edges.push({ from: nucleo.id, to: comunidade.id, style: 'territorio' });
                });
            });
            
            // Conectar coletivo às comunidades
            if (coletivos.length > 0 && comunidades.length > 0) {
                edges.push({ from: coletivos[0].id, to: comunidades[0].id, style: 'gestao', dashes: true });
            }
            break;
            
        case 'inactive':
        default:
            // No modo inativo, conectar aleatoriamente com linhas tracejadas
            for (let i = 0; i < Math.min(3, nodes.length - 1); i++) {
                const from = nodes[i];
                const to = nodes[(i + 2) % nodes.length];
                edges.push({ from: from.id, to: to.id, dashes: true });
            }
            break;
    }
    
    return { nodes, edges };
}

// Função para renderizar o grafo
function renderGovernanceGraph(container, data, mode) {
    // Limpar container
    container.innerHTML = '';
    
    // Criar elemento SVG simples para visualização
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('width', '100%');
    svg.setAttribute('height', '100%');
    svg.setAttribute('viewBox', '0 0 800 400');
    
    // Cores por grupo
    const colors = {
        circulo: '#3498db',
        nucleo: '#9b59b6',
        comunidade: '#27ae60',
        assembleia: '#f39c12'
    };
    
    // Posições dos nós baseadas no layout
    const positions = calculateNodePositions(data.nodes, mode);
    
    // Desenhar conexões (edges) primeiro
    data.edges.forEach(edge => {
        const fromPos = positions[edge.from];
        const toPos = positions[edge.to];
        
        if (fromPos && toPos) {
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', fromPos.x);
            line.setAttribute('y1', fromPos.y);
            line.setAttribute('x2', toPos.x);
            line.setAttribute('y2', toPos.y);
            
            // Definir cor e estilo baseado no tipo de conexão
            let strokeColor = '#34495e';
            let strokeWidth = '2';
            
            switch (edge.style) {
                case 'coletivo':
                    strokeColor = '#e74c3c'; // Vermelho para vínculos do coletivo
                    strokeWidth = '3';
                    break;
                case 'projeto':
                    strokeColor = '#3498db'; // Azul para vínculos de projeto/trilha
                    strokeWidth = '2';
                    break;
                case 'territorio':
                    strokeColor = '#27ae60'; // Verde para vínculos territoriais
                    strokeWidth = '2';
                    break;
                case 'gestao':
                    strokeColor = '#f39c12'; // Laranja para vínculos de gestão
                    strokeWidth = '2';
                    break;
                default:
                    strokeColor = edge.dashes ? '#bdc3c7' : '#34495e';
                    break;
            }
            
            line.setAttribute('stroke', strokeColor);
            line.setAttribute('stroke-width', strokeWidth);
            
            if (edge.dashes) {
                line.setAttribute('stroke-dasharray', '5,5');
            }
            svg.appendChild(line);
            
            // Adicionar seta se especificada
            if (edge.arrows === 'to') {
                const arrow = createArrow(fromPos, toPos, strokeColor);
                svg.appendChild(arrow);
            }
        }
    });
    
    // Desenhar nós
    data.nodes.forEach(node => {
        const pos = positions[node.id];
        if (!pos) return;
        
        // Círculo do nó
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', pos.x);
        circle.setAttribute('cy', pos.y);
        circle.setAttribute('r', node.group === 'assembleia' ? 25 : 20);
        circle.setAttribute('fill', colors[node.group] || '#95a5a6');
        circle.setAttribute('stroke', '#ffffff');
        circle.setAttribute('stroke-width', '3');
        svg.appendChild(circle);
        
        // Label do nó
        const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        text.setAttribute('x', pos.x);
        text.setAttribute('y', pos.y + 35);
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('font-family', 'Arial, sans-serif');
        text.setAttribute('font-size', '12');
        text.setAttribute('fill', '#2c3e50');
        text.setAttribute('font-weight', 'bold');
        text.textContent = node.label;
        svg.appendChild(text);
        
        // Mostrar número de membros se disponível
        if (node.members !== undefined) {
            const membersText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            membersText.setAttribute('x', pos.x);
            membersText.setAttribute('y', pos.y + 50);
            membersText.setAttribute('text-anchor', 'middle');
            membersText.setAttribute('font-family', 'Arial, sans-serif');
            membersText.setAttribute('font-size', '10');
            membersText.setAttribute('fill', '#7f8c8d');
            membersText.textContent = `${node.members} membros`;
            svg.appendChild(membersText);
        }
    });
    
    container.appendChild(svg);
    
    // Adicionar informações do modo
    const modeInfo = document.createElement('div');
    modeInfo.className = 'ql-graph-mode-info';
    modeInfo.innerHTML = getModeDescription(mode);
    container.appendChild(modeInfo);
}

// Função para calcular posições dos nós
function calculateNodePositions(nodes, mode) {
    const positions = {};
    
    // Separar nós por tipo
    const circulos = nodes.filter(n => n.group === 'circulo');
    const nucleos = nodes.filter(n => n.group === 'nucleo');
    const comunidades = nodes.filter(n => n.group === 'comunidade');
    const coletivos = nodes.filter(n => n.group === 'coletivo');
    const assembleias = nodes.filter(n => n.group === 'assembleia');
    
    switch (mode) {
        case 'assembly':
            // Assembleia centralizada
            assembleias.forEach(assembly => {
                positions[assembly.id] = { x: 400, y: 200 }; // Centro
            });
            
            // Posicionar outras instâncias ao redor da assembleia
            const otherNodes = nodes.filter(n => n.group !== 'assembleia');
            otherNodes.forEach((node, index) => {
                const angle = (index * 2 * Math.PI) / otherNodes.length;
                positions[node.id] = {
                    x: 400 + 150 * Math.cos(angle),
                    y: 200 + 100 * Math.sin(angle)
                };
            });
            break;
            
        case 'decentralized':
            // Layout hierárquico organizado por níveis
            
            // Círculos no topo (nível 1)
            const circleSpacing = Math.min(150, 600 / Math.max(1, circulos.length));
            const circleStartX = 400 - (circulos.length - 1) * circleSpacing / 2;
            circulos.forEach((circulo, index) => {
                positions[circulo.id] = { 
                    x: circleStartX + index * circleSpacing, 
                    y: 80 
                };
            });
            
            // Núcleos no meio (nível 2)
            const nucleoSpacing = Math.min(200, 600 / Math.max(1, nucleos.length));
            const nucleoStartX = 400 - (nucleos.length - 1) * nucleoSpacing / 2;
            nucleos.forEach((nucleo, index) => {
                positions[nucleo.id] = { 
                    x: nucleoStartX + index * nucleoSpacing, 
                    y: 200 
                };
            });
            
            // Comunidades abaixo (nível 3)
            const comunidadeSpacing = Math.min(180, 600 / Math.max(1, comunidades.length));
            const comunidadeStartX = 400 - (comunidades.length - 1) * comunidadeSpacing / 2;
            comunidades.forEach((comunidade, index) => {
                positions[comunidade.id] = { 
                    x: comunidadeStartX + index * comunidadeSpacing, 
                    y: 320 
                };
            });
            
            // Coletivos no fundo (nível 4)
            coletivos.forEach((coletivo, index) => {
                positions[coletivo.id] = { 
                    x: 300 + index * 200, 
                    y: 380 
                };
            });
            break;
            
        case 'inactive':
        default:
            // Distribuição semi-aleatória com alguma estrutura
            nodes.forEach((node, index) => {
                const cols = Math.ceil(Math.sqrt(nodes.length));
                const row = Math.floor(index / cols);
                const col = index % cols;
                
                positions[node.id] = {
                    x: 150 + col * (500 / cols) + (Math.random() - 0.5) * 80,
                    y: 100 + row * (200 / Math.ceil(nodes.length / cols)) + (Math.random() - 0.5) * 60
                };
            });
            break;
    }
    
    return positions;
}

// Função para criar seta SVG
function createArrow(from, to, color = '#34495e') {
    const angle = Math.atan2(to.y - from.y, to.x - from.x);
    const arrowLength = 15;
    const arrowAngle = Math.PI / 6;
    
    const arrowX = to.x - 20 * Math.cos(angle);
    const arrowY = to.y - 20 * Math.sin(angle);
    
    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    const point1 = `${arrowX},${arrowY}`;
    const point2 = `${arrowX - arrowLength * Math.cos(angle - arrowAngle)},${arrowY - arrowLength * Math.sin(angle - arrowAngle)}`;
    const point3 = `${arrowX - arrowLength * Math.cos(angle + arrowAngle)},${arrowY - arrowLength * Math.sin(angle + arrowAngle)}`;
    
    polygon.setAttribute('points', `${point1} ${point2} ${point3}`);
    polygon.setAttribute('fill', color);
    
    return polygon;
}

// Função para obter descrição do modo
function getModeDescription(mode) {
    switch (mode) {
        case 'assembly':
            return '<div style="background: #fff3cd; padding: 10px; border-radius: 4px; margin-top: 10px;"><strong>🗣️ Modo Assembleia Ativo:</strong> Todas as prerrogativas estão centralizadas na assembleia.</div>';
        case 'decentralized':
            return '<div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px;"><strong>🕸️ Modo Descentralizado:</strong> Organização distribuída por proximidade e responsabilidades.</div>';
        case 'inactive':
        default:
            return '<div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px;"><strong>😴 Coletivo Inativo:</strong> Participantes distribuídos aleatoriamente, sem coordenação ativa.</div>';
    }
}
</script>

<?php
/**
 * Helper function para renderizar árvore de instâncias
 */
function render_instances_tree($hierarchy, $instances_system) {
    $output = '';
    
    foreach ($hierarchy as $instance) {
        $members = $instances_system->get_instance_members($instance->id);
        $type_config = QL_Instances::get_instance_types()[$instance->type];
        
        $output .= '<div class="ql-instance-item">';
        $output .= '<div class="ql-instance-header" style="border-left: 4px solid ' . $type_config['color'] . '">';
        $output .= '<div class="ql-instance-info">';
        $output .= '<span class="dashicons ' . $type_config['icon'] . '"></span>';
        $output .= '<strong>' . esc_html($instance->name) . '</strong>';
        $output .= '<span class="ql-instance-type">(' . $type_config['label'] . ')</span>';
        $output .= '<span class="ql-instance-status ' . $instance->status . '">' . ucfirst($instance->status) . '</span>';
        $output .= '</div>';
        $output .= '<div class="ql-member-count">' . count($members) . ' membros</div>';
        $output .= '</div>';
        
        if (!empty($members)) {
            $output .= '<div class="ql-instance-members" style="display: none;">';
            $output .= '<strong>Membros:</strong>';
            $output .= '<div class="ql-member-list">';
            foreach ($members as $member) {
                $output .= '<span class="ql-member-badge">';
                $output .= esc_html($member->display_name);
                if ($member->role !== 'member') {
                    $output .= ' (' . $member->role . ')';
                }
                $output .= '</span>';
            }
            $output .= '</div>';
            $output .= '</div>';
        }
        
        // Renderizar instâncias filhas recursivamente
        if (!empty($instance->children)) {
            $output .= '<div class="ql-instance-children" style="margin-left: 20px;">';
            $output .= render_instances_tree($instance->children, $instances_system);
            $output .= '</div>';
        }
        
        $output .= '</div>';
    }
    
    return $output;
}
?>