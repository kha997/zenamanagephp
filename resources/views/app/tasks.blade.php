{{-- Tasks Management - Complete Implementation --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks Management - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="tasksManagement()">
    <!-- Universal Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-tasks text-green-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Tasks Management</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-green-500 mr-1"></i>
                            <span x-text="tasks.length"></span> Tasks
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="openCreateModal" 
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        New Task
                    </button>
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=Task+Manager&background=10b981&color=ffffff" 
                                 alt="User" class="h-8 w-8 rounded-full">
                            <span class="hidden md:block text-sm font-medium">Task Manager</span>
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
                    <a href="/app/tasks" class="text-green-600 font-medium border-b-2 border-green-600 pb-2">
                        <i class="fas fa-tasks mr-2"></i>Tasks
                    </a>
                    <a href="/app/calendar" class="text-gray-600 hover:text-gray-900 font-medium">
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
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @input="filterTasks" 
                               placeholder="Search tasks..." 
                               class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- KPI Strip -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Total Tasks</p>
                            <p class="text-3xl font-bold" x-text="stats.totalTasks">47</p>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span x-text="stats.taskGrowth">+8</span> this week
                            </p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-tasks text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Completed</p>
                            <p class="text-3xl font-bold" x-text="stats.completedTasks">23</p>
                            <p class="text-blue-100 text-sm">
                                <i class="fas fa-check mr-1"></i>
                                <span x-text="stats.completionRate">49%</span> completion rate
                            </p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-100 text-sm font-medium">In Progress</p>
                            <p class="text-3xl font-bold" x-text="stats.inProgressTasks">18</p>
                            <p class="text-yellow-100 text-sm">
                                <i class="fas fa-play mr-1"></i>
                                Active work
                            </p>
                        </div>
                        <div class="bg-yellow-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-play text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-sm font-medium">Overdue</p>
                            <p class="text-3xl font-bold" x-text="stats.overdueTasks">6</p>
                            <p class="text-red-100 text-sm">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Need attention
                            </p>
                        </div>
                        <div class="bg-red-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Smart Filters -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-gray-700">Quick Filters:</span>
                    <button @click="setFilter('all')" 
                            :class="currentFilter === 'all' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        All Tasks
                    </button>
                    <button @click="setFilter('my-tasks')" 
                            :class="currentFilter === 'my-tasks' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        My Tasks
                    </button>
                    <button @click="setFilter('todo')" 
                            :class="currentFilter === 'todo' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        To Do
                    </button>
                    <button @click="setFilter('in-progress')" 
                            :class="currentFilter === 'in-progress' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        In Progress
                    </button>
                    <button @click="setFilter('completed')" 
                            :class="currentFilter === 'completed' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Completed
                    </button>
                    <button @click="setFilter('overdue')" 
                            :class="currentFilter === 'overdue' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Overdue
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="toggleView('kanban')" 
                            :class="viewMode === 'kanban' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-2 rounded-lg transition-colors">
                        <i class="fas fa-columns"></i>
                    </button>
                    <button @click="toggleView('list')" 
                            :class="viewMode === 'list' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-2 rounded-lg transition-colors">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Kanban View -->
        <div x-show="viewMode === 'kanban'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- To Do Column -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">To Do</h3>
                    <span class="bg-gray-100 text-gray-600 text-sm font-medium px-2 py-1 rounded-full" 
                          x-text="getTasksByStatus('todo').length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="task in getTasksByStatus('todo')" :key="'todo-' + task.id">
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow cursor-pointer"
                             @click="openTaskModal(task)">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-sm font-medium text-gray-900" x-text="task.title"></h4>
                                <span :class="getPriorityColor(task.priority)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="task.priority"></span>
                            </div>
                            <p class="text-xs text-gray-600 mb-3" x-text="task.description"></p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <img :src="task.assignee.avatar" :alt="task.assignee.name" 
                                         class="w-6 h-6 rounded-full">
                                    <span class="text-xs text-gray-600" x-text="task.assignee.name"></span>
                                </div>
                                <span class="text-xs text-gray-500" x-text="task.dueDate"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- In Progress Column -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">In Progress</h3>
                    <span class="bg-blue-100 text-blue-600 text-sm font-medium px-2 py-1 rounded-full" 
                          x-text="getTasksByStatus('in-progress').length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="task in getTasksByStatus('in-progress')" :key="'progress-' + task.id">
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 hover:shadow-md transition-shadow cursor-pointer"
                             @click="openTaskModal(task)">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-sm font-medium text-gray-900" x-text="task.title"></h4>
                                <span :class="getPriorityColor(task.priority)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="task.priority"></span>
                            </div>
                            <p class="text-xs text-gray-600 mb-3" x-text="task.description"></p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <img :src="task.assignee.avatar" :alt="task.assignee.name" 
                                         class="w-6 h-6 rounded-full">
                                    <span class="text-xs text-gray-600" x-text="task.assignee.name"></span>
                                </div>
                                <span class="text-xs text-gray-500" x-text="task.dueDate"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Review Column -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Review</h3>
                    <span class="bg-yellow-100 text-yellow-600 text-sm font-medium px-2 py-1 rounded-full" 
                          x-text="getTasksByStatus('review').length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="task in getTasksByStatus('review')" :key="'review-' + task.id">
                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200 hover:shadow-md transition-shadow cursor-pointer"
                             @click="openTaskModal(task)">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-sm font-medium text-gray-900" x-text="task.title"></h4>
                                <span :class="getPriorityColor(task.priority)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="task.priority"></span>
                            </div>
                            <p class="text-xs text-gray-600 mb-3" x-text="task.description"></p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <img :src="task.assignee.avatar" :alt="task.assignee.name" 
                                         class="w-6 h-6 rounded-full">
                                    <span class="text-xs text-gray-600" x-text="task.assignee.name"></span>
                                </div>
                                <span class="text-xs text-gray-500" x-text="task.dueDate"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Completed Column -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Completed</h3>
                    <span class="bg-green-100 text-green-600 text-sm font-medium px-2 py-1 rounded-full" 
                          x-text="getTasksByStatus('completed').length"></span>
                </div>
                <div class="space-y-3">
                    <template x-for="task in getTasksByStatus('completed')" :key="'completed-' + task.id">
                        <div class="bg-green-50 rounded-lg p-4 border border-green-200 hover:shadow-md transition-shadow cursor-pointer"
                             @click="openTaskModal(task)">
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-sm font-medium text-gray-900 line-through" x-text="task.title"></h4>
                                <span :class="getPriorityColor(task.priority)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="task.priority"></span>
                            </div>
                            <p class="text-xs text-gray-600 mb-3 line-through" x-text="task.description"></p>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <img :src="task.assignee.avatar" :alt="task.assignee.name" 
                                         class="w-6 h-6 rounded-full">
                                    <span class="text-xs text-gray-600" x-text="task.assignee.name"></span>
                                </div>
                                <span class="text-xs text-gray-500" x-text="task.completedDate"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- List View -->
        <div x-show="viewMode === 'list'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Task
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Priority
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Assignee
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Due Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="task in filteredTasks" :key="'filtered-' + task.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-lg bg-green-100 flex items-center justify-center">
                                                <i class="fas fa-tasks text-green-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="task.title"></div>
                                            <div class="text-sm text-gray-500" x-text="task.description"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusColor(task.status)" 
                                          class="px-2 py-1 text-xs font-medium rounded-full" 
                                          x-text="task.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getPriorityColor(task.priority)" 
                                          class="px-2 py-1 text-xs font-medium rounded-full" 
                                          x-text="task.priority"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img :src="task.assignee.avatar" :alt="task.assignee.name" 
                                             class="h-8 w-8 rounded-full mr-2">
                                        <span class="text-sm text-gray-900" x-text="task.assignee.name"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.dueDate"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="task.project"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="openTaskModal(task)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="editTask(task.id)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="deleteTask(task.id)" 
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Create Task Modal -->
    <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Create New Task</h3>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="createTask">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task Title</label>
                            <input type="text" x-model="newTask.title" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select x-model="newTask.status" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="todo">To Do</option>
                                <option value="in-progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea x-model="newTask.description" rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                            <select x-model="newTask.assignee" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="John Doe">John Doe</option>
                                <option value="Jane Smith">Jane Smith</option>
                                <option value="Mike Johnson">Mike Johnson</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select x-model="newTask.priority" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                            <input type="date" x-model="newTask.dueDate" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                            <select x-model="newTask.project" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="Website Redesign">Website Redesign</option>
                                <option value="Mobile App Development">Mobile App Development</option>
                                <option value="Database Migration">Database Migration</option>
                                <option value="API Integration">API Integration</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="closeCreateModal" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div x-show="showTaskModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900" x-text="selectedTask?.title"></h3>
                    <button @click="closeTaskModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div x-show="selectedTask">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <p class="text-gray-900" x-text="selectedTask?.description"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <span :class="getStatusColor(selectedTask?.status)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="selectedTask?.status"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <span :class="getPriorityColor(selectedTask?.priority)" 
                                      class="px-2 py-1 text-xs font-medium rounded-full" 
                                      x-text="selectedTask?.priority"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                                <div class="flex items-center">
                                    <img :src="selectedTask?.assignee.avatar" :alt="selectedTask?.assignee.name" 
                                         class="h-6 w-6 rounded-full mr-2">
                                    <span class="text-gray-900" x-text="selectedTask?.assignee.name"></span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                                <span class="text-gray-900" x-text="selectedTask?.dueDate"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function tasksManagement() {
            return {
                searchQuery: '',
                currentFilter: 'all',
                viewMode: 'kanban',
                showCreateModal: false,
                showTaskModal: false,
                selectedTask: null,
                
                newTask: {
                    title: '',
                    description: '',
                    status: 'todo',
                    assignee: 'John Doe',
                    priority: 'medium',
                    dueDate: '',
                    project: 'Website Redesign'
                },

                stats: {
                    totalTasks: 47,
                    taskGrowth: '+8',
                    completedTasks: 23,
                    completionRate: '49%',
                    inProgressTasks: 18,
                    overdueTasks: 6
                },

                tasks: [
                    {
                        id: 1,
                        title: 'Design Homepage Layout',
                        description: 'Create modern homepage layout with responsive design',
                        status: 'todo',
                        priority: 'high',
                        assignee: { name: 'John Doe', avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff' },
                        dueDate: '2025-09-30',
                        project: 'Website Redesign',
                        createdDate: '2025-09-20'
                    },
                    {
                        id: 2,
                        title: 'Implement User Authentication',
                        description: 'Add login and registration functionality',
                        status: 'in-progress',
                        priority: 'high',
                        assignee: { name: 'Jane Smith', avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff' },
                        dueDate: '2025-10-05',
                        project: 'Website Redesign',
                        createdDate: '2025-09-18'
                    },
                    {
                        id: 3,
                        title: 'Mobile App UI Design',
                        description: 'Design user interface for mobile application',
                        status: 'review',
                        priority: 'medium',
                        assignee: { name: 'Mike Johnson', avatar: 'https://ui-avatars.com/api/?name=Mike+Johnson&background=8b5cf6&color=ffffff' },
                        dueDate: '2025-10-10',
                        project: 'Mobile App Development',
                        createdDate: '2025-09-15'
                    },
                    {
                        id: 4,
                        title: 'Database Schema Design',
                        description: 'Design database schema for new system',
                        status: 'completed',
                        priority: 'high',
                        assignee: { name: 'Sarah Wilson', avatar: 'https://ui-avatars.com/api/?name=Sarah+Wilson&background=f59e0b&color=ffffff' },
                        dueDate: '2025-09-25',
                        project: 'Database Migration',
                        createdDate: '2025-09-10',
                        completedDate: '2025-09-24'
                    },
                    {
                        id: 5,
                        title: 'API Documentation',
                        description: 'Create comprehensive API documentation',
                        status: 'todo',
                        priority: 'medium',
                        assignee: { name: 'Tom Davis', avatar: 'https://ui-avatars.com/api/?name=Tom+Davis&background=06b6d4&color=ffffff' },
                        dueDate: '2025-10-15',
                        project: 'API Integration',
                        createdDate: '2025-09-22'
                    },
                    {
                        id: 6,
                        title: 'Performance Testing',
                        description: 'Conduct performance testing on new features',
                        status: 'overdue',
                        priority: 'high',
                        assignee: { name: 'John Doe', avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff' },
                        dueDate: '2025-09-20',
                        project: 'Website Redesign',
                        createdDate: '2025-09-15'
                    }
                ],

                get filteredTasks() {
                    let filtered = this.tasks;
                    
                    // Filter by search query
                    if (this.searchQuery) {
                        filtered = filtered.filter(task => 
                            task.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            task.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    }
                    
                    // Filter by status
                    if (this.currentFilter === 'my-tasks') {
                        filtered = filtered.filter(task => task.assignee.name === 'John Doe');
                    } else if (this.currentFilter !== 'all') {
                        filtered = filtered.filter(task => task.status === this.currentFilter);
                    }
                    
                    return filtered;
                },

                getTasksByStatus(status) {
                    return this.filteredTasks.filter(task => task.status === status);
                },

                setFilter(filter) {
                    this.currentFilter = filter;
                },

                toggleView(mode) {
                    this.viewMode = mode;
                },

                filterTasks() {
                    // This will trigger the filteredTasks computed property
                },

                getStatusColor(status) {
                    const colors = {
                        'todo': 'bg-gray-100 text-gray-800',
                        'in-progress': 'bg-blue-100 text-blue-800',
                        'review': 'bg-yellow-100 text-yellow-800',
                        'completed': 'bg-green-100 text-green-800',
                        'overdue': 'bg-red-100 text-red-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                getPriorityColor(priority) {
                    const colors = {
                        'low': 'bg-gray-100 text-gray-800',
                        'medium': 'bg-yellow-100 text-yellow-800',
                        'high': 'bg-red-100 text-red-800'
                    };
                    return colors[priority] || 'bg-gray-100 text-gray-800';
                },

                openCreateModal() {
                    this.showCreateModal = true;
                },

                closeCreateModal() {
                    this.showCreateModal = false;
                    this.newTask = {
                        title: '',
                        description: '',
                        status: 'todo',
                        assignee: 'John Doe',
                        priority: 'medium',
                        dueDate: '',
                        project: 'Website Redesign'
                    };
                },

                createTask() {
                    // Add new task to the list
                    const newId = Math.max(...this.tasks.map(t => t.id)) + 1;
                    this.tasks.push({
                        id: newId,
                        ...this.newTask,
                        assignee: { 
                            name: this.newTask.assignee, 
                            avatar: `https://ui-avatars.com/api/?name=${encodeURIComponent(this.newTask.assignee)}&background=10b981&color=ffffff` 
                        },
                        createdDate: new Date().toISOString().split('T')[0]
                    });
                    
                    this.closeCreateModal();
                },

                openTaskModal(task) {
                    this.selectedTask = task;
                    this.showTaskModal = true;
                },

                closeTaskModal() {
                    this.showTaskModal = false;
                    this.selectedTask = null;
                },

                editTask(taskId) {
                    console.log('Editing task:', taskId);
                },

                deleteTask(taskId) {
                    if (confirm('Are you sure you want to delete this task?')) {
                        this.tasks = this.tasks.filter(t => t.id !== taskId);
                    }
                }
            }
        }
    </script>
</body>
</html>
