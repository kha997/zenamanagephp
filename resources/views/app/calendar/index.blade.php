@extends('layouts.app')

@section('title', __('calendar.title'))

@section('kpi-strip')
{{-- <x-kpi.strip :kpis="$kpis" /> --}}
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('calendar.title') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('calendar.subtitle') }}</p>
            </div>
            <div class="flex space-x-3">
                <button onclick="createEvent()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-plus mr-2"></i>{{ __('calendar.create_event') }}
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Container -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6">
            <!-- Calendar Navigation -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-4">
                    <button id="prev-month" class="p-2 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-chevron-left text-gray-600"></i>
                    </button>
                    <h2 id="current-month" class="text-xl font-semibold text-gray-900">January 2025</h2>
                    <button id="next-month" class="p-2 hover:bg-gray-100 rounded-lg">
                        <i class="fas fa-chevron-right text-gray-600"></i>
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="today-btn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">
                        {{ __('calendar.today') }}
                    </button>
                    <div class="flex items-center space-x-2">
                        <button id="month-view" class="px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg">
                            {{ __('calendar.month') }}
                        </button>
                        <button id="week-view" class="px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">
                            {{ __('calendar.week') }}
                        </button>
                        <button id="day-view" class="px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg">
                            {{ __('calendar.day') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Calendar Grid -->
            <div id="calendar-container" class="calendar-container">
                <!-- Calendar will be rendered here by FullCalendar.js -->
            </div>
        </div>
    </div>

    <!-- Event Details Modal -->
    <div id="event-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-modal-backdrop">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 id="modal-title" class="text-lg font-semibold text-gray-900">{{ __('calendar.event_details') }}</h3>
                        <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="modal-content">
                        <!-- Event details will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar-container');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: false, // We have custom header
        height: 'auto',
        events: @json($events ?? []),
        eventClick: function(info) {
            showEventModal(info.event);
        },
        dateClick: function(info) {
            createEvent(info.dateStr);
        },
        eventDrop: function(info) {
            updateEvent(info.event);
        },
        eventResize: function(info) {
            updateEvent(info.event);
        }
    });

    calendar.render();

    // Navigation handlers
    document.getElementById('prev-month').addEventListener('click', () => {
        calendar.prev();
        updateMonthDisplay();
    });

    document.getElementById('next-month').addEventListener('click', () => {
        calendar.next();
        updateMonthDisplay();
    });

    document.getElementById('today-btn').addEventListener('click', () => {
        calendar.today();
        updateMonthDisplay();
    });

    // View handlers
    document.getElementById('month-view').addEventListener('click', () => {
        calendar.changeView('dayGridMonth');
        updateViewButtons('month');
    });

    document.getElementById('week-view').addEventListener('click', () => {
        calendar.changeView('timeGridWeek');
        updateViewButtons('week');
    });

    document.getElementById('day-view').addEventListener('click', () => {
        calendar.changeView('timeGridDay');
        updateViewButtons('day');
    });

    function updateMonthDisplay() {
        const view = calendar.view;
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        const monthName = monthNames[view.currentStart.getMonth()];
        const year = view.currentStart.getFullYear();
        document.getElementById('current-month').textContent = `${monthName} ${year}`;
    }

    function updateViewButtons(activeView) {
        const buttons = ['month-view', 'week-view', 'day-view'];
        buttons.forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btnId === `${activeView}-view`) {
                btn.className = 'px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg';
            } else {
                btn.className = 'px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg';
            }
        });
    }

    function showEventModal(event) {
        document.getElementById('modal-title').textContent = event.title;
        document.getElementById('modal-content').innerHTML = `
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('calendar.title') }}</label>
                    <p class="mt-1 text-sm text-gray-900">${event.title}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('calendar.start_time') }}</label>
                    <p class="mt-1 text-sm text-gray-900">${event.start.toLocaleString()}</p>
                </div>
                ${event.end ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('calendar.end_time') }}</label>
                    <p class="mt-1 text-sm text-gray-900">${event.end.toLocaleString()}</p>
                </div>
                ` : ''}
                ${event.extendedProps.description ? `
                <div>
                    <label class="block text-sm font-medium text-gray-700">{{ __('calendar.description') }}</label>
                    <p class="mt-1 text-sm text-gray-900">${event.extendedProps.description}</p>
                </div>
                ` : ''}
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="editEvent('${event.id}')" class="px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg">
                    {{ __('calendar.edit') }}
                </button>
                <button onclick="deleteEvent('${event.id}')" class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg">
                    {{ __('calendar.delete') }}
                </button>
            </div>
        `;
        document.getElementById('event-modal').classList.remove('hidden');
    }

    // Close modal
    document.getElementById('close-modal').addEventListener('click', () => {
        document.getElementById('event-modal').classList.add('hidden');
    });

    // Close modal on backdrop click
    document.getElementById('event-modal').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            document.getElementById('event-modal').classList.add('hidden');
        }
    });

    // Global functions
    window.createEvent = function(dateStr) {
        // Show event creation modal
        document.getElementById('event-modal').classList.remove('hidden');
        document.getElementById('event-start-date').value = dateStr;
        document.getElementById('event-end-date').value = dateStr;
        document.getElementById('event-form').reset();
        document.getElementById('event-form').dataset.mode = 'create';
    };

    window.editEvent = function(eventId) {
        // Fetch event data and show edit modal
        fetch(`/api/v1/app/calendar/${eventId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const event = data.data;
                    document.getElementById('event-id').value = event.id;
                    document.getElementById('event-title').value = event.title;
                    document.getElementById('event-description').value = event.description || '';
                    document.getElementById('event-start-date').value = event.start;
                    document.getElementById('event-end-date').value = event.end;
                    document.getElementById('event-type').value = event.type;
                    document.getElementById('event-all-day').checked = event.allDay;
                    document.getElementById('event-project-id').value = event.project_id || '';
                    
                    document.getElementById('event-modal').classList.remove('hidden');
                    document.getElementById('event-form').dataset.mode = 'edit';
                } else {
                    alert('Failed to load event data');
                }
            })
            .catch(error => {
                console.error('Error loading event:', error);
                alert('Failed to load event data');
            });
    };

    window.deleteEvent = function(eventId) {
        if (confirm('Are you sure you want to delete this event?')) {
            fetch(`/api/v1/app/calendar/${eventId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Event deleted successfully');
                    loadCalendarEvents(); // Reload calendar
                } else {
                    alert('Failed to delete event: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting event:', error);
                alert('Failed to delete event');
            });
        }
    };

    window.updateEvent = function(event) {
        // This function is called by the form submission
        const form = document.getElementById('event-form');
        const mode = form.dataset.mode;
        const eventId = document.getElementById('event-id').value;
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        const url = mode === 'edit' ? `/api/v1/app/calendar/${eventId}` : '/api/v1/app/calendar';
        const method = mode === 'edit' ? 'PUT' : 'POST';
        
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(mode === 'edit' ? 'Event updated successfully' : 'Event created successfully');
                document.getElementById('event-modal').classList.add('hidden');
                loadCalendarEvents(); // Reload calendar
            } else {
                alert('Failed to save event: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error saving event:', error);
            alert('Failed to save event');
        });
    };

    // Load calendar events
    function loadCalendarEvents() {
        const startDate = currentDate.startOf('month').format('YYYY-MM-DD');
        const endDate = currentDate.endOf('month').format('YYYY-MM-DD');
        
        fetch(`/api/v1/app/calendar?start=${startDate}&end=${endDate}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update calendar display with events
                    updateCalendarWithEvents(data.data);
                } else {
                    console.error('Failed to load events:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading events:', error);
            });
    }

    function updateCalendarWithEvents(events) {
        // Clear existing event indicators
        document.querySelectorAll('.calendar-day .event-indicator').forEach(indicator => {
            indicator.remove();
        });
        
        // Add event indicators to calendar days
        events.forEach(event => {
            const eventDate = moment(event.start).format('YYYY-MM-DD');
            const dayElement = document.querySelector(`[data-date="${eventDate}"]`);
            
            if (dayElement) {
                const indicator = document.createElement('div');
                indicator.className = 'event-indicator';
                indicator.style.backgroundColor = event.color;
                indicator.title = event.title;
                dayElement.appendChild(indicator);
            }
        });
    }

    // Initial display update
    updateMonthDisplay();
});
</script>
@endpush
@endsection