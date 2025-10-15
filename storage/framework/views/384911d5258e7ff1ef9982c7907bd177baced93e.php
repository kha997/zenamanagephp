
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="taskManagement()">
    <!-- Header -->
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">ZenaManage</h1>
                    <span class="ml-2 text-sm text-gray-500">Acme Corporation</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        Welcome back, <span class="font-medium text-gray-900">John Doe</span>
                    </div>
                    <button 
                        @click="refreshTasks()"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors"
                        :disabled="refreshing"
                    >
                        <i class="fas fa-sync-alt" :class="{ 'animate-spin': refreshing }"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-2">
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Dashboard
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Projects
                </a>
                <a href="#" class="text-blue-600 border-b-2 border-blue-600 px-1 py-2 text-sm font-medium">
                    Tasks
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Team
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Documents
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
                    Calendar
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Tasks</h1>
                <p class="text-lg text-gray-600 mt-2">
                    Manage and track your team's tasks
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <button 
                    @click="exportTasks()"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors"
                >
                    <i class="fas fa-download mr-2"></i>
                    Export
                </button>
                <button 
                    @click="createTask()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors"
                >
                    <i class="fas fa-plus mr-2"></i>
                    New Task
                </button>
            </div>
        </div>

        <!-- Task Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600">Total Tasks</p>
                        <p class="text-2xl font-bold text-blue-900" x-text="stats.totalTasks">47</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-tasks text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600">Completed</p>
                        <p class="text-2xl font-bold text-green-900" x-text="stats.completedTasks">23</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-yellow-600">In Progress</p>
                        <p class="text-2xl font-bold text-yellow-900" x-text="stats.inProgressTasks">18</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-600">Overdue</p>
                        <p class="text-2xl font-bold text-red-900" x-text="stats.overdueTasks">6</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="text" 
                            x-model="searchQuery"
                            @input.debounce.300ms="searchTasks()"
                            placeholder="Search tasks by title, description, or assignee..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="flex gap-3">
                    <select 
                        x-model="statusFilter"
                        @change="filterTasks()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Status</option>
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="review">Review</option>
                        <option value="completed">Completed</option>
                    </select>
                    
                    <select 
                        x-model="priorityFilter"
                        @change="filterTasks()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Priorities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    
                    <select 
                        x-model="assigneeFilter"
                        @change="filterTasks()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Assignees</option>
                        <option value="john">John Doe</option>
                        <option value="jane">Jane Smith</option>
                        <option value="bob">Bob Johnson</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Tasks Table -->
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <!-- Table Header -->
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Tasks <span class="text-gray-500">(<span x-text="filteredTasks.length"></span>)</span>
                    </h2>
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="selectAll()"
                            class="text-sm text-blue-600 hover:text-blue-800"
                        >
                            Select All
                        </button>
                        <button 
                            @click="bulkAction()"
                            :disabled="selectedTasks.length === 0"
                            class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded text-sm transition-colors"
                            :class="{ 'opacity-50 cursor-not-allowed': selectedTasks.length === 0 }"
                        >
                            Bulk Actions (<span x-text="selectedTasks.length"></span>)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input 
                                    type="checkbox" 
                                    x-model="selectAllTasks"
                                    @change="toggleSelectAll()"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                >
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Task
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Assignee
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Priority
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Due Date
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="task in filteredTasks" :key="task.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input 
                                        type="checkbox" 
                                        :value="task.id"
                                        x-model="selectedTasks"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    >
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                            <i class="fas fa-tasks text-gray-600 text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900" x-text="task.title"></div>
                                            <div class="text-sm text-gray-500" x-text="task.description"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.project"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center mr-2">
                                            <i class="fas fa-user text-gray-600 text-xs"></i>
                                        </div>
                                        <span class="text-sm text-gray-900" x-text="task.assignee"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="getPriorityColor(task.priority)"
                                        x-text="task.priority"
                                    ></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                        :class="getStatusColor(task.status)"
                                        x-text="task.status"
                                    ></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(task.due_date)"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button 
                                            @click="viewTask(task.id)"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button 
                                            @click="editTask(task.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button 
                                            @click="completeTask(task.id)"
                                            :class="task.status === 'completed' ? 'text-green-600 hover:text-green-900' : 'text-gray-600 hover:text-gray-900'"
                                        >
                                            <i :class="task.status === 'completed' ? 'fas fa-check' : 'fas fa-check-circle'"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task.id)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing <span x-text="(currentPage - 1) * perPage + 1"></span> to 
                        <span x-text="Math.min(currentPage * perPage, filteredTasks.length)"></span> of 
                        <span x-text="filteredTasks.length"></span> results
                    </div>
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="previousPage()"
                            :disabled="currentPage === 1"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        <span class="px-3 py-1 text-sm text-gray-700">
                            Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                        </span>
                        <button 
                            @click="nextPage()"
                            :disabled="currentPage === totalPages"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function taskManagement() {
            return {
                refreshing: false,
                searchQuery: '',
                statusFilter: '',
                priorityFilter: '',
                assigneeFilter: '',
                selectedTasks: [],
                selectAllTasks: false,
                currentPage: 1,
                perPage: 10,
                
                stats: {
                    totalTasks: 47,
                    completedTasks: 23,
                    inProgressTasks: 18,
                    overdueTasks: 6
                },
                
                tasks: [
                    {
                        id: 1,
                        title: 'Review design mockups',
                        description: 'Review and provide feedback on new website design mockups',
                        project: 'Website Redesign',
                        assignee: 'John Doe',
                        priority: 'high',
                        status: 'in_progress',
                        due_date: '2025-09-25T10:00:00Z'
                    },
                    {
                        id: 2,
                        title: 'Update project documentation',
                        description: 'Update project documentation with latest changes',
                        project: 'Mobile App Development',
                        assignee: 'Jane Smith',
                        priority: 'medium',
                        status: 'todo',
                        due_date: '2025-09-26T14:00:00Z'
                    },
                    {
                        id: 3,
                        title: 'Prepare marketing materials',
                        description: 'Prepare marketing materials for Q4 campaign',
                        project: 'Marketing Campaign',
                        assignee: 'Bob Johnson',
                        priority: 'high',
                        status: 'review',
                        due_date: '2025-09-27T09:00:00Z'
                    },
                    {
                        id: 4,
                        title: 'Database optimization',
                        description: 'Optimize database performance for better response times',
                        project: 'Database Migration',
                        assignee: 'Alice Brown',
                        priority: 'low',
                        status: 'completed',
                        due_date: '2025-09-20T16:00:00Z'
                    },
                    {
                        id: 5,
                        title: 'User testing session',
                        description: 'Conduct user testing session for mobile app',
                        project: 'Mobile App Development',
                        assignee: 'Charlie Wilson',
                        priority: 'medium',
                        status: 'in_progress',
                        due_date: '2025-09-28T11:00:00Z'
                    }
                ],

                get filteredTasks() {
                    let filtered = this.tasks;
                    
                    // Search filter
                    if (this.searchQuery) {
                        const query = this.searchQuery.toLowerCase();
                        filtered = filtered.filter(task => 
                            task.title.toLowerCase().includes(query) ||
                            task.description.toLowerCase().includes(query) ||
                            task.assignee.toLowerCase().includes(query)
                        );
                    }
                    
                    // Status filter
                    if (this.statusFilter) {
                        filtered = filtered.filter(task => task.status === this.statusFilter);
                    }
                    
                    // Priority filter
                    if (this.priorityFilter) {
                        filtered = filtered.filter(task => task.priority === this.priorityFilter);
                    }
                    
                    // Assignee filter
                    if (this.assigneeFilter) {
                        filtered = filtered.filter(task => task.assignee.toLowerCase().includes(this.assigneeFilter));
                    }
                    
                    return filtered;
                },

                get totalPages() {
                    return Math.ceil(this.filteredTasks.length / this.perPage);
                },

                searchTasks() {
                    this.currentPage = 1;
                },

                filterTasks() {
                    this.currentPage = 1;
                },

                selectAll() {
                    this.selectAllTasks = !this.selectAllTasks;
                    if (this.selectAllTasks) {
                        this.selectedTasks = this.filteredTasks.map(task => task.id);
                    } else {
                        this.selectedTasks = [];
                    }
                },

                toggleSelectAll() {
                    if (this.selectAllTasks) {
                        this.selectedTasks = this.filteredTasks.map(task => task.id);
                    } else {
                        this.selectedTasks = [];
                    }
                },

                bulkAction() {
                    if (this.selectedTasks.length === 0) return;
                    
                    const action = prompt('Bulk action (complete, delete, assign):');
                    if (action) {
                        console.log(`Performing ${action} on tasks:`, this.selectedTasks);
                        // Implement bulk action logic
                    }
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

                getPriorityColor(priority) {
                    const colors = {
                        'high': 'bg-red-100 text-red-800',
                        'medium': 'bg-yellow-100 text-yellow-800',
                        'low': 'bg-green-100 text-green-800'
                    };
                    return colors[priority] || 'bg-gray-100 text-gray-800';
                },

                getStatusColor(status) {
                    const colors = {
                        'todo': 'bg-gray-100 text-gray-800',
                        'in_progress': 'bg-blue-100 text-blue-800',
                        'review': 'bg-yellow-100 text-yellow-800',
                        'completed': 'bg-green-100 text-green-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString();
                },

                async refreshTasks() {
                    this.refreshing = true;
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    this.refreshing = false;
                },

                createTask() {
                    console.log('Creating new task...');
                    // Implement create task logic
                },

                viewTask(taskId) {
                    console.log('Viewing task:', taskId);
                    // Implement view task logic
                },

                editTask(taskId) {
                    console.log('Editing task:', taskId);
                    // Implement edit task logic
                },

                completeTask(taskId) {
                    const task = this.tasks.find(t => t.id === taskId);
                    if (task) {
                        task.status = task.status === 'completed' ? 'todo' : 'completed';
                    }
                },

                deleteTask(taskId) {
                    if (confirm('Are you sure you want to delete this task?')) {
                        this.tasks = this.tasks.filter(t => t.id !== taskId);
                    }
                },

                exportTasks() {
                    console.log('Exporting tasks...');
                    // Implement export logic
                }
            }
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tenant/tasks/index.blade.php ENDPATH**/ ?>