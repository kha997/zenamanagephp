<?php $__env->startSection('title', 'Tasks Management'); ?>
<?php $__env->startSection('page-title', 'Tasks Management'); ?>
<?php $__env->startSection('page-description', 'Comprehensive task management with project integration'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>
<?php $__env->startSection('current-route', 'tasks'); ?>

<?php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Tasks Management',
        'url' => '/tasks'
    ]
];
$currentRoute = 'tasks';
?>

<?php $__env->startSection('content'); ?>
<div x-data="tasksManagement()">
    <!-- Header Actions -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">📝 Tasks Management</h2>
            <p class="text-gray-600 mt-1">Comprehensive task management with project integration</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="exportTasks()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
            >
                📊 Export
            </button>
            <button 
                @click="viewAnalytics()"
                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
            >
                📈 Analytics
            </button>
            <button 
                @click="createTask()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
            >
                🚀 Create Task
            </button>
        </div>
    </div>

    <!-- Enhanced Task Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="dashboard-card metric-card green p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Total Tasks</p>
                    <p class="text-3xl font-bold text-white" x-text="tasks.length"></p>
                    <p class="text-white/80 text-sm">+5 this week</p>
                </div>
                <i class="fas fa-tasks text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card blue p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">In Progress</p>
                    <p class="text-3xl font-bold text-white" x-text="getInProgressTasks()"></p>
                    <p class="text-white/80 text-sm">Active tasks</p>
                </div>
                <i class="fas fa-play text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card orange p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Completed</p>
                    <p class="text-3xl font-bold text-white" x-text="getCompletedTasks()"></p>
                    <p class="text-white/80 text-sm">This month</p>
                </div>
                <i class="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
        </div>

        <div class="dashboard-card metric-card purple p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-white/80 text-sm">Overdue</p>
                    <p class="text-3xl font-bold text-white" x-text="getOverdueTasks()"></p>
                    <p class="text-white/80 text-sm">Need attention</p>
                </div>
                <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
            </div>
        </div>
    </div>

    <!-- Advanced Analytics Dashboard -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Time Tracking Analysis -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-clock text-blue-600 mr-2"></i>
                Time Tracking
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Estimated Hours:</span>
                    <span class="font-semibold text-gray-900" x-text="getTotalEstimatedHours() + 'h'"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Actual Hours:</span>
                    <span class="text-blue-600 font-semibold" x-text="getTotalActualHours() + 'h'"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Efficiency:</span>
                    <span class="text-green-600 font-semibold" x-text="getEfficiencyRate() + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full" :style="`width: ${getTimeUtilization()}%`"></div>
                </div>
                <div class="text-xs text-gray-500 text-center" x-text="`${getTimeUtilization()}% time utilized`"></div>
            </div>
        </div>
        
        <!-- Progress Analysis -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-chart-line text-green-600 mr-2"></i>
                Progress Analysis
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Avg. Progress:</span>
                    <span class="text-green-600 font-semibold" x-text="getAverageProgress() + '%'"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">On Track:</span>
                    <span class="text-green-600 font-semibold" x-text="getOnTrackTasks()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Behind Schedule:</span>
                    <span class="text-red-600 font-semibold" x-text="getBehindScheduleTasks()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">At Risk:</span>
                    <span class="text-orange-600 font-semibold" x-text="getAtRiskTasks()"></span>
                </div>
            </div>
        </div>
        
        <!-- Project Integration -->
        <div class="dashboard-card p-6">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-project-diagram text-purple-600 mr-2"></i>
                Project Integration
            </h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Active Projects:</span>
                    <span class="font-semibold text-gray-900" x-text="getActiveProjectsCount()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Tasks per Project:</span>
                    <span class="text-blue-600 font-semibold" x-text="getAverageTasksPerProject()"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Project Completion:</span>
                    <span class="text-green-600 font-semibold" x-text="getProjectCompletionRate() + '%'"></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-purple-500 h-2 rounded-full" :style="`width: ${getProjectCompletionRate()}%`"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters and Search -->
    <div class="dashboard-card p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Tasks</label>
                <input 
                    type="text" 
                    x-model="searchQuery"
                    @input="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Search by name, description, or assignee..."
                >
            </div>
            
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select 
                    x-model="selectedStatus"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <!-- Priority Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select 
                    x-model="selectedPriority"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Priority</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            
            <!-- Project Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                <select 
                    x-model="selectedProject"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Projects</option>
                    <template x-for="project in getUniqueProjects()" :key="project.id">
                        <option :value="project.id" x-text="project.name"></option>
                    </template>
                </select>
            </div>
            
            <!-- Sort Options -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select 
                    x-model="sortBy"
                    @change="sortTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="name">Name</option>
                    <option value="due_date">Due Date</option>
                    <option value="priority">Priority</option>
                    <option value="progress">Progress</option>
                    <option value="created_at">Created Date</option>
                    <option value="estimated_hours">Estimated Hours</option>
                </select>
            </div>
        </div>
        
        <!-- Advanced Filters -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Date Range Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <div class="flex space-x-2">
                    <input 
                        type="date" 
                        x-model="dateFrom"
                        @change="filterTasks()"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                    <input 
                        type="date" 
                        x-model="dateTo"
                        @change="filterTasks()"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </div>
            
            <!-- Assignee Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Assignee</label>
                <select 
                    x-model="selectedAssignee"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Assignees</option>
                    <template x-for="assignee in getUniqueAssignees()" :key="assignee">
                        <option :value="assignee" x-text="assignee"></option>
                    </template>
                </select>
            </div>
            
            <!-- Progress Range Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Progress Range</label>
                <select 
                    x-model="selectedProgressRange"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Progress</option>
                    <option value="0-25">0% - 25%</option>
                    <option value="25-50">25% - 50%</option>
                    <option value="50-75">50% - 75%</option>
                    <option value="75-100">75% - 100%</option>
                </select>
            </div>
            
            <!-- Hours Range Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hours Range</label>
                <select 
                    x-model="selectedHoursRange"
                    @change="filterTasks()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">All Hours</option>
                    <option value="0-8">0 - 8h</option>
                    <option value="8-40">8 - 40h</option>
                    <option value="40-80">40 - 80h</option>
                    <option value="80+">80h+</option>
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
            <div class="text-sm text-gray-500" x-text="`${filteredTasks.length} of ${tasks.length} tasks`"></div>
        </div>
    </div>

    <!-- Bulk Operations -->
    <div class="dashboard-card p-4 mb-6" x-show="selectedTasks.length > 0">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600" x-text="`${selectedTasks.length} tasks selected`"></span>
                <button 
                    @click="selectAllTasks()"
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

    <!-- Tasks List with Enhanced Features -->
    <div class="space-y-4">
        <template x-for="task in filteredTasks" :key="task.id">
            <div class="dashboard-card p-6 hover:shadow-lg transition-shadow cursor-pointer" 
                 :class="{'ring-2 ring-blue-500': selectedTasks.includes(task.id)}"
                 @click="toggleTaskSelection(task)">
                <div class="flex items-start justify-between">
                    <div class="flex items-start space-x-4 flex-1">
                        <!-- Selection Checkbox -->
                        <input 
                            type="checkbox" 
                            :checked="selectedTasks.includes(task.id)"
                            @click.stop="toggleTaskSelection(task)"
                            class="mt-1 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        
                        <!-- Task Info -->
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-3">
                                <h3 class="text-lg font-semibold text-gray-900" x-text="task.name"></h3>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getStatusClass(task.status)"
                                    x-text="task.status"
                                ></span>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getPriorityClass(task.priority)"
                                    x-text="task.priority"
                                ></span>
                                <span 
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="getRiskClass(task.risk_level)"
                                    x-text="task.risk_level"
                                ></span>
                            </div>
                            
                            <p class="text-gray-600 mb-4" x-text="task.description"></p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-500 mb-4">
                                <div>
                                    <span class="font-medium">Project:</span>
                                    <span x-text="task.project_name"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Assignee:</span>
                                    <span x-text="task.assignee || 'Unassigned'"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Due Date:</span>
                                    <span x-text="task.due_date"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Hours:</span>
                                    <span x-text="task.actual_hours + '/' + task.estimated_hours + 'h'"></span>
                                </div>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm text-gray-600 mb-1">
                                    <span>Progress</span>
                                    <span x-text="task.progress_percent + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div 
                                        class="h-2 rounded-full"
                                        :class="getProgressColor(task.progress_percent)"
                                        :style="`width: ${task.progress_percent}%`"
                                    ></div>
                                </div>
                            </div>
                            
                            <!-- Dependencies -->
                            <div class="flex items-center space-x-2 mb-4" x-show="task.dependencies && task.dependencies.length > 0">
                                <span class="text-sm text-gray-600">Dependencies:</span>
                                <div class="flex space-x-1">
                                    <template x-for="dep in task.dependencies" :key="dep">
                                        <span class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded" x-text="dep"></span>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Tags -->
                            <div class="flex items-center space-x-2 mb-4" x-show="task.tags && task.tags.length > 0">
                                <span class="text-sm text-gray-600">Tags:</span>
                                <div class="flex space-x-1">
                                    <template x-for="tag in task.tags" :key="tag">
                                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded" x-text="tag"></span>
                                    </template>
                                </div>
                            </div>
                            
                            <!-- Time Tracking -->
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <div class="flex items-center">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span x-text="task.actual_hours + 'h logged'"></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar mr-1"></i>
                                    <span x-text="task.created_at"></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-1"></i>
                                    <span x-text="task.assignee || 'Unassigned'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex space-x-1 ml-4">
                        <button 
                            @click.stop="viewTask(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="View Details"
                        >
                            <i class="fas fa-eye"></i>
                        </button>
                        <button 
                            @click.stop="editTask(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Edit Task"
                        >
                            <i class="fas fa-edit"></i>
                        </button>
                        <button 
                            @click.stop="showTaskDocuments(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Documents & Notes"
                        >
                            <i class="fas fa-file-alt"></i>
                        </button>
                        <button 
                            @click.stop="showTaskHistory(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="History & Log"
                        >
                            <i class="fas fa-history"></i>
                        </button>
                        <button 
                            @click.stop="duplicateTask(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Duplicate Task"
                        >
                            <i class="fas fa-copy"></i>
                        </button>
                        <button 
                            @click.stop="archiveTask(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Archive Task"
                        >
                            <i class="fas fa-archive"></i>
                        </button>
                        <button 
                            @click.stop="deleteTask(task)"
                            class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300 transition-colors"
                            title="Delete Task"
                        >
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="filteredTasks.length === 0" class="text-center py-12">
        <div class="text-6xl mb-4">📝</div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
        <p class="text-gray-600 mb-4">Create your first task to get started</p>
        <button 
            @click="createTask()"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
            Create Task
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
    
    <!-- Task Detail Modal -->
    <div x-show="showTaskModalFlag" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="hideTaskModal()"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900" x-text="selectedTask?.name"></h3>
                        <button @click="hideTaskModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4" x-show="selectedTask">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.status"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Priority</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.priority"></span>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <p class="mt-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                               x-text="selectedTask?.description"></p>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assignee</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.assignee || 'Unassigned'"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Due Date</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.due_date"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Progress</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.progress_percent + '%'"></span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Estimated Hours</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.estimated_hours + 'h'"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Actual Hours</label>
                                <span class="mt-1 block px-3 py-2 border border-gray-300 rounded-md bg-gray-50" 
                                      x-text="selectedTask?.actual_hours + 'h'"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="hideTaskModal()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Documents Modal -->
    <div x-show="showDocumentsModalFlag" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="hideDocumentsModal()"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">📄 Documents & Notes</h3>
                        <button @click="hideDocumentsModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task: <span x-text="selectedTask?.name"></span></label>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Add Note</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" 
                                      rows="3" placeholder="Add a note about this task..."></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Upload Document</label>
                            <input type="file" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-md">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Existing Documents</h4>
                            <div class="text-sm text-gray-500">No documents uploaded yet.</div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="hideDocumentsModal()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Save & Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- History Modal -->
    <div x-show="showHistoryModalFlag" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="hideHistoryModal()"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">🕒 History & Log</h3>
                        <button @click="hideHistoryModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task: <span x-text="selectedTask?.name"></span></label>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Task Created</p>
                                        <p class="text-sm text-gray-600">Task was created and assigned</p>
                                    </div>
                                    <span class="text-xs text-gray-500" x-text="selectedTask?.created_at"></span>
                                </div>
                            </div>
                            
                            <div class="border-l-4 border-green-500 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Status Changed</p>
                                        <p class="text-sm text-gray-600">Status changed to <span x-text="selectedTask?.status"></span></p>
                                    </div>
                                    <span class="text-xs text-gray-500">2023-01-20</span>
                                </div>
                            </div>
                            
                            <div class="border-l-4 border-purple-500 pl-4 py-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Time Logged</p>
                                        <p class="text-sm text-gray-600"><span x-text="selectedTask?.actual_hours"></span> hours logged</p>
                                    </div>
                                    <span class="text-xs text-gray-500">2023-01-25</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="hideHistoryModal()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-purple-600 text-base font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Archive/Move Modal -->
    <div x-show="showArchiveModalFlag" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="hideArchiveModal()"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">📦 Archive or Move Task</h3>
                        <button @click="hideArchiveModal()" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task: <span x-text="selectedTask?.name"></span></label>
                        </div>
                        
                        <div class="space-y-3">
                            <button @click="confirmArchive()" 
                                    class="w-full px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                                📦 Archive Task
                            </button>
                            
                            <div class="border-t pt-3">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Or Move to Project:</label>
                                <select x-model="selectedProjectToMove" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select Project</option>
                                    <template x-for="project in getUniqueProjects()" :key="project.id">
                                        <option :value="project.id" x-text="project.name"></option>
                                    </template>
                                </select>
                                <button @click="moveTask()" 
                                        :disabled="!selectedProjectToMove"
                                        class="w-full mt-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                    🔄 Move Task
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="hideArchiveModal()" 
                            class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function tasksManagement() {
    return {
        // State
        searchQuery: '',
        selectedStatus: '',
        selectedPriority: '',
        selectedProject: '',
        sortBy: 'name',
        dateFrom: '',
        dateTo: '',
        selectedAssignee: '',
        selectedProgressRange: '',
        selectedHoursRange: '',
        selectedTasks: [],
        currentPage: 1,
        itemsPerPage: 10,
        
        // Modal states
        showTaskModalFlag: false,
        showDocumentsModalFlag: false,
        showHistoryModalFlag: false,
        showArchiveModalFlag: false,
        selectedTask: null,
        selectedProjectToMove: '',
        
        // Enhanced Task Data
        tasks: [
            {
                id: 1,
                name: 'Design System Architecture',
                description: 'Create comprehensive design system for the project',
                status: 'in_progress',
                priority: 'high',
                risk_level: 'medium',
                project_id: 1,
                project_name: 'Office Building Complex',
                assignee: 'John Smith',
                due_date: 'Mar 15, 2024',
                estimated_hours: 40,
                actual_hours: 25,
                progress_percent: 62,
                created_at: '2023-01-15',
                dependencies: ['TASK-001', 'TASK-002'],
                tags: ['design', 'architecture', 'system']
            },
            {
                id: 2,
                name: 'Database Schema Design',
                description: 'Design and implement database schema for the application',
                status: 'completed',
                priority: 'high',
                risk_level: 'low',
                project_id: 1,
                project_name: 'Office Building Complex',
                assignee: 'Sarah Wilson',
                due_date: 'Feb 28, 2024',
                estimated_hours: 32,
                actual_hours: 30,
                progress_percent: 100,
                created_at: '2023-01-10',
                dependencies: [],
                tags: ['database', 'schema', 'backend']
            },
            {
                id: 3,
                name: 'Frontend Development',
                description: 'Develop responsive frontend components',
                status: 'pending',
                priority: 'medium',
                risk_level: 'high',
                project_id: 2,
                project_name: 'Shopping Mall Development',
                assignee: 'Mike Johnson',
                due_date: 'Apr 30, 2024',
                estimated_hours: 80,
                actual_hours: 0,
                progress_percent: 0,
                created_at: '2023-02-01',
                dependencies: ['TASK-002'],
                tags: ['frontend', 'react', 'ui']
            },
            {
                id: 4,
                name: 'API Integration',
                description: 'Integrate third-party APIs and services',
                status: 'in_progress',
                priority: 'medium',
                risk_level: 'medium',
                project_id: 2,
                project_name: 'Shopping Mall Development',
                assignee: 'Alex Lee',
                due_date: 'Mar 20, 2024',
                estimated_hours: 24,
                actual_hours: 12,
                progress_percent: 50,
                created_at: '2023-02-05',
                dependencies: ['TASK-003'],
                tags: ['api', 'integration', 'backend']
            },
            {
                id: 5,
                name: 'Testing & QA',
                description: 'Comprehensive testing and quality assurance',
                status: 'pending',
                priority: 'high',
                risk_level: 'low',
                project_id: 3,
                project_name: 'Residential Complex',
                assignee: 'Emma Davis',
                due_date: 'Dec 10, 2024',
                estimated_hours: 48,
                actual_hours: 0,
                progress_percent: 0,
                created_at: '2023-03-10',
                dependencies: ['TASK-004', 'TASK-005'],
                tags: ['testing', 'qa', 'quality']
            }
        ],
        
        // Computed Properties
        get filteredTasks() {
            let filtered = this.tasks;
            
            // Search filter
            if (this.searchQuery) {
                filtered = filtered.filter(task => 
                    task.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    task.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (task.assignee && task.assignee.toLowerCase().includes(this.searchQuery.toLowerCase())) ||
                    task.project_name.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            // Status filter
            if (this.selectedStatus) {
                filtered = filtered.filter(task => task.status === this.selectedStatus);
            }
            
            // Priority filter
            if (this.selectedPriority) {
                filtered = filtered.filter(task => task.priority === this.selectedPriority);
            }
            
            // Project filter
            if (this.selectedProject) {
                filtered = filtered.filter(task => task.project_id == this.selectedProject);
            }
            
            // Assignee filter
            if (this.selectedAssignee) {
                filtered = filtered.filter(task => task.assignee === this.selectedAssignee);
            }
            
            // Date range filter
            if (this.dateFrom) {
                filtered = filtered.filter(task => new Date(task.created_at) >= new Date(this.dateFrom));
            }
            if (this.dateTo) {
                filtered = filtered.filter(task => new Date(task.created_at) <= new Date(this.dateTo));
            }
            
            // Progress range filter
            if (this.selectedProgressRange) {
                const [min, max] = this.selectedProgressRange.split('-').map(v => parseInt(v));
                filtered = filtered.filter(task => {
                    if (max === 100) return task.progress_percent >= min;
                    return task.progress_percent >= min && task.progress_percent <= max;
                });
            }
            
            // Hours range filter
            if (this.selectedHoursRange) {
                const [min, max] = this.selectedHoursRange.split('-').map(v => v === '' ? Infinity : parseInt(v));
                filtered = filtered.filter(task => {
                    if (max === Infinity) return task.estimated_hours >= min;
                    return task.estimated_hours >= min && task.estimated_hours <= max;
                });
            }
            
            // Sort
            filtered.sort((a, b) => {
                switch (this.sortBy) {
                    case 'name':
                        return a.name.localeCompare(b.name);
                    case 'due_date':
                        return new Date(a.due_date) - new Date(b.due_date);
                    case 'priority':
                        const priorityOrder = { urgent: 4, high: 3, medium: 2, low: 1 };
                        return priorityOrder[b.priority] - priorityOrder[a.priority];
                    case 'progress':
                        return b.progress_percent - a.progress_percent;
                    case 'created_at':
                        return new Date(b.created_at) - new Date(a.created_at);
                    case 'estimated_hours':
                        return b.estimated_hours - a.estimated_hours;
                    default:
                        return 0;
                }
            });
            
            return filtered;
        },
        
        get totalPages() {
            return Math.ceil(this.filteredTasks.length / this.itemsPerPage);
        },
        
        // Methods
        getInProgressTasks() {
            return this.tasks.filter(t => t.status === 'in_progress').length;
        },
        
        getCompletedTasks() {
            return this.tasks.filter(t => t.status === 'completed').length;
        },
        
        getOverdueTasks() {
            const today = new Date();
            return this.tasks.filter(t => {
                const dueDate = new Date(t.due_date);
                return dueDate < today && t.status !== 'completed';
            }).length;
        },
        
        getTotalEstimatedHours() {
            return this.tasks.reduce((sum, task) => sum + task.estimated_hours, 0);
        },
        
        getTotalActualHours() {
            return this.tasks.reduce((sum, task) => sum + task.actual_hours, 0);
        },
        
        getEfficiencyRate() {
            const totalEstimated = this.getTotalEstimatedHours();
            const totalActual = this.getTotalActualHours();
            if (totalEstimated === 0) return 0;
            return Math.round((totalActual / totalEstimated) * 100);
        },
        
        getTimeUtilization() {
            const totalEstimated = this.getTotalEstimatedHours();
            const totalActual = this.getTotalActualHours();
            if (totalEstimated === 0) return 0;
            return Math.min(Math.round((totalActual / totalEstimated) * 100), 100);
        },
        
        getAverageProgress() {
            const totalProgress = this.tasks.reduce((sum, task) => sum + task.progress_percent, 0);
            return Math.round(totalProgress / this.tasks.length);
        },
        
        getOnTrackTasks() {
            return this.tasks.filter(t => t.progress_percent >= 75 && t.status === 'in_progress').length;
        },
        
        getBehindScheduleTasks() {
            return this.tasks.filter(t => t.progress_percent < 50 && t.status === 'in_progress').length;
        },
        
        getAtRiskTasks() {
            return this.tasks.filter(t => t.risk_level === 'high').length;
        },
        
        getActiveProjectsCount() {
            const uniqueProjects = new Set(this.tasks.map(t => t.project_id));
            return uniqueProjects.size;
        },
        
        getAverageTasksPerProject() {
            const projectTaskCounts = {};
            this.tasks.forEach(task => {
                projectTaskCounts[task.project_id] = (projectTaskCounts[task.project_id] || 0) + 1;
            });
            const counts = Object.values(projectTaskCounts);
            return Math.round(counts.reduce((sum, count) => sum + count, 0) / counts.length);
        },
        
        getProjectCompletionRate() {
            const projectProgress = {};
            this.tasks.forEach(task => {
                if (!projectProgress[task.project_id]) {
                    projectProgress[task.project_id] = { total: 0, completed: 0 };
                }
                projectProgress[task.project_id].total += task.progress_percent;
                projectProgress[task.project_id].completed += task.progress_percent;
            });
            
            const rates = Object.values(projectProgress).map(p => p.total / p.total);
            return Math.round(rates.reduce((sum, rate) => sum + rate, 0) / rates.length * 100);
        },
        
        getUniqueProjects() {
            const projects = [];
            const seen = new Set();
            this.tasks.forEach(task => {
                if (!seen.has(task.project_id)) {
                    seen.add(task.project_id);
                    projects.push({ id: task.project_id, name: task.project_name });
                }
            });
            return projects;
        },
        
        getUniqueAssignees() {
            return [...new Set(this.tasks.map(t => t.assignee).filter(Boolean))];
        },
        
        getStatusClass(status) {
            const classes = {
                'pending': 'bg-yellow-100 text-yellow-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'completed': 'bg-green-100 text-green-800',
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
        filterTasks() {
            // Filtering is handled by computed property
        },
        
        sortTasks() {
            // Sorting is handled by computed property
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.selectedStatus = '';
            this.selectedPriority = '';
            this.selectedProject = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.selectedAssignee = '';
            this.selectedProgressRange = '';
            this.selectedHoursRange = '';
            this.sortBy = 'name';
        },
        
        saveFilters() {
            const filters = {
                searchQuery: this.searchQuery,
                selectedStatus: this.selectedStatus,
                selectedPriority: this.selectedPriority,
                selectedProject: this.selectedProject,
                dateFrom: this.dateFrom,
                dateTo: this.dateTo,
                selectedAssignee: this.selectedAssignee,
                selectedProgressRange: this.selectedProgressRange,
                selectedHoursRange: this.selectedHoursRange,
                sortBy: this.sortBy
            };
            localStorage.setItem('taskFilters', JSON.stringify(filters));
            alert('Filters saved successfully!');
        },
        
        // Selection Methods
        toggleTaskSelection(task) {
            const index = this.selectedTasks.indexOf(task.id);
            if (index > -1) {
                this.selectedTasks.splice(index, 1);
            } else {
                this.selectedTasks.push(task.id);
            }
        },
        
        selectAllTasks() {
            this.selectedTasks = this.filteredTasks.map(t => t.id);
        },
        
        clearSelection() {
            this.selectedTasks = [];
        },
        
        // Bulk Operations
        async bulkExport() {
            if (this.selectedTasks.length === 0) {
                alert('Please select tasks to export');
                return;
            }

            try {
                const response = await fetch('/api/tasks/bulk/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        task_ids: this.selectedTasks,
                        format: 'csv'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert(`Export completed! Download: ${result.data.filename}`);
                    // Auto download
                    window.open(result.data.download_url, '_blank');
                    this.clearSelection();
                } else {
                    alert('Export failed: ' + result.message);
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Export failed: ' + error.message);
            }
        },
        
        async bulkStatusChange() {
            if (this.selectedTasks.length === 0) {
                alert('Please select tasks to update status');
                return;
            }

            const newStatus = prompt('Enter new status (pending, in_progress, completed, cancelled):');
            if (newStatus && ['pending', 'in_progress', 'completed', 'cancelled'].includes(newStatus)) {
                try {
                    const response = await fetch('/api/tasks/bulk/status-change', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            task_ids: this.selectedTasks,
                            status: newStatus
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Update local data
                        this.tasks.forEach(task => {
                            if (this.selectedTasks.includes(task.id)) {
                                task.status = newStatus;
                            }
                        });
                        this.clearSelection();
                        alert(result.message);
                    } else {
                        alert('Status update failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Status update error:', error);
                    alert('Status update failed: ' + error.message);
                }
            }
        },
        
        async bulkAssign() {
            if (this.selectedTasks.length === 0) {
                alert('Please select tasks to assign');
                return;
            }

            const assigneeId = prompt('Enter assignee ID (user ID):');
            if (assigneeId && !isNaN(assigneeId)) {
                try {
                    const response = await fetch('/api/tasks/bulk/assign', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            task_ids: this.selectedTasks,
                            assignee_id: parseInt(assigneeId)
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Update local data
                        this.tasks.forEach(task => {
                            if (this.selectedTasks.includes(task.id)) {
                                task.assignee = `User ${assigneeId}`;
                            }
                        });
                        this.clearSelection();
                        alert(result.message);
                    } else {
                        alert('Assignment failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Assignment error:', error);
                    alert('Assignment failed: ' + error.message);
                }
            }
        },
        
        async bulkArchive() {
            if (this.selectedTasks.length === 0) {
                alert('Please select tasks to archive');
                return;
            }

            if (confirm(`Archive ${this.selectedTasks.length} tasks?`)) {
                try {
                    const response = await fetch('/api/tasks/bulk/archive', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            task_ids: this.selectedTasks
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Update local data
                        this.tasks.forEach(task => {
                            if (this.selectedTasks.includes(task.id)) {
                                task.status = 'archived';
                            }
                        });
                        this.clearSelection();
                        alert(result.message);
                    } else {
                        alert('Archive failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Archive error:', error);
                    alert('Archive failed: ' + error.message);
                }
            }
        },
        
        async bulkDelete() {
            if (this.selectedTasks.length === 0) {
                alert('Please select tasks to delete');
                return;
            }

            if (confirm(`Delete ${this.selectedTasks.length} tasks? This action cannot be undone.`)) {
                try {
                    const response = await fetch('/api/tasks/bulk/delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            task_ids: this.selectedTasks
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Remove from local data
                        this.tasks = this.tasks.filter(t => !this.selectedTasks.includes(t.id));
                        this.clearSelection();
                        alert(result.message);
                    } else {
                        alert('Delete failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    alert('Delete failed: ' + error.message);
                }
            }
        },
        
        // Task Actions
        viewTask(task) {
            console.log('Viewing task:', task);
            this.showTaskModal(task);
        },
        
        createTask() {
            console.log('Creating new task');
            alert('Opening task creation form...');
        },
        
        editTask(task) {
            console.log('Editing task:', task);
            window.location.href = `/tasks/${task.id}/edit`;
        },
        
        duplicateTask(task) {
            const newTask = {
                ...task,
                id: Date.now(),
                name: task.name + ' (Copy)',
                status: 'pending',
                progress_percent: 0,
                actual_hours: 0
            };
            this.tasks.push(newTask);
            alert(`Task duplicated: ${newTask.name}`);
        },
        
        async showTaskDocuments(task) {
            try {
                const response = await fetch(`/api/documents/task/${task.id}`);
                const result = await response.json();
                
                if (result.success) {
                    console.log('Task documents:', result.data.documents);
                    this.selectedTask = task;
                    this.selectedTask.documents = result.data.documents;
                    this.showDocumentsModal(task);
                } else {
                    alert('Failed to load documents: ' + result.message);
                }
            } catch (error) {
                console.error('Documents error:', error);
                alert('Failed to load documents: ' + error.message);
            }
        },
        
        timeTrack(task) {
            const hours = prompt(`Enter hours to log for task: ${task.name}`);
            if (hours && !isNaN(hours)) {
                task.actual_hours += parseFloat(hours);
                alert(`Logged ${hours} hours for task: ${task.name}`);
            }
        },
        
        async showTaskHistory(task) {
            try {
                const response = await fetch(`/tasks/${task.id}/history`);
                const result = await response.json();
                
                if (result.success) {
                    console.log('Task history:', result.data.history);
                    this.selectedTask = task;
                    this.selectedTask.history = result.data.history;
                    this.showHistoryModal(task);
                } else {
                    alert('Failed to load history: ' + result.message);
                }
            } catch (error) {
                console.error('History error:', error);
                alert('Failed to load history: ' + error.message);
            }
        },
        
        archiveTask(task) {
            this.selectedTask = task;
            this.showArchiveModalFlag = true;
        },
        
        confirmArchive() {
            if (this.selectedTask) {
                this.selectedTask.status = 'archived';
                this.hideArchiveModal();
                alert('Task archived successfully!');
            }
        },
        
        moveTask() {
            if (this.selectedTask && this.selectedProjectToMove) {
                this.selectedTask.project_id = this.selectedProjectToMove;
                this.selectedTask.project_name = this.getUniqueProjects().find(p => p.id == this.selectedProjectToMove)?.name || 'Unknown Project';
                this.hideArchiveModal();
                alert('Task moved successfully!');
            }
        },
        
        async duplicateTask(task) {
            if (confirm(`Duplicate task: ${task.name}?`)) {
                try {
                    const response = await fetch('/api/tasks/bulk/duplicate', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            task_id: task.id
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        const newTask = {
                            ...task,
                            id: result.data.new_task_id,
                            name: result.data.new_task_name,
                            status: 'pending',
                            progress_percent: 0,
                            actual_hours: 0,
                            created_at: new Date().toISOString().split('T')[0]
                        };
                        this.tasks.push(newTask);
                        alert(result.message);
                    } else {
                        alert('Duplicate failed: ' + result.message);
                    }
                } catch (error) {
                    console.error('Duplicate error:', error);
                    alert('Duplicate failed: ' + error.message);
                }
            }
        },
        
        deleteTask(task) {
            if (confirm(`Delete task: ${task.name}? This action cannot be undone.`)) {
                this.tasks = this.tasks.filter(t => t.id !== task.id);
                this.showNotification('Task deleted successfully!', 'success');
            }
        },
        
        async exportTasks() {
            try {
                const response = await fetch('/api/tasks/bulk/export', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        task_ids: this.tasks.map(t => t.id),
                        format: 'csv'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert(`Export completed! Download: ${result.data.filename}`);
                    // Auto download
                    window.open(result.data.download_url, '_blank');
                } else {
                    alert('Export failed: ' + result.message);
                }
            } catch (error) {
                console.error('Export error:', error);
                alert('Export failed: ' + error.message);
            }
        },
        
        async viewAnalytics() {
            try {
                const response = await fetch('/api/analytics/tasks');
                const result = await response.json();
                
                if (result.success) {
                    // Show analytics in a modal or redirect to analytics page
                    console.log('Analytics data:', result.data);
                    alert(`Analytics loaded! Total tasks: ${result.data.summary.total_tasks}, Completion rate: ${result.data.summary.completion_rate}%`);
                } else {
                    alert('Failed to load analytics: ' + result.message);
                }
            } catch (error) {
                console.error('Analytics error:', error);
                alert('Failed to load analytics: ' + error.message);
            }
        },
        
        // Modal Methods
        showTaskModal(task) {
            this.selectedTask = task;
            this.showTaskModalFlag = true;
        },
        
        hideTaskModal() {
            this.showTaskModalFlag = false;
            this.selectedTask = null;
        },
        
        showDocumentsModal(task) {
            this.selectedTask = task;
            this.showDocumentsModalFlag = true;
        },
        
        hideDocumentsModal() {
            this.showDocumentsModalFlag = false;
            this.selectedTask = null;
        },
        
        showHistoryModal(task) {
            this.selectedTask = task;
            this.showHistoryModalFlag = true;
        },
        
        hideHistoryModal() {
            this.showHistoryModalFlag = false;
            this.selectedTask = null;
        },
        
        hideArchiveModal() {
            this.showArchiveModalFlag = false;
            this.selectedTask = null;
            this.selectedProjectToMove = '';
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
            const savedFilters = localStorage.getItem('taskFilters');
            if (savedFilters) {
                const filters = JSON.parse(savedFilters);
                Object.assign(this, filters);
            }
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tasks/index.blade.php ENDPATH**/ ?>