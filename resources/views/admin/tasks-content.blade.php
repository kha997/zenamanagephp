<!-- Admin Tasks Management Content - System-wide Task Monitoring & Investigation -->
<div x-data="adminTasks()" x-init="init()">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading tasks data...</span>
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
        
        <!-- 1. System-wide Task Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- System Total Tasks -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-blue-500" 
                 @click="filterTasks('all')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">System Total Tasks</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.total || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-blue-600 font-medium" x-text="`${taskStats.active || 0} active`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500" x-text="`${taskStats.completed || 0} completed`"></span>
                    </div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-yellow-500" 
                 @click="filterTasks('pending')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Pending</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="taskStats.pending || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-yellow-600 font-medium" x-text="`${taskStats.overdue || 0} overdue`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500">Needs attention</span>
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
                        <span class="text-xs text-green-600 font-medium" x-text="`${taskStats.dueToday || 0} due today`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500">Active work</span>
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
                        <span class="text-xs text-purple-600 font-medium" x-text="`${taskStats.completedToday || 0} today`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500">This week</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. System-wide Task Management Tools -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">System-wide Task Management</h3>
                <div class="flex items-center space-x-3">
                    <button @click="showCreateModal = true" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Create System Task
                    </button>
                    <button @click="refreshTasks()" 
                            class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <!-- System-wide Filters -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h4 class="text-md font-semibold text-gray-800 mb-3">System-wide Task Filters</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Tenant Filter (Required) -->
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-2">
                            Tenant <span class="text-red-500">*</span>
                        </label>
                        <select x-model="tenantFilter" @change="filterTasks()" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Tenant (Required)</option>
                            <option value="all">All Tenants</option>
                            <option value="tenant-1">Tenant A</option>
                            <option value="tenant-2">Tenant B</option>
                            <option value="tenant-3">Tenant C</option>
                        </select>
                    </div>
                    
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
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    
                    <!-- Priority Filter -->
                    <div class="flex flex-col">
                        <label class="text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select x-model="priorityFilter" @change="filterTasks()" 
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Priorities</option>
                            <option value="critical">Critical</option>
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

        <!-- 3. Tasks List -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Tasks List</h3>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500" x-text="`${filteredTasks.length} tasks`"></span>
                    <button @click="toggleViewMode()" 
                            class="p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                        <i :class="viewMode === 'list' ? 'fas fa-th-large' : 'fas fa-list'" class="text-lg"></i>
                    </button>
                </div>
            </div>
            
            <!-- Tasks Table/Grid -->
            <div x-show="viewMode === 'list'" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignee</th>
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
                                            <div class="h-10 w-10 rounded-full flex items-center justify-center"
                                                 :class="getTaskIconBg(task.priority)">
                                                <i :class="getTaskIcon(task.type)" 
                                                   :class="getTaskIconColor(task.priority)"
                                                   class="text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="task.title"></div>
                                            <div class="text-sm text-gray-500" x-text="task.description"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="task.project"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-bold">
                                            <span x-text="task.assignee.charAt(0)"></span>
                                        </div>
                                        <div class="ml-2 text-sm text-gray-900" x-text="task.assignee"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                          :class="getPriorityBadgeClass(task.priority)"
                                          x-text="task.priority"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                          :class="getStatusBadgeClass(task.status)"
                                          x-text="task.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.dueDate"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="editTask(task)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="viewTask(task)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="moveTask(task, null, 'completed')" 
                                                class="text-green-600 hover:text-green-900" 
                                                title="Mark as Completed">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button @click="archiveTask(task)" 
                                                class="text-orange-600 hover:text-orange-900" 
                                                title="Archive Task">
                                            <i class="fas fa-archive"></i>
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
                    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-8 w-8 rounded-full flex items-center justify-center mr-3"
                                     :class="getTaskIconBg(task.priority)">
                                    <i :class="getTaskIcon(task.type)" 
                                       :class="getTaskIconColor(task.priority)"
                                       class="text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900" x-text="task.title"></h4>
                                    <p class="text-xs text-gray-500" x-text="task.project"></p>
                                </div>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                  :class="getPriorityBadgeClass(task.priority)"
                                  x-text="task.priority"></span>
                        </div>
                        
                        <p class="text-sm text-gray-600 mb-3" x-text="task.description"></p>
                        
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                                <div class="h-6 w-6 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-bold mr-2">
                                    <span x-text="task.assignee.charAt(0)"></span>
                                </div>
                                <span class="text-xs text-gray-700" x-text="task.assignee"></span>
                            </div>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full"
                                  :class="getStatusBadgeClass(task.status)"
                                  x-text="task.status"></span>
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
                                <button @click="moveTask(task, null, 'completed')" 
                                        class="p-1 text-green-600 hover:text-green-900" 
                                        title="Mark as Completed">
                                    <i class="fas fa-check text-xs"></i>
                                </button>
                                <button @click="archiveTask(task)" 
                                        class="p-1 text-orange-600 hover:text-orange-900" 
                                        title="Archive Task">
                                    <i class="fas fa-archive text-xs"></i>
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
            <div x-show="filteredTasks.length === 0" class="text-center py-12">
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
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Recent Task Activity</h3>
                <button @click="viewAllActivity()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All
                </button>
            </div>
            
            <div class="space-y-3">
                <template x-for="activity in recentActivity" :key="activity.id">
                    <div class="flex items-start space-x-3 p-3 border-b border-gray-100 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="getActivityIconBg(activity.type)">
                                <i :class="getActivityIcon(activity.type)" 
                                   :class="getActivityIconColor(activity.type)"
                                   class="text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900" x-text="activity.description"></p>
                            <p class="text-xs text-gray-500" x-text="activity.timestamp"></p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function adminTasks() {
    return {
        loading: true,
        error: null,
        
        // Task Data
        tasks: [],
        filteredTasks: [],
        taskStats: {
            total: 0,
            active: 0,
            pending: 0,
            inProgress: 0,
            completed: 0,
            overdue: 0,
            dueToday: 0,
            completedToday: 0
        },
        
        // Filters
        statusFilter: 'all',
        priorityFilter: 'all',
        projectFilter: 'all',
        tenantFilter: '', // Required for Admin Tasks
        
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
                        title: 'Design System Architecture',
                        description: 'Create comprehensive system architecture documentation',
                        project: 'ZenaManage Platform',
                        assignee: 'John Doe',
                        priority: 'high',
                        status: 'in_progress',
                        type: 'design',
                        dueDate: '2024-01-15',
                        createdAt: '2024-01-10',
                        tenant: 'tenant-1'
                    },
                    {
                        id: 2,
                        title: 'Implement User Authentication',
                        description: 'Set up JWT-based authentication system',
                        project: 'ZenaManage Platform',
                        assignee: 'Jane Smith',
                        priority: 'high',
                        status: 'completed',
                        type: 'development',
                        dueDate: '2024-01-12',
                        createdAt: '2024-01-08',
                        tenant: 'tenant-1'
                    },
                    {
                        id: 3,
                        title: 'Database Migration Script',
                        description: 'Create migration scripts for production deployment',
                        project: 'ZenaManage Platform',
                        assignee: 'Mike Johnson',
                        priority: 'medium',
                        status: 'pending',
                        type: 'database',
                        dueDate: '2024-01-20',
                        createdAt: '2024-01-11',
                        tenant: 'tenant-2'
                    },
                    {
                        id: 4,
                        title: 'API Documentation',
                        description: 'Generate comprehensive API documentation',
                        project: 'ZenaManage Platform',
                        assignee: 'Sarah Wilson',
                        priority: 'medium',
                        status: 'in_progress',
                        type: 'documentation',
                        dueDate: '2024-01-18',
                        createdAt: '2024-01-09',
                        tenant: 'tenant-2'
                    },
                    {
                        id: 5,
                        title: 'Security Audit',
                        description: 'Conduct comprehensive security audit',
                        project: 'ZenaManage Platform',
                        assignee: 'David Brown',
                        priority: 'critical',
                        status: 'pending',
                        type: 'security',
                        dueDate: '2024-01-14',
                        createdAt: '2024-01-07',
                        tenant: 'tenant-3'
                    }
                ];
                
                this.calculateStats();
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading tasks:', error);
                this.error = error.message;
                this.loading = false;
            }
        },
        
        async loadProjects() {
            this.projects = [
                { id: 1, name: 'ZenaManage Platform' },
                { id: 2, name: 'Mobile App Development' },
                { id: 3, name: 'API Integration' },
                { id: 4, name: 'UI/UX Redesign' }
            ];
        },
        
        async loadRecentActivity() {
            this.recentActivity = [
                {
                    id: 1,
                    type: 'completed',
                    description: 'Task "Implement User Authentication" completed by Jane Smith',
                    timestamp: '2 hours ago'
                },
                {
                    id: 2,
                    type: 'created',
                    description: 'New task "Database Migration Script" created',
                    timestamp: '4 hours ago'
                },
                {
                    id: 3,
                    type: 'assigned',
                    description: 'Task "Security Audit" assigned to David Brown',
                    timestamp: '6 hours ago'
                },
                {
                    id: 4,
                    type: 'updated',
                    description: 'Task "API Documentation" status updated to In Progress',
                    timestamp: '8 hours ago'
                },
                {
                    id: 5,
                    type: 'overdue',
                    description: 'Task "Design System Architecture" is overdue',
                    timestamp: '1 day ago'
                }
            ];
        },
        
        calculateStats() {
            this.taskStats = {
                total: this.tasks.length,
                active: this.tasks.filter(t => t.status === 'in_progress').length,
                pending: this.tasks.filter(t => t.status === 'pending').length,
                inProgress: this.tasks.filter(t => t.status === 'in_progress').length,
                completed: this.tasks.filter(t => t.status === 'completed').length,
                overdue: this.tasks.filter(t => this.isOverdue(t.dueDate)).length,
                dueToday: this.tasks.filter(t => this.isDueToday(t.dueDate)).length,
                completedToday: this.tasks.filter(t => t.status === 'completed' && this.isToday(t.createdAt)).length
            };
        },
        
        filterTasks(filter = null) {
            if (filter) {
                this.statusFilter = filter;
            }
            
            // Validate tenant filter is selected
            if (!this.tenantFilter) {
                this.filteredTasks = [];
                return;
            }
            
            this.filteredTasks = this.tasks.filter(task => {
                const statusMatch = this.statusFilter === 'all' || task.status === this.statusFilter;
                const priorityMatch = this.priorityFilter === 'all' || task.priority === this.priorityFilter;
                const projectMatch = this.projectFilter === 'all' || task.project === this.projects.find(p => p.id == this.projectFilter)?.name;
                const tenantMatch = this.tenantFilter === 'all' || task.tenant === this.tenantFilter;
                
                return statusMatch && priorityMatch && projectMatch && tenantMatch;
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
                // For now, just log the action
            } catch (error) {
                console.error('Error editing task:', error);
            }
        },
        
        async viewTask(task) {
            try {
                console.log('View task:', task);
                // TODO: Open view modal or navigate to detail page
                // For now, just log the action
            } catch (error) {
                console.error('Error viewing task:', error);
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
                        // Remove task from local array
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
        
        async moveTask(task, newProjectId = null, newStatus = null) {
            try {
                const payload = {};
                if (newProjectId) payload.project_id = newProjectId;
                if (newStatus) payload.status = newStatus;
                
                const response = await fetch(`/api/v1/app/tasks/${task.id}/move`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });
                
                if (response.ok) {
                    const result = await response.json();
                    // Update task in local array
                    const taskIndex = this.tasks.findIndex(t => t.id === task.id);
                    if (taskIndex !== -1) {
                        this.tasks[taskIndex] = { ...this.tasks[taskIndex], ...result.data };
                    }
                    this.calculateStats();
                    this.filterTasks();
                    console.log('Task moved successfully');
                } else {
                    const error = await response.json();
                    console.error('Failed to move task:', error.message);
                    alert('Failed to move task: ' + error.message);
                }
            } catch (error) {
                console.error('Error moving task:', error);
                alert('Error moving task. Please try again.');
            }
        },
        
        async archiveTask(task, reason = null) {
            if (confirm(`Are you sure you want to archive "${task.title}"?`)) {
                try {
                    const payload = {};
                    if (reason) payload.reason = reason;
                    
                    const response = await fetch(`/api/v1/app/tasks/${task.id}/archive`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(payload)
                    });
                    
                    if (response.ok) {
                        const result = await response.json();
                        // Update task in local array
                        const taskIndex = this.tasks.findIndex(t => t.id === task.id);
                        if (taskIndex !== -1) {
                            this.tasks[taskIndex] = { ...this.tasks[taskIndex], ...result.data };
                        }
                        this.calculateStats();
                        this.filterTasks();
                        console.log('Task archived successfully');
                    } else {
                        const error = await response.json();
                        console.error('Failed to archive task:', error.message);
                        alert('Failed to archive task: ' + error.message);
                    }
                } catch (error) {
                    console.error('Error archiving task:', error);
                    alert('Error archiving task. Please try again.');
                }
            }
        },
        
        viewAllActivity() {
            console.log('View all activity');
            // Implement view all activity functionality
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
                'database': 'fas fa-database',
                'documentation': 'fas fa-file-alt',
                'security': 'fas fa-shield-alt',
                'testing': 'fas fa-bug',
                'deployment': 'fas fa-rocket'
            };
            return icons[type] || 'fas fa-tasks';
        },
        
        getTaskIconBg(priority) {
            const colors = {
                'high': 'bg-red-100',
                'medium': 'bg-yellow-100',
                'low': 'bg-green-100'
            };
            return colors[priority] || 'bg-gray-100';
        },
        
        getTaskIconColor(priority) {
            const colors = {
                'high': 'text-red-600',
                'medium': 'text-yellow-600',
                'low': 'text-green-600'
            };
            return colors[priority] || 'text-gray-600';
        },
        
        getPriorityBadgeClass(priority) {
            const classes = {
                'high': 'bg-red-100 text-red-800',
                'medium': 'bg-yellow-100 text-yellow-800',
                'low': 'bg-green-100 text-green-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
        },
        
        getStatusBadgeClass(status) {
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
                'assigned': 'fas fa-user-plus',
                'updated': 'fas fa-edit',
                'overdue': 'fas fa-exclamation-triangle'
            };
            return icons[type] || 'fas fa-info-circle';
        },
        
        getActivityIconBg(type) {
            const colors = {
                'completed': 'bg-green-100',
                'created': 'bg-blue-100',
                'assigned': 'bg-purple-100',
                'updated': 'bg-yellow-100',
                'overdue': 'bg-red-100'
            };
            return colors[type] || 'bg-gray-100';
        },
        
        getActivityIconColor(type) {
            const colors = {
                'completed': 'text-green-600',
                'created': 'text-blue-600',
                'assigned': 'text-purple-600',
                'updated': 'text-yellow-600',
                'overdue': 'text-red-600'
            };
            return colors[type] || 'text-gray-600';
        }
    }
}
</script>
