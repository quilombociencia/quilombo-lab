<?php
/**
 * Template da página administrativa de Papéis Organizativos
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$roles_system = QL_Organizational_Roles::get_instance();
$role_definitions = QL_Organizational_Roles::get_role_definitions();
$all_users = get_users();
?>

<div class="wrap">
    <h1>
        <span class="dashicons dashicons-groups" style="color: #3498db;"></span>
        Papéis Organizativos
    </h1>
    
    <div class="ql-organizational-roles-admin">
        
        <!-- Resumo dos Papéis -->
        <div class="ql-roles-overview">
            <h2>Modelo Organizativo - 6 Papéis Básicos</h2>
            <div class="ql-roles-grid">
                <?php foreach ($role_definitions as $role_key => $role_data): ?>
                    <div class="ql-role-card" style="border-left: 4px solid <?php echo $role_data['color']; ?>">
                        <div class="ql-role-header">
                            <span class="dashicons <?php echo $role_data['icon']; ?>" style="color: <?php echo $role_data['color']; ?>"></span>
                            <h3><?php echo $role_data['label']; ?></h3>
                            <?php if (isset($role_data['hierarchy_level'])): ?>
                                <span class="ql-hierarchy-badge">Nível <?php echo $role_data['hierarchy_level']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="ql-role-description"><?php echo $role_data['description']; ?></p>
                        
                        <div class="ql-role-details">
                            <strong>Tarefas:</strong> <?php echo implode(', ', $role_data['tasks']); ?><br>
                            <strong>Ações:</strong> <?php echo implode(', ', array_slice($role_data['actions'], 0, 4)); ?>
                            <?php if (count($role_data['actions']) > 4): ?>
                                <span class="ql-more-actions">... (+<?php echo count($role_data['actions']) - 4; ?> mais)</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($role_data['requires_admin_permission'])): ?>
                            <div class="ql-admin-required">
                                <span class="dashicons dashicons-lock"></span>
                                Requer permissão administrativa
                            </div>
                        <?php endif; ?>
                        
                        <div class="ql-role-stats">
                            <?php 
                            $users_with_role = $roles_system->get_users_with_role($role_key);
                            $count = count($users_with_role);
                            ?>
                            <strong><?php echo $count; ?></strong> 
                            <?php echo $count == 1 ? 'pessoa com este papel' : 'pessoas com este papel'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Atribuir Papéis -->
        <div class="ql-assign-roles">
            <h2>Atribuir Papel Organizativo</h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ql_assign_role'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_id">Usuário</label>
                        </th>
                        <td>
                            <select name="user_id" id="user_id" required class="regular-text">
                                <option value="">Selecionar usuário...</option>
                                <?php foreach ($all_users as $user): ?>
                                    <option value="<?php echo $user->ID; ?>">
                                        <?php echo $user->display_name; ?> (<?php echo $user->user_email; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="role_key">Papel</label>
                        </th>
                        <td>
                            <select name="role_key" id="role_key" required class="regular-text">
                                <option value="">Selecionar papel...</option>
                                <?php foreach ($role_definitions as $key => $data): ?>
                                    <option value="<?php echo $key; ?>">
                                        <?php echo $data['label']; ?> - <?php echo $data['description']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="context_type">Contexto</label>
                        </th>
                        <td>
                            <select name="context_type" id="context_type" class="regular-text">
                                <option value="global">Global (todo o sistema)</option>
                                <option value="trilha">Trilha específica</option>
                                <option value="projeto">Projeto específico</option>
                                <option value="circulo">Círculo específico</option>
                                <option value="coletivo">Coletivo</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="context_id">ID do Contexto</label>
                        </th>
                        <td>
                            <input type="number" name="context_id" id="context_id" class="regular-text" 
                                   placeholder="Deixar vazio para contexto global">
                            <p class="description">
                                ID específico do projeto, trilha, círculo, etc. Deixar vazio para aplicar globalmente.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="assign_role" class="button-primary" value="Atribuir Papel">
                </p>
            </form>
        </div>
        
        <!-- Lista de Usuários e seus Papéis -->
        <div class="ql-users-roles">
            <h2>Usuários e seus Papéis Organizativos</h2>
            
            <div class="ql-users-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">Usuário</th>
                            <th scope="col">Papéis Globais</th>
                            <th scope="col">Papéis Específicos</th>
                            <th scope="col">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $user): 
                            $global_roles = $roles_system->get_user_roles($user->ID, 'global');
                            $specific_roles = $roles_system->get_user_roles($user->ID);
                            $specific_roles = array_filter($specific_roles, function($r) { return $r->context_type !== 'global'; });
                        ?>
                            <tr>
                                <td>
                                    <div class="ql-user-info">
                                        <?php echo get_avatar($user->ID, 32, '', '', ['class' => 'ql-user-avatar']); ?>
                                        <div>
                                            <strong><?php echo $user->display_name; ?></strong><br>
                                            <small><?php echo $user->user_email; ?></small>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="ql-user-roles">
                                        <?php if (empty($global_roles)): ?>
                                            <span class="ql-no-roles">Nenhum papel global</span>
                                        <?php else: ?>
                                            <?php foreach ($global_roles as $role): 
                                                $role_def = $role_definitions[$role->role_key];
                                            ?>
                                                <span class="ql-role-badge" style="background-color: <?php echo $role_def['color']; ?>">
                                                    <span class="dashicons <?php echo $role_def['icon']; ?>"></span>
                                                    <?php echo $role_def['label']; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="ql-user-roles">
                                        <?php if (empty($specific_roles)): ?>
                                            <span class="ql-no-roles">Nenhum papel específico</span>
                                        <?php else: ?>
                                            <?php foreach ($specific_roles as $role): 
                                                $role_def = $role_definitions[$role->role_key];
                                            ?>
                                                <span class="ql-role-badge ql-specific-role" style="border-color: <?php echo $role_def['color']; ?>">
                                                    <span class="dashicons <?php echo $role_def['icon']; ?>"></span>
                                                    <?php echo $role_def['label']; ?>
                                                    <small>(<?php echo $role->context_type; ?> <?php echo $role->context_id; ?>)</small>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <button type="button" class="button button-small ql-manage-user-roles" 
                                            data-user-id="<?php echo $user->ID; ?>">
                                        Gerenciar Papéis
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Estatísticas -->
        <div class="ql-roles-statistics">
            <h2>Estatísticas Organizativas</h2>
            
            <div class="ql-stats-grid">
                <div class="ql-stat-card">
                    <h3>Total de Usuários</h3>
                    <div class="ql-stat-number"><?php echo count($all_users); ?></div>
                </div>
                
                <?php foreach ($role_definitions as $role_key => $role_data): 
                    $count = count($roles_system->get_users_with_role($role_key));
                ?>
                    <div class="ql-stat-card" style="border-top: 3px solid <?php echo $role_data['color']; ?>">
                        <h3><?php echo $role_data['label']; ?></h3>
                        <div class="ql-stat-number"><?php echo $count; ?></div>
                        <div class="ql-stat-percentage">
                            <?php echo count($all_users) > 0 ? round(($count / count($all_users)) * 100, 1) : 0; ?>% dos usuários
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Gerenciar Papéis do Usuário -->
<div id="ql-user-roles-modal" class="ql-modal" style="display: none;">
    <div class="ql-modal-content">
        <div class="ql-modal-header">
            <h2>Gerenciar Papéis do Usuário</h2>
            <span class="ql-modal-close">&times;</span>
        </div>
        
        <div class="ql-modal-body">
            <div id="ql-user-roles-content">
                <!-- Conteúdo carregado via AJAX -->
            </div>
        </div>
    </div>
</div>

<style>
.ql-organizational-roles-admin {
    max-width: 1200px;
    margin: 20px 0;
}

.ql-roles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.ql-role-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.ql-role-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.ql-role-header h3 {
    margin: 0;
    flex-grow: 1;
}

.ql-hierarchy-badge {
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}

.ql-role-description {
    color: #666;
    margin-bottom: 15px;
    line-height: 1.4;
}

.ql-role-details {
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.ql-admin-required {
    background: #fef2f2;
    color: #dc3545;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    margin: 10px 0;
}

.ql-role-stats {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
    text-align: center;
    color: #495057;
}

.ql-users-table-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    margin: 20px 0;
}

.ql-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.ql-user-avatar {
    border-radius: 50%;
}

.ql-user-roles {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.ql-role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    color: white;
    font-size: 12px;
    font-weight: 500;
}

.ql-specific-role {
    background: white;
    color: #333;
    border: 1px solid;
}

.ql-no-roles {
    color: #999;
    font-style: italic;
    font-size: 13px;
}

.ql-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.ql-stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid #ddd;
}

.ql-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
}

.ql-stat-percentage {
    color: #666;
    font-size: 14px;
}

.ql-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.ql-modal-content {
    background: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow: hidden;
}

.ql-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #ddd;
    background: #f8f9fa;
}

.ql-modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

.ql-modal-close:hover {
    color: #333;
}

.ql-modal-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .ql-roles-grid {
        grid-template-columns: 1fr;
    }
    
    .ql-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gerenciar papéis do usuário
    $('.ql-manage-user-roles').on('click', function() {
        var userId = $(this).data('user-id');
        
        $('#ql-user-roles-modal').show();
        $('#ql-user-roles-content').html('<div class="ql-loading">Carregando...</div>');
        
        $.post(ajaxurl, {
            action: 'ql_get_user_roles',
            user_id: userId,
            nonce: ql_admin.nonce
        }, function(response) {
            if (response.success) {
                // Renderizar interface de gestão de papéis
                // TODO: Implementar interface detalhada
                $('#ql-user-roles-content').html('<p>Interface de gestão em desenvolvimento...</p>');
            } else {
                $('#ql-user-roles-content').html('<p class="error">Erro ao carregar papéis.</p>');
            }
        });
    });
    
    // Fechar modal
    $('.ql-modal-close, .ql-modal').on('click', function(e) {
        if (e.target === this) {
            $('.ql-modal').hide();
        }
    });
    
    // Escape key para fechar modal
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27) {
            $('.ql-modal').hide();
        }
    });
});
</script>