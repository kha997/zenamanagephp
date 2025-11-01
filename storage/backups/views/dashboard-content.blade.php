<!-- Dashboard Content - Modern Design System -->
<style>
    [x-cloak] { display: none !important; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    
    /* Prevent chart flashing */
    canvas {
        opacity: 1 !important;
        transition: opacity 0.3s ease-in-out;
    }
    
    /* Ensure stable chart containers */
    .chart-container {
        min-height: 200px;
        position: relative;
    }
    
    /* Prevent layout shift during chart loading */
    #taskCompletionChart, #projectStatusChart, #teamPerformanceChart, #productivityChart {
        opacity: 1;
        visibility: visible;
    }
    
    /* KPI Cards Animations */
    .kpi-card {
        position: relative;
        overflow: hidden;
    }
    
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }
    
    .kpi-card:hover::before {
        left: 100%;
    }
    
    .kpi-card:hover {
        transform: translateY(-4px) scale(1.02);
    }
    
    /* Floating animation for decorative circles */
    .floating-circle {
        animation: float 6s ease-in-out infinite;
    }
    
    .floating-circle:nth-child(2) {
        animation-delay: -2s;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(180deg); }
    }
    
    /* Pulse animation for icons */
    .kpi-icon {
        animation: pulse-glow 2s ease-in-out infinite alternate;
    }
    
    @keyframes pulse-glow {
        from { box-shadow: 0 0 5px rgba(255,255,255,0.3); }
        to { box-shadow: 0 0 20px rgba(255,255,255,0.6), 0 0 30px rgba(255,255,255,0.3); }
    }
</style>
<div x-data="appSPA()" x-init="init()" class="space-y-8" :class="darkMode ? 'dark' : ''">
    
    <!-- Global Search & Filters Bar -->
    <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm mb-6">
        <div class="flex flex-col lg:flex-row gap-4">
            <!-- Global Search Bar -->
            <div class="flex-1">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input 
                        type="text" 
                        x-model="globalSearchQuery" 
                        @input="performGlobalSearch()"
                        @focus="showSearchSuggestions = true"
                        @blur="setTimeout(() => showSearchSuggestions = false, 200)"
                        placeholder="Search tasks, projects, team members, documents..."
                        class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <button @click="clearSearch()" 
                                x-show="globalSearchQuery"
                                class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <!-- Search Suggestions Dropdown -->
                    <div x-show="showSearchSuggestions && searchSuggestions.length > 0" 
                         class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                        <template x-for="suggestion in searchSuggestions" :key="suggestion.id">
                            <div @click="selectSuggestion(suggestion)" 
                                 class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <i :class="suggestion.icon" class="text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900" x-text="suggestion.title"></p>
                                        <p class="text-xs text-gray-500" x-text="suggestion.description"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Filters Toggle -->
            <div class="flex items-center space-x-2">
                <button @click="toggleAdvancedFilters()" 
                        :class="showAdvancedFilters ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                        class="px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    <i class="fas fa-filter mr-2"></i>
                    <span x-text="showAdvancedFilters ? 'Hide Filters' : 'Advanced Filters'"></span>
                </button>
                
                <button @click="resetAllFilters()" 
                        class="px-4 py-3 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                    <i class="fas fa-undo mr-2"></i>
                    Reset All
                </button>
            </div>
        </div>
        
        <!-- Advanced Filters Panel -->
        <div x-show="showAdvancedFilters" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="mt-4 pt-4 border-t border-gray-200">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Date Range Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <div class="space-y-2">
                        <input 
                            type="date" 
                            x-model="filters.dateFrom" 
                            @change="applyFilters()"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                        <input 
                            type="date" 
                            x-model="filters.dateTo" 
                            @change="applyFilters()"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </div>
                
                <!-- Priority Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select x-model="filters.priority" 
                            @change="applyFilters()"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Priorities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select x-model="filters.status" 
                            @change="applyFilters()"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                </div>
                
                <!-- Assignee Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                    <select x-model="filters.assignee" 
                            @change="applyFilters()"
                            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Assignees</option>
                        <option value="me">Me</option>
                        <option value="john_smith">John Smith</option>
                        <option value="sarah_johnson">Sarah Johnson</option>
                        <option value="mike_wilson">Mike Wilson</option>
                    </select>
                </div>
            </div>
            
            <!-- Quick Filter Tags -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quick Filters</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="tag in quickFilterTags" :key="tag.id">
                        <button @click="toggleQuickFilter(tag)" 
                                :class="tag.active ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                                class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                            <i :class="tag.icon" class="mr-1"></i>
                            <span x-text="tag.label"></span>
                        </button>
                    </template>
                </div>
            </div>
            
            <!-- Active Filters Display -->
            <div x-show="activeFiltersCount > 0" class="mt-4 pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-sm font-medium text-gray-700">Active Filters:</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full" x-text="activeFiltersCount"></span>
                    </div>
                    <button @click="clearAllFilters()" 
                            class="text-sm text-red-600 hover:text-red-800 font-medium">
                        Clear All Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Controls -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4">
            <!-- Customize Button -->
            <button @click="toggleCustomizeMode()" 
                    :class="customizeMode ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                <i class="fas fa-cog mr-2"></i>
                <span x-text="customizeMode ? 'Exit Customize' : 'Customize Dashboard'"></span>
            </button>
            
            <!-- Reset Layout -->
            <button @click="resetLayout()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                <i class="fas fa-undo mr-2"></i>
                Reset Layout
            </button>
        </div>
        
        <!-- Export Options -->
        <div class="flex items-center space-x-2">
            <button @click="exportToPDF()" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                <i class="fas fa-file-pdf mr-2"></i>
                Export PDF
            </button>
            <button @click="exportToExcel()" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 transition-colors">
                <i class="fas fa-file-excel mr-2"></i>
                Export Excel
            </button>
        </div>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-xl p-6" role="alert" aria-live="polite">
        <div class="flex items-center mb-4">
            <div class="p-2 bg-red-100 rounded-lg mr-3" aria-hidden="true">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-red-900">Something went wrong</h3>
                <p class="text-sm text-red-700 mt-1" x-text="error"></p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="retryLoad()" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                <i class="fas fa-redo mr-2"></i>
                Retry
            </button>
            <button @click="dismissError()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                Dismiss
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="space-y-6">
        <!-- KPI Loading -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <template x-for="i in 4" :key="'kpi-' + i">
                <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="animate-pulse">
                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                        <div class="h-8 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Charts Loading -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <template x-for="i in 4" :key="'chart-' + i">
                <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="animate-pulse">
                        <div class="h-6 bg-gray-200 rounded w-1/3 mb-4"></div>
                        <div class="h-48 bg-gray-200 rounded"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- KPI Strip -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Active Tasks KPI -->
            <div class="kpi-card relative overflow-hidden bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 rounded-xl p-4 sm:p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="floating-circle absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>
                <div class="floating-circle absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full translate-y-8 -translate-x-8"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-100 mb-1">Active Tasks</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.activeTasks || '--'"></p>
                            <p class="text-xs text-blue-200 mt-1">+12% from last week</p>
                        </div>
                        <div class="kpi-icon p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                            <i class="fas fa-tasks text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Completed Today KPI -->
            <div class="kpi-card relative overflow-hidden bg-gradient-to-br from-emerald-500 via-green-600 to-teal-700 rounded-xl p-4 sm:p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="floating-circle absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>
                <div class="floating-circle absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full translate-y-8 -translate-x-8"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-emerald-100 mb-1">Completed Today</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.completedToday || '--'"></p>
                            <p class="text-xs text-emerald-200 mt-1">+8% from yesterday</p>
                        </div>
                        <div class="kpi-icon p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                            <i class="fas fa-check-circle text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Team Members KPI -->
            <div class="kpi-card relative overflow-hidden bg-gradient-to-br from-purple-500 via-violet-600 to-purple-700 rounded-xl p-4 sm:p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="floating-circle absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>
                <div class="floating-circle absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full translate-y-8 -translate-x-8"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-100 mb-1">Team Members</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.teamMembers || '--'"></p>
                            <p class="text-xs text-purple-200 mt-1">+2 new members</p>
                        </div>
                        <div class="kpi-icon p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects KPI -->
            <div class="kpi-card relative overflow-hidden bg-gradient-to-br from-orange-500 via-red-500 to-pink-600 rounded-xl p-4 sm:p-6 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="floating-circle absolute top-0 right-0 w-20 h-20 bg-white/10 rounded-full -translate-y-10 translate-x-10"></div>
                <div class="floating-circle absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full translate-y-8 -translate-x-8"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-orange-100 mb-1">Active Projects</p>
                            <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.projects || '--'"></p>
                            <p class="text-xs text-orange-200 mt-1">+1 new project</p>
                        </div>
                        <div class="kpi-icon p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                            <i class="fas fa-project-diagram text-white text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Critical Alerts -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Critical Alerts</h3>
                        <p class="text-sm text-gray-500">Issues requiring immediate attention</p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-3">
                <template x-for="alert in alerts" :key="alert.id">
                    <div class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="p-1 bg-red-100 rounded">
                                <i class="fas fa-exclamation text-red-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-red-900" x-text="alert.title"></p>
                                <p class="text-xs text-red-700" x-text="alert.message"></p>
                            </div>
                        </div>
                        <button class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-1"></i>
                            View
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Do It Now - Priority Tasks -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <i class="fas fa-clock text-orange-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Do It Now</h3>
                        <p class="text-sm text-gray-500">Priority tasks requiring immediate attention</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="toggleFocusMode()" 
                            :class="focusMode.is_active ? 'bg-orange-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        <i class="fas fa-bullseye mr-1"></i>
                        <span x-text="focusMode.is_active ? 'Exit Focus' : 'Focus Mode'"></span>
                    </button>
                </div>
            </div>
            
            <div class="space-y-3">
                <template x-for="task in nowPanel" :key="task.id">
                    <div class="flex items-center justify-between p-4 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition-colors">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-orange-100 rounded-lg">
                                <i class="fas fa-tasks text-orange-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-orange-900" x-text="task.title"></p>
                                <p class="text-xs text-orange-700" x-text="task.description"></p>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="px-2 py-1 bg-orange-200 text-orange-800 text-xs rounded-full" x-text="task.priority"></span>
                                    <span class="text-xs text-orange-600" x-text="task.due_date"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700 transition-colors">
                                <i class="fas fa-play mr-1"></i>
                                Start
                            </button>
                            <button class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300 transition-colors">
                                <i class="fas fa-eye mr-1"></i>
                                View
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Work Queue -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-list-check text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Work Queue</h3>
                        <p class="text-sm text-gray-500">Your tasks and team assignments</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="activeTab = 'my'" 
                            :class="activeTab === 'my' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        My Work
                    </button>
                    <button @click="activeTab = 'team'" 
                            :class="activeTab === 'team' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">
                        Team Work
                    </button>
                </div>
            </div>
            
            <!-- My Work Tab -->
            <div x-show="activeTab === 'my'" class="space-y-3">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-gray-700">My Tasks (<span x-text="workQueue.my_work.total"></span>)</h4>
                    <button class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-1"></i>
                        Add Task
                    </button>
                </div>
                <template x-for="task in workQueue.my_work.tasks" :key="task.id">
                    <div class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-blue-900" x-text="task.title"></p>
                                <p class="text-xs text-blue-700" x-text="task.project"></p>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="px-2 py-1 bg-blue-200 text-blue-800 text-xs rounded-full" x-text="task.status"></span>
                                    <span class="text-xs text-blue-600" x-text="task.due_date"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                <i class="fas fa-edit mr-1"></i>
                                Edit
                            </button>
                        </div>
                    </div>
                </template>
            </div>
            
            <!-- Team Work Tab -->
            <div x-show="activeTab === 'team'" class="space-y-3">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-medium text-gray-700">Team Tasks (<span x-text="workQueue.team_work.total"></span>)</h4>
                    <button class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
                        <i class="fas fa-users mr-1"></i>
                        Assign Task
                    </button>
                </div>
                <template x-for="task in workQueue.team_work.tasks" :key="task.id">
                    <div class="flex items-center justify-between p-3 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-green-100 rounded-lg">
                                <i class="fas fa-users text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-green-900" x-text="task.title"></p>
                                <p class="text-xs text-green-700" x-text="task.assignee"></p>
                                <div class="flex items-center space-x-2 mt-1">
                                    <span class="px-2 py-1 bg-green-200 text-green-800 text-xs rounded-full" x-text="task.status"></span>
                                    <span class="text-xs text-green-600" x-text="task.due_date"></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700 transition-colors">
                                <i class="fas fa-eye mr-1"></i>
                                View
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Insights & Analytics -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-chart-line text-green-600"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900">Insights & Analytics</h3>
                        <p class="text-sm text-gray-500">Performance metrics and trends</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <select x-model="chartPeriod" @change="updateCharts()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="7d">Last 7 days</option>
                        <option value="30d">Last 30 days</option>
                        <option value="90d">Last 90 days</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <!-- Task Completion Chart -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-4 sm:p-6">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">Task Completion Trend</h4>
                    <div class="relative h-48 sm:h-64 chart-container">
                        <canvas id="taskCompletionChart" width="400" height="200" x-cloak></canvas>
                    </div>
                </div>
                
                <!-- Project Status Chart -->
                <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-4 sm:p-6">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">Project Status Distribution</h4>
                    <div class="relative h-48 sm:h-64 chart-container">
                        <canvas id="projectStatusChart" width="400" height="200" x-cloak></canvas>
                    </div>
                </div>
                
                <!-- Team Performance Chart -->
                <div class="bg-gradient-to-br from-purple-50 to-violet-50 dark:from-purple-900/20 dark:to-violet-900/20 rounded-xl p-4 sm:p-6">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">Team Performance</h4>
                    <div class="relative h-48 sm:h-64 chart-container">
                        <canvas id="teamPerformanceChart" width="400" height="200" x-cloak></canvas>
                    </div>
                </div>
                
                <!-- Productivity Metrics -->
                <div class="bg-gradient-to-br from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 rounded-xl p-4 sm:p-6">
                    <h4 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white mb-3 sm:mb-4">Productivity Metrics</h4>
                    <div class="relative h-48 sm:h-64 chart-container">
                        <canvas id="productivityChart" width="400" height="200" x-cloak></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-history text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                        <p class="text-sm text-gray-500">Latest updates and changes</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <select x-model="activityFilter" @change="filterActivity()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="all">All Activity</option>
                        <option value="tasks">Tasks</option>
                        <option value="projects">Projects</option>
                        <option value="team">Team</option>
                    </select>
                </div>
            </div>
            
            <div class="space-y-3">
                <template x-for="item in activity" :key="item.id">
                    <div class="flex items-center space-x-3 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-circle text-purple-600 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-purple-900" x-text="item.description"></p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-xs text-purple-600" x-text="item.user"></span>
                                <span class="text-xs text-purple-500">•</span>
                                <span class="text-xs text-purple-600" x-text="item.created_at"></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="px-3 py-1 bg-purple-600 text-white text-xs rounded hover:bg-purple-700 transition-colors">
                                <i class="fas fa-eye mr-1"></i>
                                View
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Quick Shortcuts -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-indigo-100 rounded-lg">
                        <i class="fas fa-bolt text-indigo-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Quick Shortcuts</h3>
                        <p class="text-sm text-gray-500">Frequently used actions</p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <template x-for="shortcut in shortcuts" :key="shortcut.id">
                    <button @click="executeShortcut(shortcut)" 
                            class="flex flex-col items-center p-4 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-colors">
                        <div class="p-3 bg-indigo-100 rounded-lg mb-2">
                            <i :class="shortcut.icon" class="text-indigo-600"></i>
                        </div>
                        <p class="text-sm font-medium text-indigo-900" x-text="shortcut.title"></p>
                        <p class="text-xs text-indigo-600 mt-1" x-text="shortcut.description"></p>
                    </button>
                </template>
            </div>
        </div>

        <!-- Focus Mode Panel -->
        <div x-show="focusMode.is_active" class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border-2 border-orange-200">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <i class="fas fa-bullseye text-orange-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Focus Mode</h3>
                        <p class="text-sm text-gray-500">Stay focused on your current task</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="toggleFocusMode()" 
                            class="px-3 py-2 bg-orange-600 text-white rounded-lg text-sm font-medium hover:bg-orange-700 transition-colors">
                        <i class="fas fa-times mr-1"></i>
                        Exit Focus
                    </button>
                </div>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <i class="fas fa-tasks text-orange-600"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-orange-900" x-text="focusMode.current_task?.title || 'No task selected'"></p>
                            <p class="text-xs text-orange-700" x-text="focusMode.current_task?.description || 'Select a task to focus on'"></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-orange-900">Focus Time Today</p>
                        <p class="text-lg font-bold text-orange-600" x-text="focusMode.focus_time_today + ' min'"></p>
                    </div>
                </div>
                
                <div class="flex items-center justify-center space-x-4">
                    <button class="px-6 py-3 bg-orange-600 text-white rounded-lg font-medium hover:bg-orange-700 transition-colors">
                        <i class="fas fa-play mr-2"></i>
                        Start Timer
                    </button>
                    <button class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                        <i class="fas fa-pause mr-2"></i>
                        Pause
                    </button>
                    <button class="px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                        <i class="fas fa-stop mr-2"></i>
                        Stop
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
function dashboardData() {
    return {
        // State management
        alerts: [],
        kpis: {},
        nowPanel: [],
        workQueue: { my_work: { tasks: [], total: 0 }, team_work: { tasks: [], total: 0 } },
        activity: [],
        shortcuts: [],
        focusMode: { is_active: false, current_task: null, focus_time_today: 0 },
        activeTab: 'my',
        activityFilter: 'all',
        loading: true,
        error: null,
        darkMode: false,
        chartPeriod: '7d',
        charts: {},
        chartsInitialized: false,
        customizeMode: false,
        
        // Search & Filter State
        globalSearchQuery: '',
        showSearchSuggestions: false,
        searchSuggestions: [],
        showAdvancedFilters: false,
        filters: {
            dateFrom: '',
            dateTo: '',
            priority: '',
            status: '',
            assignee: ''
        },
        quickFilterTags: [
            { id: 'today', label: 'Today', icon: 'fas fa-calendar-day', active: false },
            { id: 'high_priority', label: 'High Priority', icon: 'fas fa-exclamation', active: false },
            { id: 'my_tasks', label: 'My Tasks', icon: 'fas fa-user', active: false },
            { id: 'overdue', label: 'Overdue', icon: 'fas fa-clock', active: false },
            { id: 'completed', label: 'Completed', icon: 'fas fa-check', active: false }
        ],
        activeFiltersCount: 0,

        async init() {
            console.log('🚀 Dashboard init started');
            this.initTheme();
            
            // Wait for DOM to be ready before loading data
            await this.$nextTick();
            await this.loadDashboardData();
            
            // Wait for DOM to be ready and then init charts (only once)
            if (!this.chartsInitialized) {
                setTimeout(() => {
                    console.log('📊 Initializing charts...');
                    this.initCharts();
                }, 100);
            }
        },

        // Theme management
        initTheme() {
            const savedTheme = localStorage.getItem('darkMode');
            this.darkMode = savedTheme === 'true';
            this.updateTheme();
        },

        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
            this.updateTheme();
        },

        updateTheme() {
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        },

        // Error handling
        retryLoad() {
            console.log('🔄 Retrying dashboard data load...');
            this.error = null;
            this.loading = true;
            this.loadDashboardData();
        },
        
        dismissError() {
            console.log('❌ Dismissing error, using fallback data');
            this.error = null;
            this.loading = false;
        },

        // Data loading
        async loadDashboardData() {
            // Prevent multiple simultaneous loads
            if (this.loading && this.kpis && Object.keys(this.kpis).length > 0) {
                console.log('⏳ Dashboard data already loaded, skipping...');
                return;
            }
            
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading dashboard data...');
                
                // Get auth token from localStorage or session
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                // Fetch real data from API
                const response = await fetch('/_debug/dashboard-data', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('📊 API Response:', data);
                
                // Map API data to KPI format
                this.kpis = {
                    'Active Tasks': data.stats.totalTasks || 0,
                    'Completed Today': data.stats.completedTasks || 0,
                    'Team Members': data.stats.teamMembers || 0,
                    'Projects': data.stats.totalProjects || 0
                };
                
                this.alerts = [
                    {
                        id: 1,
                        title: 'Project Deadline Approaching',
                        message: 'Website Redesign project due in 2 days',
                        type: 'warning'
                    },
                    {
                        id: 2,
                        title: 'Server Maintenance Required',
                        message: 'Scheduled maintenance window tonight',
                        type: 'info'
                    }
                ];
                
                this.nowPanel = [
                    {
                        id: 1,
                        title: 'Review Design Mockups',
                        description: 'Review and approve design mockups for mobile app',
                        priority: 'High',
                        due_date: 'Today'
                    },
                    {
                        id: 2,
                        title: 'Update Project Documentation',
                        description: 'Update project documentation with latest changes',
                        priority: 'Medium',
                        due_date: 'Tomorrow'
                    }
                ];
                
                this.workQueue = {
                    my_work: {
                        tasks: [
                            {
                                id: 1,
                                title: 'Fix Login Bug',
                                project: 'Website Redesign',
                                status: 'In Progress',
                                due_date: 'Today'
                            },
                            {
                                id: 2,
                                title: 'Update User Interface',
                                project: 'Mobile App',
                                status: 'Pending',
                                due_date: 'Tomorrow'
                            }
                        ],
                        total: 2
                    },
                    team_work: {
                        tasks: [
                            {
                                id: 3,
                                title: 'Database Optimization',
                                assignee: 'John Smith',
                                status: 'In Progress',
                                due_date: 'Today'
                            },
                            {
                                id: 4,
                                title: 'API Integration',
                                assignee: 'Sarah Johnson',
                                status: 'Pending',
                                due_date: 'Tomorrow'
                            }
                        ],
                        total: 2
                    }
                };
                
                this.activity = [
                    {
                        id: 1,
                        description: 'Task "Fix Login Bug" was updated',
                        user: 'John Smith',
                        created_at: '2 hours ago'
                    },
                    {
                        id: 2,
                        description: 'New project "Mobile App" was created',
                        user: 'Sarah Johnson',
                        created_at: '4 hours ago'
                    },
                    {
                        id: 3,
                        description: 'Team member "Mike Wilson" joined',
                        user: 'Admin',
                        created_at: '6 hours ago'
                    }
                ];
                
                this.shortcuts = [
                    {
                        id: 1,
                        title: 'New Task',
                        description: 'Create task',
                        icon: 'fas fa-plus',
                        action: 'create_task'
                    },
                    {
                        id: 2,
                        title: 'New Project',
                        description: 'Create project',
                        icon: 'fas fa-folder-plus',
                        action: 'create_project'
                    },
                    {
                        id: 3,
                        title: 'Team Member',
                        description: 'Add member',
                        icon: 'fas fa-user-plus',
                        action: 'add_member'
                    },
                    {
                        id: 4,
                        title: 'Reports',
                        description: 'View reports',
                        icon: 'fas fa-chart-bar',
                        action: 'view_reports'
                    }
                ];
                
                this.loading = false;
                console.log('✅ Dashboard data loaded successfully');
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                this.error = error.message || 'Failed to load dashboard data. Please try again.';
                
                // Fallback to mock data
                console.log('🔄 Falling back to mock data...');
                this.kpis = {
                    'Active Tasks': 15,
                    'Completed Today': 8,
                    'Team Members': 5,
                    'Projects': 7
                };
                
                this.alerts = [
                    {
                        id: 1,
                        title: 'Project Deadline Approaching',
                        message: 'Website Redesign project due in 2 days',
                        type: 'warning'
                    }
                ];
                
                this.nowPanel = [
                    {
                        id: 1,
                        title: 'Review Design Mockups',
                        description: 'Review and approve design mockups for mobile app',
                        priority: 'High',
                        due_date: 'Today'
                    }
                ];
                
                this.workQueue = {
                    my_work: {
                        tasks: [
                            {
                                id: 1,
                                title: 'Fix Login Bug',
                                project: 'Website Redesign',
                                status: 'In Progress',
                                due_date: 'Today'
                            }
                        ],
                        total: 1
                    },
                    team_work: {
                        tasks: [],
                        total: 0
                    }
                };
                
                this.activity = [
                    {
                        id: 1,
                        description: 'Task "Fix Login Bug" was updated',
                        user: 'John Smith',
                        created_at: '2 hours ago'
                    }
                ];
                
                this.shortcuts = [
                    {
                        id: 1,
                        title: 'New Task',
                        description: 'Create task',
                        icon: 'fas fa-plus',
                        action: 'create_task'
                    }
                ];
                
                this.loading = false;
            }
        },

        // Chart management
        initCharts() {
            console.log('📊 initCharts called');
            console.log('Chart.js available:', typeof Chart !== 'undefined');
            
            // Prevent multiple chart initializations
            if (this.chartsInitialized) {
                console.log('⏳ Charts already initialized, skipping...');
                return;
            }
            
            // Wait for Chart.js to be available
            if (typeof Chart === 'undefined') {
                console.log('⏳ Chart.js not available yet, retrying in 500ms...');
                setTimeout(() => this.initCharts(), 500);
                return;
            }
            
            // Destroy existing charts first
            this.destroyCharts();
            
            try {
                this.createTaskCompletionChart();
                this.createProjectStatusChart();
                this.createTeamPerformanceChart();
                this.createProductivityChart();
                console.log('✅ All charts initialized successfully');
                this.chartsInitialized = true;
            } catch (error) {
                console.error('❌ Chart initialization error:', error);
                // Fallback: try again after a delay
                setTimeout(() => {
                    console.log('🔄 Retrying chart initialization...');
                    this.initCharts();
                }, 1000);
            }
        },

        destroyCharts() {
            // Destroy existing charts
            Object.values(this.charts).forEach(chart => {
                if (chart && typeof chart.destroy === 'function') {
                    chart.destroy();
                }
            });
            this.charts = {};
        },

        updateCharts() {
            // Destroy existing charts
            Object.values(this.charts).forEach(chart => {
                if (chart) chart.destroy();
            });
            this.charts = {};
            
            // Recreate charts with new period
            this.$nextTick(() => {
                this.initCharts();
            });
        },

        createTaskCompletionChart() {
            console.log('📈 Creating Task Completion Chart');
            const ctx = document.getElementById('taskCompletionChart');
            if (!ctx) {
                console.error('❌ taskCompletionChart canvas not found');
                return;
            }
            console.log('✅ taskCompletionChart canvas found');

            const data = this.getTaskCompletionData();
            
            this.charts.taskCompletion = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Completed Tasks',
                        data: data.completed,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Created Tasks',
                        data: data.created,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            console.log('✅ Task Completion Chart created');
        },

        createProjectStatusChart() {
            const ctx = document.getElementById('projectStatusChart');
            if (!ctx) return;

            const data = this.getProjectStatusData();
            
            this.charts.projectStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.values,
                        backgroundColor: [
                            'rgb(34, 197, 94)',  // Green - Completed
                            'rgb(59, 130, 246)', // Blue - In Progress
                            'rgb(245, 158, 11)', // Yellow - Planning
                            'rgb(239, 68, 68)'   // Red - On Hold
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        },

        createTeamPerformanceChart() {
            const ctx = document.getElementById('teamPerformanceChart');
            if (!ctx) return;

            const data = this.getTeamPerformanceData();
            
            this.charts.teamPerformance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Tasks Completed',
                        data: data.completed,
                        backgroundColor: 'rgba(147, 51, 234, 0.8)',
                        borderColor: 'rgb(147, 51, 234)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        createProductivityChart() {
            const ctx = document.getElementById('productivityChart');
            if (!ctx) return;

            const data = this.getProductivityData();
            
            this.charts.productivity = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Productivity Score',
                        data: data.values,
                        backgroundColor: 'rgba(245, 158, 11, 0.2)',
                        borderColor: 'rgb(245, 158, 11)',
                        pointBackgroundColor: 'rgb(245, 158, 11)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgb(245, 158, 11)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        // Data generators for charts
        getTaskCompletionData() {
            const days = this.chartPeriod === '7d' ? 7 : this.chartPeriod === '30d' ? 30 : 90;
            const labels = [];
            const completed = [];
            const created = [];
            
            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                completed.push(Math.floor(Math.random() * 20) + 5);
                created.push(Math.floor(Math.random() * 15) + 3);
            }
            
            return { labels, completed, created };
        },

        getProjectStatusData() {
            return {
                labels: ['Completed', 'In Progress', 'Planning', 'On Hold'],
                values: [12, 8, 5, 2]
            };
        },

        getTeamPerformanceData() {
            return {
                labels: ['John', 'Sarah', 'Mike', 'Lisa', 'David'],
                completed: [45, 38, 42, 35, 40]
            };
        },

        getProductivityData() {
            return {
                labels: ['Efficiency', 'Quality', 'Speed', 'Collaboration', 'Innovation'],
                values: [85, 90, 75, 88, 82]
            };
        },

        // Dashboard controls
        toggleCustomizeMode() {
            this.customizeMode = !this.customizeMode;
        },

        resetLayout() {
            if (confirm('Are you sure you want to reset the dashboard layout?')) {
                localStorage.removeItem('dashboardLayout');
                location.reload();
            }
        },

        // Focus Mode
        toggleFocusMode() {
            this.focusMode.is_active = !this.focusMode.is_active;
            if (this.focusMode.is_active) {
                // Set focus time to 0 when starting
                this.focusMode.focus_time_today = 0;
            }
        },

        // Activity filtering
        filterActivity() {
            // In a real app, this would filter the activity array
            console.log('Filtering activity by:', this.activityFilter);
        },

        // Shortcut execution
        executeShortcut(shortcut) {
            console.log('Executing shortcut:', shortcut.action);
            // In a real app, this would navigate to the appropriate page
            switch(shortcut.action) {
                case 'create_task':
                    alert('Navigate to create task page');
                    break;
                case 'create_project':
                    alert('Navigate to create project page');
                    break;
                case 'add_member':
                    alert('Navigate to add member page');
                    break;
                case 'view_reports':
                    alert('Navigate to reports page');
                    break;
                default:
                    console.log('Unknown shortcut action:', shortcut.action);
            }
        },

        // Global Search Functions
        performGlobalSearch() {
            if (this.globalSearchQuery.length < 2) {
                this.searchSuggestions = [];
                return;
            }
            
            // Simulate search suggestions
            this.searchSuggestions = [
                {
                    id: 1,
                    title: 'Fix Login Bug',
                    description: 'Task in Website Redesign project',
                    icon: 'fas fa-tasks',
                    type: 'task'
                },
                {
                    id: 2,
                    title: 'Website Redesign',
                    description: 'Project with 5 tasks',
                    icon: 'fas fa-folder',
                    type: 'project'
                },
                {
                    id: 3,
                    title: 'John Smith',
                    description: 'Team member - Developer',
                    icon: 'fas fa-user',
                    type: 'user'
                },
                {
                    id: 4,
                    title: 'API Documentation',
                    description: 'Document in Mobile App project',
                    icon: 'fas fa-file-alt',
                    type: 'document'
                }
            ].filter(item => 
                item.title.toLowerCase().includes(this.globalSearchQuery.toLowerCase()) ||
                item.description.toLowerCase().includes(this.globalSearchQuery.toLowerCase())
            );
        },

        selectSuggestion(suggestion) {
            this.globalSearchQuery = suggestion.title;
            this.showSearchSuggestions = false;
            console.log('Selected suggestion:', suggestion);
            // In a real app, this would navigate to the item
        },

        clearSearch() {
            this.globalSearchQuery = '';
            this.searchSuggestions = [];
            this.showSearchSuggestions = false;
        },

        // Advanced Filters Functions
        toggleAdvancedFilters() {
            this.showAdvancedFilters = !this.showAdvancedFilters;
        },

        applyFilters() {
            this.updateActiveFiltersCount();
            console.log('Applying filters:', this.filters);
            // In a real app, this would filter the data
        },

        resetAllFilters() {
            this.filters = {
                dateFrom: '',
                dateTo: '',
                priority: '',
                status: '',
                assignee: ''
            };
            this.quickFilterTags.forEach(tag => tag.active = false);
            this.updateActiveFiltersCount();
            console.log('All filters reset');
        },

        clearAllFilters() {
            this.resetAllFilters();
        },

        // Quick Filter Functions
        toggleQuickFilter(tag) {
            tag.active = !tag.active;
            this.updateActiveFiltersCount();
            console.log('Quick filter toggled:', tag);
        },

        updateActiveFiltersCount() {
            let count = 0;
            
            // Count advanced filters
            Object.values(this.filters).forEach(value => {
                if (value && value !== '') count++;
            });
            
            // Count quick filter tags
            this.quickFilterTags.forEach(tag => {
                if (tag.active) count++;
            });
            
            this.activeFiltersCount = count;
        },

        // Export functionality
        async exportToPDF() {
            try {
                alert('PDF export functionality will be implemented');
            } catch (error) {
                console.error('PDF export error:', error);
            }
        },

        async exportToExcel() {
            try {
                alert('Excel export functionality will be implemented');
            } catch (error) {
                console.error('Excel export error:', error);
            }
        }
    }
}
</script>

<!-- Fallback Chart Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 DOM Content Loaded - Checking for charts...');
    
    // Test Chart.js availability
    if (typeof Chart !== 'undefined') {
        console.log('✅ Chart.js is available');
    } else {
        console.log('❌ Chart.js not available');
    }
    
    // Wait a bit for Alpine.js to initialize
    setTimeout(() => {
        console.log('📊 Attempting to initialize charts...');
        
        // Try to find the dashboard component
        const dashboardElement = document.querySelector('[x-data*="dashboardData"]');
        console.log('Dashboard element found:', !!dashboardElement);
        
        if (dashboardElement && dashboardElement._x_dataStack) {
            const dashboardData = dashboardElement._x_dataStack[0];
            console.log('Dashboard data found:', !!dashboardData);
            console.log('initCharts method available:', typeof dashboardData.initCharts === 'function');
            
            if (dashboardData && typeof dashboardData.initCharts === 'function') {
                console.log('✅ Found dashboard data, calling initCharts...');
                dashboardData.initCharts();
            } else {
                console.log('❌ Dashboard data not found or initCharts not available');
            }
        } else {
            console.log('❌ Dashboard element not found or no data stack');
            
            // Fallback: Try to create charts directly
            console.log('🔄 Attempting direct chart creation...');
            createChartsDirectly();
        }
    }, 1000);
});

function createChartsDirectly() {
    console.log('🎯 Creating charts directly...');
    
    // Check if canvas elements exist
    const canvases = [
        { id: 'taskCompletionChart', type: 'line', title: 'Task Completion Trend' },
        { id: 'projectStatusChart', type: 'doughnut', title: 'Project Status Distribution' },
        { id: 'teamPerformanceChart', type: 'bar', title: 'Team Performance' },
        { id: 'productivityChart', type: 'radar', title: 'Productivity Metrics' }
    ];
    
    canvases.forEach(canvasInfo => {
        const canvas = document.getElementById(canvasInfo.id);
        console.log(`${canvasInfo.id} canvas found:`, !!canvas);
        
        if (canvas && typeof Chart !== 'undefined') {
            console.log(`Creating ${canvasInfo.id}...`);
            
            // Create appropriate chart based on type
            const ctx = canvas.getContext('2d');
            let chartConfig;
            
            switch(canvasInfo.type) {
                case 'line':
                    chartConfig = {
                        type: 'line',
                        data: {
                            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                            datasets: [{
                                label: 'Completed Tasks',
                                data: [12, 19, 3, 5, 2, 3, 7],
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'top' }
                            },
                            scales: { y: { beginAtZero: true } }
                        }
                    };
                    break;
                    
                case 'doughnut':
                    chartConfig = {
                        type: 'doughnut',
                        data: {
                            labels: ['Completed', 'In Progress', 'Planning', 'On Hold'],
                            datasets: [{
                                data: [12, 8, 5, 2],
                                backgroundColor: [
                                    'rgb(34, 197, 94)',
                                    'rgb(59, 130, 246)',
                                    'rgb(245, 158, 11)',
                                    'rgb(239, 68, 68)'
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    };
                    break;
                    
                case 'bar':
                    chartConfig = {
                        type: 'bar',
                        data: {
                            labels: ['John', 'Sarah', 'Mike', 'Lisa', 'David'],
                            datasets: [{
                                label: 'Tasks Completed',
                                data: [45, 38, 42, 35, 40],
                                backgroundColor: 'rgba(147, 51, 234, 0.8)',
                                borderColor: 'rgb(147, 51, 234)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: { y: { beginAtZero: true } }
                        }
                    };
                    break;
                    
                case 'radar':
                    chartConfig = {
                        type: 'radar',
                        data: {
                            labels: ['Efficiency', 'Quality', 'Speed', 'Collaboration', 'Innovation'],
                            datasets: [{
                                label: 'Productivity Score',
                                data: [85, 90, 75, 88, 82],
                                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                                borderColor: 'rgb(245, 158, 11)',
                                pointBackgroundColor: 'rgb(245, 158, 11)',
                                pointBorderColor: '#fff',
                                pointHoverBackgroundColor: '#fff',
                                pointHoverBorderColor: 'rgb(245, 158, 11)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                r: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            }
                        }
                    };
                    break;
            }
            
            try {
                new Chart(ctx, chartConfig);
                console.log(`✅ ${canvasInfo.id} (${canvasInfo.type}) created successfully`);
            } catch (error) {
                console.error(`❌ Error creating ${canvasInfo.id}:`, error);
            }
        }
    });
}
</script>