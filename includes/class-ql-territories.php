<?php
/**
 * Sistema de Territorialização e Georreferenciamento
 * 
 * Implementa o sistema de territórios como entidade central conforme modelo organizativo:
 * - Definição territorial por endereços
 * - Auto-atribuição de usuários por localização
 * - Mapas interativos OpenStreetMap/Leaflet
 * - Suporte desde um edifício até territórios extensos
 * 
 * @package QuilomboLab
 * @since 1.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class QL_Territories {
    
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Construtor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'create_territory_post_type']);
        add_action('add_meta_boxes', [$this, 'add_territory_meta_boxes']);
        add_action('save_post', [$this, 'save_territory_meta']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_map_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_map_assets']);
        
        // AJAX para busca de endereços e geolocalização
        add_action('wp_ajax_ql_search_address', [$this, 'ajax_search_address']);
        add_action('wp_ajax_nopriv_ql_search_address', [$this, 'ajax_search_address']);
        add_action('wp_ajax_ql_save_territory_bounds', [$this, 'ajax_save_territory_bounds']);
        add_action('wp_ajax_ql_get_user_territory', [$this, 'ajax_get_user_territory']);
        add_action('wp_ajax_nopriv_ql_get_user_territory', [$this, 'ajax_get_user_territory']);
        add_action('wp_ajax_ql_save_user_location', [$this, 'ajax_save_user_location']);
        add_action('wp_ajax_ql_get_all_territories', [$this, 'ajax_get_all_territories']);
        add_action('wp_ajax_ql_get_territory_data', [$this, 'ajax_get_territory_data']);
        add_action('wp_ajax_ql_get_location_by_ip', [$this, 'ajax_get_location_by_ip']);
        add_action('wp_ajax_nopriv_ql_get_location_by_ip', [$this, 'ajax_get_location_by_ip']);
        add_action('wp_ajax_ql_get_nucleo_data', [$this, 'ajax_get_nucleo_data']);
        add_action('wp_ajax_ql_create_territory', [$this, 'ajax_create_territory']);

        // AJAX para administração de territórios (somente admin)
        add_action('wp_ajax_ql_assign_all_users_world_defaults', [$this, 'ajax_assign_all_users_world_defaults']);
        add_action('wp_ajax_ql_ensure_world_defaults', [$this, 'ajax_ensure_world_defaults']);
        add_action('wp_ajax_ql_save_collective_address', [$this, 'ajax_save_collective_address']);

        // AJAX para obter localizações de usuários (para mapas)
        add_action('wp_ajax_ql_get_all_users_locations', [$this, 'ajax_get_all_users_locations']);
        add_action('wp_ajax_nopriv_ql_get_all_users_locations', [$this, 'ajax_get_all_users_locations']);

        // AJAX para obter territórios do usuário atual
        add_action('wp_ajax_ql_get_user_territories', [$this, 'ajax_get_user_territories']);

        // Shortcode para exibir mapas
        add_shortcode('ql_territory_map', [$this, 'territory_map_shortcode']);
        add_shortcode('ql_user_location_selector', [$this, 'user_location_selector_shortcode']);
        
        // Hook para auto-atribuição de usuários
        add_action('user_register', [$this, 'auto_assign_user_territory']);
        add_action('user_register', [$this, 'assign_user_to_world_defaults']);
        add_action('profile_update', [$this, 'update_user_territory']);
    }
    
    /**
     * Criar post type para territórios
     */
    public function create_territory_post_type() {
        register_post_type('ql_territory', [
            'labels' => [
                'name' => __('Territórios', 'quilombo-lab'),
                'singular_name' => __('Território', 'quilombo-lab'),
                'menu_name' => __('Territórios', 'quilombo-lab'),
                'add_new' => __('Adicionar Território', 'quilombo-lab'),
                'add_new_item' => __('Adicionar Novo Território', 'quilombo-lab'),
                'edit_item' => __('Editar Território', 'quilombo-lab'),
                'new_item' => __('Novo Território', 'quilombo-lab'),
                'view_item' => __('Ver Território', 'quilombo-lab'),
                'search_items' => __('Buscar Territórios', 'quilombo-lab'),
                'not_found' => __('Nenhum território encontrado', 'quilombo-lab'),
                'not_found_in_trash' => __('Nenhum território na lixeira', 'quilombo-lab')
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'quilombo-lab-organizacao', // Adicionar ao menu Organização
            'query_var' => true,
            'rewrite' => ['slug' => 'territorio'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => true, // Permite territórios aninhados
            'menu_position' => null,
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields'],
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location-alt'
        ]);
        
        // Taxonomia para tipos de território
        register_taxonomy('territory_type', 'ql_territory', [
            'labels' => [
                'name' => __('Tipos de Território', 'quilombo-lab'),
                'singular_name' => __('Tipo de Território', 'quilombo-lab'),
                'menu_name' => __('Tipos', 'quilombo-lab')
            ],
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
        ]);
        
        // Criar tipos de território padrão
        $this->create_default_territory_types();
        
        // Criar território e comunidade mundial padrão
        $this->create_default_world_territory_and_community();
        
        // Força execução em toda inicialização durante desenvolvimento
        add_action('init', [$this, 'force_territory_creation'], 20);
    }
    
    /**
     * Criar tipos de território padrão
     */
    private function create_default_territory_types() {
        if (!get_option('ql_default_territory_types_created')) {
            $types = [
                'edificio' => __('Edifício', 'quilombo-lab'),
                'residencia' => __('Residência', 'quilombo-lab'),
                'rua' => __('Rua/Logradouro', 'quilombo-lab'),
                'bairro' => __('Bairro', 'quilombo-lab'),
                'favela' => __('Favela/Comunidade', 'quilombo-lab'),
                'vila' => __('Vila/Povoado', 'quilombo-lab'),
                'sitio' => __('Sítio/Propriedade Rural', 'quilombo-lab'),
                'aldeia' => __('Aldeia', 'quilombo-lab'),
                'cidade' => __('Cidade/Município', 'quilombo-lab'),
                'estado' => __('Estado/Província', 'quilombo-lab'),
                'pais' => __('País', 'quilombo-lab'),
                'regiao' => __('Região', 'quilombo-lab'),
                'continente' => __('Continente', 'quilombo-lab')
            ];
            
            foreach ($types as $slug => $name) {
                if (!term_exists($slug, 'territory_type')) {
                    wp_insert_term($name, 'territory_type', ['slug' => $slug]);
                }
            }
            
            update_option('ql_default_territory_types_created', true);
        }
    }
    
    /**
     * Criar território mundo e comunidade mundial padrão
     */
    private function create_default_world_territory_and_community() {
        $this->create_world_territory();
        $this->create_world_community();
        $this->create_world_territory_for_collectives(); // Manter para coletivos específicos
    }
    
    /**
     * Criar território mundo padrão
     */
    private function create_world_territory() {
        // Verificar se já existe território mundo padrão
        $existing_world_territory = get_posts([
            'post_type' => 'ql_territory',
            'meta_query' => [
                [
                    'key' => '_territory_type',
                    'value' => 'default_world',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);
        
        if (!empty($existing_world_territory)) {
            return $existing_world_territory[0]->ID;
        }
        
        // Criar território mundo padrão
        $world_territory_id = wp_insert_post([
            'post_title' => 'Território Mundo',
            'post_content' => 'Território mundial padrão. Abrange todo o planeta Terra.',
            'post_status' => 'publish',
            'post_type' => 'ql_territory',
            'meta_input' => [
                '_territory_lat' => '0',
                '_territory_lng' => '0',
                '_territory_address' => 'Planeta Terra',
                '_territory_auto_assign' => '1',
                '_territory_type' => 'default_world',
                '_territory_is_default' => '1',
                '_territory_bounds' => json_encode([
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[
                            [-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]
                        ]]
                    ]
                ])
            ]
        ]);
        
        if (!is_wp_error($world_territory_id)) {
            // Garantir que existe tipo "mundo"
            if (!term_exists('mundo', 'territory_type')) {
                wp_insert_term('Mundo', 'territory_type', ['slug' => 'mundo']);
            }
            wp_set_object_terms($world_territory_id, 'mundo', 'territory_type');
            
            error_log('QL Territories: Território Mundo criado com ID: ' . $world_territory_id);
        }
        
        return $world_territory_id;
    }
    
    /**
     * Criar comunidade mundial padrão
     */
    private function create_world_community() {
        global $wpdb;
        
        // Verificar se já existe comunidade mundial
        $existing_community = $wpdb->get_row("
            SELECT * FROM {$wpdb->prefix}ql_instances 
            WHERE name = 'Comunidade Mundial' 
            AND type = 'comunidade'
            LIMIT 1
        ");
        
        if ($existing_community) {
            return $existing_community->id;
        }
        
        // Criar comunidade mundial
        $community_data = [
            'type' => 'comunidade',
            'name' => 'Comunidade Mundial',
            'slug' => 'comunidade-mundial',
            'description' => 'Comunidade mundial padrão. Abrange todos os membros do sistema.',
            'status' => 'active',
            'parent_instance_id' => null,
            'creator_id' => get_current_user_id() ?: 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'ql_instances',
            $community_data,
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
        
        if ($inserted) {
            $community_id = $wpdb->insert_id;
            
            // Adicionar metadata para indicar que é comunidade padrão
            $metadata = json_encode(['is_default_community' => true]);
            
            $wpdb->update(
                $wpdb->prefix . 'ql_instances',
                ['metadata' => $metadata],
                ['id' => $community_id],
                ['%s'],
                ['%d']
            );
            
            error_log('QL Territories: Comunidade Mundial criada com ID: ' . $community_id);
            
            return $community_id;
        }
        
        return false;
    }
    
    /**
     * Criar território mundial para coletivos específicos
     */
    private function create_world_territory_for_collectives() {
        // Verificar se já foi criado
        if (get_option('ql_world_territory_created')) {
            return;
        }
        
        global $wpdb;
        
        $coletivos = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ql_instances
            WHERE type = 'coletivo' AND status = 'active'
        ");
        
        foreach ($coletivos as $coletivo) {
            // Verificar se já tem território
            $existing_territory = get_posts([
                'post_type' => 'ql_territory',
                'meta_query' => [
                    [
                        'key' => '_territory_instance_id',
                        'value' => $coletivo->id,
                        'compare' => '='
                    ]
                ],
                'posts_per_page' => 1
            ]);
            
            if (empty($existing_territory)) {
                // Criar território mundial para o coletivo
                $world_territory_id = wp_insert_post([
                    'post_title' => $coletivo->nome . ' - Território Mundial',
                    'post_content' => 'Território global do coletivo ' . $coletivo->nome . '. Abrange todo o mundo.',
                    'post_status' => 'publish',
                    'post_type' => 'ql_territory',
                    'meta_input' => [
                        '_territory_lat' => '0',
                        '_territory_lng' => '0', 
                        '_territory_address' => 'Mundo',
                        '_territory_auto_assign' => '1',
                        '_territory_instance_id' => $coletivo->id,
                        '_territory_bounds' => json_encode([
                            'type' => 'Feature',
                            'geometry' => [
                                'type' => 'Polygon',
                                'coordinates' => [[
                                    [-180, -85], [180, -85], [180, 85], [-180, 85], [-180, -85]
                                ]]
                            ]
                        ])
                    ]
                ]);
                
                // Atribuir tipo "mundo"
                if (!term_exists('mundo', 'territory_type')) {
                    wp_insert_term('Mundo', 'territory_type', ['slug' => 'mundo']);
                }
                wp_set_object_terms($world_territory_id, 'mundo', 'territory_type');
                
                // Buscar núcleos do coletivo que tenham endereço
                $nucleos_com_endereco = $this->get_nucleos_with_address($coletivo->id);
                
                // Se houver núcleos com endereço, usar o primeiro como localização central
                if (!empty($nucleos_com_endereco)) {
                    $nucleo_principal = $nucleos_com_endereco[0];
                    update_post_meta($world_territory_id, '_territory_lat', $nucleo_principal['lat']);
                    update_post_meta($world_territory_id, '_territory_lng', $nucleo_principal['lng']);
                    update_post_meta($world_territory_id, '_territory_address', $nucleo_principal['endereco']);
                    update_post_meta($world_territory_id, '_territory_nucleo_principal', $nucleo_principal['id']);
                }
            }
        }
        
        update_option('ql_world_territory_created', true);
    }
    
    /**
     * Obter núcleos do coletivo que possuem endereço físico
     */
    private function get_nucleos_with_address($coletivo_id) {
        global $wpdb;
        
        $nucleos = $wpdb->get_results($wpdb->prepare("
            SELECT i.*, im.endereco_fisico, im.lat, im.lng
            FROM {$wpdb->prefix}ql_instances i
            LEFT JOIN {$wpdb->prefix}ql_instance_meta im ON i.id = im.instance_id
            WHERE i.parent_instance_id = %d
            AND i.type = 'nucleo'
            AND i.status = 'active'
            AND (im.endereco_fisico IS NOT NULL AND im.endereco_fisico != '')
            ORDER BY i.created_at ASC
        ", $coletivo_id));
        
        $nucleos_formatados = [];
        
        foreach ($nucleos as $nucleo) {
            // Se não tem coordenadas, tentar geocodificar
            if (empty($nucleo->lat) || empty($nucleo->lng)) {
                $geocoding_results = $this->geocode_address($nucleo->endereco_fisico);
                if (!empty($geocoding_results)) {
                    $nucleo->lat = $geocoding_results[0]['lat'];
                    $nucleo->lng = $geocoding_results[0]['lon'];
                    
                    // Salvar coordenadas para próxima vez
                    $wpdb->update(
                        $wpdb->prefix . 'ql_instance_meta',
                        ['lat' => $nucleo->lat, 'lng' => $nucleo->lng],
                        ['instance_id' => $nucleo->id],
                        ['%f', '%f'],
                        ['%d']
                    );
                }
            }
            
            if (!empty($nucleo->lat) && !empty($nucleo->lng)) {
                $nucleos_formatados[] = [
                    'id' => $nucleo->id,
                    'nome' => $nucleo->nome,
                    'endereco' => $nucleo->endereco_fisico,
                    'lat' => floatval($nucleo->lat),
                    'lng' => floatval($nucleo->lng)
                ];
            }
        }
        
        return $nucleos_formatados;
    }
    
    /**
     * Forçar criação de territórios (temporário para desenvolvimento)
     */
    public function force_territory_creation() {
        // Remover após confirmar que funciona
        if (current_user_can('manage_options')) {
            delete_option('ql_world_territory_created');
            
            // Forçar criação de território mundo e comunidade mundial
            $world_territory_id = $this->create_world_territory();
            $world_community_id = $this->create_world_community();
            
            // Log para debug
            if ($world_territory_id && $world_community_id) {
                error_log('QL Territories: Território e Comunidade Mundial criados - Territory: ' . $world_territory_id . ', Community: ' . $world_community_id);
            }
            
            $this->create_world_territory_for_collectives();
        }
    }
    
    /**
     * Adicionar meta boxes para territórios
     */
    public function add_territory_meta_boxes() {
        add_meta_box(
            'ql-territory-location',
            __('Localização e Coordenadas', 'quilombo-lab'),
            [$this, 'territory_location_meta_box'],
            'ql_territory',
            'normal',
            'high'
        );
        
        add_meta_box(
            'ql-territory-map',
            __('Mapa Interativo', 'quilombo-lab'),
            [$this, 'territory_map_meta_box'],
            'ql_territory',
            'normal',
            'high'
        );
        
        add_meta_box(
            'ql-territory-settings',
            __('Configurações do Território', 'quilombo-lab'),
            [$this, 'territory_settings_meta_box'],
            'ql_territory',
            'side',
            'default'
        );
    }
    
    /**
     * Meta box para localização
     */
    public function territory_location_meta_box($post) {
        wp_nonce_field('ql_territory_meta', 'ql_territory_meta_nonce');
        
        $address = get_post_meta($post->ID, '_territory_address', true);
        $lat = get_post_meta($post->ID, '_territory_lat', true);
        $lng = get_post_meta($post->ID, '_territory_lng', true);
        $bounds = get_post_meta($post->ID, '_territory_bounds', true);
        $auto_assign = get_post_meta($post->ID, '_territory_auto_assign', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="territory_address"><?php _e('Endereço', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <input type="text" 
                           id="territory_address" 
                           name="territory_address" 
                           value="<?php echo esc_attr($address); ?>" 
                           class="regular-text"
                           placeholder="<?php _e('Digite um endereço para buscar...', 'quilombo-lab'); ?>" />
                    <button type="button" 
                            id="search_address_btn" 
                            class="button"><?php _e('Buscar', 'quilombo-lab'); ?></button>
                    <p class="description">
                        <?php _e('Digite qualquer endereço: casa, rua, bairro, cidade, estado, país.', 'quilombo-lab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Coordenadas', 'quilombo-lab'); ?></th>
                <td>
                    <label for="territory_lat"><?php _e('Latitude:', 'quilombo-lab'); ?></label>
                    <input type="text" 
                           id="territory_lat" 
                           name="territory_lat" 
                           value="<?php echo esc_attr($lat); ?>" 
                           class="small-text" readonly />
                    
                    <label for="territory_lng" style="margin-left: 20px;"><?php _e('Longitude:', 'quilombo-lab'); ?></label>
                    <input type="text" 
                           id="territory_lng" 
                           name="territory_lng" 
                           value="<?php echo esc_attr($lng); ?>" 
                           class="small-text" readonly />
                    
                    <p class="description">
                        <?php _e('Coordenadas são preenchidas automaticamente ao buscar endereço.', 'quilombo-lab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="territory_bounds"><?php _e('Área/Limites', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <textarea id="territory_bounds" 
                              name="territory_bounds" 
                              rows="3" 
                              class="regular-text"
                              readonly><?php echo esc_textarea($bounds); ?></textarea>
                    <p class="description">
                        <?php _e('Defina a área no mapa abaixo para estabelecer os limites do território.', 'quilombo-lab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="territory_auto_assign"><?php _e('Auto-atribuição', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="territory_auto_assign" 
                               name="territory_auto_assign" 
                               value="1" 
                               <?php checked($auto_assign, 1); ?> />
                        <?php _e('Atribuir automaticamente usuários por localização', 'quilombo-lab'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Usuários dentro desta área serão automaticamente associados a este território.', 'quilombo-lab'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#search_address_btn').on('click', function() {
                var address = $('#territory_address').val();
                if (!address) {
                    alert('<?php _e('Digite um endereço para buscar', 'quilombo-lab'); ?>');
                    return;
                }
                
                // Usar API de geocoding (Nominatim - OpenStreetMap)
                $.get('https://nominatim.openstreetmap.org/search', {
                    q: address,
                    format: 'json',
                    limit: 1,
                    addressdetails: 1
                }, function(data) {
                    if (data && data.length > 0) {
                        var result = data[0];
                        $('#territory_lat').val(result.lat);
                        $('#territory_lng').val(result.lon);
                        
                        // Atualizar mapa se existir
                        if (window.territoryMap) {
                            window.territoryMap.setView([result.lat, result.lon], 15);
                            if (window.territoryMarker) {
                                window.territoryMarker.setLatLng([result.lat, result.lon]);
                            } else {
                                window.territoryMarker = L.marker([result.lat, result.lon]).addTo(window.territoryMap);
                            }
                        }
                        
                        alert('<?php _e('Endereço encontrado! Verifique as coordenadas.', 'quilombo-lab'); ?>');
                    } else {
                        alert('<?php _e('Endereço não encontrado. Tente ser mais específico.', 'quilombo-lab'); ?>');
                    }
                }).fail(function() {
                    alert('<?php _e('Erro ao buscar endereço. Tente novamente.', 'quilombo-lab'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Meta box para mapa interativo
     */
    public function territory_map_meta_box($post) {
        $lat = get_post_meta($post->ID, '_territory_lat', true) ?: '-15.7942287'; // Brasil central
        $lng = get_post_meta($post->ID, '_territory_lng', true) ?: '-47.8821945';
        
        ?>
        <div id="territory-map" style="height: 400px; width: 100%;"></div>
        <p class="description">
            <?php _e('Clique no mapa para definir a localização. Use as ferramentas para delimitar a área do território.', 'quilombo-lab'); ?>
        </p>
        
        <script>
        jQuery(document).ready(function($) {
            // Inicializar mapa Leaflet
            window.territoryMap = L.map('territory-map').setView([<?php echo $lat; ?>, <?php echo $lng; ?>], 10);
            
            // Adicionar camada OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(window.territoryMap);
            
            // Adicionar marcador se já houver coordenadas
            <?php if ($lat && $lng): ?>
            window.territoryMarker = L.marker([<?php echo $lat; ?>, <?php echo $lng; ?>]).addTo(window.territoryMap);
            <?php endif; ?>
            
            // Permitir clicar no mapa para definir localização
            window.territoryMap.on('click', function(e) {
                var lat = e.latlng.lat;
                var lng = e.latlng.lng;
                
                $('#territory_lat').val(lat);
                $('#territory_lng').val(lng);
                
                if (window.territoryMarker) {
                    window.territoryMarker.setLatLng(e.latlng);
                } else {
                    window.territoryMarker = L.marker(e.latlng).addTo(window.territoryMap);
                }
            });
            
            // Ferramenta para desenhar área/limites
            var drawnItems = new L.FeatureGroup();
            window.territoryMap.addLayer(drawnItems);
            
            var drawControl = new L.Control.Draw({
                edit: {
                    featureGroup: drawnItems
                },
                draw: {
                    polygon: true,
                    rectangle: true,
                    circle: true,
                    marker: false,
                    polyline: false,
                    circlemarker: false
                }
            });
            window.territoryMap.addControl(drawControl);
            
            // Salvar limites quando desenhados
            window.territoryMap.on('draw:created', function(event) {
                var layer = event.layer;
                drawnItems.addLayer(layer);
                
                // Converter para formato JSON e salvar
                var bounds = layer.toGeoJSON();
                $('#territory_bounds').val(JSON.stringify(bounds));
            });
        });
        </script>
        <?php
    }
    
    /**
     * Meta box para configurações do território
     */
    public function territory_settings_meta_box($post) {
        $instance_id = get_post_meta($post->ID, '_territory_instance_id', true);
        $visibility = get_post_meta($post->ID, '_territory_visibility', true) ?: 'public';
        $can_create_communities = get_post_meta($post->ID, '_territory_can_create_communities', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="territory_instance"><?php _e('Instância Responsável', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <select id="territory_instance" name="territory_instance_id" class="regular-text">
                        <option value=""><?php _e('Nenhuma instância', 'quilombo-lab'); ?></option>
                        <?php
                        // Listar instâncias disponíveis
                        $instances = $this->get_available_instances();
                        foreach ($instances as $instance) {
                            echo '<option value="' . esc_attr($instance['id']) . '"' . 
                                 selected($instance_id, $instance['id'], false) . '>' . 
                                 esc_html($instance['name']) . ' (' . esc_html($instance['type']) . ')' .
                                 '</option>';
                        }
                        ?>
                    </select>
                    <p class="description">
                        <?php _e('Instância (Círculo/Núcleo) responsável por este território.', 'quilombo-lab'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="territory_visibility"><?php _e('Visibilidade', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <select id="territory_visibility" name="territory_visibility">
                        <option value="public" <?php selected($visibility, 'public'); ?>><?php _e('Público', 'quilombo-lab'); ?></option>
                        <option value="members" <?php selected($visibility, 'members'); ?>><?php _e('Apenas Membros', 'quilombo-lab'); ?></option>
                        <option value="private" <?php selected($visibility, 'private'); ?>><?php _e('Privado', 'quilombo-lab'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="territory_communities"><?php _e('Comunidades', 'quilombo-lab'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               id="territory_communities" 
                               name="territory_can_create_communities" 
                               value="1" 
                               <?php checked($can_create_communities, 1); ?> />
                        <?php _e('Permitir criação de comunidades neste território', 'quilombo-lab'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Salvar meta dados do território
     */
    public function save_territory_meta($post_id) {
        if (!isset($_POST['ql_territory_meta_nonce']) || 
            !wp_verify_nonce($_POST['ql_territory_meta_nonce'], 'ql_territory_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Salvar campos
        $fields = [
            '_territory_address' => 'territory_address',
            '_territory_lat' => 'territory_lat', 
            '_territory_lng' => 'territory_lng',
            '_territory_bounds' => 'territory_bounds',
            '_territory_auto_assign' => 'territory_auto_assign',
            '_territory_instance_id' => 'territory_instance_id',
            '_territory_visibility' => 'territory_visibility',
            '_territory_can_create_communities' => 'territory_can_create_communities'
        ];
        
        foreach ($fields as $meta_key => $post_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        
        // Trigger auto-assignment se habilitado
        if (isset($_POST['territory_auto_assign']) && $_POST['territory_auto_assign'] == '1') {
            $this->trigger_territory_auto_assignment($post_id);
        }
    }
    
    /**
     * Enfileirar assets do mapa para frontend
     */
    public function enqueue_map_assets() {
        // Carregar em territórios, admin do QL e páginas com shortcodes
        if (is_singular('ql_territory') || is_post_type_archive('ql_territory') || 
            (isset($_GET['page']) && strpos($_GET['page'], 'quilombo-lab') !== false) ||
            (is_page() && has_shortcode(get_post()->post_content, 'ql_territory_map'))) {
            
            // Leaflet CSS
            wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', [], '1.9.4');
            
            // Leaflet Draw CSS
            wp_enqueue_style('leaflet-draw', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css', ['leaflet'], '1.0.4');
            
            // CSS customizado
            wp_enqueue_style('ql-territories', QL_PLUGIN_URL . 'assets/css/territories.css', [], QL_PLUGIN_VERSION);
            
            // Leaflet JS
            wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', [], '1.9.4', true);
            
            // Leaflet Draw JS
            wp_enqueue_script('leaflet-draw', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js', ['leaflet'], '1.0.4', true);
            
            // Script customizado
            wp_enqueue_script('ql-territories', QL_PLUGIN_URL . 'assets/js/territories.js', ['jquery', 'leaflet'], QL_PLUGIN_VERSION, true);
            
            wp_localize_script('ql-territories', 'ql_territories', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ql_territories_nonce'),
                'strings' => [
                    'loading' => __('Carregando...', 'quilombo-lab'),
                    'error' => __('Erro ao carregar dados.', 'quilombo-lab'),
                    'location_saved' => __('Localização salva!', 'quilombo-lab'),
                    'territory_assigned' => __('Território atribuído!', 'quilombo-lab'),
                    'enter_address' => __('Digite um endereço para buscar', 'quilombo-lab'),
                    'address_not_found' => __('Endereço não encontrado', 'quilombo-lab'),
                    'search_error' => __('Erro ao buscar endereço', 'quilombo-lab'),
                    'your_location' => __('Sua localização', 'quilombo-lab'),
                    'save_location' => __('Salvar localização', 'quilombo-lab')
                ]
            ]);
        }
    }
    
    /**
     * Enfileirar assets para admin
     */
    public function enqueue_admin_map_assets($hook) {
        global $post_type;
        
        // Carregar assets em todas as páginas administrativas do QL e páginas de território
        if (($post_type == 'ql_territory' && ($hook == 'post.php' || $hook == 'post-new.php')) ||
            (isset($_GET['page']) && strpos($_GET['page'], 'quilombo-lab') !== false)) {
            $this->enqueue_map_assets();
        }
    }
    
    /**
     * AJAX: Buscar endereço
     */
    public function ajax_search_address() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $address = sanitize_text_field($_POST['address']);
        
        if (empty($address)) {
            wp_send_json_error(__('Endereço é obrigatório', 'quilombo-lab'));
        }
        
        // Tentar múltiplos provedores de geocoding
        $geocoding_results = $this->geocode_address($address);
        
        if (empty($geocoding_results)) {
            wp_send_json_error(__('Endereço não encontrado em nenhum provedor', 'quilombo-lab'));
        }
        
        wp_send_json_success($geocoding_results);
    }
    
    /**
     * Geocodificar endereço usando múltiplos provedores
     */
    private function geocode_address($address) {
        $results = [];
        
        // 1. Nominatim (OpenStreetMap) - Principal
        $nominatim_results = $this->geocode_nominatim($address);
        if (!empty($nominatim_results)) {
            $results = array_merge($results, $nominatim_results);
        }
        
        // 2. ViaCEP para CEPs brasileiros
        if (preg_match('/\d{5}-?\d{3}/', $address)) {
            $viacep_results = $this->geocode_viacep($address);
            if (!empty($viacep_results)) {
                $results = array_merge($results, $viacep_results);
            }
        }
        
        // 3. Fallback: criar resultado genérico para cidades conhecidas
        if (empty($results)) {
            $results = $this->geocode_fallback($address);
        }
        
        return array_slice($results, 0, 5); // Máximo 5 resultados
    }
    
    /**
     * Geocodificar com Nominatim
     */
    private function geocode_nominatim($address) {
        $url = "https://nominatim.openstreetmap.org/search?" . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 3,
            'addressdetails' => 1,
            'accept-language' => 'pt-BR,pt,en'
        ]);
        
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'user-agent' => 'QuilomboLab/1.0 (WordPress Plugin)',
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            error_log('Nominatim error: ' . $response->get_error_message());
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            error_log('Nominatim invalid response: ' . $body);
            return [];
        }
        
        return $data;
    }
    
    /**
     * Geocodificar CEP brasileiro com ViaCEP
     */
    private function geocode_viacep($address) {
        $cep = preg_replace('/\D/', '', $address);
        
        if (strlen($cep) !== 8) {
            return [];
        }
        
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        
        $response = wp_remote_get($url, ['timeout' => 5]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['erro'])) {
            return [];
        }
        
        // ViaCEP não fornece coordenadas, então usar o endereço completo no Nominatim
        if (isset($data['logradouro']) && isset($data['localidade'])) {
            $full_address = trim($data['logradouro'] . ', ' . $data['localidade'] . ', ' . $data['uf'] . ', Brasil');
            return $this->geocode_nominatim($full_address);
        }
        
        return [];
    }
    
    /**
     * Geocodificação de fallback para cidades conhecidas
     */
    private function geocode_fallback($address) {
        $fallback_cities = [
            'são paulo' => [-23.5505, -46.6333, 'São Paulo, SP, Brasil'],
            'rio de janeiro' => [-22.9068, -43.1729, 'Rio de Janeiro, RJ, Brasil'],
            'brasília' => [-15.7942, -47.8822, 'Brasília, DF, Brasil'],
            'salvador' => [-12.9714, -38.5014, 'Salvador, BA, Brasil'],
            'fortaleza' => [-3.7319, -38.5267, 'Fortaleza, CE, Brasil'],
            'belo horizonte' => [-19.9167, -43.9345, 'Belo Horizonte, MG, Brasil'],
            'manaus' => [-3.1190, -60.0217, 'Manaus, AM, Brasil'],
            'curitiba' => [-25.4284, -49.2733, 'Curitiba, PR, Brasil'],
            'porto alegre' => [-30.0277, -51.2287, 'Porto Alegre, RS, Brasil'],
            'goiânia' => [-16.6869, -49.2648, 'Goiânia, GO, Brasil'],
            'recife' => [-8.0476, -34.8770, 'Recife, PE, Brasil'],
            'belém' => [-1.4558, -48.5044, 'Belém, PA, Brasil'],
        ];
        
        $normalized_address = strtolower(trim($address));
        $normalized_address = preg_replace('/[^a-záàâãéèêíïóôõöúçñ\s]/', '', $normalized_address);
        
        foreach ($fallback_cities as $city => $coords) {
            if (strpos($normalized_address, $city) !== false) {
                return [[
                    'lat' => $coords[0],
                    'lon' => $coords[1],
                    'display_name' => $coords[2] . ' (localização aproximada)',
                    'type' => 'fallback'
                ]];
            }
        }
        
        return [];
    }
    
    /**
     * AJAX: Salvar limites do território
     */
    public function ajax_save_territory_bounds() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $territory_id = intval($_POST['territory_id']);
        $bounds = $_POST['bounds'];
        
        if (!current_user_can('edit_post', $territory_id)) {
            wp_send_json_error(__('Sem permissão', 'quilombo-lab'));
        }
        
        update_post_meta($territory_id, '_territory_bounds', $bounds);
        
        wp_send_json_success(__('Limites salvos!', 'quilombo-lab'));
    }
    
    /**
     * AJAX: Obter território do usuário
     */
    public function ajax_get_user_territory() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('Usuário não logado', 'quilombo-lab'));
        }
        
        $territory_id = $this->get_user_territory($user_id);
        $territory = null;
        
        if ($territory_id) {
            $territory = [
                'id' => $territory_id,
                'name' => get_the_title($territory_id),
                'url' => get_permalink($territory_id)
            ];
        }
        
        wp_send_json_success($territory);
    }
    
    /**
     * AJAX: Salvar localização do usuário
     */
    public function ajax_save_user_location() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error(__('Usuário não logado', 'quilombo-lab'));
        }
        
        $lat = floatval($_POST['lat']);
        $lng = floatval($_POST['lng']);
        $address = sanitize_text_field($_POST['address']);
        
        if (!$lat || !$lng) {
            wp_send_json_error(__('Coordenadas inválidas', 'quilombo-lab'));
        }
        
        // Salvar coordenadas e endereço do usuário
        update_user_meta($user_id, 'user_lat', $lat);
        update_user_meta($user_id, 'user_lng', $lng);
        
        if ($address) {
            update_user_meta($user_id, 'user_address', $address);
        }
        
        // Auto-atribuir território se possível
        $territory_id = $this->find_territory_by_coordinates($lat, $lng);
        if ($territory_id) {
            update_user_meta($user_id, 'user_territory_id', $territory_id);
            wp_send_json_success([
                'territory_assigned' => true,
                'territory_id' => $territory_id,
                'territory_name' => get_the_title($territory_id)
            ]);
        }
        
        wp_send_json_success(['territory_assigned' => false]);
    }
    
    /**
     * AJAX: Obter todos os territórios
     */
    public function ajax_get_all_territories() {
        check_ajax_referer('ql_territories_nonce', 'nonce');

        $territories = get_posts([
            'post_type' => 'ql_territory',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $territories_data = [];

        foreach ($territories as $territory) {
            $lat = get_post_meta($territory->ID, '_territory_lat', true);
            $lng = get_post_meta($territory->ID, '_territory_lng', true);
            $address = get_post_meta($territory->ID, '_territory_address', true);
            $bounds = get_post_meta($territory->ID, '_territory_bounds', true);
            $territory_type = get_post_meta($territory->ID, '_territory_type', true);

            // Obter tipo de território da taxonomia
            $terms = wp_get_post_terms($territory->ID, 'territory_type', ['fields' => 'names']);
            $type_name = !empty($terms) && !is_wp_error($terms) ? $terms[0] : '';

            // Contar usuários neste território
            $users_count = count(get_users([
                'meta_key' => 'user_territory_id',
                'meta_value' => $territory->ID
            ]));

            $territories_data[] = [
                'id' => $territory->ID,
                'name' => $territory->post_title,
                'lat' => $lat ? floatval($lat) : null,
                'lng' => $lng ? floatval($lng) : null,
                'address' => $address,
                'type' => $type_name,
                'type_slug' => $territory_type,
                'bounds' => $bounds,
                'users_count' => $users_count,
                'url' => get_permalink($territory->ID),
                'edit_url' => admin_url('post.php?post=' . $territory->ID . '&action=edit')
            ];
        }

        wp_send_json_success($territories_data);
    }

    /**
     * AJAX: Obter localizações de todos os usuários para exibição em mapas
     *
     * Retorna usuários que possuem coordenadas definidas (user_lat e user_lng)
     */
    public function ajax_get_all_users_locations() {
        // Nonce é opcional para permitir visualização pública
        // mas limitamos os dados retornados para não logados

        $is_logged_in = is_user_logged_in();
        $current_user_id = get_current_user_id();

        // Buscar usuários com localização definida
        $users = get_users([
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'user_lat',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'user_lng',
                    'compare' => 'EXISTS'
                ],
                [
                    'key' => 'user_lat',
                    'value' => '',
                    'compare' => '!='
                ],
                [
                    'key' => 'user_lng',
                    'value' => '',
                    'compare' => '!='
                ]
            ],
            'fields' => ['ID', 'display_name', 'user_email']
        ]);

        $users_data = [];

        foreach ($users as $user) {
            $user_lat = get_user_meta($user->ID, 'user_lat', true);
            $user_lng = get_user_meta($user->ID, 'user_lng', true);

            // Validar coordenadas
            if (empty($user_lat) || empty($user_lng)) {
                continue;
            }

            $lat = floatval($user_lat);
            $lng = floatval($user_lng);

            // Validar que são coordenadas válidas
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }

            // Obter dados adicionais
            $user_address = get_user_meta($user->ID, 'user_address', true);
            $user_territory_id = get_user_meta($user->ID, 'user_territory_id', true);
            $territory_name = $user_territory_id ? get_the_title($user_territory_id) : '';

            // Obter avatar
            $avatar_url = get_avatar_url($user->ID, ['size' => 32]);

            // Dados básicos para todos
            $user_data = [
                'id' => $user->ID,
                'lat' => $lat,
                'lng' => $lng,
                'is_current_user' => ($user->ID === $current_user_id),
                'territory_id' => $user_territory_id ? intval($user_territory_id) : null
            ];

            // Dados adicionais apenas para usuários logados
            if ($is_logged_in) {
                $user_data['name'] = $user->display_name;
                $user_data['address'] = $user_address;
                $user_data['territory'] = $territory_name;
                $user_data['avatar'] = $avatar_url;
                $user_data['profile_url'] = get_author_posts_url($user->ID);
            } else {
                // Para não logados, mostrar apenas iniciais
                $user_data['name'] = $this->get_user_initials($user->display_name);
                $user_data['territory'] = $territory_name;
            }

            $users_data[] = $user_data;
        }

        // Adicionar estatísticas
        $response = [
            'users' => $users_data,
            'total' => count($users_data),
            'timestamp' => current_time('mysql')
        ];

        wp_send_json_success($response);
    }

    /**
     * Obter iniciais do nome do usuário
     *
     * @param string $name Nome completo
     * @return string Iniciais (até 2 letras)
     */
    private function get_user_initials($name) {
        $parts = explode(' ', trim($name));
        $initials = '';

        if (count($parts) >= 2) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($name, 0, 2));
        }

        return $initials;
    }

    /**
     * AJAX: Obter localização por IP
     */
    public function ajax_get_location_by_ip() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $user_ip = $this->get_user_ip();
        $location_data = $this->geolocate_ip($user_ip);
        
        if (empty($location_data)) {
            wp_send_json_error(__('Não foi possível determinar localização pelo IP', 'quilombo-lab'));
        }
        
        wp_send_json_success($location_data);
    }
    
    /**
     * Obter IP real do usuário
     */
    private function get_user_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Se há múltiplos IPs, pegar o primeiro
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                
                // Validar IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Geolocalizar IP usando múltiplos serviços
     */
    private function geolocate_ip($ip) {
        // Não geolocalizar IPs locais
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || 
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'lat' => -15.7942287,
                'lng' => -47.8821945,
                'address' => 'Brasília, DF, Brasil (IP local)'
            ];
        }
        
        // 1. Tentar ip-api.com
        $result = $this->geolocate_ip_api($ip);
        if (!empty($result)) {
            return $result;
        }
        
        // 2. Tentar ipapi.co
        $result = $this->geolocate_ipapi_co($ip);
        if (!empty($result)) {
            return $result;
        }
        
        // 3. Fallback para Brasil central
        return [
            'lat' => -15.7942287,
            'lng' => -47.8821945,
            'address' => 'Brasil (localização aproximada)'
        ];
    }
    
    /**
     * Geolocalizar com ip-api.com
     */
    private function geolocate_ip_api($ip) {
        $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,lat,lon";
        
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['status']) || $data['status'] !== 'success') {
            return [];
        }
        
        $address_parts = array_filter([
            $data['city'] ?? '',
            $data['regionName'] ?? '',
            $data['country'] ?? ''
        ]);
        
        return [
            'lat' => floatval($data['lat']),
            'lng' => floatval($data['lon']),
            'address' => implode(', ', $address_parts)
        ];
    }
    
    /**
     * Geolocalizar com ipapi.co
     */
    private function geolocate_ipapi_co($ip) {
        $url = "https://ipapi.co/{$ip}/json/";
        
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['latitude']) || !isset($data['longitude'])) {
            return [];
        }
        
        $address_parts = array_filter([
            $data['city'] ?? '',
            $data['region'] ?? '',
            $data['country_name'] ?? ''
        ]);
        
        return [
            'lat' => floatval($data['latitude']),
            'lng' => floatval($data['longitude']),
            'address' => implode(', ', $address_parts)
        ];
    }
    
    /**
     * AJAX: Obter dados de um núcleo específico
     */
    public function ajax_get_nucleo_data() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $nucleo_id = intval($_POST['nucleo_id']);
        
        if (empty($nucleo_id)) {
            wp_send_json_error(__('ID do núcleo é obrigatório', 'quilombo-lab'));
        }
        
        global $wpdb;
        
        $nucleo = $wpdb->get_row($wpdb->prepare("
            SELECT i.*, im.endereco_fisico, im.lat, im.lng
            FROM {$wpdb->prefix}ql_instances i
            LEFT JOIN {$wpdb->prefix}ql_instance_meta im ON i.id = im.instance_id
            WHERE i.id = %d AND i.type = 'nucleo' AND i.status = 'active'
        ", $nucleo_id));
        
        if (!$nucleo) {
            wp_send_json_error(__('Núcleo não encontrado', 'quilombo-lab'));
        }
        
        // Se não tem coordenadas, tentar geocodificar
        if ((empty($nucleo->lat) || empty($nucleo->lng)) && !empty($nucleo->endereco_fisico)) {
            $geocoding_results = $this->geocode_address($nucleo->endereco_fisico);
            if (!empty($geocoding_results)) {
                $nucleo->lat = $geocoding_results[0]['lat'];
                $nucleo->lng = $geocoding_results[0]['lon'];
                
                // Salvar coordenadas
                $wpdb->update(
                    $wpdb->prefix . 'ql_instance_meta',
                    ['lat' => $nucleo->lat, 'lng' => $nucleo->lng],
                    ['instance_id' => $nucleo_id],
                    ['%f', '%f'],
                    ['%d']
                );
            }
        }
        
        wp_send_json_success([
            'id' => $nucleo->id,
            'nome' => $nucleo->nome,
            'endereco' => $nucleo->endereco_fisico,
            'lat' => floatval($nucleo->lat),
            'lng' => floatval($nucleo->lng)
        ]);
    }
    
    /**
     * AJAX: Criar novo território
     */
    public function ajax_create_territory() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Sem permissão para criar territórios', 'quilombo-lab'));
        }
        
        $territory_name = sanitize_text_field($_POST['territory_name']);
        $territory_type = sanitize_text_field($_POST['territory_type']);
        $location_method = sanitize_text_field($_POST['location_method']);
        $territory_lat = floatval($_POST['territory_lat']);
        $territory_lng = floatval($_POST['territory_lng']);
        
        if (empty($territory_name)) {
            wp_send_json_error(__('Nome do território é obrigatório', 'quilombo-lab'));
        }
        
        if (empty($territory_lat) || empty($territory_lng)) {
            wp_send_json_error(__('Coordenadas são obrigatórias. Defina uma localização.', 'quilombo-lab'));
        }
        
        // Preparar dados do endereço
        $address = '';
        $nucleo_id = null;
        
        if ($location_method === 'nucleo' && !empty($_POST['territory_nucleo'])) {
            $nucleo_id = intval($_POST['territory_nucleo']);
            
            // Obter dados do núcleo
            global $wpdb;
            $nucleo = $wpdb->get_row($wpdb->prepare("
                SELECT i.nome, im.endereco_fisico 
                FROM {$wpdb->prefix}ql_instances i
                LEFT JOIN {$wpdb->prefix}ql_instance_meta im ON i.id = im.instance_id
                WHERE i.id = %d
            ", $nucleo_id));
            
            if ($nucleo) {
                $address = $nucleo->endereco_fisico ?: $nucleo->nome;
            }
        } elseif ($location_method === 'address' && !empty($_POST['territory_address'])) {
            $address = sanitize_text_field($_POST['territory_address']);
        } else {
            // Usar reverse geocoding para obter endereço das coordenadas
            $address = $this->reverse_geocode($territory_lat, $territory_lng);
        }
        
        // Criar o território
        $territory_id = wp_insert_post([
            'post_title' => $territory_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'ql_territory',
            'meta_input' => [
                '_territory_lat' => $territory_lat,
                '_territory_lng' => $territory_lng,
                '_territory_address' => $address,
                '_territory_auto_assign' => '0',
                '_territory_creation_method' => $location_method
            ]
        ]);
        
        if (is_wp_error($territory_id)) {
            wp_send_json_error(__('Erro ao criar território', 'quilombo-lab'));
        }
        
        // Atribuir tipo de território
        if (!empty($territory_type)) {
            wp_set_object_terms($territory_id, $territory_type, 'territory_type');
        }
        
        // Associar núcleo se aplicável
        if ($nucleo_id) {
            update_post_meta($territory_id, '_territory_nucleo_id', $nucleo_id);
        }
        
        wp_send_json_success([
            'territory_id' => $territory_id,
            'message' => __('Território criado com sucesso!', 'quilombo-lab')
        ]);
    }
    
    /**
     * Geocodificação reversa - obter endereço a partir de coordenadas
     */
    private function reverse_geocode($lat, $lng) {
        $url = "https://nominatim.openstreetmap.org/reverse?" . http_build_query([
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'accept-language' => 'pt-BR,pt,en'
        ]);
        
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'user-agent' => 'QuilomboLab/1.0 (WordPress Plugin)'
        ]);
        
        if (is_wp_error($response)) {
            return "Lat: {$lat}, Lng: {$lng}";
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['display_name'])) {
            return $data['display_name'];
        }
        
        return "Lat: {$lat}, Lng: {$lng}";
    }
    
    /**
     * AJAX: Obter dados de um território específico
     */
    public function ajax_get_territory_data() {
        check_ajax_referer('ql_territories_nonce', 'nonce');
        
        $territory_id = intval($_POST['territory_id']);
        
        if (!$territory_id) {
            wp_send_json_error(__('ID do território inválido', 'quilombo-lab'));
        }
        
        $territory = get_post($territory_id);
        
        if (!$territory || $territory->post_type !== 'ql_territory') {
            wp_send_json_error(__('Território não encontrado', 'quilombo-lab'));
        }
        
        $lat = get_post_meta($territory_id, '_territory_lat', true);
        $lng = get_post_meta($territory_id, '_territory_lng', true);
        $address = get_post_meta($territory_id, '_territory_address', true);
        $bounds = get_post_meta($territory_id, '_territory_bounds', true);
        
        $data = [
            'id' => $territory_id,
            'name' => $territory->post_title,
            'lat' => $lat ? floatval($lat) : null,
            'lng' => $lng ? floatval($lng) : null,
            'address' => $address,
            'bounds' => $bounds,
            'url' => get_permalink($territory_id),
            'color' => '#3498db' // Cor padrão, pode ser customizada
        ];
        
        wp_send_json_success($data);
    }
    
    /**
     * Shortcode para mapa de território
     */
    public function territory_map_shortcode($atts) {
        $atts = shortcode_atts([
            'territory_id' => '',
            'height' => '400px',
            'show_controls' => 'false',
            'user_location' => 'false'
        ], $atts);
        
        $map_id = 'ql-map-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($map_id); ?>" style="height: <?php echo esc_attr($atts['height']); ?>; width: 100%;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            var map = L.map('<?php echo $map_id; ?>').setView([-15.7942287, -47.8821945], 4);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            <?php if ($atts['territory_id']): ?>
                // Carregar território específico
                var territoryId = <?php echo intval($atts['territory_id']); ?>;
                // Implementar carregamento de território
            <?php endif; ?>
            
            <?php if ($atts['user_location'] === 'true'): ?>
                // Mostrar localização do usuário
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        var lat = position.coords.latitude;
                        var lng = position.coords.longitude;
                        
                        map.setView([lat, lng], 15);
                        L.marker([lat, lng])
                            .addTo(map)
                            .bindPopup('<?php _e('Sua localização', 'quilombo-lab'); ?>');
                    });
                }
            <?php endif; ?>
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode para seletor de localização do usuário
     * Mostra mapa com localização, territórios hierárquicos e comunidades disponíveis
     */
    public function user_location_selector_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect' => '',
            'show_communities' => 'true'
        ], $atts);

        if (!is_user_logged_in()) {
            return '<p>' . __('Você precisa estar logado para definir sua localização.', 'quilombo-lab') . '</p>';
        }

        $user_id = get_current_user_id();
        $user_lat = get_user_meta($user_id, 'user_lat', true);
        $user_lng = get_user_meta($user_id, 'user_lng', true);
        $user_address = get_user_meta($user_id, 'user_address', true);

        // Obter todos os territórios do usuário
        $user_territories = $this->get_user_all_territories($user_id);
        $user_communities = $this->get_user_available_communities($user_id);

        ob_start();
        ?>
        <div class="ql-user-location-selector">
            <!-- Seção: Meus Territórios -->
            <div class="ql-my-territories-section" style="margin-bottom: 30px;">
                <h3><?php _e('Meus Territórios', 'quilombo-lab'); ?></h3>

                <?php if (!empty($user_lat) && !empty($user_lng)): ?>
                    <div class="current-location-info" style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #2196f3;">
                        <p style="margin: 0 0 5px 0;"><strong>📍 <?php _e('Sua localização atual:', 'quilombo-lab'); ?></strong></p>
                        <p style="margin: 0; color: #666;"><?php echo esc_html($user_address ?: sprintf('Lat: %.6f, Lng: %.6f', $user_lat, $user_lng)); ?></p>
                    </div>

                    <?php if (!empty($user_territories)): ?>
                        <div class="territories-hierarchy" style="margin-bottom: 20px;">
                            <h4 style="margin-bottom: 10px;"><?php _e('Você faz parte dos seguintes territórios:', 'quilombo-lab'); ?></h4>
                            <div class="territory-cards" style="display: flex; flex-wrap: wrap; gap: 10px;">
                                <?php foreach ($user_territories as $territory): ?>
                                    <div class="territory-card" style="
                                        background: <?php echo $territory['is_default'] ? '#e8f5e9' : '#fff'; ?>;
                                        border: 1px solid <?php echo $territory['is_default'] ? '#4caf50' : '#ddd'; ?>;
                                        border-radius: 8px;
                                        padding: 12px 16px;
                                        min-width: 150px;
                                        flex: 1;
                                        max-width: 250px;
                                    ">
                                        <span class="territory-type-badge" style="
                                            display: inline-block;
                                            background: #607d8b;
                                            color: white;
                                            font-size: 10px;
                                            padding: 2px 6px;
                                            border-radius: 3px;
                                            text-transform: uppercase;
                                            margin-bottom: 5px;
                                        "><?php echo esc_html($territory['type_label']); ?></span>
                                        <h5 style="margin: 5px 0; font-size: 14px;">
                                            <a href="<?php echo esc_url($territory['url']); ?>" target="_blank" style="text-decoration: none; color: #333;">
                                                <?php echo esc_html($territory['name']); ?>
                                            </a>
                                        </h5>
                                        <?php if (!empty($territory['address']) && $territory['address'] !== 'Planeta Terra'): ?>
                                            <small style="color: #666;"><?php echo esc_html($territory['address']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($atts['show_communities'] === 'true' && !empty($user_communities)): ?>
                            <div class="available-communities" style="margin-bottom: 20px;">
                                <h4 style="margin-bottom: 10px;"><?php _e('Comunidades disponíveis para você:', 'quilombo-lab'); ?></h4>
                                <div class="community-list" style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php foreach ($user_communities as $community): ?>
                                        <div class="community-card" style="
                                            background: #fff3e0;
                                            border: 1px solid #ff9800;
                                            border-radius: 8px;
                                            padding: 12px 16px;
                                            min-width: 200px;
                                        ">
                                            <h5 style="margin: 0 0 5px 0; font-size: 14px; color: #e65100;">
                                                🏘️ <?php echo esc_html($community['name']); ?>
                                            </h5>
                                            <small style="color: #666;">
                                                <?php _e('Território:', 'quilombo-lab'); ?> <?php echo esc_html($community['territory_name']); ?>
                                            </small>
                                            <?php if (!empty($community['description'])): ?>
                                                <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;"><?php echo esc_html(wp_trim_words($community['description'], 15)); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #666;"><?php _e('Nenhum território específico encontrado para sua localização. Você faz parte do Território Mundo.', 'quilombo-lab'); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-location-warning" style="background: #fff3e0; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-left: 4px solid #ff9800;">
                        <p style="margin: 0;">⚠️ <?php _e('Você ainda não definiu sua localização. Defina abaixo para visualizar seus territórios e comunidades disponíveis.', 'quilombo-lab'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Seção: Definir/Atualizar Localização -->
            <div class="ql-set-location-section" style="background: #f5f5f5; padding: 20px; border-radius: 8px;">
                <h3><?php echo $user_lat ? __('Atualizar Minha Localização', 'quilombo-lab') : __('Definir Minha Localização', 'quilombo-lab'); ?></h3>

                <div class="location-methods" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                        <!-- Método 1: Por endereço -->
                        <div class="method-card" style="flex: 1; min-width: 250px; background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                            <h5 style="margin: 0 0 10px 0;">🔍 <?php _e('Por endereço', 'quilombo-lab'); ?></h5>
                            <input type="text"
                                   id="user_address"
                                   name="user_address"
                                   class="regular-text"
                                   style="width: 100%; margin-bottom: 8px;"
                                   value="<?php echo esc_attr($user_address); ?>"
                                   placeholder="<?php _e('Rua, bairro, cidade...', 'quilombo-lab'); ?>" />
                            <button type="button" id="locate_by_address_btn" class="button" style="width: 100%;">
                                <?php _e('Buscar', 'quilombo-lab'); ?>
                            </button>
                        </div>

                        <!-- Método 2: Por GPS -->
                        <div class="method-card" style="flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                            <h5 style="margin: 0 0 10px 0;">📍 <?php _e('GPS do navegador', 'quilombo-lab'); ?></h5>
                            <button type="button" id="locate_by_gps_btn" class="button" style="width: 100%;">
                                <?php _e('Detectar Localização', 'quilombo-lab'); ?>
                            </button>
                            <small style="display: block; color: #666; margin-top: 5px; font-size: 11px;"><?php _e('Requer permissão', 'quilombo-lab'); ?></small>
                        </div>

                        <!-- Método 3: Por IP -->
                        <div class="method-card" style="flex: 1; min-width: 200px; background: white; padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                            <h5 style="margin: 0 0 10px 0;">🌐 <?php _e('Por IP (aproximado)', 'quilombo-lab'); ?></h5>
                            <button type="button" id="locate_by_ip_btn" class="button" style="width: 100%;">
                                <?php _e('Localizar por IP', 'quilombo-lab'); ?>
                            </button>
                            <small style="display: block; color: #666; margin-top: 5px; font-size: 11px;"><?php _e('Menos preciso', 'quilombo-lab'); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Mapa -->
                <div id="user-location-map" class="ql-territory-map" style="height: 400px; margin: 20px 0; border-radius: 8px; <?php echo ($user_lat && $user_lng) ? '' : 'display: none;'; ?>"></div>

                <!-- Info da localização selecionada -->
                <div id="location-info" style="<?php echo ($user_lat && $user_lng) ? '' : 'display: none;'; ?> background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 15px 0; border: 1px solid #4caf50;">
                    <h5 style="margin: 0 0 10px 0;"><?php _e('Localização Selecionada:', 'quilombo-lab'); ?></h5>
                    <p id="selected-address" style="margin: 0 0 5px 0;"><?php echo esc_html($user_address); ?></p>
                    <p style="margin: 0 0 15px 0; color: #666; font-size: 12px;">
                        <strong><?php _e('Coordenadas:', 'quilombo-lab'); ?></strong>
                        <span id="selected-coords"><?php echo $user_lat ? sprintf('Lat: %.6f, Lng: %.6f', $user_lat, $user_lng) : ''; ?></span>
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" id="save_location_btn" class="button button-primary">
                            <?php _e('✅ Salvar Localização', 'quilombo-lab'); ?>
                        </button>
                        <button type="button" id="cancel_location_btn" class="button">
                            <?php _e('❌ Cancelar', 'quilombo-lab'); ?>
                        </button>
                    </div>
                </div>

                <input type="hidden" id="selected_lat" name="selected_lat" value="<?php echo esc_attr($user_lat); ?>" />
                <input type="hidden" id="selected_lng" name="selected_lng" value="<?php echo esc_attr($user_lng); ?>" />
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Variáveis globais para o mapa do usuário (usando window para acesso global)
            window.qlUserMapData = window.qlUserMapData || {
                map: null,
                marker: null,
                latLng: null,
                otherUsersLayer: null,
                territoriesLayer: null
            };

            // Ícones personalizados (definidos primeiro)
            var otherUserIcon = L.divIcon({
                className: 'ql-other-user-marker',
                html: '<div style="background: #9b59b6; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3); opacity: 0.8;"></div>',
                iconSize: [10, 10],
                iconAnchor: [5, 5]
            });

            var currentUserIcon = L.divIcon({
                className: 'ql-current-user-marker',
                html: '<div style="background: #27ae60; width: 16px; height: 16px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.4);"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            });

            // Listener para quando a aba "Minha Localização" ficar visível
            $(document).on('ql-user-location-tab-visible', function() {
                if (window.qlUserMapData && window.qlUserMapData.map) {
                    setTimeout(function() {
                        window.qlUserMapData.map.invalidateSize();
                    }, 150);
                }
            });

            function initUserMap(lat, lng, address) {
                window.qlUserMapData.latLng = [lat, lng];

                $('#user-location-map').show();
                if (window.qlUserMapData.map) {
                    window.qlUserMapData.map.remove();
                }

                window.qlUserMapData.map = L.map('user-location-map').setView([lat, lng], 12);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(window.qlUserMapData.map);

                // Corrigir problema de renderização quando o mapa é inicializado em container oculto
                setTimeout(function() {
                    if (window.qlUserMapData.map) {
                        window.qlUserMapData.map.invalidateSize();
                    }
                }, 200);

                // Marcador do usuário atual (destacado)
                window.qlUserMapData.marker = L.marker([lat, lng], {icon: currentUserIcon, zIndexOffset: 1000})
                    .addTo(window.qlUserMapData.map)
                    .bindPopup('<strong><?php _e("Sua localização", "quilombo-lab"); ?></strong>');

                // Mostrar informações da localização
                $('#selected-address').text(address || 'Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6));
                $('#selected-coords').text('Lat: ' + lat.toFixed(6) + ', Lng: ' + lng.toFixed(6));
                $('#selected_lat').val(lat);
                $('#selected_lng').val(lng);
                $('#location-info').show();

                // Carregar territórios do usuário no mapa
                loadUserTerritoriesOnMap();

                // Carregar outros usuários no mapa
                loadOtherUsersOnMap();
            }

            // Cores para diferentes tipos de territórios
            var territoryColors = {
                'mundo': '#2196f3',
                'continente': '#9c27b0',
                'pais': '#e91e63',
                'regiao': '#673ab7',
                'estado': '#3f51b5',
                'cidade': '#00bcd4',
                'bairro': '#009688',
                'favela': '#ff9800',
                'vila': '#8bc34a',
                'rua': '#cddc39',
                'edificio': '#ffc107',
                'residencia': '#ff5722',
                'sitio': '#795548',
                'aldeia': '#607d8b'
            };

            function loadUserTerritoriesOnMap() {
                if (!window.qlUserMapData.map) return;

                // Remover camada anterior se existir
                if (window.qlUserMapData.territoriesLayer) {
                    window.qlUserMapData.map.removeLayer(window.qlUserMapData.territoriesLayer);
                }

                window.qlUserMapData.territoriesLayer = L.layerGroup().addTo(window.qlUserMapData.map);

                // Carregar territórios do usuário atual
                $.post(ql_territories.ajax_url, {
                    action: 'ql_get_user_territories',
                    nonce: ql_territories.nonce
                }, function(response) {
                    if (response.success && response.data && response.data.territories) {
                        response.data.territories.forEach(function(territory) {
                            // Se território tem bounds, desenhar polígono
                            if (territory.bounds) {
                                try {
                                    var bounds = typeof territory.bounds === 'string' ? JSON.parse(territory.bounds) : territory.bounds;
                                    if (bounds && bounds.geometry) {
                                        var color = territoryColors[territory.type] || '#3498db';
                                        var geoJsonLayer = L.geoJSON(bounds, {
                                            style: {
                                                fillColor: color,
                                                weight: 2,
                                                opacity: 0.8,
                                                color: color,
                                                dashArray: '3',
                                                fillOpacity: 0.15
                                            }
                                        }).bindPopup('<div class="ql-territory-popup"><strong>' + territory.type_label + '</strong><br>' + territory.name + '</div>');

                                        window.qlUserMapData.territoriesLayer.addLayer(geoJsonLayer);
                                    }
                                } catch (e) {
                                    console.warn('Erro ao carregar bounds do território:', territory.name, e);
                                }
                            }

                            // Adicionar marcador do centro do território (se não for território mundo)
                            if (territory.lat && territory.lng && !territory.is_default) {
                                var territoryIcon = L.divIcon({
                                    className: 'ql-territory-center-marker',
                                    html: '<div style="background: ' + (territoryColors[territory.type] || '#3498db') + '; width: 8px; height: 8px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>',
                                    iconSize: [8, 8],
                                    iconAnchor: [4, 4]
                                });

                                L.marker([territory.lat, territory.lng], {icon: territoryIcon})
                                    .addTo(window.qlUserMapData.territoriesLayer)
                                    .bindPopup('<div class="ql-territory-popup"><strong>' + territory.type_label + '</strong><br>' + territory.name + '</div>');
                            }
                        });

                        console.log('Territórios do usuário carregados:', response.data.territories.length);
                    }
                });
            }

            function loadOtherUsersOnMap() {
                if (!window.qlUserMapData.map) return;

                // Remover camada anterior se existir
                if (window.qlUserMapData.otherUsersLayer) {
                    window.qlUserMapData.map.removeLayer(window.qlUserMapData.otherUsersLayer);
                }

                window.qlUserMapData.otherUsersLayer = L.layerGroup().addTo(window.qlUserMapData.map);

                $.post(ql_territories.ajax_url, {
                    action: 'ql_get_all_users_locations',
                    nonce: ql_territories.nonce
                }, function(response) {
                    if (response.success && response.data && response.data.users) {
                        var usersCount = 0;
                        response.data.users.forEach(function(user) {
                            // Não adicionar o usuário atual (já tem marcador destacado)
                            if (user.is_current_user) return;

                            if (user.lat && user.lng) {
                                var popupContent = '<div class="ql-user-popup">';
                                popupContent += '<strong>👤 ' + user.name + '</strong>';
                                if (user.address) {
                                    popupContent += '<br><small>📍 ' + user.address + '</small>';
                                }
                                if (user.territory) {
                                    popupContent += '<br><small>🗺️ ' + user.territory + '</small>';
                                }
                                popupContent += '</div>';

                                L.marker([user.lat, user.lng], {icon: otherUserIcon})
                                    .addTo(window.qlUserMapData.otherUsersLayer)
                                    .bindPopup(popupContent);
                                usersCount++;
                            }
                        });

                        console.log('Outros usuários carregados no mapa: ' + usersCount);
                    }
                });
            }
            
            // Método 1: Buscar por endereço
            $('#locate_by_address_btn').on('click', function() {
                var address = $('#user_address').val();
                if (!address) {
                    alert('<?php _e('Digite um endereço', 'quilombo-lab'); ?>');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('🔄 Buscando...');
                
                $.post(ql_territories.ajax_url, {
                    action: 'ql_search_address',
                    address: address,
                    nonce: ql_territories.nonce
                }, function(response) {
                    if (response.success && response.data.length > 0) {
                        var result = response.data[0];
                        var lat = parseFloat(result.lat);
                        var lng = parseFloat(result.lon);
                        
                        initUserMap(lat, lng, result.display_name);
                    } else {
                        alert('<?php _e('Endereço não encontrado', 'quilombo-lab'); ?>');
                    }
                }).fail(function() {
                    alert('<?php _e('Erro ao buscar endereço', 'quilombo-lab'); ?>');
                }).always(function() {
                    $btn.prop('disabled', false).text('🔍 Buscar Endereço');
                });
            });
            
            // Método 2: Geolocalização do navegador
            $('#locate_by_gps_btn').on('click', function() {
                if (!navigator.geolocation) {
                    alert('<?php _e('Geolocalização não suportada pelo navegador', 'quilombo-lab'); ?>');
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).text('📍 Localizando...');
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        var lat = position.coords.latitude;
                        var lng = position.coords.longitude;
                        
                        // Buscar endereço via reverse geocoding
                        $.get('https://nominatim.openstreetmap.org/reverse', {
                            lat: lat,
                            lon: lng,
                            format: 'json'
                        }, function(data) {
                            var address = data && data.display_name ? data.display_name : 'Localização detectada';
                            $('#user_address').val(address);
                            initUserMap(lat, lng, address);
                        }).fail(function() {
                            initUserMap(lat, lng, 'Localização detectada pelo GPS');
                        });
                    },
                    function(error) {
                        var messages = {
                            1: '<?php _e('Permissão negada para acessar localização', 'quilombo-lab'); ?>',
                            2: '<?php _e('Posição não disponível', 'quilombo-lab'); ?>',
                            3: '<?php _e('Timeout ao obter localização', 'quilombo-lab'); ?>'
                        };
                        alert(messages[error.code] || '<?php _e('Erro ao obter localização', 'quilombo-lab'); ?>');
                        $btn.prop('disabled', false).text('📍 Detectar Minha Localização');
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000
                    }
                );
            });
            
            // Método 3: Localização por IP
            $('#locate_by_ip_btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('🌐 Detectando...');
                
                // Usar AJAX para o backend WordPress que implementa múltiplos serviços
                $.post(ql_territories.ajax_url, {
                    action: 'ql_get_location_by_ip',
                    nonce: ql_territories.nonce
                }, function(response) {
                    if (response && response.success && response.data) {
                        var data = response.data;
                        var lat = parseFloat(data.lat);
                        var lng = parseFloat(data.lng);
                        var address = data.address || 'Localização detectada por IP';
                        
                        $('#user_address').val(address);
                        initUserMap(lat, lng, address + ' (via IP)');
                    } else {
                        // Fallback direto no frontend
                        tryIPGeolocationFallback();
                    }
                }).fail(function() {
                    tryIPGeolocationFallback();
                }).always(function() {
                    $btn.prop('disabled', false).text('🌐 Usar Localização por IP');
                });
                
                function tryIPGeolocationFallback() {
                    // Usar serviço direto HTTPS confiável
                    $.getJSON('https://ipapi.co/json/', function(data) {
                        if (data && data.latitude && data.longitude && !data.error) {
                            var lat = parseFloat(data.latitude);
                            var lng = parseFloat(data.longitude);
                            var address = [data.city, data.region, data.country_name].filter(Boolean).join(', ');
                            
                            $('#user_address').val(address);
                            initUserMap(lat, lng, address + ' (via IP)');
                        } else {
                            // Segundo fallback
                            $.getJSON('https://api.ipify.org?format=json', function(ipData) {
                                if (ipData && ipData.ip) {
                                    // Usar httpbin.org/ip que retorna info confiável
                                    $.ajax({
                                        url: 'https://httpbin.org/ip',
                                        dataType: 'json',
                                        timeout: 5000
                                    }).done(function(result) {
                                        if (result && result.origin) {
                                            // IP detectado, usar localização padrão Brasil
                                            var address = 'Brasil (localização aproximada)';
                                            $('#user_address').val(address);
                                            initUserMap(-15.7942287, -47.8821945, address);
                                        } else {
                                            alert('<?php _e('Não foi possível determinar localização', 'quilombo-lab'); ?>');
                                        }
                                    }).fail(function() {
                                        alert('<?php _e('Serviços de localização por IP indisponíveis', 'quilombo-lab'); ?>');
                                    });
                                } else {
                                    alert('<?php _e('Não foi possível obter informações de rede', 'quilombo-lab'); ?>');
                                }
                            }).fail(function() {
                                alert('<?php _e('Serviços de localização por IP indisponíveis', 'quilombo-lab'); ?>');
                            });
                        }
                    }).fail(function() {
                        alert('<?php _e('Serviços de localização por IP indisponíveis', 'quilombo-lab'); ?>');
                    });
                }
            });
            
            // Salvar localização
            $('#save_location_btn').on('click', function() {
                if (!window.qlUserMapData.latLng) {
                    alert('<?php _e('Selecione uma localização primeiro', 'quilombo-lab'); ?>');
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('💾 Salvando...');

                $.post(ql_territories.ajax_url, {
                    action: 'ql_save_user_location',
                    lat: window.qlUserMapData.latLng[0],
                    lng: window.qlUserMapData.latLng[1],
                    address: $('#user_address').val(),
                    nonce: ql_territories.nonce
                }, function(response) {
                    if (response.success) {
                        if (response.data.territory_assigned) {
                            alert('✅ Localização salva! Você foi atribuído ao território: ' + response.data.territory_name);
                        } else {
                            alert('✅ Localização salva com sucesso!');
                        }
                        
                        <?php if ($atts['redirect']): ?>
                        window.location.href = '<?php echo esc_url($atts['redirect']); ?>';
                        <?php else: ?>
                        location.reload();
                        <?php endif; ?>
                    } else {
                        alert(response.data || '<?php _e('Erro ao salvar localização', 'quilombo-lab'); ?>');
                    }
                }).fail(function() {
                    alert('<?php _e('Erro ao salvar localização', 'quilombo-lab'); ?>');
                }).always(function() {
                    $btn.prop('disabled', false).text('✅ Salvar Esta Localização');
                });
            });
            
            // Cancelar seleção
            $('#cancel_location_btn').on('click', function() {
                $('#location-info').hide();
                $('#user-location-map').hide();
                if (window.qlUserMapData.map) {
                    window.qlUserMapData.map.remove();
                    window.qlUserMapData.map = null;
                }
                window.qlUserMapData.latLng = null;
                window.qlUserMapData.marker = null;
            });

            // Inicializar mapa se já há localização salva
            <?php if ($user_lat && $user_lng): ?>
            initUserMap(<?php echo $user_lat; ?>, <?php echo $user_lng; ?>, '<?php echo esc_js($user_address); ?>');
            <?php endif; ?>
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Auto-atribuir usuário a território baseado na localização
     */
    public function auto_assign_user_territory($user_id) {
        $user_lat = get_user_meta($user_id, 'user_lat', true);
        $user_lng = get_user_meta($user_id, 'user_lng', true);
        
        if ($user_lat && $user_lng) {
            $territory_id = $this->find_territory_by_coordinates($user_lat, $user_lng);
            
            if ($territory_id) {
                update_user_meta($user_id, 'user_territory_id', $territory_id);
            }
        }
    }
    
    /**
     * Atualizar território do usuário
     */
    public function update_user_territory($user_id) {
        $this->auto_assign_user_territory($user_id);
    }
    
    /**
     * Obter o ID do território mundo padrão
     *
     * @return int|null ID do território mundo ou null se não existir
     */
    public function get_world_territory_id() {
        // Primeiro tenta buscar pelo meta _territory_is_default
        $world_territory = get_posts([
            'post_type' => 'ql_territory',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_territory_is_default',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (!empty($world_territory)) {
            return $world_territory[0];
        }

        // Fallback: buscar pelo tipo default_world
        $world_territory = get_posts([
            'post_type' => 'ql_territory',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_territory_type',
                    'value' => 'default_world',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        if (!empty($world_territory)) {
            return $world_territory[0];
        }

        // Fallback: buscar pelo nome
        $world_territory = get_posts([
            'post_type' => 'ql_territory',
            'post_status' => 'publish',
            'title' => 'Território Mundo',
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);

        return !empty($world_territory) ? $world_territory[0] : null;
    }

    /**
     * Obter o ID da comunidade mundial padrão
     *
     * @return int|null ID da comunidade mundial ou null se não existir
     */
    public function get_world_community_id() {
        global $wpdb;

        // Primeiro tenta buscar pelo metadata is_default_community
        $world_community = $wpdb->get_var("
            SELECT id FROM {$wpdb->prefix}ql_instances
            WHERE type = 'comunidade'
            AND JSON_EXTRACT(metadata, '$.is_default_community') = true
            LIMIT 1
        ");

        if ($world_community) {
            return (int) $world_community;
        }

        // Fallback: buscar pelo nome
        $world_community = $wpdb->get_var("
            SELECT id FROM {$wpdb->prefix}ql_instances
            WHERE name = 'Comunidade Mundial'
            AND type = 'comunidade'
            LIMIT 1
        ");

        return $world_community ? (int) $world_community : null;
    }

    /**
     * Atribuir usuário aos padrões mundiais (território mundo + comunidade mundial)
     */
    public function assign_user_to_world_defaults($user_id) {
        global $wpdb;

        // 1. ATRIBUIR AO TERRITÓRIO MUNDO
        // Sempre atribui ao território mundo se o usuário ainda não tem território
        $current_territory = get_user_meta($user_id, 'user_territory_id', true);

        if (empty($current_territory)) {
            $world_territory_id = $this->get_world_territory_id();

            if ($world_territory_id) {
                update_user_meta($user_id, 'user_territory_id', $world_territory_id);
                error_log('QL Territories: Usuário ' . $user_id . ' atribuído ao Território Mundo (ID: ' . $world_territory_id . ')');
            } else {
                error_log('QL Territories: AVISO - Território Mundo não encontrado ao tentar atribuir usuário ' . $user_id);
            }
        }

        // 2. ADICIONAR COMO MEMBRO DO TERRITÓRIO MUNDO
        // Todos os usuários devem ser membros do território mundo
        $world_territory_id = $this->get_world_territory_id();

        if ($world_territory_id) {
            $territory_member_key = 'ql_territory_member_' . $world_territory_id;
            $is_territory_member = get_user_meta($user_id, $territory_member_key, true);

            if (empty($is_territory_member)) {
                update_user_meta($user_id, $territory_member_key, '1');
                update_user_meta($user_id, 'ql_territory_member_since_' . $world_territory_id, current_time('mysql'));
                error_log('QL Territories: Usuário ' . $user_id . ' adicionado como membro do Território Mundo');
            }
        }

        // 3. ATRIBUIR À COMUNIDADE MUNDIAL
        $world_community_id = $this->get_world_community_id();

        if ($world_community_id) {
            // Verificar se já é membro
            $existing_membership = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}ql_instance_members
                WHERE instance_id = %d AND user_id = %d
                LIMIT 1
            ", $world_community_id, $user_id));

            if (!$existing_membership) {
                $inserted = $wpdb->insert(
                    $wpdb->prefix . 'ql_instance_members',
                    [
                        'instance_id' => $world_community_id,
                        'user_id' => $user_id,
                        'role' => 'member',
                        'status' => 'active',
                        'joined_at' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%s', '%s']
                );

                if ($inserted) {
                    error_log('QL Territories: Usuário ' . $user_id . ' atribuído à Comunidade Mundial (ID: ' . $world_community_id . ')');
                } else {
                    error_log('QL Territories: ERRO ao inserir usuário ' . $user_id . ' na Comunidade Mundial');
                }
            }
        } else {
            error_log('QL Territories: AVISO - Comunidade Mundial não encontrada ao tentar atribuir usuário ' . $user_id);
        }
    }

    /**
     * Atribuir todos os usuários existentes ao território mundo e comunidade mundial
     *
     * Esta função deve ser executada uma vez para garantir que todos os usuários
     * existentes sejam atribuídos aos padrões mundiais.
     *
     * @param bool $force_reassign Se true, reatribui mesmo usuários que já têm território
     * @return array Estatísticas da operação
     */
    public function assign_all_existing_users_to_world_defaults($force_reassign = false) {
        global $wpdb;

        $stats = [
            'total_users' => 0,
            'territory_assigned' => 0,
            'territory_member_added' => 0,
            'community_assigned' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        // Obter IDs do território mundo e comunidade mundial
        $world_territory_id = $this->get_world_territory_id();
        $world_community_id = $this->get_world_community_id();

        if (!$world_territory_id) {
            $stats['errors'][] = 'Território Mundo não encontrado';
            error_log('QL Territories: Território Mundo não encontrado para atribuição em massa');
        }

        if (!$world_community_id) {
            $stats['errors'][] = 'Comunidade Mundial não encontrada';
            error_log('QL Territories: Comunidade Mundial não encontrada para atribuição em massa');
        }

        // Obter todos os usuários
        $users = get_users(['fields' => 'ID']);
        $stats['total_users'] = count($users);

        foreach ($users as $user_id) {
            // 1. Atribuir território mundo se não tem território ou se force_reassign
            $current_territory = get_user_meta($user_id, 'user_territory_id', true);

            if ($world_territory_id && (empty($current_territory) || $force_reassign)) {
                update_user_meta($user_id, 'user_territory_id', $world_territory_id);
                $stats['territory_assigned']++;
            }

            // 2. Adicionar como membro do território mundo
            if ($world_territory_id) {
                $territory_member_key = 'ql_territory_member_' . $world_territory_id;
                $is_territory_member = get_user_meta($user_id, $territory_member_key, true);

                if (empty($is_territory_member)) {
                    update_user_meta($user_id, $territory_member_key, '1');
                    update_user_meta($user_id, 'ql_territory_member_since_' . $world_territory_id, current_time('mysql'));
                    $stats['territory_member_added']++;
                }
            }

            // 3. Adicionar à comunidade mundial se não é membro
            if ($world_community_id) {
                $existing_membership = $wpdb->get_var($wpdb->prepare("
                    SELECT id FROM {$wpdb->prefix}ql_instance_members
                    WHERE instance_id = %d AND user_id = %d
                    LIMIT 1
                ", $world_community_id, $user_id));

                if (!$existing_membership) {
                    $inserted = $wpdb->insert(
                        $wpdb->prefix . 'ql_instance_members',
                        [
                            'instance_id' => $world_community_id,
                            'user_id' => $user_id,
                            'role' => 'member',
                            'status' => 'active',
                            'joined_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s']
                    );

                    if ($inserted) {
                        $stats['community_assigned']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            }
        }

        error_log('QL Territories: Atribuição em massa concluída - ' . json_encode($stats));

        return $stats;
    }

    /**
     * Verificar e garantir que o território mundo e comunidade mundial existam
     *
     * @return array IDs do território mundo e comunidade mundial
     */
    public function ensure_world_defaults_exist() {
        // Forçar criação se não existirem
        $this->create_world_territory();
        $this->create_world_community();

        return [
            'territory_id' => $this->get_world_territory_id(),
            'community_id' => $this->get_world_community_id()
        ];
    }

    /**
     * AJAX: Atribuir todos os usuários aos padrões mundiais
     */
    public function ajax_assign_all_users_world_defaults() {
        // Verificar nonce
        check_ajax_referer('ql_admin_territories_nonce', 'nonce');

        // Verificar permissões (somente administradores)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'quilombo-lab'));
        }

        // Executar a atribuição em massa
        $stats = $this->assign_all_existing_users_to_world_defaults(false);

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Verificar e criar padrões mundiais
     */
    public function ajax_ensure_world_defaults() {
        // Verificar nonce
        check_ajax_referer('ql_admin_territories_nonce', 'nonce');

        // Verificar permissões (somente administradores)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'quilombo-lab'));
        }

        // Garantir que os padrões existam
        $result = $this->ensure_world_defaults_exist();

        wp_send_json_success($result);
    }

    /**
     * AJAX: Salvar endereço do coletivo
     */
    public function ajax_save_collective_address() {
        // Verificar nonce
        check_ajax_referer('ql_admin_territories_nonce', 'nonce');

        // Verificar permissões (somente administradores)
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permissão negada', 'quilombo-lab'));
        }

        // Obter dados
        $address = sanitize_text_field($_POST['address'] ?? '');
        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);

        if (empty($address) || !$lat || !$lng) {
            wp_send_json_error(__('Dados incompletos. Preencha o endereço e as coordenadas.', 'quilombo-lab'));
        }

        // Salvar nas options
        update_option('ql_collective_address', $address);
        update_option('ql_collective_lat', $lat);
        update_option('ql_collective_lng', $lng);

        // Atualizar o Território Mundo com este endereço
        $world_territory_id = $this->get_world_territory_id();

        if ($world_territory_id) {
            update_post_meta($world_territory_id, '_territory_address', $address);
            update_post_meta($world_territory_id, '_territory_lat', $lat);
            update_post_meta($world_territory_id, '_territory_lng', $lng);

            // Criar ou atualizar o núcleo mundial (associação com o endereço do coletivo)
            update_post_meta($world_territory_id, '_territory_collective_address', $address);

            $message = sprintf(
                __('Território Mundo (ID: %d) atualizado com o endereço do coletivo.', 'quilombo-lab'),
                $world_territory_id
            );
        } else {
            // Criar território mundo se não existir
            $this->create_world_territory();
            $world_territory_id = $this->get_world_territory_id();

            if ($world_territory_id) {
                update_post_meta($world_territory_id, '_territory_address', $address);
                update_post_meta($world_territory_id, '_territory_lat', $lat);
                update_post_meta($world_territory_id, '_territory_lng', $lng);
                update_post_meta($world_territory_id, '_territory_collective_address', $address);

                $message = sprintf(
                    __('Território Mundo criado (ID: %d) e configurado com o endereço do coletivo.', 'quilombo-lab'),
                    $world_territory_id
                );
            } else {
                $message = __('Endereço salvo, mas não foi possível criar/atualizar o Território Mundo.', 'quilombo-lab');
            }
        }

        wp_send_json_success([
            'message' => $message,
            'world_territory_id' => $world_territory_id,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng
        ]);
    }

    /**
     * Encontrar território por coordenadas
     */
    private function find_territory_by_coordinates($lat, $lng) {
        // Query territórios com auto-assign habilitado
        $territories = get_posts([
            'post_type' => 'ql_territory',
            'meta_query' => [
                [
                    'key' => '_territory_auto_assign',
                    'value' => '1'
                ]
            ],
            'posts_per_page' => -1
        ]);
        
        foreach ($territories as $territory) {
            $bounds = get_post_meta($territory->ID, '_territory_bounds', true);
            
            if ($bounds && $this->point_in_territory($lat, $lng, $bounds)) {
                return $territory->ID;
            }
        }
        
        return null;
    }
    
    /**
     * Verificar se ponto está dentro do território
     */
    private function point_in_territory($lat, $lng, $bounds_json) {
        $bounds = json_decode($bounds_json, true);
        
        if (!$bounds || !isset($bounds['geometry'])) {
            return false;
        }
        
        // Implementar algoritmo point-in-polygon
        // Simplificado - para produção usar biblioteca geoespacial
        $geometry = $bounds['geometry'];
        
        if ($geometry['type'] === 'Polygon') {
            return $this->point_in_polygon($lat, $lng, $geometry['coordinates'][0]);
        }
        
        return false;
    }
    
    /**
     * Algoritmo point-in-polygon (Ray Casting)
     */
    private function point_in_polygon($lat, $lng, $polygon) {
        $x = $lng;
        $y = $lat;
        $inside = false;
        
        $n = count($polygon);
        $j = $n - 1;
        
        for ($i = 0; $i < $n; $i++) {
            $xi = $polygon[$i][0];
            $yi = $polygon[$i][1];
            $xj = $polygon[$j][0]; 
            $yj = $polygon[$j][1];
            
            if ((($yi > $y) !== ($yj > $y)) && 
                ($x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            
            $j = $i;
        }
        
        return $inside;
    }
    
    /**
     * Obter território do usuário (principal)
     */
    public function get_user_territory($user_id) {
        return get_user_meta($user_id, 'user_territory_id', true);
    }

    /**
     * Obter TODOS os territórios onde uma localização está inserida
     * Retorna hierarquia: mundo → país → estado → cidade → bairro → rua
     *
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return array Lista de territórios ordenados por hierarquia (maior para menor)
     */
    public function get_all_territories_for_location($lat, $lng) {
        $territories = get_posts([
            'post_type' => 'ql_territory',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $matching_territories = [];

        // Hierarquia de tipos (ordem de maior para menor)
        $type_hierarchy = [
            'mundo' => 1,
            'continente' => 2,
            'pais' => 3,
            'regiao' => 4,
            'estado' => 5,
            'cidade' => 6,
            'bairro' => 7,
            'favela' => 8,
            'vila' => 9,
            'rua' => 10,
            'edificio' => 11,
            'residencia' => 12,
            'sitio' => 13,
            'aldeia' => 14
        ];

        foreach ($territories as $territory) {
            $bounds = get_post_meta($territory->ID, '_territory_bounds', true);
            $is_default = get_post_meta($territory->ID, '_territory_is_default', true);

            // Território mundo padrão sempre inclui todas as localizações
            if ($is_default == '1') {
                $terms = wp_get_post_terms($territory->ID, 'territory_type', ['fields' => 'slugs']);
                $type_slug = !empty($terms) && !is_wp_error($terms) ? $terms[0] : 'mundo';
                $hierarchy_order = $type_hierarchy[$type_slug] ?? 100;

                $matching_territories[] = [
                    'id' => $territory->ID,
                    'name' => $territory->post_title,
                    'type' => $type_slug,
                    'type_label' => $this->get_territory_type_label($type_slug),
                    'hierarchy_order' => $hierarchy_order,
                    'address' => get_post_meta($territory->ID, '_territory_address', true),
                    'lat' => get_post_meta($territory->ID, '_territory_lat', true),
                    'lng' => get_post_meta($territory->ID, '_territory_lng', true),
                    'bounds' => $bounds,
                    'url' => get_permalink($territory->ID),
                    'is_default' => true
                ];
                continue;
            }

            // Verificar se a localização está dentro dos limites do território
            if ($bounds && $this->point_in_territory($lat, $lng, $bounds)) {
                $terms = wp_get_post_terms($territory->ID, 'territory_type', ['fields' => 'slugs']);
                $type_slug = !empty($terms) && !is_wp_error($terms) ? $terms[0] : 'outro';
                $hierarchy_order = $type_hierarchy[$type_slug] ?? 100;

                $matching_territories[] = [
                    'id' => $territory->ID,
                    'name' => $territory->post_title,
                    'type' => $type_slug,
                    'type_label' => $this->get_territory_type_label($type_slug),
                    'hierarchy_order' => $hierarchy_order,
                    'address' => get_post_meta($territory->ID, '_territory_address', true),
                    'lat' => get_post_meta($territory->ID, '_territory_lat', true),
                    'lng' => get_post_meta($territory->ID, '_territory_lng', true),
                    'bounds' => $bounds,
                    'url' => get_permalink($territory->ID),
                    'is_default' => false
                ];
            }
        }

        // Ordenar por hierarquia (mundo primeiro, depois mais específicos)
        usort($matching_territories, function($a, $b) {
            return $a['hierarchy_order'] - $b['hierarchy_order'];
        });

        return $matching_territories;
    }

    /**
     * Obter todos os territórios de um usuário baseado em sua localização
     *
     * @param int $user_id ID do usuário
     * @return array Lista de territórios do usuário
     */
    public function get_user_all_territories($user_id) {
        $user_lat = get_user_meta($user_id, 'user_lat', true);
        $user_lng = get_user_meta($user_id, 'user_lng', true);

        if (empty($user_lat) || empty($user_lng)) {
            // Sem localização, retorna apenas território mundo
            $world_id = $this->get_world_territory_id();
            if ($world_id) {
                return [[
                    'id' => $world_id,
                    'name' => get_the_title($world_id),
                    'type' => 'mundo',
                    'type_label' => __('Mundo', 'quilombo-lab'),
                    'hierarchy_order' => 1,
                    'address' => get_post_meta($world_id, '_territory_address', true),
                    'lat' => '0',
                    'lng' => '0',
                    'url' => get_permalink($world_id),
                    'is_default' => true
                ]];
            }
            return [];
        }

        return $this->get_all_territories_for_location(floatval($user_lat), floatval($user_lng));
    }

    /**
     * Obter label do tipo de território
     */
    private function get_territory_type_label($type_slug) {
        $labels = [
            'mundo' => __('Mundo', 'quilombo-lab'),
            'continente' => __('Continente', 'quilombo-lab'),
            'pais' => __('País', 'quilombo-lab'),
            'regiao' => __('Região', 'quilombo-lab'),
            'estado' => __('Estado', 'quilombo-lab'),
            'cidade' => __('Cidade', 'quilombo-lab'),
            'bairro' => __('Bairro', 'quilombo-lab'),
            'favela' => __('Favela/Comunidade', 'quilombo-lab'),
            'vila' => __('Vila', 'quilombo-lab'),
            'rua' => __('Rua', 'quilombo-lab'),
            'edificio' => __('Edifício', 'quilombo-lab'),
            'residencia' => __('Residência', 'quilombo-lab'),
            'sitio' => __('Sítio', 'quilombo-lab'),
            'aldeia' => __('Aldeia', 'quilombo-lab')
        ];

        return $labels[$type_slug] ?? ucfirst($type_slug);
    }

    /**
     * Obter comunidades de um território
     *
     * @param int $territory_id ID do território
     * @return array Lista de comunidades
     */
    public function get_territory_communities($territory_id) {
        global $wpdb;

        $communities = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ql_instances
            WHERE type = 'comunidade'
            AND territory_id = %d
            AND status = 'active'
            ORDER BY name ASC
        ", $territory_id));

        return $communities;
    }

    /**
     * Obter comunidades do usuário baseado em seus territórios
     *
     * @param int $user_id ID do usuário
     * @return array Lista de comunidades disponíveis para o usuário
     */
    public function get_user_available_communities($user_id) {
        $territories = $this->get_user_all_territories($user_id);
        $communities = [];

        foreach ($territories as $territory) {
            $territory_communities = $this->get_territory_communities($territory['id']);
            foreach ($territory_communities as $community) {
                $communities[$community->id] = [
                    'id' => $community->id,
                    'name' => $community->name,
                    'territory_id' => $territory['id'],
                    'territory_name' => $territory['name'],
                    'description' => $community->description
                ];
            }
        }

        return array_values($communities);
    }

    /**
     * Atribuir usuário a todos os territórios correspondentes à sua localização
     * Atualiza também a membresia nas comunidades disponíveis
     *
     * @param int $user_id ID do usuário
     * @return array Resultado da atribuição
     */
    public function assign_user_to_all_territories($user_id) {
        $territories = $this->get_user_all_territories($user_id);

        if (empty($territories)) {
            return ['success' => false, 'message' => __('Nenhum território encontrado para esta localização', 'quilombo-lab')];
        }

        // Guardar lista de IDs dos territórios
        $territory_ids = array_column($territories, 'id');
        update_user_meta($user_id, 'user_territory_ids', $territory_ids);

        // Definir território principal (mais específico - último da lista)
        $most_specific = end($territories);
        update_user_meta($user_id, 'user_territory_id', $most_specific['id']);

        return [
            'success' => true,
            'territories_count' => count($territories),
            'territories' => $territories,
            'primary_territory' => $most_specific
        ];
    }

    /**
     * AJAX: Obter territórios do usuário atual
     */
    public function ajax_get_user_territories() {
        check_ajax_referer('ql_territories_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(__('Usuário não logado', 'quilombo-lab'));
        }

        $territories = $this->get_user_all_territories($user_id);
        $communities = $this->get_user_available_communities($user_id);

        wp_send_json_success([
            'territories' => $territories,
            'communities' => $communities,
            'total_territories' => count($territories),
            'total_communities' => count($communities)
        ]);
    }

    /**
     * Obter instâncias disponíveis
     */
    private function get_available_instances() {
        if (class_exists('QL_Instances')) {
            return QL_Instances::get_all_instances();
        }

        return [];
    }

    /**
     * Trigger auto-assignment para território
     */
    private function trigger_territory_auto_assignment($territory_id) {
        // Implementar em background job para performance
        wp_schedule_single_event(time(), 'ql_territory_auto_assign', [$territory_id]);
    }
}

// Hook para auto-assignment em background
add_action('ql_territory_auto_assign', function($territory_id) {
    // Buscar todos usuários e verificar se estão no território
    $users = get_users(['fields' => 'ID']);
    
    foreach ($users as $user_id) {
        $territories = QL_Territories::get_instance();
        $territories->auto_assign_user_territory($user_id);
    }
});