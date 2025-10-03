/**
 * Quilombo Laboratório - Calendar JavaScript
 */

(function($) {
    'use strict';

    // Objeto principal do calendário
    window.QLCalendar = {
        
        // Configurações
        config: {
            calendarEl: null,
            calendar: null,
            currentView: 'dayGridMonth',
            currentProjectId: null,
            currentUserId: null
        },

        // Inicializar
        init: function() {
            this.config.calendarEl = document.getElementById('ql-calendar');
            
            if (!this.config.calendarEl) {
                console.log('Elemento de calendário não encontrado');
                return;
            }

            this.initFullCalendar();
            this.bindEvents();
            console.log('QL Calendar inicializado');
        },

        // Inicializar FullCalendar
        initFullCalendar: function() {
            var self = this;
            
            this.config.calendar = new FullCalendar.Calendar(this.config.calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                firstDay: 0, // Domingo
                height: 'auto',
                
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                
                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    day: 'Dia',
                    list: 'Lista'
                },
                
                // Carregar eventos
                events: function(fetchInfo, successCallback, failureCallback) {
                    self.loadEvents(fetchInfo, successCallback, failureCallback);
                },
                
                // Clique em evento
                eventClick: function(info) {
                    self.handleEventClick(info);
                },
                
                // Selecionar data
                dateClick: function(info) {
                    self.handleDateClick(info);
                },
                
                // Customizar renderização de eventos
                eventDidMount: function(info) {
                    self.customizeEventRender(info);
                },
                
                // Arrastar e soltar eventos
                editable: true,
                droppable: true,
                
                // Callback para quando evento é movido
                eventDrop: function(info) {
                    self.handleEventDrop(info);
                },
                
                // Callback para quando evento é redimensionado
                eventResize: function(info) {
                    self.handleEventResize(info);
                },
                
                // Configurações de tempo
                slotMinTime: '06:00:00',
                slotMaxTime: '22:00:00',
                slotDuration: '01:00:00',
                
                // Configurações de exibição
                nowIndicator: true,
                weekNumbers: true,
                weekNumberFormat: { week: 'numeric' },
                
                // Configurações de responsividade
                aspectRatio: window.innerWidth < 768 ? 1.0 : 1.35
            });

            this.config.calendar.render();
        },

        // Carregar eventos do servidor
        loadEvents: function(fetchInfo, successCallback, failureCallback) {
            var self = this;
            
            $.ajax({
                url: ql_calendar.ajax_url,
                method: 'GET',
                data: {
                    action: 'ql_get_calendar_events',
                    nonce: ql_calendar.nonce,
                    start: fetchInfo.startStr,
                    end: fetchInfo.endStr,
                    project_id: this.config.currentProjectId,
                    user_id: this.config.currentUserId
                },
                success: function(response) {
                    if (response.success) {
                        successCallback(response.data);
                    } else {
                        failureCallback(response.data || 'Erro ao carregar eventos');
                    }
                },
                error: function(xhr) {
                    console.error('Erro ao carregar eventos:', xhr);
                    failureCallback('Erro de conexão');
                }
            });
        },

        // Vincular eventos
        bindEvents: function() {
            var self = this;
            
            // Novo evento
            $('#ql-new-event-btn').on('click', function() {
                self.openEventModal();
            });
            
            // Filtro por projeto
            $('#ql-calendar-filter-project').on('change', function() {
                self.config.currentProjectId = $(this).val() || null;
                self.refreshCalendar();
            });
            
            // Filtro por usuário
            $('#ql-calendar-filter-user').on('change', function() {
                self.config.currentUserId = $(this).val() || null;
                self.refreshCalendar();
            });
            
            // Modal de evento
            $('#ql-save-event-btn').on('click', function() {
                self.saveEvent();
            });
            
            $('#ql-delete-event-btn').on('click', function() {
                self.deleteEvent();
            });
            
            // Fechar modal
            $(document).on('click', '#ql-event-modal .ql-modal-close, #ql-event-modal .ql-modal-backdrop', function() {
                self.closeEventModal();
            });
            
            // ESC para fechar modal
            $(document).on('keyup', function(e) {
                if (e.keyCode === 27) {
                    self.closeEventModal();
                }
            });
            
            // Responsividade
            $(window).on('resize', function() {
                if (self.config.calendar) {
                    self.config.calendar.updateSize();
                }
            });
        },

        // Lidar com clique em evento
        handleEventClick: function(info) {
            var event = info.event;
            var extendedProps = event.extendedProps;
            
            if (extendedProps.type === 'task') {
                this.showTaskDetails(extendedProps.task_id);
            } else {
                this.openEventModal(event);
            }
        },

        // Lidar com clique em data
        handleDateClick: function(info) {
            this.openEventModal(null, info.dateStr);
        },

        // Customizar renderização de eventos
        customizeEventRender: function(info) {
            var event = info.event;
            var extendedProps = event.extendedProps;
            var element = info.el;
            
            // Adicionar classes CSS
            if (extendedProps.type) {
                element.classList.add('event-type-' + extendedProps.type);
            }
            
            if (extendedProps.status) {
                element.classList.add('event-status-' + extendedProps.status);
            }
            
            if (extendedProps.priority) {
                element.classList.add('event-priority-' + extendedProps.priority);
            }
            
            // Adicionar tooltip
            var tooltipContent = this.buildTooltipContent(event);
            $(element).attr('title', tooltipContent);
            
            // Verificar se está atrasado
            if (extendedProps.type === 'task' && extendedProps.status !== 'completed') {
                var now = new Date();
                var eventDate = new Date(event.start);
                
                if (eventDate < now) {
                    element.classList.add('event-overdue');
                }
            }
        },

        // Construir conteúdo do tooltip
        buildTooltipContent: function(event) {
            var extendedProps = event.extendedProps;
            var content = event.title;
            
            if (extendedProps.description) {
                content += '\n' + extendedProps.description;
            }
            
            if (extendedProps.project_name) {
                content += '\nProjeto: ' + extendedProps.project_name;
            }
            
            if (extendedProps.assigned_user) {
                content += '\nResponsável: ' + extendedProps.assigned_user;
            }
            
            if (extendedProps.location) {
                content += '\nLocal: ' + extendedProps.location;
            }
            
            return content;
        },

        // Lidar com arrastar evento
        handleEventDrop: function(info) {
            var event = info.event;
            var extendedProps = event.extendedProps;
            
            if (extendedProps.type === 'task') {
                this.updateTaskDate(extendedProps.task_id, event.start);
            } else {
                this.updateCustomEvent(extendedProps.event_id, {
                    event_date: this.formatDate(event.start),
                    event_time: event.allDay ? null : this.formatTime(event.start)
                });
            }
        },

        // Lidar com redimensionar evento
        handleEventResize: function(info) {
            var event = info.event;
            var extendedProps = event.extendedProps;
            
            if (extendedProps.type === 'custom') {
                this.updateCustomEvent(extendedProps.event_id, {
                    event_date: this.formatDate(event.start),
                    event_time: event.allDay ? null : this.formatTime(event.start),
                    end_date: this.formatDate(event.end),
                    end_time: event.allDay ? null : this.formatTime(event.end)
                });
            }
        },

        // Atualizar data da tarefa
        updateTaskDate: function(taskId, newDate) {
            $.ajax({
                url: ql_calendar.ajax_url,
                method: 'POST',
                data: {
                    action: 'ql_update_task',
                    task_id: taskId,
                    due_date: this.formatDate(newDate),
                    nonce: ql_calendar.nonce
                },
                success: function(response) {
                    if (response.success) {
                        QLAdmin.showNotification('Data da tarefa atualizada', 'success');
                    } else {
                        QLAdmin.showNotification(response.data || 'Erro ao atualizar tarefa', 'error');
                    }
                },
                error: function() {
                    QLAdmin.showNotification('Erro de conexão', 'error');
                }
            });
        },

        // Abrir modal de evento
        openEventModal: function(event, dateStr) {
            var modal = $('#ql-event-modal');
            var form = $('#ql-event-form')[0];
            
            // Resetar formulário
            form.reset();
            
            if (event) {
                // Editar evento existente
                var extendedProps = event.extendedProps;
                
                modal.find('.ql-modal-title').text(ql_calendar.strings.edit_event);
                $('#ql-delete-event-btn').removeClass('hidden');
                
                $('#event-id').val(extendedProps.event_id);
                $('#event-title').val(event.title);
                $('#event-description').val(extendedProps.description || '');
                $('#event-type').val(extendedProps.event_type || 'meeting');
                $('#event-color').val(event.backgroundColor || '#007bff');
                $('#event-date').val(this.formatDate(event.start));
                $('#event-time').val(event.allDay ? '' : this.formatTime(event.start));
                $('#event-end-date').val(event.end ? this.formatDate(event.end) : '');
                $('#event-end-time').val(event.end && !event.allDay ? this.formatTime(event.end) : '');
                $('#event-location').val(extendedProps.location || '');
                $('#event-project').val(extendedProps.project_id || '');
                
            } else {
                // Novo evento
                modal.find('.ql-modal-title').text(ql_calendar.strings.new_event);
                $('#ql-delete-event-btn').addClass('hidden');
                
                if (dateStr) {
                    $('#event-date').val(dateStr);
                }
                
                if (this.config.currentProjectId) {
                    $('#event-project').val(this.config.currentProjectId);
                }
            }
            
            modal.removeClass('hidden').addClass('ql-modal-open');
            $('#event-title').focus();
        },

        // Fechar modal de evento
        closeEventModal: function() {
            $('#ql-event-modal').removeClass('ql-modal-open').addClass('hidden');
        },

        // Salvar evento
        saveEvent: function() {
            var form = $('#ql-event-form')[0];
            var formData = new FormData(form);
            var eventId = $('#event-id').val();
            var isEdit = !!eventId;
            
            // Validação
            if (!$('#event-title').val().trim()) {
                QLAdmin.showNotification('Título é obrigatório', 'error');
                return;
            }
            
            if (!$('#event-date').val()) {
                QLAdmin.showNotification('Data é obrigatória', 'error');
                return;
            }
            
            var action = isEdit ? 'ql_update_calendar_event' : 'ql_create_calendar_event';
            formData.append('action', action);
            formData.append('nonce', ql_calendar.nonce);
            
            var self = this;
            
            $.ajax({
                url: ql_calendar.ajax_url,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    QLAdmin.showLoading();
                },
                success: function(response) {
                    QLAdmin.hideLoading();
                    
                    if (response.success) {
                        QLAdmin.showNotification(
                            isEdit ? 'Evento atualizado com sucesso' : 'Evento criado com sucesso',
                            'success'
                        );
                        self.closeEventModal();
                        self.refreshCalendar();
                    } else {
                        QLAdmin.showNotification(response.data || 'Erro ao salvar evento', 'error');
                    }
                },
                error: function() {
                    QLAdmin.hideLoading();
                    QLAdmin.showNotification('Erro de conexão', 'error');
                }
            });
        },

        // Excluir evento
        deleteEvent: function() {
            if (!confirm(ql_calendar.strings.delete_confirm)) {
                return;
            }
            
            var eventId = $('#event-id').val();
            if (!eventId) return;
            
            var self = this;
            
            $.ajax({
                url: ql_calendar.ajax_url,
                method: 'POST',
                data: {
                    action: 'ql_delete_calendar_event',
                    event_id: eventId,
                    nonce: ql_calendar.nonce
                },
                beforeSend: function() {
                    QLAdmin.showLoading();
                },
                success: function(response) {
                    QLAdmin.hideLoading();
                    
                    if (response.success) {
                        QLAdmin.showNotification('Evento excluído com sucesso', 'success');
                        self.closeEventModal();
                        self.refreshCalendar();
                    } else {
                        QLAdmin.showNotification(response.data || 'Erro ao excluir evento', 'error');
                    }
                },
                error: function() {
                    QLAdmin.hideLoading();
                    QLAdmin.showNotification('Erro de conexão', 'error');
                }
            });
        },

        // Atualizar evento personalizado
        updateCustomEvent: function(eventId, eventData) {
            $.ajax({
                url: ql_calendar.ajax_url,
                method: 'POST',
                data: $.extend({
                    action: 'ql_update_calendar_event',
                    event_id: eventId,
                    nonce: ql_calendar.nonce
                }, eventData),
                success: function(response) {
                    if (response.success) {
                        QLAdmin.showNotification('Evento atualizado', 'success');
                    } else {
                        QLAdmin.showNotification(response.data || 'Erro ao atualizar evento', 'error');
                    }
                },
                error: function() {
                    QLAdmin.showNotification('Erro de conexão', 'error');
                }
            });
        },

        // Mostrar detalhes da tarefa
        showTaskDetails: function(taskId) {
            // Usar função do QLAdmin se disponível
            if (window.QLAdmin && window.QLAdmin.showTaskDetails) {
                window.QLAdmin.showTaskDetails(taskId);
            } else {
                // Implementação básica
                QLAdmin.showNotification('Carregando detalhes da tarefa...', 'info');
            }
        },

        // Atualizar calendário
        refreshCalendar: function() {
            if (this.config.calendar) {
                this.config.calendar.refetchEvents();
            }
        },

        // Utilitários de data
        formatDate: function(date) {
            if (!date) return '';
            var d = new Date(date);
            return d.getFullYear() + '-' + 
                   String(d.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(d.getDate()).padStart(2, '0');
        },

        formatTime: function(date) {
            if (!date) return '';
            var d = new Date(date);
            return String(d.getHours()).padStart(2, '0') + ':' + 
                   String(d.getMinutes()).padStart(2, '0');
        },

        // Navegar para data específica
        goToDate: function(date) {
            if (this.config.calendar) {
                this.config.calendar.gotoDate(date);
            }
        },

        // Mudar visualização
        changeView: function(viewName) {
            if (this.config.calendar) {
                this.config.calendar.changeView(viewName);
                this.config.currentView = viewName;
            }
        },

        // Exportar eventos
        exportEvents: function(format) {
            var events = this.config.calendar.getEvents();
            
            if (format === 'csv') {
                this.exportToCSV(events);
            } else if (format === 'ical') {
                this.exportToICal(events);
            }
        },

        // Exportar para CSV
        exportToCSV: function(events) {
            var csv = 'Título,Descrição,Data Início,Data Fim,Projeto,Tipo\n';
            
            events.forEach(function(event) {
                var props = event.extendedProps;
                csv += '"' + event.title + '",';
                csv += '"' + (props.description || '') + '",';
                csv += '"' + (event.start ? event.start.toISOString() : '') + '",';
                csv += '"' + (event.end ? event.end.toISOString() : '') + '",';
                csv += '"' + (props.project_name || '') + '",';
                csv += '"' + (props.type || '') + '"\n';
            });
            
            this.downloadFile(csv, 'calendario_laboratorio.csv', 'text/csv');
        },

        // Download de arquivo
        downloadFile: function(content, filename, mimeType) {
            var blob = new Blob([content], { type: mimeType });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    };

    // Inicializar quando documento estiver pronto
    $(document).ready(function() {
        // Verificar se FullCalendar está disponível
        if (typeof FullCalendar !== 'undefined') {
            QLCalendar.init();
        } else {
            console.error('FullCalendar não foi carregado');
        }
    });

})(jQuery);