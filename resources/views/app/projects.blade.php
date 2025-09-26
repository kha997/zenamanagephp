{{-- Projects Management - Complete Implementation --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Management - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="projectsManagement()">
    <!-- Universal Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-project-diagram text-blue-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Projects Management</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-blue-500 mr-1"></i>
                            <span x-text="projects.length"></span> Projects
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="openCreateModal" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        New Project
                    </button>
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                            <img src="https://ui-avatars.com/api/?name=Project+Manager&background=3b82f6&color=ffffff" 
                                 alt="User" class="h-8 w-8 rounded-full">
                            <span class="hidden md:block text-sm font-medium">Project Manager</span>
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
                    <a href="/app/projects" class="text-blue-600 font-medium border-b-2 border-blue-600 pb-2">
                        <i class="fas fa-project-diagram mr-2"></i>Projects
                    </a>
                    <a href="/app/tasks" class="text-gray-600 hover:text-gray-900 font-medium">
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
                        <input type="text" x-model="searchQuery" @input="filterProjects" 
                               placeholder="Search projects..." 
                               class="w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Projects</p>
                            <p class="text-3xl font-bold" x-text="stats.totalProjects">12</p>
                            <p class="text-blue-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                <span x-text="stats.projectGrowth">+3</span> this month
                            </p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-project-diagram text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Active Projects</p>
                            <p class="text-3xl font-bold" x-text="stats.activeProjects">8</p>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-play mr-1"></i>
                                In progress
                            </p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-play text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">Completed</p>
                            <p class="text-3xl font-bold" x-text="stats.completedProjects">3</p>
                            <p class="text-purple-100 text-sm">
                                <i class="fas fa-check mr-1"></i>
                                This month
                            </p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-check text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Team Members</p>
                            <p class="text-3xl font-bold" x-text="stats.teamMembers">8</p>
                            <p class="text-orange-100 text-sm">
                                <i class="fas fa-users mr-1"></i>
                                Active contributors
                            </p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-users text-2xl"></i>
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
                            :class="currentFilter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        All Projects
                    </button>
                    <button @click="setFilter('active')" 
                            :class="currentFilter === 'active' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Active
                    </button>
                    <button @click="setFilter('completed')" 
                            :class="currentFilter === 'completed' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        Completed
                    </button>
                    <button @click="setFilter('on-hold')" 
                            :class="currentFilter === 'on-hold' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                        On Hold
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="toggleView('grid')" 
                            :class="viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-2 rounded-lg transition-colors">
                        <i class="fas fa-th"></i>
                    </button>
                    <button @click="toggleView('list')" 
                            :class="viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700'"
                            class="p-2 rounded-lg transition-colors">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Grid View -->
        <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="project in filteredProjects" :key="project.id">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="project.name"></h3>
                            <p class="text-sm text-gray-600 mb-3" x-text="project.description"></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="getStatusColor(project.status)" 
                                  class="px-2 py-1 text-xs font-medium rounded-full" 
                                  x-text="project.status"></span>
                            <div class="relative">
                                <button @click="toggleProjectMenu(project.id)" class="text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div x-show="activeProjectMenu === project.id" @click.away="activeProjectMenu = null"
                                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                    <div class="py-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i>Edit Project
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-tasks mr-2"></i>View Tasks
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-users mr-2"></i>Manage Team
                                        </a>
                                        <hr class="my-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                            <i class="fas fa-trash mr-2"></i>Delete Project
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700">Progress</span>
                            <span class="text-sm font-medium text-gray-900" x-text="project.progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div :style="'width: ' + project.progress + '%'" 
                                 :class="getProgressColor(project.progress)" 
                                 class="h-2 rounded-full transition-all duration-300"></div>
                        </div>
                    </div>
                    
                    <!-- Project Info -->
                    <div class="space-y-2 mb-4">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar mr-2"></i>
                            <span x-text="project.startDate"></span> - <span x-text="project.endDate"></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-user mr-2"></i>
                            <span x-text="project.manager"></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-tasks mr-2"></i>
                            <span x-text="project.tasksCompleted"></span> of <span x-text="project.totalTasks"></span> tasks
                        </div>
                    </div>
                    
                    <!-- Team Members -->
                    <div class="flex items-center justify-between">
                        <div class="flex -space-x-2">
                            <template x-for="member in project.team.slice(0, 3)" :key="member.id">
                                <img :src="member.avatar" :alt="member.name" 
                                     :title="member.name"
                                     class="w-8 h-8 rounded-full border-2 border-white">
                            </template>
                            <div x-show="project.team.length > 3" 
                                 class="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center text-xs font-medium text-gray-600">
                                +<span x-text="project.team.length - 3"></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button @click="viewProject(project.id)" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- List View -->
        <div x-show="viewMode === 'list'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Project
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Progress
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Manager
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Due Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="project in filteredProjects" :key="project.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                                <i class="fas fa-project-diagram text-blue-600"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="project.name"></div>
                                            <div class="text-sm text-gray-500" x-text="project.description"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusColor(project.status)" 
                                          class="px-2 py-1 text-xs font-medium rounded-full" 
                                          x-text="project.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                            <div :style="'width: ' + project.progress + '%'" 
                                                 :class="getProgressColor(project.progress)" 
                                                 class="h-2 rounded-full"></div>
                                        </div>
                                        <span class="text-sm text-gray-900" x-text="project.progress + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img :src="project.managerAvatar" :alt="project.manager" 
                                             class="h-8 w-8 rounded-full mr-2">
                                        <span class="text-sm text-gray-900" x-text="project.manager"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="project.endDate"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="viewProject(project.id)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="editProject(project.id)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="deleteProject(project.id)" 
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

    <!-- Create Project Modal -->
    <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Create New Project</h3>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form @submit.prevent="createProject">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                            <input type="text" x-model="newProject.name" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select x-model="newProject.status" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <textarea x-model="newProject.description" rows="3"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                            <input type="date" x-model="newProject.startDate" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                            <input type="date" x-model="newProject.endDate" required
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project Manager</label>
                            <select x-model="newProject.manager" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="John Doe">John Doe</option>
                                <option value="Jane Smith">Jane Smith</option>
                                <option value="Mike Johnson">Mike Johnson</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                            <select x-model="newProject.priority" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="closeCreateModal" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Create Project
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function projectsManagement() {
            return {
                searchQuery: '',
                currentFilter: 'all',
                viewMode: 'grid',
                showCreateModal: false,
                activeProjectMenu: null,
                
                newProject: {
                    name: '',
                    description: '',
                    status: 'planning',
                    startDate: '',
                    endDate: '',
                    manager: 'John Doe',
                    priority: 'medium'
                },

                stats: {
                    totalProjects: 12,
                    projectGrowth: '+3',
                    activeProjects: 8,
                    completedProjects: 3,
                    teamMembers: 8
                },

                projects: [
                    {
                        id: 1,
                        name: 'Website Redesign',
                        description: 'Complete overhaul of company website with modern design',
                        status: 'active',
                        progress: 75,
                        startDate: '2025-01-15',
                        endDate: '2025-03-15',
                        manager: 'John Doe',
                        managerAvatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff',
                        tasksCompleted: 15,
                        totalTasks: 20,
                        priority: 'high',
                        team: [
                            { id: 1, name: 'John Doe', avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff' },
                            { id: 2, name: 'Jane Smith', avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff' },
                            { id: 3, name: 'Mike Johnson', avatar: 'https://ui-avatars.com/api/?name=Mike+Johnson&background=8b5cf6&color=ffffff' }
                        ]
                    },
                    {
                        id: 2,
                        name: 'Mobile App Development',
                        description: 'Native mobile application for iOS and Android',
                        status: 'active',
                        progress: 45,
                        startDate: '2025-02-01',
                        endDate: '2025-05-01',
                        manager: 'Jane Smith',
                        managerAvatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff',
                        tasksCompleted: 9,
                        totalTasks: 20,
                        priority: 'medium',
                        team: [
                            { id: 1, name: 'Jane Smith', avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff' },
                            { id: 2, name: 'Sarah Wilson', avatar: 'https://ui-avatars.com/api/?name=Sarah+Wilson&background=f59e0b&color=ffffff' }
                        ]
                    },
                    {
                        id: 3,
                        name: 'Database Migration',
                        description: 'Migrate legacy database to new cloud infrastructure',
                        status: 'completed',
                        progress: 100,
                        startDate: '2024-12-01',
                        endDate: '2025-01-15',
                        manager: 'Mike Johnson',
                        managerAvatar: 'https://ui-avatars.com/api/?name=Mike+Johnson&background=8b5cf6&color=ffffff',
                        tasksCompleted: 12,
                        totalTasks: 12,
                        priority: 'high',
                        team: [
                            { id: 1, name: 'Mike Johnson', avatar: 'https://ui-avatars.com/api/?name=Mike+Johnson&background=8b5cf6&color=ffffff' },
                            { id: 2, name: 'David Brown', avatar: 'https://ui-avatars.com/api/?name=David+Brown&background=ef4444&color=ffffff' }
                        ]
                    },
                    {
                        id: 4,
                        name: 'API Integration',
                        description: 'Integrate third-party APIs for enhanced functionality',
                        status: 'on-hold',
                        progress: 30,
                        startDate: '2025-01-20',
                        endDate: '2025-03-20',
                        manager: 'Sarah Wilson',
                        managerAvatar: 'https://ui-avatars.com/api/?name=Sarah+Wilson&background=f59e0b&color=ffffff',
                        tasksCompleted: 3,
                        totalTasks: 10,
                        priority: 'medium',
                        team: [
                            { id: 1, name: 'Sarah Wilson', avatar: 'https://ui-avatars.com/api/?name=Sarah+Wilson&background=f59e0b&color=ffffff' },
                            { id: 2, name: 'Tom Davis', avatar: 'https://ui-avatars.com/api/?name=Tom+Davis&background=06b6d4&color=ffffff' }
                        ]
                    }
                ],

                get filteredProjects() {
                    let filtered = this.projects;
                    
                    // Filter by search query
                    if (this.searchQuery) {
                        filtered = filtered.filter(project => 
                            project.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            project.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    }
                    
                    // Filter by status
                    if (this.currentFilter !== 'all') {
                        filtered = filtered.filter(project => project.status === this.currentFilter);
                    }
                    
                    return filtered;
                },

                setFilter(filter) {
                    this.currentFilter = filter;
                },

                toggleView(mode) {
                    this.viewMode = mode;
                },

                filterProjects() {
                    // This will trigger the filteredProjects computed property
                },

                getStatusColor(status) {
                    const colors = {
                        'active': 'bg-green-100 text-green-800',
                        'completed': 'bg-blue-100 text-blue-800',
                        'on-hold': 'bg-yellow-100 text-yellow-800',
                        'planning': 'bg-gray-100 text-gray-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                getProgressColor(progress) {
                    if (progress >= 80) return 'bg-green-500';
                    if (progress >= 50) return 'bg-blue-500';
                    if (progress >= 25) return 'bg-yellow-500';
                    return 'bg-red-500';
                },

                toggleProjectMenu(projectId) {
                    this.activeProjectMenu = this.activeProjectMenu === projectId ? null : projectId;
                },

                openCreateModal() {
                    this.showCreateModal = true;
                },

                closeCreateModal() {
                    this.showCreateModal = false;
                    this.newProject = {
                        name: '',
                        description: '',
                        status: 'planning',
                        startDate: '',
                        endDate: '',
                        manager: 'John Doe',
                        priority: 'medium'
                    };
                },

                createProject() {
                    // Add new project to the list
                    const newId = Math.max(...this.projects.map(p => p.id)) + 1;
                    this.projects.push({
                        id: newId,
                        ...this.newProject,
                        progress: 0,
                        tasksCompleted: 0,
                        totalTasks: 0,
                        team: []
                    });
                    
                    this.closeCreateModal();
                },

                viewProject(projectId) {
                    console.log('Viewing project:', projectId);
                },

                editProject(projectId) {
                    console.log('Editing project:', projectId);
                },

                deleteProject(projectId) {
                    if (confirm('Are you sure you want to delete this project?')) {
                        this.projects = this.projects.filter(p => p.id !== projectId);
                    }
                }
            }
        }
    </script>
</body>
</html>
