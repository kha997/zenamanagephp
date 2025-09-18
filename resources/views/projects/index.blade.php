@extends('layouts.dashboard')

@section('title', 'Projects Management')
@section('page-title', 'Projects Management')
@section('page-description', 'Comprehensive project management and analytics')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')
@section('current-route', 'projects')

@php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Projects Management',
        'url' => '/projects'
    ]
];
$currentRoute = 'projects';
@endphp

@section('content')
<div x-data="projectsManagement()">
    <!-- Header Actions -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">📋 Projects Management</h2>
            <p class="text-gray-600 mt-1">Comprehensive project management and analytics</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="exportProjects()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
            >
                📊 Export
            </button>
            <button 
                @click="viewDashboard()"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
            >
                📈 Analytics
            </button>
            <button 
                @click="createProject()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
            >
                🚀 Create Project
            </button>
        </div>
    </div>

    <!-- Enhanced Project Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Projects</p>
                    <p class="text-3xl font-bold text-white" x-text="projects.length"></p>
                    <p class="text-white/80 text-sm">+2 this week</p>
                </div>
                <i class="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-white" x-text="getActiveProjects()"></p>
                    <p class="text-white/80 text-sm">In progress</p>
                </div>
                <i class="fas fa-play text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Completed</p>
                    <p class="text-3xl font-bold text-white" x-text="getCompletedProjects()"></p>
                    <p class="text-white/80 text-sm">This month</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">On Hold</p>
                    <p class="text-3xl font-bold text-white" x-text="getOnHoldProjects()"></p>
                    <p class="text-white/80 text-sm">Need attention</p>
                </div>
                <i class="fas fa-pause text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Advanced Analytics Dashboard -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Budget Analysis -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-dollar-sign text-green-600 mr-2"></i>
                Budget Analysis
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Budget:</span>
                    <span class="font-semibold text-gray-900" x-text="formatCurrency(getTotalBudget())"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Spent:</span>
                    <span class="text-red-600 font-semibold" x-text="formatCurrency(getSpentBudget())"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Remaining:</span>
                    <span class="text-green-600 font-semibold" x-text="formatCurrency(getRemainingBudget())"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-red-500 h-2 rounded-full" :style="`width: ${getBudgetUtilization()}%`"></div>
                </div>
                <div class="text-xs text-gray-500 text-center" x-text="`${getBudgetUtilization()}% utilized`"></div>
            </div>
        </div>
        
        <!-- Timeline Analysis -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-calendar-alt text-blue-600 mr-2"></i>
                Timeline Analysis
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">On Schedule:</span>
                    <span class="text-green-600 font-semibold" x-text="getOnScheduleProjects()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Behind Schedule:</span>
                    <span class="text-red-600 font-semibold" x-text="getBehindScheduleProjects()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">At Risk:</span>
                    <span class="text-orange-600 font-semibold" x-text="getAtRiskProjects()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Avg. Duration:</span>
                    <span class="text-gray-900 font-semibold" x-text="getAverageDuration()"></span>
                </div>
            </div>
        </div>
        
        <!-- Resource Utilization -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-users text-purple-600 mr-2"></i>
                Resource Utilization
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Team Members:</span>
                    <span class="font-semibold text-gray-900" x-text="getTotalTeamMembers()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Active:</span>
                    <span class="text-green-600 font-semibold" x-text="getActiveTeamMembers()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Utilization:</span>
                    <span class="text-blue-600 font-semibold" x-text="getResourceUtilization() + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full" :style="`width: ${getResourceUtilization()}%`"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters and Search -->
    <div class="dashboard-card p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Projects</label>
                <input 
                    type="text" 
                    x-model="searchQuery"
                    @input="filterProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Search by name, client, or description..."
                >
            </div>
            
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select 
                    x-model="selectedStatus"
                    @change="filterProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Status</option>
                    <option value="planning">Planning</option>
                    <option value="active">Active</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <!-- Priority Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select 
                    x-model="selectedPriority"
                    @change="filterProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Priority</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            
            <!-- Sort Options -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select 
                    x-model="sortBy"
                    @change="sortProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="name">Name</option>
                    <option value="due_date">Due Date</option>
                    <option value="budget">Budget</option>
                    <option value="progress">Progress</option>
                    <option value="priority">Priority</option>
                    <option value="created_at">Created Date</option>
                </select>
            </div>
        </div>
        
        <!-- Advanced Filters -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Date Range Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="flex space-x-2">
                    <input 
                        type="date" 
                        x-model="dateFrom"
                        @change="filterProjects()"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                    <input 
                        type="date" 
                        x-model="dateTo"
                        @change="filterProjects()"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>
            
            <!-- Budget Range Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Budget Range</label>
                <select 
                    x-model="selectedBudgetRange"
                    @change="filterProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Budgets</option>
                    <option value="0-1000000">$0 - $1M</option>
                    <option value="1000000-5000000">$1M - $5M</option>
                    <option value="5000000-10000000">$5M - $10M</option>
                    <option value="10000000+">$10M+</option>
                </select>
            </div>
            
            <!-- Client Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
                <select 
                    x-model="selectedClient"
                    @change="filterProjects()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Clients</option>
                    <template x-for="client in getUniqueClients()" :key="client">
                        <option :value="client" x-text="client"></option>
                    </template>
                </select>
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="mt-4 flex justify-between items-center">
            <div class="flex space-x-2">
                <button 
                    @click="clearFilters()"
                    class="px-3 py-2 text-gray-600 hover:text-gray-800 text-sm"
                >
                    Clear Filters
                </button>
                <button 
                    @click="saveFilters()"
                    class="px-3 py-2 bg-gray-600 text-white rounded text-sm hover:bg-gray-700"
                >
                    Save Filters
                </button>
            </div>
            <div class="text-sm text-gray-500" x-text="`${filteredProjects.length} of ${projects.length} projects`"></div>
        </div>
    </div>

    <!-- Bulk Operations -->
    <div class="dashboard-card p-4 mb-6" x-show="selectedProjects.length > 0">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600" x-text="`${selectedProjects.length} projects selected`"></span>
                <button 
                    @click="selectAllProjects()"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                >
                    Select All
                </button>
                <button 
                    @click="clearSelection()"
                    class="text-gray-600 hover:text-gray-800 text-sm"
                >
                    Clear Selection
                </button>
            </div>
            <div class="flex space-x-2">
                <button 
                    @click="bulkExport()"
                    class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors"
                >
                    <i class="fas fa-chart-bar mr-1"></i>Export Selected
                </button>
                <button 
                    @click="bulkStatusChange()"
                    class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors"
                >
                    <i class="fas fa-list-check mr-1"></i>Change Status
                </button>
                <button 
                    @click="bulkAssign()"
                    class="px-3 py-1 bg-purple-600 text-white rounded text-sm hover:bg-purple-700 transition-colors"
                >
                    <i class="fas fa-user mr-1"></i>Assign
                </button>
                <button 
                    @click="bulkArchive()"
                    class="px-3 py-1 bg-yellow-600 text-white rounded text-sm hover:bg-yellow-700 transition-colors"
                >
                    <i class="fas fa-archive mr-1"></i>Archive
                </button>
                <button 
                    @click="bulkDelete()"
                    class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700 transition-colors"
                >
                    <i class="fas fa-trash mr-1"></i>Delete
                </button>
            </div>
        </div>
    </div>

    <!-- Projects List with Enhanced Features -->
    <div class="space-y-4">
        <template x-for="project in filteredProjects" :key="project.id">
            <div class="dashboard-card p-6 hover:shadow-lg transition-shadow cursor-pointer" 
                 :class="{'ring-2 ring-blue-500': selectedProjects.includes(project.id)}"
                 @click="toggleProjectSelection(project)">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-4 flex-1">
                        <!-- Selection Checkbox -->
                        <input 
                            type="checkbox" 
                            :checked="selectedProjects.includes(project.id)"
                            @click.stop="toggleProjectSelection(project)"
                            class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        
                        <!-- Project Info -->
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-3">
                                <h3 class="text-lg font-semibold text-gray-900" x-text="project.name"></h3>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getStatusClass(project.status)"
                                    x-text="project.status"
                                ></span>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getPriorityClass(project.priority)"
                                    x-text="project.priority"
                                ></span>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getRiskClass(project.risk_level)"
                                    x-text="project.risk_level"
                                ></span>
                            </div>
                            
                            <p class="text-gray-600 mb-4" x-text="project.description"></p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-500 mb-4">
                                <div>
                                    <span class="font-medium">Client:</span>
                                    <span x-text="project.client"></span>
                                </div>
                                <div>
                                    <span class="font-medium">PM:</span>
                                    <span x-text="project.pm"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Due Date:</span>
                                    <span x-text="project.due_date"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Budget:</span>
                                    <span x-text="formatCurrency(project.budget)"></span>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span x-text="project.progress + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="h-2 rounded-full"
                                        :class="getProgressColor(project.progress)"
                                        :style="`width: ${project.progress}%`"
                                    ></div>
                                </div>
                            </div>
                            
                            <!-- Team Members -->
                            <div class="flex items-center space-x-2 mb-4">
                                <span class="text-sm text-gray-600">Team:</span>
                                <div class="flex -space-x-2">
                                    <template x-for="member in project.team_members" :key="member.id">
                                        <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs" 
                                             :title="member.name">
                                            <span x-text="member.name.charAt(0)"></span>
                                        </div>
                                    </template>
                                </div>
                                <span class="text-xs text-gray-500" x-text="`+${project.team_members.length} members`"></span>
                            </div>
                            
                            <!-- Documents & Tasks -->
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <div class="flex items-center">
                                    <i class="fas fa-file-alt mr-1"></i>
                                    <span x-text="project.documents_count + ' docs'"></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-tasks mr-1"></i>
                                    <span x-text="project.tasks_count + ' tasks'"></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-comments mr-1"></i>
                                    <span x-text="project.comments_count + ' comments'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-1 ml-4">
                        <button 
                            @click.stop="viewProject(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="View Details"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                        <button 
                            @click.stop="editProject(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Edit Project"
                        >
                            <i class="fas fa-edit"></i>
                        </button>
                        <button 
                            @click.stop="showProjectDocuments(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Documents & Files"
                        >
                            <i class="fas fa-file-alt"></i>
                        </button>
                        <button 
                            @click.stop="showProjectHistory(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="History & Log"
                        >
                            <i class="fas fa-history"></i>
                        </button>
                        <button 
                            @click.stop="duplicateProject(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Duplicate Project"
                        >
                            <i class="fas fa-copy"></i>
                        </button>
                        <button 
                            @click.stop="archiveProject(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Archive Project"
                        >
                            <i class="fas fa-archive"></i>
                        </button>
                        <button 
                            @click.stop="deleteProject(project)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Delete Project"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="filteredProjects.length === 0" class="text-center py-12">
        <div class="text-6xl mb-4">📋</div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No projects found</h3>
        <p class="text-gray-600 mb-4">Create your first project to get started</p>
        <button 
            @click="createProject()"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
            Create Project
        </button>
    </div>

    <!-- Pagination -->
    <div class="mt-6 flex justify-center">
        <nav class="flex items-center space-x-2">
            <button 
                @click="previousPage()"
                :disabled="currentPage === 1"
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50"
            >
                Previous
            </button>
            <template x-for="page in getPageNumbers()" :key="page">
                <button 
                    @click="goToPage(page)"
                    :class="{'bg-blue-600 text-white': page === currentPage, 'text-gray-700 hover:text-gray-900': page !== currentPage}"
                    class="px-3 py-2 text-sm rounded"
                >
                    <span x-text="page"></span>
                </button>
            </template>
            <button 
                @click="nextPage()"
                :disabled="currentPage === totalPages"
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 disabled:opacity-50"
            >
                Next
            </button>
        </nav>
    </div>
</div>

<script>
function projectsManagement() {
    return {
        // State
        searchQuery: '',
        selectedStatus: '',
        selectedPriority: '',
        sortBy: 'name',
        dateFrom: '',
        dateTo: '',
        selectedBudgetRange: '',
        selectedClient: '',
        selectedProjects: [],
        currentPage: 1,
        itemsPerPage: 10,
        
        // Enhanced Project Data
        projects: [
            {
                id: 1,
                name: 'Office Building Complex',
                description: 'Modern office building with 20 floors and advanced facilities',
                status: 'active',
                priority: 'high',
                risk_level: 'medium',
                client: 'ABC Corporation',
                pm: 'John Smith',
                due_date: 'Mar 15, 2024',
                budget: 5000000,
                progress: 75,
                created_at: '2023-01-15',
                team_members: [
                    { id: 1, name: 'John Smith' },
                    { id: 2, name: 'Sarah Wilson' },
                    { id: 3, name: 'Mike Johnson' }
                ],
                documents_count: 45,
                tasks_count: 23,
                comments_count: 12
            },
            {
                id: 2,
                name: 'Shopping Mall Development',
                description: 'Large shopping mall with retail spaces and entertainment areas',
                status: 'active',
                priority: 'medium',
                risk_level: 'low',
                client: 'XYZ Group',
                pm: 'Sarah Wilson',
                due_date: 'Feb 28, 2024',
                budget: 8500000,
                progress: 45,
                created_at: '2023-02-01',
                team_members: [
                    { id: 4, name: 'Sarah Wilson' },
                    { id: 5, name: 'Alex Lee' },
                    { id: 6, name: 'Emma Davis' }
                ],
                documents_count: 32,
                tasks_count: 18,
                comments_count: 8
            },
            {
                id: 3,
                name: 'Residential Complex',
                description: 'Luxury residential complex with 500 units',
                status: 'planning',
                priority: 'medium',
                risk_level: 'high',
                client: 'DEF Properties',
                pm: 'Mike Johnson',
                due_date: 'Dec 15, 2024',
                budget: 12000000,
                progress: 15,
                created_at: '2023-03-10',
                team_members: [
                    { id: 7, name: 'Mike Johnson' },
                    { id: 8, name: 'Lisa Brown' }
                ],
                documents_count: 18,
                tasks_count: 8,
                comments_count: 5
            },
            {
                id: 4,
                name: 'Hotel Complex',
                description: '5-star hotel with conference facilities',
                status: 'on_hold',
                priority: 'low',
                risk_level: 'medium',
                client: 'GHI Hotels',
                pm: 'Alex Lee',
                due_date: 'Jun 30, 2024',
                budget: 6200000,
                progress: 30,
                created_at: '2023-04-05',
                team_members: [
                    { id: 9, name: 'Alex Lee' },
                    { id: 10, name: 'Tom Wilson' }
                ],
                documents_count: 25,
                tasks_count: 12,
                comments_count: 6
            },
            {
                id: 5,
                name: 'Industrial Warehouse',
                description: 'Large-scale industrial warehouse facility',
                status: 'completed',
                priority: 'high',
                risk_level: 'low',
                client: 'JKL Industries',
                pm: 'Emma Davis',
                due_date: 'Jan 20, 2024',
                budget: 3500000,
                progress: 100,
                created_at: '2023-01-01',
                team_members: [
                    { id: 11, name: 'Emma Davis' },
                    { id: 12, name: 'David Chen' }
                ],
                documents_count: 28,
                tasks_count: 15,
                comments_count: 9
            }
        ],
        
        // Computed Properties
        get filteredProjects() {
            let filtered = this.projects;
            
            // Search filter
            if (this.searchQuery) {
                filtered = filtered.filter(project => 
                    project.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    project.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    project.client.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    project.pm.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            // Status filter
            if (this.selectedStatus) {
                filtered = filtered.filter(project => project.status === this.selectedStatus);
            }
            
            // Priority filter
            if (this.selectedPriority) {
                filtered = filtered.filter(project => project.priority === this.selectedPriority);
            }
            
            // Date range filter
            if (this.dateFrom) {
                filtered = filtered.filter(project => new Date(project.created_at) >= new Date(this.dateFrom));
            }
            if (this.dateTo) {
                filtered = filtered.filter(project => new Date(project.created_at) <= new Date(this.dateTo));
            }
            
            // Budget range filter
            if (this.selectedBudgetRange) {
                const [min, max] = this.selectedBudgetRange.split('-').map(v => v === '' ? Infinity : parseInt(v));
                filtered = filtered.filter(project => {
                    if (max === Infinity) return project.budget >= min;
                    return project.budget >= min && project.budget <= max;
                });
            }
            
            // Client filter
            if (this.selectedClient) {
                filtered = filtered.filter(project => project.client === this.selectedClient);
            }
            
            // Sort
            filtered.sort((a, b) => {
                switch (this.sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name);
                    case 'due_date':
                        return new Date(a.due_date) - new Date(b.due_date);
                    case 'budget':
                        return b.budget - a.budget;
                    case 'progress':
                        return b.progress - a.progress;
                    case 'priority':
                        const priorityOrder = { urgent: 4, high: 3, medium: 2, low: 1 };
                        return priorityOrder[b.priority] - priorityOrder[a.priority];
                    case 'created_at':
                        return new Date(b.created_at) - new Date(a.created_at);
                    default:
                        return 0;
                }
            });
            
            return filtered;
        },
        
        get totalPages() {
            return Math.ceil(this.filteredProjects.length / this.itemsPerPage);
        },
        
        // Methods
        getActiveProjects() {
            return this.projects.filter(p => p.status === 'active').length;
        },
        
        getCompletedProjects() {
            return this.projects.filter(p => p.status === 'completed').length;
        },
        
        getOnHoldProjects() {
            return this.projects.filter(p => p.status === 'on_hold').length;
        },
        
        getTotalBudget() {
            return this.projects.reduce((sum, project) => sum + project.budget, 0);
        },
        
        getSpentBudget() {
            return this.projects.reduce((sum, project) => sum + (project.budget * project.progress / 100), 0);
        },
        
        getRemainingBudget() {
            return this.getTotalBudget() - this.getSpentBudget();
        },
        
        getBudgetUtilization() {
            return Math.round((this.getSpentBudget() / this.getTotalBudget()) * 100);
        },
        
        getOnScheduleProjects() {
            return this.projects.filter(p => p.progress >= 75 && p.status === 'active').length;
        },
        
        getBehindScheduleProjects() {
            return this.projects.filter(p => p.progress < 50 && p.status === 'active').length;
        },
        
        getAtRiskProjects() {
            return this.projects.filter(p => p.risk_level === 'high').length;
        },
        
        getAverageDuration() {
            const durations = this.projects.map(p => {
                const start = new Date(p.created_at);
                const end = new Date(p.due_date);
                return Math.ceil((end - start) / (1000 * 60 * 60 * 24));
            });
            return Math.round(durations.reduce((sum, d) => sum + d, 0) / durations.length) + ' days';
        },
        
        getTotalTeamMembers() {
            const allMembers = new Set();
            this.projects.forEach(project => {
                project.team_members.forEach(member => allMembers.add(member.id));
            });
            return allMembers.size;
        },
        
        getActiveTeamMembers() {
            const activeMembers = new Set();
            this.projects.filter(p => p.status === 'active').forEach(project => {
                project.team_members.forEach(member => activeMembers.add(member.id));
            });
            return activeMembers.size;
        },
        
        getResourceUtilization() {
            return Math.round((this.getActiveTeamMembers() / this.getTotalTeamMembers()) * 100);
        },
        
        getUniqueClients() {
            return [...new Set(this.projects.map(p => p.client))];
        },
        
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        },
        
        getStatusClass(status) {
            const classes = {
                'planning': 'bg-yellow-100 text-yellow-800',
                'active': 'bg-green-100 text-green-800',
                'on_hold': 'bg-orange-100 text-orange-800',
                'completed': 'bg-blue-100 text-blue-800',
                'cancelled': 'bg-red-100 text-red-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },
        
        getPriorityClass(priority) {
            const classes = {
                'low': 'bg-gray-100 text-gray-800',
                'medium': 'bg-yellow-100 text-yellow-800',
                'high': 'bg-orange-100 text-orange-800',
                'urgent': 'bg-red-100 text-red-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
        },
        
        getRiskClass(risk) {
            const classes = {
                'low': 'bg-green-100 text-green-800',
                'medium': 'bg-yellow-100 text-yellow-800',
                'high': 'bg-red-100 text-red-800'
            };
            return classes[risk] || 'bg-gray-100 text-gray-800';
        },
        
        getProgressColor(progress) {
            if (progress >= 80) return 'bg-green-500';
            if (progress >= 60) return 'bg-blue-500';
            if (progress >= 40) return 'bg-yellow-500';
            return 'bg-red-500';
        },
        
        // Filter and Search Methods
        filterProjects() {
            // Filtering is handled by computed property
        },
        
        sortProjects() {
            // Sorting is handled by computed property
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.selectedStatus = '';
            this.selectedPriority = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.selectedBudgetRange = '';
            this.selectedClient = '';
            this.sortBy = 'name';
        },
        
        saveFilters() {
            // Save current filters to localStorage
            const filters = {
                searchQuery: this.searchQuery,
                selectedStatus: this.selectedStatus,
                selectedPriority: this.selectedPriority,
                dateFrom: this.dateFrom,
                dateTo: this.dateTo,
                selectedBudgetRange: this.selectedBudgetRange,
                selectedClient: this.selectedClient,
                sortBy: this.sortBy
            };
            localStorage.setItem('projectFilters', JSON.stringify(filters));
            this.showNotification('Filters saved successfully!', 'success');
        },
        
        // Selection Methods
        toggleProjectSelection(project) {
            const index = this.selectedProjects.indexOf(project.id);
            if (index > -1) {
                this.selectedProjects.splice(index, 1);
            } else {
                this.selectedProjects.push(project.id);
            }
        },
        
        selectAllProjects() {
            this.selectedProjects = this.filteredProjects.map(p => p.id);
        },
        
        clearSelection() {
            this.selectedProjects = [];
        },
        
        // Bulk Operations
        bulkExport() {
            const selectedProjectsData = this.projects.filter(p => this.selectedProjects.includes(p.id));
            console.log('Exporting projects:', selectedProjectsData);
            this.showNotification(`Exporting ${selectedProjectsData.length} projects...`, 'info');
        },
        
        bulkStatusChange() {
            if (this.selectedProjects.length === 0) {
                this.showNotification('Please select projects first', 'warning');
                return;
            }
            
            const newStatus = prompt('Enter new status (draft, active, on_hold, completed, archived):');
            if (newStatus && ['draft', 'active', 'on_hold', 'completed', 'archived'].includes(newStatus)) {
                this.projects.forEach(project => {
                    if (this.selectedProjects.includes(project.id)) {
                        project.status = newStatus;
                    }
                });
                this.showNotification(`${this.selectedProjects.length} projects status updated to ${newStatus}`, 'success');
                this.clearSelection();
            } else if (newStatus) {
                this.showNotification('Invalid status. Please use: draft, active, on_hold, completed, or archived', 'error');
            }
        },
        
        bulkAssign() {
            if (this.selectedProjects.length === 0) {
                this.showNotification('Please select projects first', 'warning');
                return;
            }
            
            const assigneeId = prompt('Enter assignee ID (1-5):');
            if (assigneeId && parseInt(assigneeId) >= 1 && parseInt(assigneeId) <= 5) {
                this.projects.forEach(project => {
                    if (this.selectedProjects.includes(project.id)) {
                        project.pm_id = parseInt(assigneeId);
                    }
                });
                this.showNotification(`${this.selectedProjects.length} projects assigned to user ${assigneeId}`, 'success');
                this.clearSelection();
            } else if (assigneeId) {
                this.showNotification('Invalid assignee ID. Please use 1-5', 'error');
            }
        },
        
        bulkArchive() {
            if (confirm(`Archive ${this.selectedProjects.length} projects?`)) {
                this.projects.forEach(project => {
                    if (this.selectedProjects.includes(project.id)) {
                        project.status = 'archived';
                    }
                });
                this.clearSelection();
                this.showNotification(`${this.selectedProjects.length} projects archived successfully!`, 'success');
            }
        },
        
        bulkDelete() {
            if (confirm(`Delete ${this.selectedProjects.length} projects? This action cannot be undone.`)) {
                const deletedCount = this.selectedProjects.length;
                this.projects = this.projects.filter(p => !this.selectedProjects.includes(p.id));
                this.clearSelection();
                this.showNotification(`${deletedCount} projects deleted successfully!`, 'success');
            }
        },
        
        // Project Actions
        viewProject(project) {
            console.log('Viewing project:', project);
            this.showNotification(`Opening project: ${project.name}`, 'info');
            // Redirect to project detail page
            setTimeout(() => {
                window.location.href = `/projects/${project.id}`;
            }, 1000);
        },
        
        createProject() {
            console.log('Creating new project');
            this.showNotification('Opening project creation form...', 'info');
            // Redirect to project creation page
            setTimeout(() => {
                window.location.href = '/projects/create';
            }, 1000);
        },
        
        editProject(project) {
            console.log('Editing project:', project);
            this.showNotification(`Opening edit form for: ${project.name}`, 'info');
            // Redirect to project edit page
            setTimeout(() => {
                window.location.href = `/projects/${project.id}/edit`;
            }, 1000);
        },
        
        showProjectDocuments(project) {
            console.log('Managing documents for project:', project);
            this.showNotification(`Opening documents for: ${project.name}`, 'info');
            // Open documents modal or redirect to documents page
            setTimeout(() => {
                window.open(`/projects/${project.id}/documents`, '_blank');
            }, 1000);
        },
        
        showProjectHistory(project) {
            console.log('Viewing history for project:', project);
            this.showNotification(`Opening history for: ${project.name}`, 'info');
            // Open history modal or redirect to history page
            setTimeout(() => {
                window.open(`/projects/${project.id}/history`, '_blank');
            }, 1000);
        },
        
        duplicateProject(project) {
            if (confirm(`Duplicate project: ${project.name}?`)) {
                const newProject = {
                    ...project,
                    id: Date.now(),
                    name: project.name + ' (Copy)',
                    status: 'planning',
                    progress: 0,
                    created_at: new Date().toISOString().split('T')[0]
                };
                this.projects.push(newProject);
                this.showNotification(`Project duplicated: ${newProject.name}`, 'success');
            }
        },
        
        archiveProject(project) {
            if (confirm(`Archive project: ${project.name}?`)) {
                project.status = 'archived';
                this.showNotification(`Project archived: ${project.name}`, 'success');
            }
        },
        
        deleteProject(project) {
            if (confirm(`Delete project: ${project.name}? This action cannot be undone.`)) {
                this.projects = this.projects.filter(p => p.id !== project.id);
                this.showNotification(`Project deleted: ${project.name}`, 'success');
            }
        },
        
        exportProjects() {
            console.log('Exporting all projects');
            this.showNotification('Exporting all projects...', 'info');
        },
        
        viewDashboard() {
            this.showNotification('Opening analytics dashboard...', 'info');
            setTimeout(() => {
                window.location.href = '/dashboard/pm';
            }, 1000);
        },
        
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        },
        
        // Pagination Methods
        getPageNumbers() {
            const pages = [];
            const start = Math.max(1, this.currentPage - 2);
            const end = Math.min(this.totalPages, this.currentPage + 2);
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            return pages;
        },
        
        goToPage(page) {
            this.currentPage = page;
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
        
        // Initialize
        init() {
            // Load saved filters
            const savedFilters = localStorage.getItem('projectFilters');
            if (savedFilters) {
                const filters = JSON.parse(savedFilters);
                Object.assign(this, filters);
            }
        }
    }
}
</script>
@endsection