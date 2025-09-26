{{-- Calendar Management - Complete Implementation --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Management - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="calendarManagement()">
    <!-- Universal Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-calendar text-purple-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Calendar Management</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-purple-500 mr-1"></i>
                            <span x-text="events.length"></span> Events
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="openCreateModal" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        New Event
                    </button>
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=Calendar+Manager&background=8b5cf6&color=ffffff" 
                                 alt="User" class="h-8 w-8 rounded-full">
                            <span class="hidden md:block text-sm font-medium">Calendar Manager</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Universal Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-8">
                    <a href="/app/dashboard" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="/app/projects" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>Projects
                    </a>
                    <a href="/app/tasks" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-tasks mr-2"></i>Tasks
                    </a>
                    <a href="/app/calendar" class="text-purple-600 font-medium border-b-2 border-purple-600 pb-2">
                        <i class="fas fa-calendar mr-2"></i>Calendar
                    </a>
                    <a href="/app/documents" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-file-alt mr-2"></i>Documents
                    </a>
                    <a href="/app/team" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-users mr-2"></i>Team
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <button @click="previousMonth" class="p-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button @click="today" class="px-3 py-1 bg-purple-600 text-white rounded-lg text-sm font-medium">
                            Today
                        </button>
                        <button @click="nextMonth" class="p-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- KPI Strip -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Total Events</p>
                            <p class="text-3xl font-bold" x-text="stats.totalEvents">24</p>
                            <p class="text-purple-100 text-sm">
                                <i class="fas fa-calendar mr-1"></i>
                                This month
                            </p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-calendar text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Meetings</p>
                            <p class="text-3xl font-bold" x-text="stats.meetings">12</p>
                            <p class="text-blue-100 text-sm">
                                <i class="fas fa-users mr-1"></i>
                                Scheduled
                            </p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Deadlines</p>
                            <p class="text-3xl font-bold" x-text="stats.deadlines">8</p>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-clock mr-1"></i>
                                Upcoming
                            </p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Today's Events</p>
                            <p class="text-3xl font-bold" x-text="stats.todayEvents">3</p>
                            <p class="text-orange-100 text-sm">
                                <i class="fas fa-calendar-day mr-1"></i>
                                Scheduled
                            </p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-calendar-day text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Calendar Controls -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <h2 class="text-xl font-semibold text-gray-900" x-text="currentMonthYear"></h2>
                    <div class="flex items-center space-x-2">
                        <button @click="setView('month')" 
                                :class="viewMode === 'month' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="px-3 py-1 rounded-lg text-sm font-medium transition-colors">
                            Month
                        </button>
                        <button @click="setView('week')" 
                                :class="viewMode === 'week' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="px-3 py-1 rounded-lg text-sm font-medium transition-colors">
                            Week
                        </button>
                        <button @click="setView('day')" 
                                :class="viewMode === 'day' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                                class="px-3 py-1 rounded-lg text-sm font-medium transition-colors">
                            Day
                        </button>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-700">Filter:</span>
                    <button @click="setFilter('all')" 
                            :class="currentFilter === 'all' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        All Events
                    </button>
                    <button @click="setFilter('meetings')" 
                            :class="currentFilter === 'meetings' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Meetings
                    </button>
                    <button @click="setFilter('deadlines')" 
                            :class="currentFilter === 'deadlines' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Deadlines
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Month View -->
        <div x-show="viewMode === 'month'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <!-- Calendar Header -->
            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                <template x-for="day in weekDays" :key="day">
                    <div class="p-4 text-center text-sm font-medium text-gray-700" x-text="day"></div>
                </template>
            </div>
            
            <!-- Calendar Grid -->
            <div class="grid grid-cols-7">
                <template x-for="day in calendarDays" :key="day.date">
                    <div class="min-h-24 border-r border-b border-gray-200 p-2 hover:bg-gray-50 cursor-pointer"
                         :class="day.isCurrentMonth ? 'bg-white' : 'bg-gray-50'"
                         @click="selectDate(day.date)">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium" 
                                  :class="day.isToday ? 'bg-purple-600 text-white rounded-full w-6 h-6 flex items-center justify-center' : 'text-gray-900'"
                                  x-text="day.day"></span>
                        </div>
                        <div class="space-y-1">
                            <template x-for="event in getEventsForDate(day.date)" :key="event.id">
                                <div :class="getEventColor(event.type)" 
                                     class="text-xs p-1 rounded truncate cursor-pointer hover:opacity-80"
                                     @click.stop="openEventModal(event)">
                                    <span x-text="event.title"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Week View -->
        <div x-show="viewMode === 'week'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="grid grid-cols-8">
                <!-- Time column -->
                <div class="bg-gray-50 border-r border-gray-200 p-4">
                    <div class="text-sm font-medium text-gray-700">Time</div>
                </div>
                
                <!-- Days of week -->
                <template x-for="day in weekDays" :key="day">
                    <div class="border-r border-gray-200 p-4 text-center">
                        <div class="text-sm font-medium text-gray-700" x-text="day"></div>
                        <div class="text-lg font-semibold text-gray-900 mt-1" x-text="getWeekDayNumber(day)"></div>
                    </div>
                </template>
            </div>
            
            <!-- Time slots -->
            <div class="grid grid-cols-8">
                <template x-for="hour in timeSlots" :key="hour">
                    <div class="border-r border-b border-gray-200 p-2 text-xs text-gray-500" x-text="hour"></div>
                    <template x-for="day in weekDays" :key="day">
                        <div class="border-r border-b border-gray-200 min-h-12 p-1 hover:bg-gray-50">
                            <!-- Events for this time slot -->
                        </div>
                    </template>
                </template>
            </div>
        </div>

        <!-- Day View -->
        <div x-show="viewMode === 'day'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900" x-text="selectedDate"></h3>
            </div>
            <div class="grid grid-cols-12">
                <div class="col-span-2 bg-gray-50 border-r border-gray-200 p-4">
                    <div class="text-sm font-medium text-gray-700">Time</div>
                </div>
                <div class="col-span-10 p-4">
                    <div class="space-y-4">
                        <template x-for="event in getEventsForDate(selectedDate)" :key="event.id">
                            <div :class="getEventColor(event.type)" 
                                 class="p-4 rounded-lg cursor-pointer hover:opacity-80"
                                 @click="openEventModal(event)">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium text-white" x-text="event.title"></h4>
                                    <span class="text-white text-sm" x-text="event.time"></span>
                                </div>
                                <p class="text-white text-sm mt-1" x-text="event.description"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Events Sidebar -->
        <div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Upcoming Events</h3>
            <div class="space-y-4">
                <template x-for="event in upcomingEvents" :key="event.id">
                    <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg cursor-pointer"
                         @click="openEventModal(event)">
                        <div :class="getEventColor(event.type)" class="w-3 h-3 rounded-full mt-2"></div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-medium text-gray-900" x-text="event.title"></h4>
                            <p class="text-sm text-gray-600" x-text="event.description"></p>
                            <p class="text-xs text-gray-500 mt-1" x-text="event.date + ' at ' + event.time"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </main>

    <!-- Create Event Modal -->
    <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Create New Event</h3>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="createEvent">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Event Title</label>
                            <input type="text" x-model="newEvent.title" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Event Type</label>
                            <select x-model="newEvent.type" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="meeting">Meeting</option>
                                <option value="deadline">Deadline</option>
                                <option value="task">Task</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea x-model="newEvent.description" rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" x-model="newEvent.startDate" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                            <input type="time" x-model="newEvent.startTime" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" x-model="newEvent.endDate" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                            <input type="time" x-model="newEvent.endTime" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="closeCreateModal" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            Create Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Event Detail Modal -->
    <div x-show="showEventModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900" x-text="selectedEvent?.title"></h3>
                    <button @click="closeEventModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div x-show="selectedEvent">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <p class="text-gray-900" x-text="selectedEvent?.description"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                                <span :class="getEventColor(selectedEvent?.type)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full text-white" 
                                      x-text="selectedEvent?.type"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                <span class="text-gray-900" x-text="selectedEvent?.date"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Time</label>
                                <span class="text-gray-900" x-text="selectedEvent?.startTime"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">End Time</label>
                                <span class="text-gray-900" x-text="selectedEvent?.endTime"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calendarManagement() {
            return {
                viewMode: 'month',
                currentFilter: 'all',
                showCreateModal: false,
                showEventModal: false,
                selectedEvent: null,
                selectedDate: new Date().toISOString().split('T')[0],
                currentDate: new Date(),
                
                newEvent: {
                    title: '',
                    description: '',
                    type: 'meeting',
                    startDate: '',
                    startTime: '',
                    endDate: '',
                    endTime: ''
                },

                weekDays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                timeSlots: ['9:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'],

                stats: {
                    totalEvents: 24,
                    meetings: 12,
                    deadlines: 8,
                    todayEvents: 3
                },

                events: [
                    {
                        id: 1,
                        title: 'Team Meeting',
                        description: 'Weekly team standup meeting',
                        type: 'meeting',
                        date: '2025-09-25',
                        startTime: '09:00',
                        endTime: '10:00'
                    },
                    {
                        id: 2,
                        title: 'Project Deadline',
                        description: 'Website redesign project deadline',
                        type: 'deadline',
                        date: '2025-09-30',
                        startTime: '17:00',
                        endTime: '17:00'
                    },
                    {
                        id: 3,
                        title: 'Client Call',
                        description: 'Client presentation and feedback session',
                        type: 'meeting',
                        date: '2025-09-26',
                        startTime: '14:00',
                        endTime: '15:30'
                    },
                    {
                        id: 4,
                        title: 'Code Review',
                        description: 'Review mobile app development progress',
                        type: 'task',
                        date: '2025-09-27',
                        startTime: '11:00',
                        endTime: '12:00'
                    },
                    {
                        id: 5,
                        title: 'Database Migration',
                        description: 'Complete database migration to new server',
                        type: 'deadline',
                        date: '2025-09-28',
                        startTime: '10:00',
                        endTime: '16:00'
                    }
                ],

                get currentMonthYear() {
                    return this.currentDate.toLocaleDateString('en-US', { 
                        month: 'long', 
                        year: 'numeric' 
                    });
                },

                get calendarDays() {
                    const year = this.currentDate.getFullYear();
                    const month = this.currentDate.getMonth();
                    const firstDay = new Date(year, month, 1);
                    const lastDay = new Date(year, month + 1, 0);
                    const startDate = new Date(firstDay);
                    startDate.setDate(startDate.getDate() - firstDay.getDay());
                    
                    const days = [];
                    const today = new Date();
                    
                    for (let i = 0; i < 42; i++) {
                        const date = new Date(startDate);
                        date.setDate(startDate.getDate() + i);
                        
                        days.push({
                            date: date.toISOString().split('T')[0],
                            day: date.getDate(),
                            isCurrentMonth: date.getMonth() === month,
                            isToday: date.toDateString() === today.toDateString()
                        });
                    }
                    
                    return days;
                },

                get upcomingEvents() {
                    const today = new Date();
                    return this.events
                        .filter(event => new Date(event.date) >= today)
                        .sort((a, b) => new Date(a.date) - new Date(b.date))
                        .slice(0, 5);
                },

                getEventsForDate(date) {
                    return this.events.filter(event => event.date === date);
                },

                getEventColor(type) {
                    const colors = {
                        'meeting': 'bg-blue-500',
                        'deadline': 'bg-red-500',
                        'task': 'bg-green-500',
                        'reminder': 'bg-yellow-500'
                    };
                    return colors[type] || 'bg-gray-500';
                },

                setView(mode) {
                    this.viewMode = mode;
                },

                setFilter(filter) {
                    this.currentFilter = filter;
                },

                previousMonth() {
                    this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                },

                nextMonth() {
                    this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                },

                today() {
                    this.currentDate = new Date();
                    this.selectedDate = this.currentDate.toISOString().split('T')[0];
                },

                selectDate(date) {
                    this.selectedDate = date;
                    if (this.viewMode === 'day') {
                        // Stay in day view
                    } else {
                        this.viewMode = 'day';
                    }
                },

                getWeekDayNumber(day) {
                    const today = new Date();
                    const dayIndex = this.weekDays.indexOf(day);
                    const weekStart = new Date(today);
                    weekStart.setDate(today.getDate() - today.getDay());
                    weekStart.setDate(weekStart.getDate() + dayIndex);
                    return weekStart.getDate();
                },

                openCreateModal() {
                    this.showCreateModal = true;
                },

                closeCreateModal() {
                    this.showCreateModal = false;
                    this.newEvent = {
                        title: '',
                        description: '',
                        type: 'meeting',
                        startDate: '',
                        startTime: '',
                        endDate: '',
                        endTime: ''
                    };
                },

                createEvent() {
                    // Add new event to the list
                    const newId = Math.max(...this.events.map(e => e.id)) + 1;
                    this.events.push({
                        id: newId,
                        ...this.newEvent,
                        time: this.newEvent.startTime + ' - ' + this.newEvent.endTime
                    });
                    
                    this.closeCreateModal();
                },

                openEventModal(event) {
                    this.selectedEvent = event;
                    this.showEventModal = true;
                },

                closeEventModal() {
                    this.showEventModal = false;
                    this.selectedEvent = null;
                }
            }
        }
    </script>
</body>
</html>
