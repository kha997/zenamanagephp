<!-- Projects Content - Modern Design System -->
<style>
    [x-cloak] { display: none !important; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    
    /* Project Card Animations */
    .project-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .project-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .project-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }
    
    .project-card:hover::before {
        left: 100%;
    }
    
    /* Status Badge Colors */
    .status-active { @apply bg-green-100 text-green-800 border-green-200; }
    .status-planning { @apply bg-blue-100 text-blue-800 border-blue-200; }
    .status-on-hold { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
    .status-completed { @apply bg-gray-100 text-gray-800 border-gray-200; }
    .status-cancelled { @apply bg-red-100 text-red-800 border-red-200; }
    
    /* Priority Indicators */
    .priority-high { @apply border-l-4 border-red-500; }
    .priority-medium { @apply border-l-4 border-yellow-500; }
    .priority-low { @apply border-l-4 border-green-500; }
    
    /* Progress Bar Animation */
    .progress-bar {
        transition: width 0.3s ease-in-out;
    }
    
    /* Line clamp for description */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<div x-data="projectsPage()" x-init="loadProjects()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-end">
        <div class="flex items-center space-x-6">
            <!-- Projects Title and Description -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Projects</h1>
                <p class="mt-1 text-sm text-gray-500">Manage and track your projects</p>
            </div>
            
            <div class="flex space-x-3">
                <button @click="showFilters = !showFilters" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-filter mr-2"></i>
                    Filters
                </button>
                <button @click="createProject()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>
                    New Project
                </button>
            </div>
        </div>
    </div>

    <!-- KPI Strip (Projects-only) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Projects -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" 
             @click="filterByStatus('all')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Projects</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="kpis.totalProjects || '--'"></p>
                    <p class="text-xs text-gray-500" x-text="`${kpis.activeProjects || 0} active`"></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- On-time Rate -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" 
             @click="filterByOverdue()">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">On-time Rate</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="kpis.onTimeRate ? kpis.onTimeRate + '%' : '--'"></p>
                    <p class="text-xs text-red-500" x-text="`${kpis.overdueProjects || 0} overdue`"></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Budget Usage -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" 
             @click="filterByOverbudget()">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Budget Usage</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="kpis.budgetUsage || '--'"></p>
                    <p class="text-xs text-orange-500" x-text="`${kpis.overBudgetProjects || 0} over-budget`"></p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>

        <!-- Health Snapshot -->
        <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow cursor-pointer" 
             @click="filterByHealth('at_risk')">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600">Health Snapshot</p>
                    <p class="text-2xl font-bold text-gray-900" x-text="kpis.healthSnapshot || '--'"></p>
                    <p class="text-xs text-red-500" x-text="`${kpis.atRiskProjects || 0} at-risk, ${kpis.criticalProjects || 0} critical`"></p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-heartbeat text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Bar (Projects) -->
    <div x-show="alerts.length > 0" x-transition class="mb-6">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-400"></i>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-medium text-red-800">Critical Project Alerts</h3>
                    <div class="mt-2">
                        <template x-for="alert in alerts.slice(0, 3)" :key="alert.id">
                            <div class="text-sm text-red-700 mb-1">
                                <span x-text="alert.message"></span>
                                <button @click="handleAlert(alert)" 
                                        class="ml-2 text-red-600 hover:text-red-800 underline">
                                    <span x-text="alert.action"></span>
                                </button>
                            </div>
                        </template>
                        <div x-show="alerts.length > 3" class="text-sm text-red-700">
                            <button @click="viewAllAlerts()" class="hover:text-red-800 underline">
                                View all <span x-text="alerts.length - 3"></span> alerts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Now Panel (Do it now) -->
    <div x-show="nowPanelActions.length > 0" x-transition class="mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="text-sm font-medium text-blue-800 mb-3">Do it now</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                <template x-for="action in nowPanelActions" :key="action.id">
                    <button @click="executeNowAction(action)" 
                            class="bg-white border border-blue-200 rounded-lg p-3 text-left hover:bg-blue-50 transition-colors">
                        <div class="flex items-center space-x-2">
                            <i :class="action.icon" class="text-blue-600"></i>
                            <span class="text-sm font-medium text-blue-800" x-text="action.title"></span>
                        </div>
                        <p class="text-xs text-blue-600 mt-1" x-text="action.description"></p>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- Global Search & Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <!-- Search Bar -->
        <div class="relative mb-4">
            <input type="text" 
                   placeholder="Search projects..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   x-model="searchQuery"
                   @input="debounceSearch()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>

        <!-- Enhanced Filter Panel -->
        <div x-show="showFilters" x-transition class="border-t pt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select x-model="filters.status" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="planning">Planning</option>
                        <option value="active">Active</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- PM Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Manager</label>
                    <select x-model="filters.pm" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All PMs</option>
                        <template x-for="pm in filterOptions.pms" :key="pm.id">
                            <option :value="pm.id" x-text="pm.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Client Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                    <select x-model="filters.client" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Clients</option>
                        <template x-for="client in filterOptions.clients" :key="client.id">
                            <option :value="client.id" x-text="client.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <select x-model="filters.dateRange" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Dates</option>
                        <option value="due_7">Due in 7 days</option>
                        <option value="due_30">Due in 30 days</option>
                        <option value="due_90">Due in 90 days</option>
                        <option value="overdue">Overdue</option>
                        <option value="custom">Custom Range</option>
                    </select>
                </div>

                <!-- Health Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Health</label>
                    <select x-model="filters.health" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Health</option>
                        <option value="good">Good</option>
                        <option value="at_risk">At Risk</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <!-- Budget Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Budget</label>
                    <select x-model="filters.budget" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Budgets</option>
                        <option value="overbudget">Over Budget</option>
                        <option value="underbudget">Under Budget</option>
                        <option value="onbudget">On Budget</option>
                    </select>
                </div>

                <!-- Tags Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                    <select x-model="filters.tags" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Tags</option>
                        <template x-for="tag in filterOptions.tags" :key="'filter-' + tag">
                            <option :value="tag" x-text="tag"></option>
                        </template>
                    </select>
                </div>

                <!-- Location Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                    <select x-model="filters.location" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Locations</option>
                        <template x-for="location in filterOptions.locations" :key="location">
                            <option :value="location" x-text="location"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Saved Views -->
            <div class="mt-4 pt-4 border-t">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-700">Saved Views</h3>
                    <button @click="showSaveViewModal = true" 
                            class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-plus mr-1"></i>Save Current View
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <template x-for="view in savedViews" :key="view.id">
                        <button @click="loadSavedView(view)" 
                                :class="currentViewId === view.id ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-700'"
                                class="px-3 py-1 rounded-full text-sm hover:bg-blue-50 transition-colors">
                            <span x-text="view.name"></span>
                            <button @click.stop="deleteSavedView(view.id)" 
                                    class="ml-2 text-gray-500 hover:text-red-600">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Active Filters -->
            <div x-show="activeFilters.length > 0" class="mt-4 pt-4 border-t">
                <div class="flex items-center justify-between">
                    <div class="flex flex-wrap gap-2">
                        <template x-for="filter in activeFilters" :key="filter.key">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800">
                                <span x-text="filter.label + ': ' + filter.value"></span>
                                <button @click="removeFilter(filter.key)" class="ml-2 text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-times"></i>
                                </button>
                            </span>
                        </template>
                    </div>
                    <div class="flex space-x-2">
                        <button @click="pinFilters()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-thumbtack mr-1"></i>Pin Filters
                        </button>
                        <button @click="clearAllFilters()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            Clear all
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar with Bulk Actions -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex items-center justify-between">
            <!-- Left side - Selection info and bulk actions -->
            <div class="flex items-center space-x-4">
                <!-- Selection checkbox -->
                <label class="flex items-center">
                    <input type="checkbox" 
                           @change="toggleSelectAll()"
                           :checked="selectedProjects.length === filteredProjects.length && filteredProjects.length > 0"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700">Select All</span>
                </label>

                <!-- Selection count -->
                <div x-show="selectedProjects.length > 0" x-transition class="flex items-center space-x-2">
                    <span class="text-sm text-gray-700">
                        <span x-text="selectedProjects.length"></span> selected
                    </span>
                    
                    <!-- Bulk Actions Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Bulk Actions
                            <i class="fas fa-chevron-down ml-2 text-xs"></i>
                        </button>
                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition
                             class="absolute left-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                            <div class="py-1">
                                <button @click="bulkAction('status', 'active')" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-play mr-2 text-green-600"></i>Activate
                                </button>
                                <button @click="bulkAction('status', 'on_hold')" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-pause mr-2 text-yellow-600"></i>Put on Hold
                                </button>
                                <button @click="bulkAction('status', 'completed')" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-check mr-2 text-blue-600"></i>Mark Complete
                                </button>
                                <div class="border-t border-gray-100"></div>
                                <button @click="bulkAction('archive')" 
                                        class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-archive mr-2 text-gray-600"></i>Archive
                                </button>
                                <button @click="bulkAction('delete')" 
                                        class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                    <i class="fas fa-trash mr-2 text-red-600"></i>Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right side - View controls and actions -->
            <div class="flex items-center space-x-4">
                <!-- View Switcher -->
                <div class="flex items-center bg-gray-100 rounded-lg p-1">
                    <button @click="currentView = 'table'" 
                            :class="currentView === 'table' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
                            class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-table mr-1"></i>Table
                    </button>
                    <button @click="currentView = 'kanban'" 
                            :class="currentView === 'kanban' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
                            class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-columns mr-1"></i>Kanban
                    </button>
                    <button @click="currentView = 'gantt'" 
                            :class="currentView === 'gantt' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600'"
                            class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-chart-gantt mr-1"></i>Gantt
                    </button>
                </div>

                <!-- Refresh Button -->
                <button @click="refreshData()" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh
                </button>

                <!-- Export Button -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-download mr-2"></i>Export
                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    
                    <div x-show="open" 
                         @click.away="open = false"
                         x-transition
                         class="absolute right-0 mt-2 w-32 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                        <div class="py-1">
                            <button @click="exportData('csv')" 
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-csv mr-2 text-green-600"></i>CSV
                            </button>
                            <button @click="exportData('excel')" 
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-excel mr-2 text-green-600"></i>Excel
                            </button>
                            <button @click="exportData('pdf')" 
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-pdf mr-2 text-red-600"></i>PDF
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Create Project Button -->
                <button @click="createProject()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>New Project
                </button>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="space-y-4">
        <template x-for="i in 3" :key="'loading-' + i">
            <div class="bg-white rounded-lg shadow p-6 animate-pulse">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                    <div class="w-20 h-6 bg-gray-200 rounded"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading projects</p>
                <p x-text="error"></p>
                <button @click="loadProjects()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Projects Views -->
    <div x-show="!loading && !error">
        <!-- Enhanced Table View -->
        <div x-show="currentView === 'table'" x-transition>
            <!-- Table Controls -->
            <div class="bg-white rounded-lg shadow mb-4 p-4">
                <div class="flex items-center justify-between">
                    <!-- Column Visibility -->
                    <div class="flex items-center space-x-4">
                        <span class="text-sm font-medium text-gray-700">Columns:</span>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="(column, index) in tableColumns" :key="'table-' + (column.key || index)">
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           x-model="column.visible"
                                           @change="updateColumnVisibility()"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700" x-text="column.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    
                    <!-- Table Actions -->
                    <div class="flex items-center space-x-2">
                        <button @click="resetTableSettings()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </button>
                        <button @click="exportTableData()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <!-- Enhanced Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <!-- Selection Column -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <input type="checkbox" 
                                           @change="toggleSelectAll()"
                                           :checked="selectedProjects.length === filteredProjects.length && filteredProjects.length > 0"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                
                                <!-- Dynamic Columns -->
                                <template x-for="(column, index) in visibleColumns" :key="'header-' + (column.key || index)">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                                        @click="sortBy(column.key)"
                                        :class="sortColumn === column.key ? 'bg-blue-50 text-blue-700' : ''">
                                        <div class="flex items-center space-x-1">
                                            <span x-text="column.label"></span>
                                            <div class="flex flex-col">
                                                <i class="fas fa-chevron-up text-xs" 
                                                   :class="sortColumn === column.key && sortDirection === 'asc' ? 'text-blue-600' : 'text-gray-400'"></i>
                                                <i class="fas fa-chevron-down text-xs" 
                                                   :class="sortColumn === column.key && sortDirection === 'desc' ? 'text-blue-600' : 'text-gray-400'"></i>
                                            </div>
                                        </div>
                                    </th>
                                </template>
                                
                                <!-- Actions Column -->
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="project in sortedProjects" :key="'table-project-' + project.id">
                                <tr class="hover:bg-gray-50 cursor-pointer group" @click="viewProject(project.id)">
                                    <!-- Selection Column -->
                                    <td class="px-6 py-4 whitespace-nowrap" @click.stop>
                                        <input type="checkbox" 
                                               :checked="selectedProjects.includes(project.id)"
                                               @change="toggleProjectSelection(project.id)"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                    
                                    <!-- Dynamic Columns -->
                                    <template x-for="(column, index) in visibleColumns" :key="'body-' + (column.key || index)">
                                        <td class="px-6 py-4 whitespace-nowrap" 
                                            :class="column.editable ? 'cursor-pointer hover:bg-blue-50' : ''"
                                            @click="column.editable ? startInlineEdit(project.id, column.key) : null">
                                            
                                            <!-- Project Column -->
                                            <div x-show="column.key === 'project'">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10">
                                                        <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                                            <i class="fas fa-project-diagram text-blue-600"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900" x-text="project.name"></div>
                                                        <div class="text-sm text-gray-500" x-text="project.code || 'PRJ-' + project.id"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Status Column -->
                                            <div x-show="column.key === 'status'">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="'status-' + project.status"
                                                      x-text="project.status"></span>
                                            </div>
                                            
                                            <!-- PM Column -->
                                            <div x-show="column.key === 'pm'">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-8 w-8">
                                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                            <i class="fas fa-user text-gray-600 text-xs"></i>
                                                        </div>
                                                    </div>
                                                    <div class="ml-2">
                                                        <div class="text-sm text-gray-900" x-text="project.pm_name || 'Unassigned'"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Client Column -->
                                            <div x-show="column.key === 'client'">
                                                <div class="text-sm text-gray-900" x-text="project.client_name || 'N/A'"></div>
                                            </div>
                                            
                                            <!-- Progress Column -->
                                            <div x-show="column.key === 'progress'">
                                                <div class="flex items-center">
                                                    <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                                             :style="'width: ' + project.progress + '%'"></div>
                                                    </div>
                                                    <span class="text-sm text-gray-900" x-text="project.progress + '%'"></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Due Date Column -->
                                            <div x-show="column.key === 'due_date'">
                                                <div class="text-sm" :class="isOverdue(project.due_date) ? 'text-red-600 font-medium' : 'text-gray-900'">
                                                    <span x-text="project.due_date || 'No due date'"></span>
                                                    <i x-show="isOverdue(project.due_date)" class="fas fa-exclamation-triangle ml-1 text-red-500"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Health Column -->
                                            <div x-show="column.key === 'health'">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="'health-' + getProjectHealth(project)"
                                                      x-text="getProjectHealth(project)"></span>
                                            </div>
                                            
                                            <!-- Budget Column -->
                                            <div x-show="column.key === 'budget'">
                                                <div class="text-sm text-gray-900">
                                                    <span x-text="formatCurrency(project.budget_total)"></span>
                                                    <div class="text-xs text-gray-500" x-text="getBudgetStatus(project)"></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Priority Column -->
                                            <div x-show="column.key === 'priority'">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                      :class="'priority-' + getProjectPriority(project)"
                                                      x-text="getProjectPriority(project)"></span>
                                            </div>
                                            
                                            <!-- Team Column -->
                                            <div x-show="column.key === 'team'">
                                                <div class="flex items-center">
                                                    <i class="fas fa-users text-gray-400 mr-2"></i>
                                                    <span class="text-sm text-gray-900" x-text="project.members_count || 0"></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Created Date Column -->
                                            <div x-show="column.key === 'created_date'">
                                                <div class="text-sm text-gray-900" x-text="formatDate(project.created_at)"></div>
                                            </div>
                                            
                                            <!-- Tags Column -->
                                            <div x-show="column.key === 'tags'">
                                                <div class="flex flex-wrap gap-1">
                                                    <template x-for="tag in (project.tags || []).slice(0, 2)" :key="'table-tag-' + tag">
                                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full" x-text="tag"></span>
                                                    </template>
                                                    <span x-show="(project.tags || []).length > 2" 
                                                          class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                                        +<span x-text="(project.tags || []).length - 2"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    </template>
                                    
                                    <!-- Actions Column -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                        <div class="flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button @click="editProject(project.id)" 
                                                    class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-100"
                                                    title="Edit Project">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="duplicateProject(project.id)" 
                                                    class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-100"
                                                    title="Duplicate Project">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                            <button @click="archiveProject(project.id)" 
                                                    class="text-gray-600 hover:text-gray-900 p-1 rounded hover:bg-gray-100"
                                                    title="Archive Project">
                                                <i class="fas fa-archive"></i>
                                            </button>
                                            <div class="relative" x-data="{ open: false }">
                                                <button @click="open = !open" 
                                                        class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100"
                                                        title="More Actions">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <div x-show="open" 
                                                     @click.away="open = false"
                                                     x-transition
                                                     class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                                                    <div class="py-1">
                                                        <button @click="viewProjectDetails(project.id)" 
                                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <i class="fas fa-eye mr-2"></i>View Details
                                                        </button>
                                                        <button @click="manageProjectTeam(project.id)" 
                                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <i class="fas fa-users mr-2"></i>Manage Team
                                                        </button>
                                                        <button @click="viewProjectTimeline(project.id)" 
                                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            <i class="fas fa-timeline mr-2"></i>Timeline
                                                        </button>
                                                        <div class="border-t border-gray-100"></div>
                                                        <button @click="deleteProject(project.id)" 
                                                                class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                            <i class="fas fa-trash mr-2"></i>Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                </table>
            </div>
        </div>

        <!-- Enhanced Kanban View -->
        <div x-show="currentView === 'kanban'" x-transition>
            <!-- Kanban Controls -->
            <div class="bg-white rounded-lg shadow mb-4 p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm font-medium text-gray-700">Kanban Settings:</span>
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   x-model="kanbanSettings.showProgress"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Show Progress</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   x-model="kanbanSettings.showDueDates"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Show Due Dates</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   x-model="kanbanSettings.showHealth"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Show Health</span>
                        </label>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click="resetKanbanSettings()" 
                                class="text-sm text-gray-500 hover:text-gray-700">
                            <i class="fas fa-undo mr-1"></i>Reset
                        </button>
                        <button @click="addKanbanColumn()" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i>Add Column
                        </button>
                    </div>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="flex space-x-6 overflow-x-auto pb-4">
                <template x-for="column in kanbanColumns" :key="column.id">
                    <div class="flex-shrink-0 w-80">
                        <!-- Column Header -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 rounded-full" :class="'bg-' + column.color + '-500'"></div>
                                    <h3 class="text-sm font-medium text-gray-700" x-text="column.title"></h3>
                                    <span class="px-2 py-1 text-xs bg-gray-200 text-gray-700 rounded-full" 
                                          x-text="getColumnCount(column.status)"></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button @click="editKanbanColumn(column.id)" 
                                            class="text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-200">
                                        <i class="fas fa-cog text-xs"></i>
                                    </button>
                                    <button @click="deleteKanbanColumn(column.id)" 
                                            class="text-gray-400 hover:text-red-600 p-1 rounded hover:bg-red-100">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Column Content -->
                        <div class="space-y-3 min-h-96"
                             @drop="dropProject($event, column.status)"
                             @dragover.prevent
                             @dragenter.prevent>
                            
                            <!-- Projects in this column -->
                            <template x-for="project in getProjectsByStatus(column.status)" :key="'kanban-project-' + project.id">
                                <div class="bg-white rounded-lg shadow p-4 cursor-pointer hover:shadow-md transition-all duration-200 group"
                                     draggable="true"
                                     @dragstart="dragProject($event, project)"
                                     @click="viewProject(project.id)">
                                    
                                    <!-- Project Header -->
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-gray-900 mb-1" x-text="project.name"></h4>
                                            <p class="text-xs text-gray-500" x-text="project.code || 'PRJ-' + project.id"></p>
                                        </div>
                                        <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <input type="checkbox" 
                                                   :checked="selectedProjects.includes(project.id)"
                                                   @change="toggleProjectSelection(project.id)"
                                                   @click.stop
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </div>
                                    </div>

                                    <!-- Project Description -->
                                    <p class="text-xs text-gray-600 mb-3 line-clamp-2" x-text="project.description"></p>

                                    <!-- Progress Bar -->
                                    <div x-show="kanbanSettings.showProgress" class="mb-3">
                                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                            <span>Progress</span>
                                            <span x-text="project.progress + '%'"></span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1">
                                            <div class="bg-blue-600 h-1 rounded-full transition-all duration-300" 
                                                 :style="'width: ' + project.progress + '%'"></div>
                                        </div>
                                    </div>

                                    <!-- Health Indicator -->
                                    <div x-show="kanbanSettings.showHealth" class="mb-3">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                              :class="'health-' + getProjectHealth(project)"
                                              x-text="getProjectHealth(project)"></span>
                                    </div>

                                    <!-- Project Meta -->
                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                        <div class="flex items-center space-x-3">
                                            <!-- PM -->
                                            <div class="flex items-center">
                                                <div class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center mr-1">
                                                    <i class="fas fa-user text-gray-600 text-xs"></i>
                                                </div>
                                                <span x-text="project.pm_name || 'Unassigned'"></span>
                                            </div>
                                            
                                            <!-- Team Size -->
                                            <div class="flex items-center">
                                                <i class="fas fa-users text-gray-400 mr-1"></i>
                                                <span x-text="project.members_count || 0"></span>
                                            </div>
                                        </div>
                                        
                                        <!-- Due Date -->
                                        <div x-show="kanbanSettings.showDueDates" class="flex items-center">
                                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                                            <span :class="isOverdue(project.due_date) ? 'text-red-600 font-medium' : ''"
                                                  x-text="project.due_date || 'No due date'"></span>
                                        </div>
                                    </div>

                                    <!-- Project Tags -->
                                    <div x-show="project.tags && project.tags.length > 0" class="mt-3">
                                        <div class="flex flex-wrap gap-1">
                                            <template x-for="tag in project.tags.slice(0, 2)" :key="'kanban-tag-' + tag">
                                                <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full" x-text="tag"></span>
                                            </template>
                                            <span x-show="project.tags.length > 2" 
                                                  class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full">
                                                +<span x-text="project.tags.length - 2"></span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-3 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="editProject(project.id)" 
                                                class="flex-1 bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs hover:bg-gray-200">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </button>
                                        <button @click.stop="viewProject(project.id)" 
                                                class="flex-1 bg-blue-600 text-white px-2 py-1 rounded text-xs hover:bg-blue-700">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Empty State -->
                            <div x-show="getProjectsByStatus(column.status).length === 0" 
                                 class="text-center py-8 text-gray-400">
                                <i class="fas fa-inbox text-2xl mb-2"></i>
                                <p class="text-sm">No projects</p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Gantt View -->
        <div x-show="currentView === 'gantt'" x-transition>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-chart-gantt text-4xl mb-4"></i>
                    <h3 class="text-lg font-medium mb-2">Gantt Chart View</h3>
                    <p class="text-sm">Gantt chart visualization will be implemented soon</p>
                    <p class="text-xs text-gray-400 mt-2">This will show project timelines, dependencies, and milestones</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div x-show="!loading && !error && filteredProjects.length === 0" class="text-center py-12">
        <i class="fas fa-project-diagram text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No projects found</h3>
        <p class="text-gray-500 mb-4">Get started by creating your first project.</p>
        <button @click="createProject()" 
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i>
            Create New Project
        </button>
    </div>

    <!-- Pagination -->
    <div x-show="!loading && !error && filteredProjects.length > 0" class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <span x-text="((currentPage - 1) * itemsPerPage) + 1"></span> to 
            <span x-text="Math.min(currentPage * itemsPerPage, totalItems)"></span> of 
            <span x-text="totalItems"></span> projects
        </div>
        <div class="flex space-x-2">
            <button @click="previousPage()" 
                    :disabled="currentPage === 1"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
            </button>
            <button @click="nextPage()" 
                    :disabled="currentPage >= totalPages"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>

    <!-- Pagination -->
    <div x-show="!loading && !error && filteredProjects.length > 0" class="flex items-center justify-between">
        <div class="text-sm text-gray-700">
            Showing <span x-text="((currentPage - 1) * itemsPerPage) + 1"></span> to 
            <span x-text="Math.min(currentPage * itemsPerPage, totalItems)"></span> of 
            <span x-text="totalItems"></span> projects
        </div>
        <div class="flex space-x-2">
            <button @click="previousPage()" 
                    :disabled="currentPage === 1"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
            </button>
            <button @click="nextPage()" 
                    :disabled="currentPage >= totalPages"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>

    <!-- Insights & Analytics Section -->
    <div class="mt-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Insights & Analytics</h2>
                <p class="text-sm text-gray-500">Project performance metrics and trends</p>
            </div>
            <div class="flex items-center space-x-2">
                <select x-model="insightsTimeRange" 
                        @change="loadInsights()"
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="7d">Last 7 days</option>
                    <option value="30d">Last 30 days</option>
                    <option value="90d">Last 90 days</option>
                    <option value="1y">Last year</option>
                </select>
                <button @click="exportInsights()" 
                        class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Project Status Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Project Status Distribution</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChartType('status', 'pie')" 
                                :class="chartTypes.status === 'pie' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-pie"></i>
                        </button>
                        <button @click="toggleChartType('status', 'doughnut')" 
                                :class="chartTypes.status === 'doughnut' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-pie"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="statusChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Project Progress Trends -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Progress Trends</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChartType('progress', 'line')" 
                                :class="chartTypes.progress === 'line' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-line"></i>
                        </button>
                        <button @click="toggleChartType('progress', 'bar')" 
                                :class="chartTypes.progress === 'bar' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="progressChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Budget vs Actual -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Budget vs Actual</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChartType('budget', 'bar')" 
                                :class="chartTypes.budget === 'bar' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                        <button @click="toggleChartType('budget', 'line')" 
                                :class="chartTypes.budget === 'line' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="budgetChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Team Performance -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="toggleChartType('team', 'radar')" 
                                :class="chartTypes.team === 'radar' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-area"></i>
                        </button>
                        <button @click="toggleChartType('team', 'bar')" 
                                :class="chartTypes.team === 'bar' ? 'bg-blue-100 text-blue-700' : 'text-gray-500'"
                                class="p-1 rounded">
                            <i class="fas fa-chart-bar"></i>
                        </button>
                    </div>
                </div>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="teamChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Avg. Completion Time</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="insights.avgCompletionTime"></p>
                        <p class="text-xs text-green-600" x-text="insights.completionTimeChange"></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Budget Utilization</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="insights.budgetUtilization"></p>
                        <p class="text-xs text-blue-600" x-text="insights.budgetChange"></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Team Efficiency</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="insights.teamEfficiency"></p>
                        <p class="text-xs text-purple-600" x-text="insights.efficiencyChange"></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Quality Score</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="insights.qualityScore"></p>
                        <p class="text-xs text-orange-600" x-text="insights.qualityChange"></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Analytics Table -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Detailed Analytics</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metric</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Previous</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Change</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="metric in insights.metrics" :key="metric.name">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="metric.name"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="metric.current"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="metric.previous"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm" :class="metric.change >= 0 ? 'text-green-600' : 'text-red-600'" x-text="metric.change + '%'"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <i :class="metric.change >= 0 ? 'fas fa-arrow-up text-green-500' : 'fas fa-arrow-down text-red-500'"></i>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Activity Feed Section -->
    <div class="mt-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Recent Activity</h2>
                <p class="text-sm text-gray-500">Latest project updates and team activities</p>
            </div>
            <div class="flex items-center space-x-2">
                <select x-model="activityFilter" 
                        @change="loadActivityFeed()"
                        class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="all">All Activities</option>
                    <option value="projects">Projects Only</option>
                    <option value="tasks">Tasks Only</option>
                    <option value="team">Team Activities</option>
                    <option value="system">System Events</option>
                </select>
                <button @click="refreshActivityFeed()" 
                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    <i class="fas fa-sync-alt mr-1"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Activity Feed -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Activity Timeline</h3>
                    <div class="flex items-center space-x-2">
                        <label class="flex items-center">
                            <input type="checkbox" 
                                   x-model="showActivityDetails"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Show Details</span>
                        </label>
                        <button @click="markAllAsRead()" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            Mark All Read
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="max-h-96 overflow-y-auto">
                <div class="px-6 py-4">
                    <template x-for="activity in filteredActivities" :key="'main-activity-' + activity.id">
                        <div class="flex items-start space-x-4 py-4 border-b border-gray-100 last:border-b-0">
                            <!-- Activity Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     :class="'bg-' + activity.type + '-100'">
                                    <i :class="activity.icon" 
                                       :class="'text-' + activity.type + '-600'"
                                       class="text-sm"></i>
                                </div>
                            </div>
                            
                            <!-- Activity Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <p class="text-sm font-medium text-gray-900" x-text="activity.title"></p>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full"
                                              :class="'bg-' + activity.type + '-100 text-' + activity.type + '-800'"
                                              x-text="activity.type"></span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs text-gray-500" x-text="activity.timestamp"></span>
                                        <div x-show="!activity.read" class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    </div>
                                </div>
                                
                                <p class="text-sm text-gray-600 mt-1" x-text="activity.description"></p>
                                
                                <!-- Activity Details -->
                                <div x-show="showActivityDetails && activity.details" class="mt-2">
                                    <div class="bg-gray-50 rounded-md p-3">
                                        <template x-for="detail in activity.details" :key="detail.key">
                                            <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                                <span x-text="detail.key + ':'"></span>
                                                <span x-text="detail.value"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                
                                <!-- Activity Actions -->
                                <div class="flex items-center space-x-4 mt-3">
                                    <button @click="viewActivityDetails(activity.id)" 
                                            class="text-xs text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye mr-1"></i>View Details
                                    </button>
                                    <button x-show="activity.type === 'project'" 
                                            @click="viewProject(activity.projectId)" 
                                            class="text-xs text-green-600 hover:text-green-800">
                                        <i class="fas fa-external-link-alt mr-1"></i>Open Project
                                    </button>
                                    <button x-show="activity.type === 'task'" 
                                            @click="viewTask(activity.taskId)" 
                                            class="text-xs text-purple-600 hover:text-purple-800">
                                        <i class="fas fa-tasks mr-1"></i>Open Task
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Empty State -->
                    <div x-show="filteredActivities.length === 0" class="text-center py-8">
                        <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Activities</h3>
                        <p class="text-sm text-gray-500">No activities match your current filter</p>
                    </div>
                </div>
            </div>
            
            <!-- Load More -->
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500" x-text="'Showing ' + filteredActivities.length + ' of ' + activities.length + ' activities'"></span>
                    <button @click="loadMoreActivities()" 
                            x-show="hasMoreActivities"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                        <i class="fas fa-plus mr-1"></i>Load More
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shortcuts Section -->
    <div class="mt-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Quick Shortcuts</h2>
                <p class="text-sm text-gray-500">Frequently used actions and navigation</p>
            </div>
            <div class="flex items-center space-x-2">
                <button @click="customizeShortcuts()" 
                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    <i class="fas fa-cog mr-1"></i>Customize
                </button>
                <button @click="resetShortcuts()" 
                        class="px-3 py-2 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                    <i class="fas fa-undo mr-1"></i>Reset
                </button>
            </div>
        </div>

        <!-- Shortcuts Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Project Shortcuts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Projects</h3>
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600"></i>
                    </div>
                </div>
                <div class="space-y-3">
                    <template x-for="shortcut in projectShortcuts" :key="'project-' + shortcut.id">
                        <button @click="executeShortcut(shortcut)" 
                                class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                 :class="'bg-' + shortcut.color + '-100'">
                                <i :class="shortcut.icon" 
                                   :class="'text-' + shortcut.color + '-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900" x-text="shortcut.title"></p>
                                <p class="text-xs text-gray-500" x-text="shortcut.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Team Shortcuts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Team</h3>
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-green-600"></i>
                    </div>
                </div>
                <div class="space-y-3">
                    <template x-for="shortcut in teamShortcuts" :key="'team-' + shortcut.id">
                        <button @click="executeShortcut(shortcut)" 
                                class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                 :class="'bg-' + shortcut.color + '-100'">
                                <i :class="shortcut.icon" 
                                   :class="'text-' + shortcut.color + '-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900" x-text="shortcut.title"></p>
                                <p class="text-xs text-gray-500" x-text="shortcut.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Tasks Shortcuts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tasks</h3>
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-purple-600"></i>
                    </div>
                </div>
                <div class="space-y-3">
                    <template x-for="shortcut in taskShortcuts" :key="'task-' + shortcut.id">
                        <button @click="executeShortcut(shortcut)" 
                                class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                 :class="'bg-' + shortcut.color + '-100'">
                                <i :class="shortcut.icon" 
                                   :class="'text-' + shortcut.color + '-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900" x-text="shortcut.title"></p>
                                <p class="text-xs text-gray-500" x-text="shortcut.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- System Shortcuts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System</h3>
                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cog text-gray-600"></i>
                    </div>
                </div>
                <div class="space-y-3">
                    <template x-for="shortcut in systemShortcuts" :key="'system-' + shortcut.id">
                        <button @click="executeShortcut(shortcut)" 
                                class="w-full flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                 :class="'bg-' + shortcut.color + '-100'">
                                <i :class="shortcut.icon" 
                                   :class="'text-' + shortcut.color + '-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 text-left">
                                <p class="text-sm font-medium text-gray-900" x-text="shortcut.title"></p>
                                <p class="text-xs text-gray-500" x-text="shortcut.description"></p>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Drawer for Quick Details -->
    <div x-show="sideDrawerOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-hidden"
         @click="closeSideDrawer()">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        
        <div x-show="sideDrawerOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             @click.stop
             class="absolute right-0 top-0 h-full w-96 bg-white shadow-xl">
            
            <!-- Drawer Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Project Details</h3>
                    <p class="text-sm text-gray-500" x-text="selectedProject?.name || 'Select a project'"></p>
                </div>
                <button @click="closeSideDrawer()" 
                        class="text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Drawer Content -->
            <div class="flex-1 overflow-y-auto p-6" x-show="selectedProject">
                <!-- Project Overview -->
                <div class="mb-6">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900" x-text="selectedProject.name"></h4>
                            <p class="text-sm text-gray-500" x-text="selectedProject.code || 'PRJ-' + selectedProject.id"></p>
                        </div>
                    </div>
                    
                    <p class="text-sm text-gray-600 mb-4" x-text="selectedProject.description"></p>
                    
                    <!-- Status and Health -->
                    <div class="flex items-center space-x-4 mb-4">
                        <span class="px-3 py-1 text-sm font-semibold rounded-full"
                              :class="'status-' + selectedProject.status"
                              x-text="selectedProject.status"></span>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full"
                              :class="'health-' + getProjectHealth(selectedProject)"
                              x-text="getProjectHealth(selectedProject)"></span>
                    </div>
                </div>

                <!-- Progress Section -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="text-sm font-medium text-gray-700">Progress</h5>
                        <span class="text-sm text-gray-500" x-text="selectedProject.progress + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                             :style="'width: ' + selectedProject.progress + '%'"></div>
                    </div>
                </div>

                <!-- Project Info -->
                <div class="space-y-4 mb-6">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Project Manager</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center">
                                <i class="fas fa-user text-gray-600 text-xs"></i>
                            </div>
                            <span class="text-sm text-gray-900" x-text="selectedProject.pm_name || 'Unassigned'"></span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Client</span>
                        <span class="text-sm text-gray-900" x-text="selectedProject.client_name || 'N/A'"></span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Team Size</span>
                        <div class="flex items-center space-x-1">
                            <i class="fas fa-users text-gray-400 text-xs"></i>
                            <span class="text-sm text-gray-900" x-text="selectedProject.members_count || 0"></span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Budget</span>
                        <span class="text-sm text-gray-900" x-text="formatCurrency(selectedProject.budget_total)"></span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Due Date</span>
                        <span class="text-sm" :class="isOverdue(selectedProject.due_date) ? 'text-red-600 font-medium' : 'text-gray-900'"
                              x-text="selectedProject.due_date || 'No due date'"></span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Created</span>
                        <span class="text-sm text-gray-900" x-text="formatDate(selectedProject.created_at)"></span>
                    </div>
                </div>

                <!-- Tags -->
                <div x-show="selectedProject.tags && selectedProject.tags.length > 0" class="mb-6">
                    <h5 class="text-sm font-medium text-gray-700 mb-2">Tags</h5>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="tag in selectedProject.tags" :key="'detail-tag-' + tag">
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded-full" x-text="tag"></span>
                        </template>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="mb-6">
                    <h5 class="text-sm font-medium text-gray-700 mb-3">Recent Activity</h5>
                    <div class="space-y-3">
                        <template x-for="activity in getProjectActivity(selectedProject.id)" :key="'detail-activity-' + activity.id">
                            <div class="flex items-start space-x-3">
                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                    <i :class="activity.icon" class="text-blue-600 text-xs"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900" x-text="activity.description"></p>
                                    <p class="text-xs text-gray-500" x-text="activity.timestamp"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="border-t border-gray-200 pt-6">
                    <h5 class="text-sm font-medium text-gray-700 mb-3">Quick Actions</h5>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="editProject(selectedProject.id)" 
                                class="flex items-center justify-center space-x-2 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-edit text-sm"></i>
                            <span class="text-sm">Edit</span>
                        </button>
                        <button @click="viewProject(selectedProject.id)" 
                                class="flex items-center justify-center space-x-2 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-eye text-sm"></i>
                            <span class="text-sm">View Full</span>
                        </button>
                        <button @click="duplicateProject(selectedProject.id)" 
                                class="flex items-center justify-center space-x-2 px-3 py-2 bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition-colors">
                            <i class="fas fa-copy text-sm"></i>
                            <span class="text-sm">Duplicate</span>
                        </button>
                        <button @click="archiveProject(selectedProject.id)" 
                                class="flex items-center justify-center space-x-2 px-3 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition-colors">
                            <i class="fas fa-archive text-sm"></i>
                            <span class="text-sm">Archive</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div x-show="!selectedProject" class="flex-1 flex items-center justify-center p-6">
                <div class="text-center">
                    <i class="fas fa-mouse-pointer text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Select a Project</h3>
                    <p class="text-sm text-gray-500">Click on any project to view its details here</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectsPage() {
    return {
        loading: true,
        error: null,
        projects: [],
        filteredProjects: [],
        searchQuery: '',
        showFilters: false,
        filters: {
            status: '',
            pm: '',
            client: '',
            dateRange: '',
            health: '',
            budget: '',
            tags: '',
            location: ''
        },
        filterOptions: {
            pms: [],
            clients: [],
            tags: [],
            locations: []
        },
        savedViews: [],
        currentViewId: null,
        showSaveViewModal: false,
        activeFilters: [],
        activeFiltersCount: 0,
        currentPage: 1,
        itemsPerPage: 12,
        totalItems: 0,
        totalPages: 0,
        searchTimeout: null,
        
        // Enhanced Table Properties
        tableColumns: [
            { key: 'project', label: 'Project', visible: true, editable: false },
            { key: 'status', label: 'Status', visible: true, editable: true },
            { key: 'pm', label: 'PM', visible: true, editable: true },
            { key: 'client', label: 'Client', visible: true, editable: true },
            { key: 'progress', label: 'Progress', visible: true, editable: true },
            { key: 'due_date', label: 'Due Date', visible: true, editable: true },
            { key: 'health', label: 'Health', visible: true, editable: false },
            { key: 'budget', label: 'Budget', visible: false, editable: true },
            { key: 'priority', label: 'Priority', visible: false, editable: true },
            { key: 'team', label: 'Team', visible: false, editable: false },
            { key: 'created_date', label: 'Created', visible: false, editable: false },
            { key: 'tags', label: 'Tags', visible: false, editable: true }
        ],
        visibleColumns: [],
        sortColumn: 'name',
        sortDirection: 'asc',
        sortedProjects: [],
        editingCell: null,
        
        // Kanban Properties
        kanbanSettings: {
            showProgress: true,
            showDueDates: true,
            showHealth: true
        },
        kanbanColumns: [
            { id: 1, title: 'Planning', status: 'planning', color: 'yellow' },
            { id: 2, title: 'Active', status: 'active', color: 'blue' },
            { id: 3, title: 'In Progress', status: 'in_progress', color: 'green' },
            { id: 4, title: 'On Hold', status: 'on_hold', color: 'red' },
            { id: 5, title: 'Completed', status: 'completed', color: 'gray' }
        ],
        draggedProject: null,
        
        // Side Drawer Properties
        sideDrawerOpen: false,
        selectedProject: null,
        
        // Insights Properties
        insightsTimeRange: '30d',
        chartTypes: {
            status: 'pie',
            progress: 'line',
            budget: 'bar',
            team: 'radar'
        },
        insights: {
            avgCompletionTime: '45 days',
            completionTimeChange: '+12% vs last month',
            budgetUtilization: '78%',
            budgetChange: '+5% vs last month',
            teamEfficiency: '92%',
            efficiencyChange: '+8% vs last month',
            qualityScore: '4.2/5',
            qualityChange: '+0.3 vs last month',
            metrics: [
                { name: 'Project Completion Rate', current: '85%', previous: '78%', change: 9 },
                { name: 'Budget Accuracy', current: '92%', previous: '88%', change: 5 },
                { name: 'Team Productivity', current: '94%', previous: '89%', change: 6 },
                { name: 'Client Satisfaction', current: '4.5/5', previous: '4.2/5', change: 7 },
                { name: 'On-time Delivery', current: '88%', previous: '82%', change: 7 }
            ]
        },
        charts: {
            statusChart: null,
            progressChart: null,
            budgetChart: null,
            teamChart: null
        },
        
        // Activity Feed Properties
        activityFilter: 'all',
        showActivityDetails: false,
        activities: [],
        filteredActivities: [],
        hasMoreActivities: true,
        activitiesPerPage: 20,
        currentActivityPage: 1,
        
        // Shortcuts Properties
        projectShortcuts: [
            { id: 1, title: 'New Project', description: 'Create a new project', icon: 'fas fa-plus', color: 'blue', action: 'createProject' },
            { id: 2, title: 'Import Projects', description: 'Import from CSV/Excel', icon: 'fas fa-file-import', color: 'green', action: 'importProjects' },
            { id: 3, title: 'Project Templates', description: 'Browse templates', icon: 'fas fa-layer-group', color: 'purple', action: 'browseTemplates' },
            { id: 4, title: 'Project Reports', description: 'Generate reports', icon: 'fas fa-chart-bar', color: 'orange', action: 'generateReports' }
        ],
        teamShortcuts: [
            { id: 1, title: 'Invite Members', description: 'Add team members', icon: 'fas fa-user-plus', color: 'green', action: 'inviteMembers' },
            { id: 2, title: 'Team Overview', description: 'View team stats', icon: 'fas fa-users', color: 'blue', action: 'viewTeamOverview' },
            { id: 3, title: 'Assign Roles', description: 'Manage permissions', icon: 'fas fa-user-cog', color: 'purple', action: 'assignRoles' },
            { id: 4, title: 'Team Calendar', description: 'View availability', icon: 'fas fa-calendar', color: 'orange', action: 'viewCalendar' }
        ],
        taskShortcuts: [
            { id: 1, title: 'Quick Task', description: 'Add task quickly', icon: 'fas fa-plus', color: 'blue', action: 'quickTask' },
            { id: 2, title: 'Task Board', description: 'View kanban board', icon: 'fas fa-columns', color: 'green', action: 'viewTaskBoard' },
            { id: 3, title: 'My Tasks', description: 'View assigned tasks', icon: 'fas fa-tasks', color: 'purple', action: 'viewMyTasks' },
            { id: 4, title: 'Task Reports', description: 'Task analytics', icon: 'fas fa-chart-line', color: 'orange', action: 'taskReports' }
        ],
        systemShortcuts: [
            { id: 1, title: 'Settings', description: 'System settings', icon: 'fas fa-cog', color: 'gray', action: 'openSettings' },
            { id: 2, title: 'Help Center', description: 'Get help', icon: 'fas fa-question-circle', color: 'blue', action: 'openHelp' },
            { id: 3, title: 'Keyboard Shortcuts', description: 'View shortcuts', icon: 'fas fa-keyboard', color: 'green', action: 'viewShortcuts' },
            { id: 4, title: 'Export Data', description: 'Export all data', icon: 'fas fa-download', color: 'purple', action: 'exportData' }
        ],
        
        // New data for enhanced features
        kpis: {
            totalProjects: 0,
            activeProjects: 0,
            onTimeRate: 0,
            overdueProjects: 0,
            budgetUsage: '0 / 0',
            overBudgetProjects: 0,
            healthSnapshot: '0 / 0 / 0',
            atRiskProjects: 0,
            criticalProjects: 0
        },
        alerts: [],
        nowPanelActions: [],
        savedViews: [],
        currentView: 'table', // table, kanban, gantt
        selectedProjects: [],
        bulkActionsVisible: false,

        async init() {
            console.log('🚀 Projects page init started');
            await Promise.all([
                this.loadProjects(),
                this.loadKPIs(),
                this.loadAlerts(),
                this.loadNowPanelActions(),
                this.loadFilterOptions(),
                this.loadSavedViews()
            ]);
            
            // Initialize table columns
            this.updateColumnVisibility();
            this.updateSortedProjects();
            
            // Initialize Kanban settings
            this.loadKanbanSettings();
            
            // Initialize insights and charts
            this.loadInsights();
            this.$nextTick(() => {
                this.initializeCharts();
            });
            
            // Initialize activity feed
            this.loadActivityFeed();
        },

        async loadProjects() {
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading projects data...');
                
                // Get auth token
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                // Fetch real data from API
                const params = new URLSearchParams();
                if (this.searchQuery) params.append('search', this.searchQuery);
                if (this.filters.status) params.append('status', this.filters.status);
                params.append('per_page', this.itemsPerPage);
                params.append('page', this.currentPage);
                
                const response = await fetch(`/app/api/v1/app/projects?${params}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('📊 API Response:', data);
                
                if (data.status === 'success' && data.data) {
                    this.projects = data.data.projects || [];
                    this.totalItems = data.data.pagination?.total || 0;
                    this.totalPages = data.data.pagination?.last_page || 1;
                    this.applyFilters();
                } else {
                    throw new Error(data.message || 'Failed to load projects');
                }
                this.loading = false;
                console.log('✅ Projects data loaded successfully');
                
            } catch (error) {
                console.error('❌ Error loading projects data:', error);
                this.error = error.message;
                this.loading = false;
                
                // Fallback to mock data if API fails
                console.log('🔄 Falling back to mock data...');
                this.loadMockData();
            }
        },

        loadMockData() {
            this.projects = [
                {
                    id: 1,
                    name: 'Website Redesign',
                    description: 'Complete redesign of the company website with modern UI/UX',
                    status: 'active',
                    priority: 'high',
                    team: 'Design Team',
                    progress: 75,
                    tasks_completed: 15,
                    total_tasks: 20,
                    due_date: '2024-01-15',
                    members_count: 5
                },
                {
                    id: 2,
                    name: 'Mobile App Development',
                    description: 'Development of iOS and Android mobile application',
                    status: 'planning',
                    priority: 'medium',
                    team: 'Development Team',
                    progress: 25,
                    tasks_completed: 5,
                    total_tasks: 20,
                    due_date: '2024-02-28',
                    members_count: 8
                },
                {
                    id: 3,
                    name: 'Marketing Campaign',
                    description: 'Q1 marketing campaign for new product launch',
                    status: 'active',
                    priority: 'high',
                    team: 'Marketing Team',
                    progress: 60,
                    tasks_completed: 12,
                    total_tasks: 20,
                    due_date: '2024-01-30',
                    members_count: 4
                }
            ];
            
            this.totalItems = this.projects.length;
            this.totalPages = Math.ceil(this.totalItems / this.itemsPerPage);
            this.applyFilters();
            this.error = null;
        },

        debounceSearch() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.applyFilters();
            }, 300);
        },

        applyFilters() {
            let filtered = [...this.projects];
            
            // Apply search filter
            if (this.searchQuery) {
                filtered = filtered.filter(project => 
                    project.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    project.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    project.team.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            // Apply status filter
            if (this.filters.status) {
                filtered = filtered.filter(project => project.status === this.filters.status);
            }
            
            // Apply priority filter
            if (this.filters.priority) {
                filtered = filtered.filter(project => project.priority === this.filters.priority);
            }
            
            // Apply team filter
            if (this.filters.team) {
                filtered = filtered.filter(project => project.team.toLowerCase().includes(this.filters.team.toLowerCase()));
            }
            
            this.filteredProjects = filtered;
            this.updateActiveFilters();
        },

        updateActiveFilters() {
            this.activeFilters = [];
            
            if (this.filters.status) {
                this.activeFilters.push({
                    key: 'status',
                    label: 'Status',
                    value: this.filters.status
                });
            }
            
            if (this.filters.priority) {
                this.activeFilters.push({
                    key: 'priority',
                    label: 'Priority',
                    value: this.filters.priority
                });
            }
            
            if (this.filters.team) {
                this.activeFilters.push({
                    key: 'team',
                    label: 'Team',
                    value: this.filters.team
                });
            }
            
            this.activeFiltersCount = this.activeFilters.length;
        },

        removeFilter(filterKey) {
            this.filters[filterKey] = '';
            this.applyFilters();
        },

        clearAllFilters() {
            this.filters = {
                status: '',
                priority: '',
                team: ''
            };
            this.searchQuery = '';
            this.applyFilters();
        },

        previousPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        viewProject(projectId) {
            console.log('Viewing project:', projectId);
            const project = this.projects.find(p => p.id === projectId);
            if (project) {
                this.selectedProject = project;
                this.sideDrawerOpen = true;
            }
        },

        closeSideDrawer() {
            this.sideDrawerOpen = false;
            this.selectedProject = null;
        },

        getProjectActivity(projectId) {
            // Mock activity data - in real implementation, this would come from API
            return [
                {
                    id: 1,
                    description: 'Project status updated to Active',
                    timestamp: '2 hours ago',
                    icon: 'fas fa-check-circle'
                },
                {
                    id: 2,
                    description: 'New team member added',
                    timestamp: '1 day ago',
                    icon: 'fas fa-user-plus'
                },
                {
                    id: 3,
                    description: 'Budget updated to $50,000',
                    timestamp: '3 days ago',
                    icon: 'fas fa-dollar-sign'
                },
                {
                    id: 4,
                    description: 'Project created',
                    timestamp: '1 week ago',
                    icon: 'fas fa-plus'
                }
            ];
        },

        editProject(projectId) {
            console.log('Editing project:', projectId);
            // Navigate to project edit page
            window.location.href = `/app/projects/${projectId}/edit`;
        },

        createProject() {
            console.log('Creating new project');
            // Navigate to project creation page
            window.location.href = '/app/projects/create';
        },

        previousPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        // New methods for enhanced features
        async loadKPIs() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch('/app/api/v1/app/projects/metrics', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        this.kpis = data.data;
                    }
                }
            } catch (error) {
                console.error('Error loading KPIs:', error);
                // Fallback to mock data
                this.kpis = {
                    totalProjects: this.projects.length,
                    activeProjects: this.projects.filter(p => ['planning', 'active'].includes(p.status)).length,
                    onTimeRate: 75,
                    overdueProjects: 2,
                    budgetUsage: '$45K / $60K',
                    overBudgetProjects: 1,
                    healthSnapshot: '12 / 5 / 2',
                    atRiskProjects: 5,
                    criticalProjects: 2
                };
            }
        },

        async loadAlerts() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch('/app/api/v1/app/projects/alerts?severity=high|critical&limit=3', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        this.alerts = data.data;
                    }
                }
            } catch (error) {
                console.error('Error loading alerts:', error);
                // Fallback to mock data
                this.alerts = [
                    {
                        id: 1,
                        message: '3 projects are overdue',
                        action: 'View',
                        severity: 'critical'
                    },
                    {
                        id: 2,
                        message: '1 project is over budget',
                        action: 'Resolve',
                        severity: 'high'
                    }
                ];
            }
        },

        async loadNowPanelActions() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch('/app/api/v1/app/projects/now-panel', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        this.nowPanelActions = data.data;
                    }
                }
            } catch (error) {
                console.error('Error loading now panel actions:', error);
                // Fallback to mock data
                this.nowPanelActions = [
                    {
                        id: 1,
                        title: 'Assign PM',
                        description: '3 projects need PM',
                        icon: 'fas fa-user-plus',
                        action: 'assign_pm'
                    },
                    {
                        id: 2,
                        title: 'Update Health',
                        description: '2 projects at risk',
                        icon: 'fas fa-heartbeat',
                        action: 'update_health'
                    },
                    {
                        id: 3,
                        title: 'Resolve Overdue',
                        description: '3 overdue projects',
                        icon: 'fas fa-clock',
                        action: 'resolve_overdue'
                    }
                ];
            }
        },

        // Filter Options methods
        async loadFilterOptions() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch('/app/api/v1/app/projects/filters', {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        this.filterOptions = data.data;
                    }
                }
            } catch (error) {
                console.error('Error loading filter options:', error);
                // Fallback to mock data
                this.filterOptions = {
                    pms: [
                        { id: 1, name: 'John Doe' },
                        { id: 2, name: 'Jane Smith' },
                        { id: 3, name: 'Mike Johnson' }
                    ],
                    clients: [
                        { id: 1, name: 'Acme Corp' },
                        { id: 2, name: 'Tech Solutions' },
                        { id: 3, name: 'Global Industries' }
                    ],
                    tags: ['urgent', 'design', 'development', 'marketing', 'research'],
                    locations: ['New York', 'San Francisco', 'London', 'Tokyo', 'Sydney']
                };
            }
        },

        async loadSavedViews() {
            try {
                const savedViews = localStorage.getItem('savedViews');
                if (savedViews) {
                    this.savedViews = JSON.parse(savedViews);
                } else {
                    // Default saved views
                    this.savedViews = [
                        { id: 1, name: 'My Projects', filters: { pm: '1' } },
                        { id: 2, name: 'Overdue', filters: { dateRange: 'overdue' } },
                        { id: 3, name: 'At Risk', filters: { health: 'at_risk' } }
                    ];
                }
            } catch (error) {
                console.error('Error loading saved views:', error);
                this.savedViews = [];
            }
        },

        saveCurrentView() {
            const viewName = prompt('Enter view name:');
            if (viewName) {
                const newView = {
                    id: Date.now(),
                    name: viewName,
                    filters: { ...this.filters }
                };
                this.savedViews.push(newView);
                localStorage.setItem('savedViews', JSON.stringify(this.savedViews));
                this.showSaveViewModal = false;
            }
        },

        loadSavedView(view) {
            this.filters = { ...view.filters };
            this.currentViewId = view.id;
            this.applyFilters();
        },

        deleteSavedView(viewId) {
            this.savedViews = this.savedViews.filter(view => view.id !== viewId);
            localStorage.setItem('savedViews', JSON.stringify(this.savedViews));
            if (this.currentViewId === viewId) {
                this.currentViewId = null;
            }
        },

        pinFilters() {
            // Save current filters as pinned
            localStorage.setItem('pinnedFilters', JSON.stringify(this.filters));
            console.log('Filters pinned');
        },

        // Enhanced filter methods
        applyFilters() {
            let filtered = [...this.projects];

            // Search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(project => 
                    project.name.toLowerCase().includes(query) ||
                    project.description.toLowerCase().includes(query) ||
                    project.code?.toLowerCase().includes(query)
                );
            }

            // Status filter
            if (this.filters.status) {
                filtered = filtered.filter(project => project.status === this.filters.status);
            }

            // PM filter
            if (this.filters.pm) {
                filtered = filtered.filter(project => project.pm_id === this.filters.pm);
            }

            // Client filter
            if (this.filters.client) {
                filtered = filtered.filter(project => project.client_id === this.filters.client);
            }

            // Date range filter
            if (this.filters.dateRange) {
                const now = new Date();
                filtered = filtered.filter(project => {
                    if (!project.due_date) return false;
                    const dueDate = new Date(project.due_date);
                    
                    switch (this.filters.dateRange) {
                        case 'due_7':
                            return dueDate <= new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
                        case 'due_30':
                            return dueDate <= new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
                        case 'due_90':
                            return dueDate <= new Date(now.getTime() + 90 * 24 * 60 * 60 * 1000);
                        case 'overdue':
                            return dueDate < now;
                        default:
                            return true;
                    }
                });
            }

            // Health filter
            if (this.filters.health) {
                filtered = filtered.filter(project => {
                    const health = this.getProjectHealth(project);
                    return health === this.filters.health;
                });
            }

            // Budget filter
            if (this.filters.budget) {
                filtered = filtered.filter(project => {
                    const budgetStatus = this.getBudgetStatus(project);
                    return budgetStatus === this.filters.budget;
                });
            }

            // Tags filter
            if (this.filters.tags) {
                filtered = filtered.filter(project => 
                    project.tags?.includes(this.filters.tags)
                );
            }

            // Location filter
            if (this.filters.location) {
                filtered = filtered.filter(project => 
                    project.location === this.filters.location
                );
            }

            this.filteredProjects = filtered;
            this.updateActiveFilters();
            this.updateSortedProjects();
        },

        getProjectHealth(project) {
            // Mock health calculation
            if (project.status === 'on_hold') return 'critical';
            if (project.status === 'planning') return 'at_risk';
            if (project.status === 'active') return 'good';
            return 'good';
        },

        getBudgetStatus(project) {
            // Mock budget calculation
            const usedBudget = project.budget_total * 0.75; // Mock 75% usage
            if (usedBudget > project.budget_total) return 'overbudget';
            if (usedBudget < project.budget_total * 0.5) return 'underbudget';
            return 'onbudget';
        },

        updateActiveFilters() {
            this.activeFilters = [];
            
            Object.keys(this.filters).forEach(key => {
                if (this.filters[key]) {
                    const label = this.getFilterLabel(key);
                    const value = this.getFilterValue(key, this.filters[key]);
                    this.activeFilters.push({
                        key,
                        label,
                        value
                    });
                }
            });
            
            this.activeFiltersCount = this.activeFilters.length;
        },

        getFilterLabel(key) {
            const labels = {
                status: 'Status',
                pm: 'PM',
                client: 'Client',
                dateRange: 'Date Range',
                health: 'Health',
                budget: 'Budget',
                tags: 'Tags',
                location: 'Location'
            };
            return labels[key] || key;
        },

        getFilterValue(key, value) {
            if (key === 'pm') {
                const pm = this.filterOptions.pms.find(p => p.id == value);
                return pm ? pm.name : value;
            }
            if (key === 'client') {
                const client = this.filterOptions.clients.find(c => c.id == value);
                return client ? client.name : value;
            }
            return value;
        },

        removeFilter(key) {
            this.filters[key] = '';
            this.currentViewId = null;
            this.applyFilters();
        },

        clearAllFilters() {
            this.filters = {
                status: '',
                pm: '',
                client: '',
                dateRange: '',
                health: '',
                budget: '',
                tags: '',
                location: ''
            };
            this.currentViewId = null;
            this.applyFilters();
        },

        // Legacy filter methods for compatibility
        filterByStatus(status) {
            this.filters.status = status;
            this.applyFilters();
        },

        filterByOverdue() {
            this.filters.dateRange = 'overdue';
            this.applyFilters();
        },

        filterByOverbudget() {
            this.filters.budget = 'overbudget';
            this.applyFilters();
        },

        filterByHealth(health) {
            this.filters.health = health;
            this.applyFilters();
        },

        // Alert methods
        handleAlert(alert) {
            console.log('Handling alert:', alert);
            // Implement alert handling logic
        },

        viewAllAlerts() {
            console.log('Viewing all alerts');
            // Implement view all alerts logic
        },

        // Now panel methods
        executeNowAction(action) {
            console.log('Executing now action:', action);
            // Implement now action logic
        },

        // Toolbar methods
        toggleSelectAll() {
            if (this.selectedProjects.length === this.filteredProjects.length) {
                this.selectedProjects = [];
            } else {
                this.selectedProjects = this.filteredProjects.map(project => project.id);
            }
        },

        toggleProjectSelection(projectId) {
            const index = this.selectedProjects.indexOf(projectId);
            if (index > -1) {
                this.selectedProjects.splice(index, 1);
            } else {
                this.selectedProjects.push(projectId);
            }
        },

        bulkAction(action, value = null) {
            if (this.selectedProjects.length === 0) {
                alert('Please select projects first');
                return;
            }

            const actionText = value ? `${action} to ${value}` : action;
            if (confirm(`Are you sure you want to ${actionText} ${this.selectedProjects.length} project(s)?`)) {
                console.log(`Bulk action: ${action}`, value, this.selectedProjects);
                
                // Implement bulk action logic
                switch (action) {
                    case 'status':
                        this.updateProjectStatus(this.selectedProjects, value);
                        break;
                    case 'archive':
                        this.archiveProjects(this.selectedProjects);
                        break;
                    case 'delete':
                        this.deleteProjects(this.selectedProjects);
                        break;
                }
                
                this.selectedProjects = [];
            }
        },

        updateProjectStatus(projectIds, status) {
            console.log('Updating project status:', projectIds, status);
            // Implement API call to update project status
            // For now, just update local data
            projectIds.forEach(id => {
                const project = this.projects.find(p => p.id === id);
                if (project) {
                    project.status = status;
                }
            });
            this.applyFilters();
        },

        archiveProjects(projectIds) {
            console.log('Archiving projects:', projectIds);
            // Implement API call to archive projects
            projectIds.forEach(id => {
                const project = this.projects.find(p => p.id === id);
                if (project) {
                    project.status = 'archived';
                }
            });
            this.applyFilters();
        },

        deleteProjects(projectIds) {
            console.log('Deleting projects:', projectIds);
            // Implement API call to delete projects
            this.projects = this.projects.filter(p => !projectIds.includes(p.id));
            this.applyFilters();
        },

        refreshData() {
            console.log('Refreshing data...');
            this.init();
        },

        exportData(format) {
            console.log('Exporting data as:', format);
            
            const data = this.filteredProjects.map(project => ({
                Name: project.name,
                Status: project.status,
                Progress: project.progress + '%',
                Due Date: project.due_date,
                Team: project.team,
                Priority: project.priority
            }));

            if (format === 'csv') {
                this.downloadCSV(data);
            } else if (format === 'excel') {
                this.downloadExcel(data);
            } else if (format === 'pdf') {
                this.downloadPDF(data);
            }
        },

        downloadCSV(data) {
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => headers.map(header => `"${row[header]}"`).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'projects.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        },

        downloadExcel(data) {
            // Mock Excel download
            console.log('Excel download not implemented yet');
            alert('Excel export will be implemented soon');
        },

        downloadPDF(data) {
            // Mock PDF download
            console.log('PDF download not implemented yet');
            alert('PDF export will be implemented soon');
        },

        createProject() {
            console.log('Creating new project...');
            // Implement project creation logic
            alert('Project creation form will be implemented soon');
        },

        // View action methods

        editProject(projectId) {
            console.log('Editing project:', projectId);
            // Implement project edit logic
            window.location.href = `/app/projects/${projectId}/edit`;
        },

        duplicateProject(projectId) {
            console.log('Duplicating project:', projectId);
            // Implement project duplication logic
            alert('Project duplication will be implemented soon');
        },

        archiveProject(projectId) {
            if (confirm('Are you sure you want to archive this project?')) {
                console.log('Archiving project:', projectId);
                // Implement project archiving logic
                const project = this.projects.find(p => p.id === projectId);
                if (project) {
                    project.status = 'archived';
                    this.applyFilters();
                }
            }
        },

        // Enhanced Table Methods
        updateColumnVisibility() {
            this.visibleColumns = this.tableColumns.filter(column => column.visible);
            this.saveTableSettings();
        },

        sortBy(columnKey) {
            if (this.sortColumn === columnKey) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortColumn = columnKey;
                this.sortDirection = 'asc';
            }
            this.updateSortedProjects();
        },

        updateSortedProjects() {
            this.sortedProjects = [...this.filteredProjects].sort((a, b) => {
                let aValue = this.getSortValue(a, this.sortColumn);
                let bValue = this.getSortValue(b, this.sortColumn);
                
                // Handle different data types
                if (typeof aValue === 'string') {
                    aValue = aValue.toLowerCase();
                    bValue = bValue.toLowerCase();
                }
                
                if (this.sortDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
        },

        getSortValue(project, columnKey) {
            switch (columnKey) {
                case 'project':
                    return project.name;
                case 'status':
                    return project.status;
                case 'pm':
                    return project.pm_name || 'Unassigned';
                case 'client':
                    return project.client_name || 'N/A';
                case 'progress':
                    return project.progress || 0;
                case 'due_date':
                    return project.due_date ? new Date(project.due_date) : new Date('9999-12-31');
                case 'health':
                    return this.getProjectHealth(project);
                case 'budget':
                    return project.budget_total || 0;
                case 'priority':
                    return this.getProjectPriority(project);
                case 'team':
                    return project.members_count || 0;
                case 'created_date':
                    return project.created_at ? new Date(project.created_at) : new Date('1900-01-01');
                case 'tags':
                    return (project.tags || []).join(',');
                default:
                    return '';
            }
        },

        startInlineEdit(projectId, columnKey) {
            this.editingCell = { projectId, columnKey };
            console.log('Starting inline edit:', projectId, columnKey);
        },

        saveInlineEdit(projectId, columnKey, value) {
            console.log('Saving inline edit:', projectId, columnKey, value);
            // Implement API call to save changes
            this.editingCell = null;
        },

        cancelInlineEdit() {
            this.editingCell = null;
        },

        resetTableSettings() {
            this.tableColumns.forEach(column => {
                column.visible = ['project', 'status', 'pm', 'client', 'progress', 'due_date', 'health'].includes(column.key);
            });
            this.updateColumnVisibility();
        },

        exportTableData() {
            const data = this.sortedProjects.map(project => {
                const row = {};
                this.visibleColumns.forEach(column => {
                    row[column.label] = this.getSortValue(project, column.key);
                });
                return row;
            });
            this.downloadCSV(data);
        },

        saveTableSettings() {
            localStorage.setItem('tableSettings', JSON.stringify({
                columns: this.tableColumns,
                sortColumn: this.sortColumn,
                sortDirection: this.sortDirection
            }));
        },

        loadTableSettings() {
            const settings = localStorage.getItem('tableSettings');
            if (settings) {
                const parsed = JSON.parse(settings);
                this.tableColumns = parsed.columns || this.tableColumns;
                this.sortColumn = parsed.sortColumn || 'name';
                this.sortDirection = parsed.sortDirection || 'asc';
            }
        },

        // Utility Methods
        formatCurrency(amount) {
            if (!amount) return '$0';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        },

        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        },

        isOverdue(dueDate) {
            if (!dueDate) return false;
            return new Date(dueDate) < new Date();
        },

        getProjectPriority(project) {
            // Mock priority calculation
            if (project.status === 'on_hold') return 'high';
            if (project.status === 'planning') return 'medium';
            if (project.status === 'active') return 'low';
            return 'medium';
        },

        // Additional Action Methods
        viewProjectDetails(projectId) {
            console.log('Viewing project details:', projectId);
            // Implement project details view
        },

        manageProjectTeam(projectId) {
            console.log('Managing project team:', projectId);
            // Implement team management
        },

        viewProjectTimeline(projectId) {
            console.log('Viewing project timeline:', projectId);
            // Implement timeline view
        },

        deleteProject(projectId) {
            if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                console.log('Deleting project:', projectId);
                this.projects = this.projects.filter(p => p.id !== projectId);
                this.applyFilters();
            }
        },

        // Kanban Methods
        getProjectsByStatus(status) {
            return this.filteredProjects.filter(project => project.status === status);
        },

        getColumnCount(status) {
            return this.getProjectsByStatus(status).length;
        },

        dragProject(event, project) {
            this.draggedProject = project;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/html', event.target.outerHTML);
        },

        dropProject(event, newStatus) {
            event.preventDefault();
            
            if (this.draggedProject && this.draggedProject.status !== newStatus) {
                console.log('Moving project:', this.draggedProject.name, 'to status:', newStatus);
                
                // Update project status
                const project = this.projects.find(p => p.id === this.draggedProject.id);
                if (project) {
                    project.status = newStatus;
                    this.applyFilters();
                }
                
                // Show success message
                this.showNotification(`Project "${this.draggedProject.name}" moved to ${newStatus}`, 'success');
            }
            
            this.draggedProject = null;
        },

        resetKanbanSettings() {
            this.kanbanSettings = {
                showProgress: true,
                showDueDates: true,
                showHealth: true
            };
            this.saveKanbanSettings();
        },

        addKanbanColumn() {
            const title = prompt('Enter column title:');
            if (title) {
                const newColumn = {
                    id: Date.now(),
                    title: title,
                    status: title.toLowerCase().replace(/\s+/g, '_'),
                    color: 'gray'
                };
                this.kanbanColumns.push(newColumn);
                this.saveKanbanSettings();
            }
        },

        editKanbanColumn(columnId) {
            const column = this.kanbanColumns.find(c => c.id === columnId);
            if (column) {
                const newTitle = prompt('Enter new column title:', column.title);
                if (newTitle && newTitle !== column.title) {
                    column.title = newTitle;
                    column.status = newTitle.toLowerCase().replace(/\s+/g, '_');
                    this.saveKanbanSettings();
                }
            }
        },

        deleteKanbanColumn(columnId) {
            if (confirm('Are you sure you want to delete this column? Projects in this column will be moved to "Planning".')) {
                const column = this.kanbanColumns.find(c => c.id === columnId);
                if (column) {
                    // Move projects to planning
                    this.projects.forEach(project => {
                        if (project.status === column.status) {
                            project.status = 'planning';
                        }
                    });
                    
                    // Remove column
                    this.kanbanColumns = this.kanbanColumns.filter(c => c.id !== columnId);
                    this.saveKanbanSettings();
                    this.applyFilters();
                }
            }
        },

        saveKanbanSettings() {
            localStorage.setItem('kanbanSettings', JSON.stringify({
                settings: this.kanbanSettings,
                columns: this.kanbanColumns
            }));
        },

        loadKanbanSettings() {
            const settings = localStorage.getItem('kanbanSettings');
            if (settings) {
                const parsed = JSON.parse(settings);
                this.kanbanSettings = parsed.settings || this.kanbanSettings;
                this.kanbanColumns = parsed.columns || this.kanbanColumns;
            }
        },

        showNotification(message, type = 'info') {
            // Simple notification - could be enhanced with a proper notification system
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                'bg-blue-500'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        },

        // Insights Methods
        async loadInsights() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch(`/app/api/v1/app/projects/insights?range=${this.insightsTimeRange}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        // Update insights data with API response
                        Object.assign(this.insights, data.data);
                    }
                }
            } catch (error) {
                console.error('Error loading insights:', error);
                // Use default mock data
            }
        },

        toggleChartType(chartName, newType) {
            this.chartTypes[chartName] = newType;
            this.updateChart(chartName);
        },

        initializeCharts() {
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, using fallback');
                return;
            }

            this.createStatusChart();
            this.createProgressChart();
            this.createBudgetChart();
            this.createTeamChart();
        },

        createStatusChart() {
            const ctx = document.getElementById('statusChart');
            if (!ctx) return;

            if (this.charts.statusChart) {
                this.charts.statusChart.destroy();
            }

            const statusData = this.getStatusDistribution();
            
            this.charts.statusChart = new Chart(ctx, {
                type: this.chartTypes.status,
                data: {
                    labels: statusData.labels,
                    datasets: [{
                        data: statusData.data,
                        backgroundColor: [
                            '#3B82F6', // Blue
                            '#10B981', // Green
                            '#F59E0B', // Yellow
                            '#EF4444', // Red
                            '#6B7280'  // Gray
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        },

        createProgressChart() {
            const ctx = document.getElementById('progressChart');
            if (!ctx) return;

            if (this.charts.progressChart) {
                this.charts.progressChart.destroy();
            }

            const progressData = this.getProgressTrends();
            
            this.charts.progressChart = new Chart(ctx, {
                type: this.chartTypes.progress,
                data: {
                    labels: progressData.labels,
                    datasets: [{
                        label: 'Average Progress',
                        data: progressData.data,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        },

        createBudgetChart() {
            const ctx = document.getElementById('budgetChart');
            if (!ctx) return;

            if (this.charts.budgetChart) {
                this.charts.budgetChart.destroy();
            }

            const budgetData = this.getBudgetData();
            
            this.charts.budgetChart = new Chart(ctx, {
                type: this.chartTypes.budget,
                data: {
                    labels: budgetData.labels,
                    datasets: [{
                        label: 'Budget',
                        data: budgetData.budget,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: '#3B82F6',
                        borderWidth: 2
                    }, {
                        label: 'Actual',
                        data: budgetData.actual,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: '#10B981',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + (value / 1000) + 'K';
                                }
                            }
                        }
                    }
                }
            });
        },

        createTeamChart() {
            const ctx = document.getElementById('teamChart');
            if (!ctx) return;

            if (this.charts.teamChart) {
                this.charts.teamChart.destroy();
            }

            const teamData = this.getTeamPerformance();
            
            this.charts.teamChart = new Chart(ctx, {
                type: this.chartTypes.team,
                data: {
                    labels: teamData.labels,
                    datasets: [{
                        label: 'Team Performance',
                        data: teamData.data,
                        backgroundColor: 'rgba(139, 92, 246, 0.2)',
                        borderColor: '#8B5CF6',
                        borderWidth: 2,
                        pointBackgroundColor: '#8B5CF6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: this.chartTypes.team === 'radar' ? {
                        r: {
                            beginAtZero: true,
                            max: 100
                        }
                    } : {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        },

        updateChart(chartName) {
            switch (chartName) {
                case 'status':
                    this.createStatusChart();
                    break;
                case 'progress':
                    this.createProgressChart();
                    break;
                case 'budget':
                    this.createBudgetChart();
                    break;
                case 'team':
                    this.createTeamChart();
                    break;
            }
        },

        getStatusDistribution() {
            const statusCounts = {};
            this.projects.forEach(project => {
                statusCounts[project.status] = (statusCounts[project.status] || 0) + 1;
            });

            return {
                labels: Object.keys(statusCounts),
                data: Object.values(statusCounts)
            };
        },

        getProgressTrends() {
            // Mock progress trends data
            return {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5'],
                data: [65, 72, 78, 85, 88]
            };
        },

        getBudgetData() {
            // Mock budget data
            return {
                labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                budget: [100000, 120000, 110000, 130000],
                actual: [95000, 115000, 108000, 125000]
            };
        },

        getTeamPerformance() {
            // Mock team performance data
            return {
                labels: ['Productivity', 'Quality', 'Collaboration', 'Innovation', 'Delivery'],
                data: [85, 92, 78, 88, 90]
            };
        },

        exportInsights() {
            console.log('Exporting insights...');
            // Implement insights export logic
            const data = {
                timeRange: this.insightsTimeRange,
                insights: this.insights,
                chartData: {
                    status: this.getStatusDistribution(),
                    progress: this.getProgressTrends(),
                    budget: this.getBudgetData(),
                    team: this.getTeamPerformance()
                }
            };
            
            this.downloadJSON(data, `project-insights-${this.insightsTimeRange}.json`);
        },

        downloadJSON(data, filename) {
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        // Activity Feed Methods
        async loadActivityFeed() {
            try {
                const token = localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
                
                const response = await fetch(`/app/api/v1/app/projects/activity?page=${this.currentActivityPage}&per_page=${this.activitiesPerPage}`, {
                    method: 'GET',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.status === 'success' && data.data) {
                        this.activities = data.data;
                        this.filterActivities();
                    }
                } else {
                    // Use mock data if API fails
                    this.loadMockActivities();
                }
            } catch (error) {
                console.error('Error loading activity feed:', error);
                this.loadMockActivities();
            }
        },

        loadMockActivities() {
            this.activities = [
                {
                    id: 1,
                    title: 'Project Status Updated',
                    description: 'Website Redesign project status changed from Planning to Active',
                    type: 'project',
                    icon: 'fas fa-check-circle',
                    timestamp: '2 minutes ago',
                    read: false,
                    projectId: 1,
                    details: [
                        { key: 'Previous Status', value: 'Planning' },
                        { key: 'New Status', value: 'Active' },
                        { key: 'Updated By', value: 'John Doe' }
                    ]
                },
                {
                    id: 2,
                    title: 'New Team Member Added',
                    description: 'Sarah Wilson joined the Mobile App Development project team',
                    type: 'team',
                    icon: 'fas fa-user-plus',
                    timestamp: '15 minutes ago',
                    read: false,
                    projectId: 2,
                    details: [
                        { key: 'Role', value: 'Frontend Developer' },
                        { key: 'Added By', value: 'Mike Johnson' },
                        { key: 'Team Size', value: '5 members' }
                    ]
                },
                {
                    id: 3,
                    title: 'Task Completed',
                    description: 'Database schema design task completed by Alex Chen',
                    type: 'task',
                    icon: 'fas fa-tasks',
                    timestamp: '1 hour ago',
                    read: true,
                    taskId: 15,
                    details: [
                        { key: 'Task', value: 'Database Schema Design' },
                        { key: 'Completed By', value: 'Alex Chen' },
                        { key: 'Duration', value: '3 days' }
                    ]
                },
                {
                    id: 4,
                    title: 'Budget Updated',
                    description: 'Marketing Campaign budget increased to $50,000',
                    type: 'project',
                    icon: 'fas fa-dollar-sign',
                    timestamp: '2 hours ago',
                    read: true,
                    projectId: 3,
                    details: [
                        { key: 'Previous Budget', value: '$35,000' },
                        { key: 'New Budget', value: '$50,000' },
                        { key: 'Updated By', value: 'Jane Smith' }
                    ]
                },
                {
                    id: 5,
                    title: 'System Maintenance',
                    description: 'Scheduled maintenance completed successfully',
                    type: 'system',
                    icon: 'fas fa-cog',
                    timestamp: '3 hours ago',
                    read: true,
                    details: [
                        { key: 'Duration', value: '30 minutes' },
                        { key: 'Status', value: 'Completed' },
                        { key: 'Impact', value: 'None' }
                    ]
                }
            ];
            this.filterActivities();
        },

        filterActivities() {
            if (this.activityFilter === 'all') {
                this.filteredActivities = [...this.activities];
            } else {
                this.filteredActivities = this.activities.filter(activity => activity.type === this.activityFilter);
            }
        },

        refreshActivityFeed() {
            this.currentActivityPage = 1;
            this.activities = [];
            this.loadActivityFeed();
        },

        loadMoreActivities() {
            this.currentActivityPage++;
            this.loadActivityFeed();
        },

        markAllAsRead() {
            this.activities.forEach(activity => {
                activity.read = true;
            });
            this.filterActivities();
        },

        viewActivityDetails(activityId) {
            const activity = this.activities.find(a => a.id === activityId);
            if (activity) {
                console.log('Viewing activity details:', activity);
                // Implement activity details modal
            }
        },

        viewTask(taskId) {
            console.log('Viewing task:', taskId);
            // Navigate to task details
            window.location.href = `/app/tasks/${taskId}`;
        },

        // Shortcuts Methods
        executeShortcut(shortcut) {
            console.log('Executing shortcut:', shortcut.action);
            
            switch (shortcut.action) {
                case 'createProject':
                    this.createProject();
                    break;
                case 'importProjects':
                    this.importProjects();
                    break;
                case 'browseTemplates':
                    this.browseTemplates();
                    break;
                case 'generateReports':
                    this.generateReports();
                    break;
                case 'inviteMembers':
                    this.inviteMembers();
                    break;
                case 'viewTeamOverview':
                    this.viewTeamOverview();
                    break;
                case 'assignRoles':
                    this.assignRoles();
                    break;
                case 'viewCalendar':
                    this.viewCalendar();
                    break;
                case 'quickTask':
                    this.quickTask();
                    break;
                case 'viewTaskBoard':
                    this.viewTaskBoard();
                    break;
                case 'viewMyTasks':
                    this.viewMyTasks();
                    break;
                case 'taskReports':
                    this.taskReports();
                    break;
                case 'openSettings':
                    this.openSettings();
                    break;
                case 'openHelp':
                    this.openHelp();
                    break;
                case 'viewShortcuts':
                    this.viewShortcuts();
                    break;
                case 'exportData':
                    this.exportData();
                    break;
                default:
                    console.log('Unknown shortcut action:', shortcut.action);
            }
        },

        customizeShortcuts() {
            console.log('Customizing shortcuts...');
            // Implement shortcuts customization modal
            alert('Shortcuts customization will be implemented soon');
        },

        resetShortcuts() {
            if (confirm('Are you sure you want to reset all shortcuts to default?')) {
                console.log('Resetting shortcuts...');
                // Reset to default shortcuts
                this.loadDefaultShortcuts();
            }
        },

        loadDefaultShortcuts() {
            // Reset shortcuts to default values
            this.projectShortcuts = [
                { id: 1, title: 'New Project', description: 'Create a new project', icon: 'fas fa-plus', color: 'blue', action: 'createProject' },
                { id: 2, title: 'Import Projects', description: 'Import from CSV/Excel', icon: 'fas fa-file-import', color: 'green', action: 'importProjects' },
                { id: 3, title: 'Project Templates', description: 'Browse templates', icon: 'fas fa-layer-group', color: 'purple', action: 'browseTemplates' },
                { id: 4, title: 'Project Reports', description: 'Generate reports', icon: 'fas fa-chart-bar', color: 'orange', action: 'generateReports' }
            ];
            // ... reset other shortcut arrays
        },

        // Shortcut Action Implementations
        importProjects() {
            console.log('Importing projects...');
            alert('Project import will be implemented soon');
        },

        browseTemplates() {
            console.log('Browsing templates...');
            window.location.href = '/app/templates';
        },

        generateReports() {
            console.log('Generating reports...');
            this.exportInsights();
        },

        inviteMembers() {
            console.log('Inviting members...');
            window.location.href = '/app/team/invite';
        },

        viewTeamOverview() {
            console.log('Viewing team overview...');
            window.location.href = '/app/team';
        },

        assignRoles() {
            console.log('Assigning roles...');
            window.location.href = '/app/team/roles';
        },

        viewCalendar() {
            console.log('Viewing calendar...');
            window.location.href = '/app/calendar';
        },

        quickTask() {
            console.log('Creating quick task...');
            window.location.href = '/app/tasks/create';
        },

        viewTaskBoard() {
            console.log('Viewing task board...');
            window.location.href = '/app/tasks?view=kanban';
        },

        viewMyTasks() {
            console.log('Viewing my tasks...');
            window.location.href = '/app/tasks?filter=my';
        },

        taskReports() {
            console.log('Viewing task reports...');
            window.location.href = '/app/tasks/reports';
        },

        openSettings() {
            console.log('Opening settings...');
            window.location.href = '/app/settings';
        },

        openHelp() {
            console.log('Opening help...');
            window.open('/help', '_blank');
        },

        viewShortcuts() {
            console.log('Viewing shortcuts...');
            alert('Keyboard shortcuts:\n\nCtrl+N - New Project\nCtrl+T - New Task\nCtrl+F - Search\nCtrl+E - Export\nEsc - Close modals');
        },

        exportData() {
            console.log('Exporting all data...');
            const data = {
                projects: this.projects,
                activities: this.activities,
                insights: this.insights,
                timestamp: new Date().toISOString()
            };
            this.downloadJSON(data, 'projects-data-export.json');
        }
    }
}
</script>

<style>
/* Status Classes */
.status-planning {
    @apply bg-yellow-100 text-yellow-800 border-yellow-200;
}

.status-active {
    @apply bg-green-100 text-green-800 border-green-200;
}

.status-in_progress {
    @apply bg-blue-100 text-blue-800 border-blue-200;
}

.status-on_hold {
    @apply bg-red-100 text-red-800 border-red-200;
}

.status-completed {
    @apply bg-gray-100 text-gray-800 border-gray-200;
}

.status-cancelled {
    @apply bg-gray-100 text-gray-800 border-gray-200;
}

.status-archived {
    @apply bg-purple-100 text-purple-800 border-purple-200;
}

/* Health Classes */
.health-good {
    @apply bg-green-100 text-green-800;
}

.health-at_risk {
    @apply bg-yellow-100 text-yellow-800;
}

.health-critical {
    @apply bg-red-100 text-red-800;
}

/* Priority Classes */
.priority-high {
    @apply bg-red-100 text-red-800;
}

.priority-medium {
    @apply bg-yellow-100 text-yellow-800;
}

.priority-low {
    @apply bg-green-100 text-green-800;
}

/* Transitions */
[x-cloak] {
    display: none !important;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects-content.blade.php ENDPATH**/ ?>