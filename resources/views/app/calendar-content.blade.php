<!-- App Calendar Content - Tenant Calendar for Project & Task Scheduling -->
<div x-data="appCalendar()" x-init="init()" class="mobile-content">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading calendar...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading calendar</p>
                <p x-text="error"></p>
                <button @click="init()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- Calendar Header -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Project & Task Calendar</h3>
                <div class="flex items-center space-x-3">
                    <button @click="previousMonth()" 
                            class="bg-gray-600 text-white px-3 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="text-lg font-medium text-gray-900" x-text="currentMonthYear"></span>
                    <button @click="nextMonth()" 
                            class="bg-gray-600 text-white px-3 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- Calendar View Toggle -->
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">View:</label>
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <button @click="viewMode = 'month'" 
                                :class="viewMode === 'month' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-calendar-alt mr-1"></i>Month
                        </button>
                        <button @click="viewMode = 'week'" 
                                :class="viewMode === 'week' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-calendar-week mr-1"></i>Week
                        </button>
                        <button @click="viewMode = 'day'" 
                                :class="viewMode === 'day' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-calendar-day mr-1"></i>Day
                        </button>
                    </div>
                </div>
                
                <button @click="showCreateEvent = true" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Add Event
                </button>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="bg-white rounded-lg shadow p-6">
            <!-- Month View -->
            <div x-show="viewMode === 'month'" class="calendar-month-view">
                <!-- Calendar Header -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    <template x-for="day in weekDays" :key="day">
                        <div class="text-center text-sm font-medium text-gray-500 py-2" x-text="day"></div>
                    </template>
                </div>
                
                <!-- Calendar Days -->
                <div class="grid grid-cols-7 gap-1">
                    <template x-for="day in calendarDays" :key="day.date">
                        <div class="min-h-24 border border-gray-200 p-2 cursor-pointer hover:bg-gray-50"
                             :class="day.isCurrentMonth ? 'bg-white' : 'bg-gray-50'"
                             @click="selectDate(day.date)">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium" 
                                      :class="day.isToday ? 'text-blue-600 font-bold' : 'text-gray-900'"
                                      x-text="day.day"></span>
                                <span x-show="day.eventCount > 0" 
                                      class="text-xs bg-blue-100 text-blue-800 px-1 rounded-full"
                                      x-text="day.eventCount"></span>
                            </div>
                            
                            <!-- Events for this day -->
                            <div class="space-y-1">
                                <template x-for="event in day.events" :key="event.id">
                                    <div class="text-xs p-1 rounded truncate"
                                         :class="getEventClass(event.type)"
                                         :title="event.title"
                                         @click.stop="viewEvent(event)">
                                        <span x-text="event.title"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Week View -->
            <div x-show="viewMode === 'week'" class="calendar-week-view">
                <div class="grid grid-cols-8 gap-1">
                    <div class="text-sm font-medium text-gray-500 py-2">Time</div>
                    <template x-for="day in weekDays" :key="day">
                        <div class="text-center text-sm font-medium text-gray-500 py-2" x-text="day"></div>
                    </template>
                </div>
                
                <div class="grid grid-cols-8 gap-1">
                    <div class="text-xs text-gray-500 py-1">9:00 AM</div>
                    <template x-for="day in weekDays" :key="day">
                        <div class="min-h-16 border border-gray-200 p-1">
                            <!-- Week view content -->
                        </div>
                    </template>
                </div>
            </div>

            <!-- Day View -->
            <div x-show="viewMode === 'day'" class="calendar-day-view">
                <div class="text-center text-lg font-semibold text-gray-900 mb-4" x-text="selectedDate"></div>
                
                <div class="space-y-2">
                    <template x-for="event in selectedDayEvents" :key="event.id">
                        <div class="bg-white border rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900" x-text="event.title"></h4>
                                    <p class="text-xs text-gray-500" x-text="event.time"></p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span :class="getEventClass(event.type)" 
                                          class="px-2 py-1 text-xs font-semibold rounded-full" 
                                          x-text="event.type"></span>
                                    <button @click="editEvent(event)" 
                                            class="text-blue-600 hover:text-blue-900" 
                                            title="Edit Event">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Events</h3>
            <div class="space-y-3">
                <template x-for="event in upcomingEvents" :key="event.id">
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div :class="getEventClass(event.type)" 
                             class="w-3 h-3 rounded-full"></div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900" x-text="event.title"></h4>
                            <p class="text-xs text-gray-500" x-text="event.date + ' at ' + event.time"></p>
                        </div>
                        <span :class="getEventClass(event.type)" 
                              class="px-2 py-1 text-xs font-semibold rounded-full" 
                              x-text="event.type"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function appCalendar() {
    return {
        loading: true,
        error: null,
        currentDate: new Date(),
        selectedDate: null,
        viewMode: 'month', // 'month', 'week', 'day'
        showCreateEvent: false,
        
        // Calendar data
        calendarDays: [],
        weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        events: [],
        upcomingEvents: [],
        selectedDayEvents: [],
        
        get currentMonthYear() {
            return this.currentDate.toLocaleDateString('en-US', { 
                month: 'long', 
                year: 'numeric' 
            });
        },
        
        async init() {
            try {
                this.loading = true;
                this.error = null;
                
                // Load calendar events from API
                await this.loadCalendarEvents();
                
                this.generateCalendarDays();
                this.loadUpcomingEvents();
                this.loading = false;
                
            } catch (error) {
                this.error = error.message;
                this.loading = false;
            }
        },
        
        async loadCalendarEvents() {
            try {
                // Get current month date range
                const startDate = this.currentDate.toISOString().split('T')[0];
                const endDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 0).toISOString().split('T')[0];
                
                const response = await fetch(`/api/v1/app/calendar?start_date=${startDate}&end_date=${endDate}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (!response.ok) {
                    throw new Error('Failed to load calendar events');
                }
                
                const data = await response.json();
                this.events = data.data.events || [];
                
            } catch (error) {
                console.error('Error loading calendar events:', error);
                // Fallback to mock data
                this.events = [
                    {
                        id: 1,
                        title: 'Project Alpha Review',
                        date: '2024-01-15',
                        time: '10:00 AM',
                        type: 'meeting',
                        description: 'Review project progress and next steps'
                    },
                    {
                        id: 2,
                        title: 'Task Deadline - UI Design',
                        date: '2024-01-18',
                        time: '5:00 PM',
                        type: 'deadline',
                        description: 'Complete UI design for mobile app'
                    },
                    {
                        id: 3,
                        title: 'Team Standup',
                        date: '2024-01-20',
                        time: '9:00 AM',
                        type: 'meeting',
                        description: 'Daily team standup meeting'
                    }
                ];
            }
        },
        
        async loadUpcomingEvents() {
            try {
                const response = await fetch('/api/v1/app/calendar/upcoming?limit=5&days=7', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.upcomingEvents = data.data.events || [];
                } else {
                    // Fallback to filtered events
                    const today = new Date().toISOString().split('T')[0];
                    this.upcomingEvents = this.events
                        .filter(event => event.date >= today)
                        .sort((a, b) => new Date(a.date) - new Date(b.date))
                        .slice(0, 5);
                }
            } catch (error) {
                console.error('Error loading upcoming events:', error);
                // Fallback to filtered events
                const today = new Date().toISOString().split('T')[0];
                this.upcomingEvents = this.events
                    .filter(event => event.date >= today)
                    .sort((a, b) => new Date(a.date) - new Date(b.date))
                    .slice(0, 5);
            }
        },
        
        async createEvent(eventData) {
            try {
                const response = await fetch('/api/v1/app/calendar', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(eventData)
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to create event');
                }
                
                const data = await response.json();
                this.events.push(data.data);
                this.generateCalendarDays();
                this.loadUpcomingEvents();
                
                return data.data;
                
            } catch (error) {
                console.error('Error creating event:', error);
                throw error;
            }
        },
        
        async updateEvent(eventId, eventData) {
            try {
                const response = await fetch(`/api/v1/app/calendar/${eventId}`, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(eventData)
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to update event');
                }
                
                const data = await response.json();
                const index = this.events.findIndex(e => e.id === eventId);
                if (index !== -1) {
                    this.events[index] = data.data;
                }
                this.generateCalendarDays();
                this.loadUpcomingEvents();
                
                return data.data;
                
            } catch (error) {
                console.error('Error updating event:', error);
                throw error;
            }
        },
        
        async deleteEvent(eventId) {
            try {
                const response = await fetch(`/api/v1/app/calendar/${eventId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to delete event');
                }
                
                this.events = this.events.filter(e => e.id !== eventId);
                this.generateCalendarDays();
                this.loadUpcomingEvents();
                
            } catch (error) {
                console.error('Error deleting event:', error);
                throw error;
            }
        },
        
        generateCalendarDays() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const startDate = new Date(firstDay);
            startDate.setDate(startDate.getDate() - firstDay.getDay());
            
            this.calendarDays = [];
            
            for (let i = 0; i < 42; i++) {
                const date = new Date(startDate);
                date.setDate(startDate.getDate() + i);
                
                const dayEvents = this.events.filter(event => 
                    event.date === date.toISOString().split('T')[0]
                );
                
                this.calendarDays.push({
                    date: date.toISOString().split('T')[0],
                    day: date.getDate(),
                    isCurrentMonth: date.getMonth() === month,
                    isToday: date.toDateString() === new Date().toDateString(),
                    events: dayEvents,
                    eventCount: dayEvents.length
                });
            }
        },
        
        loadUpcomingEvents() {
            const today = new Date().toISOString().split('T')[0];
            this.upcomingEvents = this.events
                .filter(event => event.date >= today)
                .sort((a, b) => new Date(a.date) - new Date(b.date))
                .slice(0, 5);
        },
        
        selectDate(date) {
            this.selectedDate = date;
            this.selectedDayEvents = this.events.filter(event => event.date === date);
            this.viewMode = 'day';
        },
        
        previousMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() - 1);
            this.generateCalendarDays();
        },
        
        nextMonth() {
            this.currentDate.setMonth(this.currentDate.getMonth() + 1);
            this.generateCalendarDays();
        },
        
        viewEvent(event) {
            console.log('View event:', event);
            // TODO: Open event detail modal
        },
        
        editEvent(event) {
            console.log('Edit event:', event);
            // TODO: Open event edit modal
        },
        
        getEventClass(type) {
            const classes = {
                'meeting': 'bg-blue-100 text-blue-800',
                'deadline': 'bg-red-100 text-red-800',
                'task': 'bg-green-100 text-green-800',
                'project': 'bg-purple-100 text-purple-800'
            };
            return classes[type] || 'bg-gray-100 text-gray-800';
        }
    }
}
</script>
