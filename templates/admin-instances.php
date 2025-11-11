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

// Processar formulário de criação
if (isset($_POST['create_instance']) && wp_verify_nonce($_POST['_wpnonce'], 'ql_create_instance')) {
    $type = sanitize_text_field($_POST['instance_type']);
    $name = sanitize_text_field($_POST['instance_name']);
    
    $options = [
        'description' => sanitize_textarea_field($_POST['description']),
        'parent_id' => !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
        'physical_address' => sanitize_textarea_field($_POST['physical_address']),
        'virtual_page_url' => esc_url($_POST['virtual_page_url'])
    ];
    
    $result = $instances_system->create_instance($type, $name, get_current_user_id(), $options);
    
    if (is_wp_error($result)) {
        echo '<div class="notice notice-error"><p>Erro: ' . $result->get_error_message() . '</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>Instância criada com sucesso!</p></div>';
        // Recarregar hierarquia
        $hierarchy = $instances_system->get_instances_hierarchy();
    }
}
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-networking" style="color: #e74c3c;"></span>
        Instâncias Organizacionais
    </h1>
    
    <div class="ql-instances-admin">
        
        <!-- Resumo dos Tipos de Instância -->
        <div class="ql-instance-types-overview">
            <h2>Modelo Organizativo - Hierarquia de Instâncias</h2>
            
            <div class="ql-hierarchy-diagram">
                <div class="ql-hierarchy-level">
                    <div class="ql-instance-type-card" style="border-left: 4px solid <?php echo $instance_types['circulo']['color']; ?>">
                        <div class="ql-type-header">
                            <span class="dashicons <?php echo $instance_types['circulo']['icon']; ?>"></span>
                            <h3>Círculos</h3>
                            <span class="ql-level-badge">Nível 1</span>
                        </div>
                        <p>3-6 pessoas • Instância mínima para projetos</p>
                        <div class="ql-count">
                            <?php 
                            $circle_count = count(array_filter($hierarchy, function($i) { return $i->type === 'circulo'; }));
                            echo $circle_count . ' ativo' . ($circle_count !== 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="ql-hierarchy-arrow">↓</div>
                
                <div class="ql-hierarchy-level">
                    <div class="ql-instance-type-card" style="border-left: 4px solid <?php echo $instance_types['nucleo']['color']; ?>">
                        <div class="ql-type-header">
                            <span class="dashicons <?php echo $instance_types['nucleo']['icon']; ?>"></span>
                            <h3>Núcleos</h3>
                            <span class="ql-level-badge">Nível 2</span>
                        </div>
                        <p>3+ círculos • Território compartilhado</p>
                        <div class="ql-count">
                            <?php 
                            $nucleo_count = count(array_filter($hierarchy, function($i) { return $i->type === 'nucleo'; }));
                            echo $nucleo_count . ' ativo' . ($nucleo_count !== 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="ql-hierarchy-arrow">↓</div>
                
                <div class="ql-hierarchy-level">
                    <div class="ql-instance-type-card" style="border-left: 4px solid <?php echo $instance_types['comunidade']['color']; ?>">
                        <div class="ql-type-header">
                            <span class="dashicons <?php echo $instance_types['comunidade']['icon']; ?>"></span>
                            <h3>Comunidades</h3>
                            <span class="ql-level-badge">Nível 3</span>
                        </div>
                        <p>Vínculos territoriais mútuos</p>
                        <div class="ql-count">
                            <?php 
                            $comunidade_count = count(array_filter($hierarchy, function($i) { return $i->type === 'comunidade'; }));
                            echo $comunidade_count . ' ativa' . ($comunidade_count !== 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="ql-hierarchy-arrow">↓</div>
                
                <div class="ql-hierarchy-level">
                    <div class="ql-instance-type-card" style="border-left: 4px solid <?php echo $instance_types['coletivo']['color']; ?>">
                        <div class="ql-type-header">
                            <span class="dashicons <?php echo $instance_types['coletivo']['icon']; ?>"></span>
                            <h3>Coletivos</h3>
                            <span class="ql-level-badge">Nível 4</span>
                        </div>
                        <p>Rede de projetos e comunidades</p>
                        <div class="ql-count">
                            <?php 
                            $coletivo_count = count(array_filter($hierarchy, function($i) { return $i->type === 'coletivo'; }));
                            echo $coletivo_count . ' ativo' . ($coletivo_count !== 1 ? 's' : '');
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Criar Nova Instância -->
        <div class="ql-create-instance">
            <h2>Criar Nova Instância</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_create_instance'); ?>
                
                <div class="ql-form-row">
                    <div class="ql-form-col">
                        <label for="instance_type">Tipo de Instância</label>
                        <select name="instance_type" id="instance_type" required onchange="updateInstanceForm()">
                            <option value="">Selecionar tipo...</option>
                            <?php foreach ($instance_types as $type_key => $type_data): ?>
                                <option value="<?php echo $type_key; ?>" 
                                        data-config="<?php echo esc_attr(json_encode($type_data)); ?>">
                                    <?php echo $type_data['label']; ?> - <?php echo $type_data['description']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="ql-form-col">
                        <label for="instance_name">Nome da Instância</label>
                        <input type="text" name="instance_name" id="instance_name" required 
                               placeholder="Ex: Círculo de Desenvolvimento">
                    </div>
                </div>
                
                <div class="ql-form-row">
                    <div class="ql-form-col-full">
                        <label for="description">Descrição</label>
                        <textarea name="description" id="description" rows="3" 
                                  placeholder="Descreva o propósito e objetivos desta instância..."></textarea>
                    </div>
                </div>
                
                <!-- Campos específicos por tipo -->
                <div id="type-specific-fields" style="display: none;">
                    
                    <!-- Campos para Núcleo -->
                    <div class="ql-type-fields" data-type="nucleo">
                        <h3>Configurações do Núcleo</h3>
                        <div class="ql-form-row">
                            <div class="ql-form-col">
                                <label for="physical_address">Endereço Físico</label>
                                <textarea name="physical_address" rows="2" 
                                          placeholder="Endereço completo do espaço físico compartilhado..."></textarea>
                            </div>
                            <div class="ql-form-col">
                                <label for="virtual_page_url">URL da Página Virtual</label>
                                <input type="url" name="virtual_page_url" 
                                       placeholder="https://site.com/pagina-do-nucleo">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos para Comunidade -->
                    <div class="ql-type-fields" data-type="comunidade">
                        <h3>Configurações da Comunidade</h3>
                        <div class="ql-form-row">
                            <div class="ql-form-col-full">
                                <label for="territory_scope">Abrangência Territorial</label>
                                <textarea name="territory_scope" rows="2" 
                                          placeholder="Descreva a área geográfica de abrangência da comunidade..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos para Coletivo -->
                    <div class="ql-type-fields" data-type="coletivo">
                        <h3>Configurações do Coletivo</h3>
                        <div class="ql-form-row">
                            <div class="ql-form-col">
                                <label>
                                    <input type="checkbox" name="requires_founding_assembly" value="1" checked>
                                    Assembleia de fundação obrigatória
                                </label>
                            </div>
                            <div class="ql-form-col">
                                <label>
                                    <input type="checkbox" name="can_span_territories" value="1" checked>
                                    Pode abranger múltiplos territórios
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Campos para Assembleia -->
                    <div class="ql-type-fields" data-type="assembleia">
                        <h3>Configurações da Assembleia</h3>
                        <div class="ql-form-row">
                            <div class="ql-form-col">
                                <label for="assembly_periodicity">Periodicidade</label>
                                <select name="assembly_periodicity">
                                    <option value="monthly">Mensal</option>
                                    <option value="quarterly">Trimestral</option>
                                    <option value="annual">Anual</option>
                                    <option value="extraordinary">Apenas extraordinária</option>
                                </select>
                            </div>
                            <div class="ql-form-col">
                                <label for="minimum_quorum">Quórum Mínimo (%)</label>
                                <input type="number" name="minimum_quorum" min="1" max="100" value="50">
                            </div>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="create_instance" class="button-primary" value="Criar Instância">
                </p>
            </form>
        </div>
        
        <!-- Hierarquia Atual -->
        <div class="ql-current-hierarchy">
            <h2>Hierarquia Organizacional Atual</h2>
            
            <?php if (empty($hierarchy)): ?>
                <div class="ql-no-instances">
                    <p>Ainda não há instâncias organizacionais criadas.</p>
                    <p>Comece criando um <strong>Círculo</strong> para seu primeiro projeto ou um <strong>Coletivo</strong> para organizar múltiplos projetos.</p>
                </div>
            <?php else: ?>
                <div class="ql-instances-tree">
                    <?php echo $this->render_instances_tree($hierarchy, $instances_system); ?>
                </div>
            <?php endif; ?>
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

.ql-create-instance {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
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
</style>

<script>
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
});
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