<!-- App Tasks Management Content - Tenant Internal Task Operations -->
<div x-data="appTasks()" x-init="init()">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading your tasks...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading tasks</p>
                <p x-text="error"></p>
                <button @click="init()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- 1. My Task Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- My Tasks -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-blue-500" 
                 @click="filterTasks('all')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">My Tasks</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.total || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-blue-600 font-medium" x-text="`${taskStats.active || 0} active`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500" x-text="`${taskStats.completed || 0} completed`"></span>
                            </div>
                        </div>
                    </div>
                    
            <!-- Due Today -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-yellow-500" 
                 @click="filterTasks('due_today')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Due Today</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.dueToday || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-yellow-600 font-medium" x-text="`${taskStats.urgent || 0} urgent`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500" x-text="`${taskStats.overdue || 0} overdue`"></span>
                    </div>
                </div>
            </div>

            <!-- In Progress -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-green-500" 
                 @click="filterTasks('in_progress')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">In Progress</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.inProgress || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-600 font-medium" x-text="`${taskStats.completedToday || 0} completed today`"></span>
                    </div>
    </div>
            </div>

            <!-- Completed -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-purple-500" 
                 @click="filterTasks('completed')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Completed</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.completed || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-purple-600 font-medium" x-text="`${Math.round((taskStats.completed || 0) / (taskStats.total || 1) * 100)}% done`"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. My Task Management Tools -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">My Task Management</h3>
                <div class="flex items-center space-x-3">
                    <button @click="showCreateModal = true" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create Task
                    </button>
                    <button @click="refreshTasks()" 
                            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <!-- My Task Filters -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h4 class="text-md font-semibold text-gray-800 mb-3">My Task Filters</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Status Filter -->
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select x-model="statusFilter" @change="filterTasks()" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        </div>
                    
                    <!-- Priority Filter -->
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select x-model="priorityFilter" @change="filterTasks()" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Priorities</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                    
                    <!-- Project Filter -->
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-2">Project</label>
                        <select x-model="projectFilter" @change="filterTasks()" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Projects</option>
                            <template x-for="project in projects" :key="project.id">
                                <option :value="project.id" x-text="project.name"></option>
                </template>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. My Tasks List -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">My Tasks</h3>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700">View:</label>
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <button @click="viewMode = 'list'" 
                                :class="viewMode === 'list' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-list mr-1"></i>List
                        </button>
                        <button @click="viewMode = 'grid'" 
                                :class="viewMode === 'grid' ? 'bg-white shadow-sm' : ''"
                                class="px-3 py-1 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-th mr-1"></i>Grid
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tasks List View -->
            <div x-show="viewMode === 'list'" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="task in filteredTasks" :key="task.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <i :class="getTaskIcon(task.type)" class="text-blue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="task.title"></div>
                                            <div class="text-sm text-gray-500" x-text="task.description"></div>
                        </div>
                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.project"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getPriorityClass(task.priority)" 
                                          class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                          x-text="task.priority"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusClass(task.status)" 
                                          class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" 
                                          x-text="task.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.dueDate"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="editTask(task)" 
                                                class="text-blue-600 hover:text-blue-900" 
                                                title="Edit Task">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="viewTask(task)" 
                                                class="text-green-600 hover:text-green-900" 
                                                title="View Task">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="completeTask(task)" 
                                                class="text-green-600 hover:text-green-900" 
                                                title="Mark Complete">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button @click="deleteTask(task)" 
                                                class="text-red-600 hover:text-red-900" 
                                                title="Delete Task">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                </template>
                    </tbody>
                </table>
            </div>

            <!-- Tasks Grid View -->
            <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <template x-for="task in filteredTasks" :key="task.id">
                    <div class="bg-white border rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                    <i :class="getTaskIcon(task.type)" class="text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-medium text-gray-900" x-text="task.title"></h4>
                                    <p class="text-xs text-gray-500" x-text="task.project"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-1">
                                <span :class="getPriorityClass(task.priority)" 
                                      class="px-2 py-1 text-xs font-semibold rounded-full" 
                                      x-text="task.priority"></span>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-3" x-text="task.description"></p>
                        
                        <div class="flex items-center justify-between mb-3">
                            <span :class="getStatusClass(task.status)" 
                                  class="px-2 py-1 text-xs font-semibold rounded-full" 
                                  x-text="task.status"></span>
                            <span class="text-xs text-gray-500" x-text="`Due: ${task.dueDate}`"></span>
        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500" x-text="`Due: ${task.dueDate}`"></span>
                            <div class="flex items-center space-x-1">
                                <button @click="editTask(task)" 
                                        class="p-1 text-blue-600 hover:text-blue-900" 
                                        title="Edit Task">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button @click="viewTask(task)" 
                                        class="p-1 text-green-600 hover:text-green-900" 
                                        title="View Task">
                                    <i class="fas fa-eye text-xs"></i>
                                </button>
                                <button @click="completeTask(task)" 
                                        class="p-1 text-green-600 hover:text-green-900" 
                                        title="Mark Complete">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                                <button @click="deleteTask(task)" 
                                        class="p-1 text-red-600 hover:text-red-900" 
                                        title="Delete Task">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Empty State -->
            <div x-show="filteredTasks.length === 0" class="text-center py-8">
                <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your filters or create a new task.</p>
                <button @click="showCreateModal = true" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Create Task
                </button>
            </div>
        </div>

        <!-- 4. Recent Activity -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                <template x-for="activity in recentActivity" :key="activity.id">
                    <div class="flex items-start space-x-3">
                        <div :class="getActivityIconClass(activity.type)" 
                             class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center">
                            <i :class="getActivityIcon(activity.type)" class="text-sm"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900" x-text="activity.description"></p>
                            <p class="text-xs text-gray-500" x-text="activity.timestamp"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function appTasks() {
    return {
        loading: true,
        error: null,
        tasks: [],
        filteredTasks: [],
        
        // Task Statistics
        taskStats: {
            total: 0,
            active: 0,
            completed: 0,
            pending: 0,
            inProgress: 0,
            overdue: 0,
            dueToday: 0,
            completedToday: 0
        },
        
        // Filters
        statusFilter: 'all',
        priorityFilter: 'all',
        projectFilter: 'all',
        
        // View Mode
        viewMode: 'list', // 'list' or 'grid'
        
        // Projects
        projects: [],
        
        // Recent Activity
        recentActivity: [],
        
        // Modals
        showCreateModal: false,

        async init() {
            await this.loadTasksData();
            await this.loadProjects();
            await this.loadRecentActivity();
            this.filterTasks();
        },
        
        async loadTasksData() {
            try {
                this.loading = true;
                this.error = null;
                
                // Mock data for demonstration
                await new Promise(resolve => setTimeout(resolve, 500));
                
                this.tasks = [
                    {
                        id: 1,
                        title: 'Complete User Interface Design',
                        description: 'Design the main dashboard interface for the mobile app',
                        project: 'Mobile App Development',
                        assignee: 'John Doe',
                        priority: 'high',
                        status: 'in_progress',
                        type: 'design',
                        dueDate: '2024-01-15',
                        createdAt: '2024-01-10'
                    },
                    {
                        id: 2,
                        title: 'Write API Documentation',
                        description: 'Document all REST API endpoints for the project',
                        project: 'API Integration',
                        assignee: 'Jane Smith',
                        priority: 'medium',
                        status: 'pending',
                        type: 'documentation',
                        dueDate: '2024-01-18',
                        createdAt: '2024-01-12'
                    },
                    {
                        id: 3,
                        title: 'Test Payment Integration',
                        description: 'Test the payment gateway integration',
                        project: 'Mobile App Development',
                        assignee: 'Mike Johnson',
                        priority: 'high',
                        status: 'completed',
                        type: 'testing',
                        dueDate: '2024-01-14',
                        createdAt: '2024-01-08'
                    }
                ];
                
                this.calculateStats();
                this.loading = false;
                
            } catch (error) {
                this.error = error.message;
                this.loading = false;
            }
        },

        async loadProjects() {
            this.projects = [
                { id: 1, name: 'Mobile App Development' },
                { id: 2, name: 'API Integration' },
                { id: 3, name: 'UI/UX Redesign' }
            ];
        },
        
        async loadRecentActivity() {
            this.recentActivity = [
                {
                    id: 1,
                    type: 'completed',
                    description: 'Task "Test Payment Integration" completed',
                    timestamp: '2 hours ago'
                },
                {
                    id: 2,
                    type: 'created',
                    description: 'New task "Write API Documentation" created',
                    timestamp: '4 hours ago'
                },
                {
                    id: 3,
                    type: 'updated',
                    description: 'Task "Complete User Interface Design" status updated to In Progress',
                    timestamp: '6 hours ago'
                }
            ];
        },
        
        calculateStats() {
            this.taskStats = {
                total: this.tasks.length,
                active: this.tasks.filter(t => t.status !== 'completed' && t.status !== 'cancelled').length,
                completed: this.tasks.filter(t => t.status === 'completed').length,
                pending: this.tasks.filter(t => t.status === 'pending').length,
                inProgress: this.tasks.filter(t => t.status === 'in_progress').length,
                overdue: this.tasks.filter(t => t.status !== 'completed' && this.isOverdue(t.dueDate)).length,
                dueToday: this.tasks.filter(t => this.isDueToday(t.dueDate)).length,
                completedToday: this.tasks.filter(t => t.status === 'completed' && this.isToday(t.createdAt)).length
            };
        },
        
        filterTasks(filter = null) {
            if (filter) {
                this.statusFilter = filter;
            }
            
            this.filteredTasks = this.tasks.filter(task => {
                const statusMatch = this.statusFilter === 'all' || task.status === this.statusFilter;
                const priorityMatch = this.priorityFilter === 'all' || task.priority === this.priorityFilter;
                const projectMatch = this.projectFilter === 'all' || task.project === this.projects.find(p => p.id == this.projectFilter)?.name;
                
                return statusMatch && priorityMatch && projectMatch;
            });
        },
        
        toggleViewMode() {
            this.viewMode = this.viewMode === 'list' ? 'grid' : 'list';
        },
        
        refreshTasks() {
            this.loadTasksData();
        },
        
        async editTask(task) {
            try {
                console.log('Edit task:', task);
                // TODO: Open edit modal or navigate to edit page
            } catch (error) {
                console.error('Error editing task:', error);
            }
        },
        
        async viewTask(task) {
            try {
                console.log('View task:', task);
                // TODO: Open view modal or navigate to detail page
            } catch (error) {
                console.error('Error viewing task:', error);
            }
        },
        
        async completeTask(task) {
            try {
                const response = await fetch(`/api/v1/app/tasks/${task.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: 'completed' })
                });
                
                if (response.ok) {
                    const result = await response.json();
                    const taskIndex = this.tasks.findIndex(t => t.id === task.id);
                    if (taskIndex !== -1) {
                        this.tasks[taskIndex] = { ...this.tasks[taskIndex], ...result.data };
                    }
                    this.calculateStats();
                    this.filterTasks();
                    console.log('Task completed successfully');
                } else {
                    const error = await response.json();
                    console.error('Failed to complete task:', error.message);
                    alert('Failed to complete task: ' + error.message);
                }
            } catch (error) {
                console.error('Error completing task:', error);
                alert('Error completing task. Please try again.');
            }
        },
        
        async deleteTask(task) {
            if (confirm(`Are you sure you want to delete "${task.title}"?`)) {
                try {
                    const response = await fetch(`/api/v1/app/tasks/${task.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    
                    if (response.ok) {
                        this.tasks = this.tasks.filter(t => t.id !== task.id);
                        this.calculateStats();
                        this.filterTasks();
                        console.log('Task deleted successfully');
                    } else {
                        const error = await response.json();
                        console.error('Failed to delete task:', error.message);
                        alert('Failed to delete task: ' + error.message);
                    }
                } catch (error) {
                    console.error('Error deleting task:', error);
                    alert('Error deleting task. Please try again.');
                }
            }
        },
        
        // Helper methods
        isOverdue(dueDate) {
            return new Date(dueDate) < new Date();
        },
        
        isDueToday(dueDate) {
            const today = new Date().toISOString().split('T')[0];
            return dueDate === today;
        },
        
        isToday(date) {
            const today = new Date().toISOString().split('T')[0];
            return date === today;
        },
        
        getTaskIcon(type) {
            const icons = {
                'design': 'fas fa-palette',
                'development': 'fas fa-code',
                'testing': 'fas fa-bug',
                'documentation': 'fas fa-file-alt',
                'database': 'fas fa-database',
                'security': 'fas fa-shield-alt'
            };
            return icons[type] || 'fas fa-tasks';
        },
        
        getPriorityClass(priority) {
            const classes = {
                'high': 'bg-red-100 text-red-800',
                'medium': 'bg-yellow-100 text-yellow-800',
                'low': 'bg-green-100 text-green-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
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
        
        getActivityIcon(type) {
            const icons = {
                'completed': 'fas fa-check-circle',
                'created': 'fas fa-plus-circle',
                'updated': 'fas fa-edit',
                'assigned': 'fas fa-user-plus'
            };
            return icons[type] || 'fas fa-circle';
        },
        
        getActivityIconClass(type) {
            const classes = {
                'completed': 'bg-green-100 text-green-600',
                'created': 'bg-blue-100 text-blue-600',
                'updated': 'bg-yellow-100 text-yellow-600',
                'assigned': 'bg-purple-100 text-purple-600'
            };
            return classes[type] || 'bg-gray-100 text-gray-600';
        }
    }
}
</script>