/**
 * JavaScript para Sistema de Territórios
 * Quilombo Laboratório
 */

(function($) {
    'use strict';
    
    // Namespace global
    window.QLTerritories = window.QLTerritories || {};
    
    // Configurações globais
    const config = {
        defaultCenter: [-15.7942287, -47.8821945], // Brasil central
        defaultZoom: 4,
        searchZoom: 15,
        maxZoom: 18,
        tileLayer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        attribution: '© OpenStreetMap contributors'
    };
    
    // Cache de mapas
    const maps = new Map();
    
    /**
     * Classe principal para gerenciar territórios
     */
    class TerritoryManager {
        constructor() {
            this.init();
        }
        
        init() {
            this.bindEvents();
            this.initMaps();
            this.initGeolocation();
        }
        
        bindEvents() {
            $(document).on('click', '.ql-search-address', this.searchAddress.bind(this));
            $(document).on('click', '.ql-save-territory-bounds', this.saveTerritoryBounds.bind(this));
            $(document).on('click', '.ql-locate-user', this.locateUser.bind(this));
            $(document).on('submit', '.ql-user-location-form', this.saveUserLocation.bind(this));
            $(document).on('click', '.ql-territory-search-result', this.selectSearchResult.bind(this));
        }
        
        initMaps() {
            $('.ql-territory-map').each((index, element) => {
                this.createMap(element);
            });
        }
        
        createMap(element) {
            const $element = $(element);
            const mapId = $element.attr('id') || 'ql-map-' + Date.now();
            $element.attr('id', mapId);
            
            const options = {
                center: $element.data('center') || config.defaultCenter,
                zoom: $element.data('zoom') || config.defaultZoom,
                maxZoom: config.maxZoom,
                territoryId: $element.data('territory-id'),
                showControls: $element.data('show-controls') === true,
                userLocation: $element.data('user-location') === true,
                interactive: $element.data('interactive') !== false
            };
            
            const map = this.setupLeafletMap(mapId, options);
            maps.set(mapId, map);
            
            return map;
        }
        
        setupLeafletMap(mapId, options) {
            const map = L.map(mapId, {
                center: options.center,
                zoom: options.zoom,
                maxZoom: options.maxZoom,
                scrollWheelZoom: options.interactive,
                dragging: options.interactive,
                touchZoom: options.interactive,
                doubleClickZoom: options.interactive,
                boxZoom: options.interactive,
                keyboard: options.interactive
            });
            
            // Adicionar camada base
            L.tileLayer(config.tileLayer, {
                attribution: config.attribution,
                maxZoom: options.maxZoom
            }).addTo(map);
            
            // Grupo para camadas desenhadas
            const drawnItems = new L.FeatureGroup();
            map.addLayer(drawnItems);
            
            // Controles de desenho se habilitados
            if (options.showControls && typeof L.Control.Draw !== 'undefined') {
                const drawControl = new L.Control.Draw({
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
                map.addControl(drawControl);
                
                // Eventos de desenho
                map.on('draw:created', (event) => {
                    const layer = event.layer;
                    drawnItems.addLayer(layer);
                    this.handleDrawCreated(map, layer, options);
                });
            }
            
            // Carregar território específico
            if (options.territoryId) {
                this.loadTerritory(map, options.territoryId);
            }
            
            // Localização do usuário
            if (options.userLocation) {
                this.showUserLocation(map);
            }
            
            // Eventos de clique
            if (options.interactive) {
                map.on('click', (event) => {
                    this.handleMapClick(map, event, options);
                });
            }
            
            return map;
        }
        
        handleDrawCreated(map, layer, options) {
            const bounds = layer.toGeoJSON();
            
            // Trigger evento personalizado
            $(map.getContainer()).trigger('ql:bounds-created', [bounds, layer]);
            
            // Se é um território específico, salvar automaticamente
            if (options.territoryId) {
                this.saveTerritoryBounds(options.territoryId, bounds);
            }
        }
        
        handleMapClick(map, event, options) {
            const latlng = event.latlng;
            
            // Trigger evento personalizado
            $(map.getContainer()).trigger('ql:map-clicked', [latlng, event]);
        }
        
        loadTerritory(map, territoryId) {
            $.post(ql_territories.ajax_url, {
                action: 'ql_get_territory_data',
                territory_id: territoryId,
                nonce: ql_territories.nonce
            }, (response) => {
                if (response.success) {
                    this.displayTerritoryData(map, response.data);
                }
            });
        }
        
        displayTerritoryData(map, data) {
            // Centralizar no território
            if (data.lat && data.lng) {
                map.setView([data.lat, data.lng], config.searchZoom);
                
                // Adicionar marcador
                L.marker([data.lat, data.lng])
                    .addTo(map)
                    .bindPopup(`<div class="ql-map-popup">
                        <h4>${data.name}</h4>
                        <p>${data.address || ''}</p>
                        ${data.url ? `<a href="${data.url}" class="territory-link">Ver Detalhes</a>` : ''}
                    </div>`);
            }
            
            // Adicionar limites se existirem
            if (data.bounds) {
                try {
                    const bounds = JSON.parse(data.bounds);
                    const geoJsonLayer = L.geoJSON(bounds, {
                        style: {
                            fillColor: data.color || '#3498db',
                            weight: 2,
                            opacity: 1,
                            color: 'white',
                            dashArray: '3',
                            fillOpacity: 0.3
                        }
                    }).addTo(map);
                    
                    // Ajustar vista para mostrar todo o território
                    map.fitBounds(geoJsonLayer.getBounds());
                } catch (e) {
                    console.warn('Erro ao carregar limites do território:', e);
                }
            }
        }
        
        showUserLocation(map) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        map.setView([lat, lng], config.searchZoom);
                        
                        const marker = L.marker([lat, lng])
                            .addTo(map)
                            .bindPopup(`<div class="ql-map-popup">
                                <h4>${ql_territories.strings.your_location || 'Sua localização'}</h4>
                                <button onclick="QLTerritories.saveCurrentLocation(${lat}, ${lng})" class="territory-link">
                                    ${ql_territories.strings.save_location || 'Salvar localização'}
                                </button>
                            </div>`);
                        
                        // Trigger evento
                        $(map.getContainer()).trigger('ql:user-located', [lat, lng, position]);
                    },
                    (error) => {
                        console.warn('Erro ao obter localização:', error);
                        this.showLocationError(map, error);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000 // 5 minutos
                    }
                );
            } else {
                this.showLocationError(map, { code: 0, message: 'Geolocalização não suportada' });
            }
        }
        
        showLocationError(map, error) {
            const messages = {
                0: 'Geolocalização não suportada pelo navegador',
                1: 'Permissão negada para acessar localização',
                2: 'Posição não disponível',
                3: 'Timeout ao obter localização'
            };
            
            const message = messages[error.code] || 'Erro desconhecido ao obter localização';
            
            // Mostrar popup de erro
            L.popup()
                .setLatLng(config.defaultCenter)
                .setContent(`<div class="ql-map-popup">
                    <h4>Erro de Localização</h4>
                    <p>${message}</p>
                </div>`)
                .openOn(map);
        }
        
        searchAddress(event) {
            event.preventDefault();
            
            const $button = $(event.currentTarget);
            const $input = $button.siblings('input[type="text"]');
            const address = $input.val().trim();
            
            if (!address) {
                alert(ql_territories.strings.enter_address || 'Digite um endereço para buscar');
                return;
            }
            
            this.performAddressSearch(address, $button);
        }
        
        performAddressSearch(address, $trigger) {
            const $button = $trigger;
            const originalText = $button.text();
            
            $button.prop('disabled', true).text(ql_territories.strings.loading || 'Carregando...');
            
            $.post(ql_territories.ajax_url, {
                action: 'ql_search_address',
                address: address,
                nonce: ql_territories.nonce
            }, (response) => {
                if (response.success && response.data.length > 0) {
                    this.displaySearchResults(response.data, $trigger);
                } else {
                    alert(ql_territories.strings.address_not_found || 'Endereço não encontrado');
                }
            }).fail(() => {
                alert(ql_territories.strings.search_error || 'Erro ao buscar endereço');
            }).always(() => {
                $button.prop('disabled', false).text(originalText);
            });
        }
        
        displaySearchResults(results, $trigger) {
            const $container = $trigger.closest('.ql-address-search');
            let $resultsDiv = $container.find('.ql-search-results');
            
            if (!$resultsDiv.length) {
                $resultsDiv = $('<div class="ql-search-results"></div>');
                $container.append($resultsDiv);
            }
            
            $resultsDiv.empty();
            
            results.forEach((result, index) => {
                const $resultItem = $(`
                    <div class="ql-search-result" data-index="${index}">
                        <strong>${result.display_name}</strong><br>
                        <small>Lat: ${result.lat}, Lng: ${result.lon}</small>
                    </div>
                `);
                
                $resultItem.data('result', result);
                $resultsDiv.append($resultItem);
            });
            
            $resultsDiv.show();
        }
        
        selectSearchResult(event) {
            const $item = $(event.currentTarget);
            const result = $item.data('result');
            const $container = $item.closest('.ql-address-search');
            
            // Preencher campos
            $container.find('input[name*="lat"]').val(result.lat);
            $container.find('input[name*="lng"]').val(result.lon);
            $container.find('input[name*="address"]').val(result.display_name);
            
            // Atualizar mapa se existir
            const mapId = $container.data('map-target');
            if (mapId) {
                const map = maps.get(mapId);
                if (map) {
                    this.updateMapLocation(map, result.lat, result.lon, result.display_name);
                }
            }
            
            // Esconder resultados
            $item.closest('.ql-search-results').hide();
            
            // Trigger evento
            $(document).trigger('ql:address-selected', [result, $container]);
        }
        
        updateMapLocation(map, lat, lng, name) {
            const latlng = [parseFloat(lat), parseFloat(lng)];
            
            map.setView(latlng, config.searchZoom);
            
            // Remover marcadores anteriores de busca
            map.eachLayer((layer) => {
                if (layer.options && layer.options.searchMarker) {
                    map.removeLayer(layer);
                }
            });
            
            // Adicionar novo marcador
            L.marker(latlng, { searchMarker: true })
                .addTo(map)
                .bindPopup(`<div class="ql-map-popup">
                    <h4>${name}</h4>
                    <small>Lat: ${lat}, Lng: ${lng}</small>
                </div>`)
                .openPopup();
        }
        
        saveUserLocation(event) {
            event.preventDefault();
            
            const $form = $(event.currentTarget);
            const formData = new FormData($form[0]);
            
            const data = {
                action: 'ql_save_user_location',
                nonce: ql_territories.nonce
            };
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            $.post(ql_territories.ajax_url, data, (response) => {
                if (response.success) {
                    alert(ql_territories.strings.location_saved || 'Localização salva!');
                    
                    // Recarregar ou redirecionar
                    const redirect = $form.data('redirect');
                    if (redirect) {
                        window.location.href = redirect;
                    } else {
                        location.reload();
                    }
                } else {
                    alert(response.data || ql_territories.strings.error);
                }
            }).fail(() => {
                alert(ql_territories.strings.error || 'Erro ao salvar localização');
            });
        }
        
        saveTerritoryBounds(territoryId, bounds) {
            $.post(ql_territories.ajax_url, {
                action: 'ql_save_territory_bounds',
                territory_id: territoryId,
                bounds: JSON.stringify(bounds),
                nonce: ql_territories.nonce
            }, (response) => {
                if (response.success) {
                    console.log('Limites do território salvos');
                } else {
                    console.warn('Erro ao salvar limites:', response.data);
                }
            });
        }
        
        initGeolocation() {
            // Inicializar funcionalidades que dependem de geolocalização
            $('.ql-auto-locate').on('click', (event) => {
                event.preventDefault();
                this.autoLocateUser($(event.currentTarget));
            });
        }
        
        autoLocateUser($trigger) {
            if (!navigator.geolocation) {
                alert('Geolocalização não suportada');
                return;
            }
            
            const originalText = $trigger.text();
            $trigger.prop('disabled', true).text('Localizando...');
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Preencher campos próximos
                    $trigger.siblings('input[name*="lat"]').val(lat);
                    $trigger.siblings('input[name*="lng"]').val(lng);
                    
                    // Buscar endereço via reverse geocoding
                    this.reverseGeocode(lat, lng, $trigger);
                },
                (error) => {
                    alert('Erro ao obter localização: ' + error.message);
                    $trigger.prop('disabled', false).text(originalText);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000
                }
            );
        }
        
        reverseGeocode(lat, lng, $trigger) {
            $.get('https://nominatim.openstreetmap.org/reverse', {
                lat: lat,
                lon: lng,
                format: 'json'
            }, (data) => {
                if (data && data.display_name) {
                    $trigger.siblings('input[name*="address"]').val(data.display_name);
                }
            }).always(() => {
                $trigger.prop('disabled', false).text($trigger.data('original-text') || 'Localizar');
            });
        }
    }
    
    // Funções utilitárias expostas globalmente
    window.QLTerritories = {
        manager: null,
        
        init() {
            this.manager = new TerritoryManager();
        },
        
        getMap(mapId) {
            return maps.get(mapId);
        },
        
        createMap(element, options = {}) {
            return this.manager.createMap(element, options);
        },
        
        searchAddress(address, callback) {
            this.manager.performAddressSearch(address, callback);
        },
        
        saveCurrentLocation(lat, lng) {
            $.post(ql_territories.ajax_url, {
                action: 'ql_save_user_location',
                lat: lat,
                lng: lng,
                nonce: ql_territories.nonce
            }, (response) => {
                if (response.success) {
                    alert(ql_territories.strings.location_saved || 'Localização salva!');
                } else {
                    alert(response.data || ql_territories.strings.error);
                }
            });
        },
        
        getUserTerritory(callback) {
            $.post(ql_territories.ajax_url, {
                action: 'ql_get_user_territory',
                nonce: ql_territories.nonce
            }, callback);
        }
    };
    
    // Inicialização quando documento estiver pronto
    $(document).ready(() => {
        window.QLTerritories.init();
    });
    
    // Compatibilidade com turbolinks/barba.js
    $(document).on('page:load turbolinks:load', () => {
        window.QLTerritories.init();
    });
    
})(jQuery);