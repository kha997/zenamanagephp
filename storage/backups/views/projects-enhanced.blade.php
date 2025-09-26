{{-- Projects Management - Enhanced UI with Beautiful Tailwind CSS --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Management - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        secondary: '#10b981',
                        accent: '#8b5cf6',
                        warning: '#f59e0b',
                        danger: '#ef4444'
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.6s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'bounce-slow': 'bounce 2s infinite',
                        'pulse-slow': 'pulse 3s infinite'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen" x-data="projectsManagement()">
    <!-- Enhanced Header -->
    <header class="bg-white/90 backdrop-blur-md shadow-xl border-b border-gray-200/50 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div class="flex items-center space-x-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-3 rounded-xl shadow-lg">
                            <i class="fas fa-project-diagram text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold gradient-text">Projects Management</h1>
                            <p class="text-gray-600 text-sm">Manage and track your projects efficiently</p>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <div class="flex items-center space-x-2 bg-blue-100 px-4 py-2 rounded-full">
                            <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                            <span class="text-blue-800 text-sm font-medium" x-text="projects.length + ' Projects'"></span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="openCreateModal" 
                            class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white px-6 py-3 rounded-xl font-medium transition-all duration-300 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-plus mr-2"></i>
                        New Project
                    </button>
                    <div class="relative">
                        <button @click="toggleUserMenu" class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none bg-gray-100 hover:bg-gray-200 px-4 py-2 rounded-xl transition-colors">
                            <img src="https://ui-avatars.com/api/?name=Project+Manager&background=3b82f6&color=ffffff&size=40" 
                                 alt="User" class="h-10 w-10 rounded-full border-2 border-white shadow-lg">
                            <div class="hidden md:block text-left">
                                <div class="text-sm font-medium">Project Manager</div>
                                <div class="text-xs text-gray-500">Team Lead</div>
                            </div>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Enhanced Navigation -->
    <nav class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-8">
                    <a href="/app/dashboard" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="/projects-enhanced" class="flex items-center space-x-2 text-blue-600 font-medium border-b-2 border-blue-600 pb-2 px-3 py-1 rounded-lg bg-blue-50">
                        <i class="fas fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-tasks"></i>
                        <span>Tasks</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-calendar"></i>
                        <span>Calendar</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-file-alt"></i>
                        <span>Documents</span>
                    </a>
                    <a href="#" class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 font-medium px-3 py-1 rounded-lg hover:bg-gray-100 transition-colors">
                        <i class="fas fa-users"></i>
                        <span>Team</span>
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <input type="text" x-model="searchQuery" @input="filterProjects" 
                               placeholder="Search projects..." 
                               class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white/80 backdrop-blur-sm">
                        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Enhanced KPI Strip -->
    <section class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 animate-fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium mb-1">Total Projects</p>
                            <p class="text-4xl font-bold mb-2" x-text="stats.totalProjects">12</p>
                            <div class="flex items-center">
                                <i class="fas fa-arrow-up mr-1 text-blue-200"></i>
                                <span class="text-blue-100 text-sm font-medium" x-text="stats.projectGrowth">+3</span>
                                <span class="text-blue-200 text-sm ml-2">this month</span>
                            </div>
                        </div>
                        <div class="bg-blue-400/30 rounded-2xl p-4">
                            <i class="fas fa-project-diagram text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 animate-fade-in" style="animation-delay: 0.1s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium mb-1">Active Projects</p>
                            <p class="text-4xl font-bold mb-2" x-text="stats.activeProjects">8</p>
                            <div class="flex items-center">
                                <i class="fas fa-play mr-1 text-green-200"></i>
                                <span class="text-green-100 text-sm font-medium">In progress</span>
                            </div>
                        </div>
                        <div class="bg-green-400/30 rounded-2xl p-4">
                            <i class="fas fa-play text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 animate-fade-in" style="animation-delay: 0.2s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium mb-1">Completed</p>
                            <p class="text-4xl font-bold mb-2" x-text="stats.completedProjects">3</p>
                            <div class="flex items-center">
                                <i class="fas fa-check mr-1 text-purple-200"></i>
                                <span class="text-purple-100 text-sm font-medium">This month</span>
                            </div>
                        </div>
                        <div class="bg-purple-400/30 rounded-2xl p-4">
                            <i class="fas fa-check text-3xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 animate-fade-in" style="animation-delay: 0.3s">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium mb-1">Team Members</p>
                            <p class="text-4xl font-bold mb-2" x-text="stats.teamMembers">8</p>
                            <div class="flex items-center">
                                <i class="fas fa-users mr-1 text-orange-200"></i>
                                <span class="text-orange-100 text-sm font-medium">Active contributors</span>
                            </div>
                        </div>
                        <div class="bg-orange-400/30 rounded-2xl p-4">
                            <i class="fas fa-users text-3xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Smart Filters -->
    <section class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm font-medium text-gray-700">Quick Filters:</span>
                    <button @click="setFilter('all')" 
                            :class="currentFilter === 'all' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-th mr-2"></i>All Projects
                    </button>
                    <button @click="setFilter('active')" 
                            :class="currentFilter === 'active' ? 'bg-green-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-play mr-2"></i>Active
                    </button>
                    <button @click="setFilter('completed')" 
                            :class="currentFilter === 'completed' ? 'bg-purple-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-check mr-2"></i>Completed
                    </button>
                    <button @click="setFilter('on-hold')" 
                            :class="currentFilter === 'on-hold' ? 'bg-yellow-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-pause mr-2"></i>On Hold
                    </button>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="toggleView('grid')" 
                            :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="p-3 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-th text-lg"></i>
                    </button>
                    <button @click="toggleView('list')" 
                            :class="viewMode === 'list' ? 'bg-blue-600 text-white shadow-lg' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                            class="p-3 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-list text-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Enhanced Grid View -->
        <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <template x-for="(project, index) in filteredProjects" :key="project.id">
                <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 p-6 hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-2 animate-fade-in"
                     :style="'animation-delay: ' + (index * 0.1) + 's'">
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 mb-2" x-text="project.name"></h3>
                            <p class="text-sm text-gray-600 mb-4 line-clamp-2" x-text="project.description"></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span :class="getStatusColor(project.status)" 
                                  class="px-3 py-1 text-xs font-medium rounded-full" 
                                  x-text="project.status"></span>
                            <div class="relative">
                                <button @click="toggleProjectMenu(project.id)" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-lg transition-colors">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div x-show="activeProjectMenu === project.id" @click.away="activeProjectMenu = null"
                                     class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-200 z-10 animate-slide-up">
                                    <div class="py-2">
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                            <i class="fas fa-edit mr-3 text-blue-500"></i>Edit Project
                                        </a>
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                            <i class="fas fa-tasks mr-3 text-green-500"></i>View Tasks
                                        </a>
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                            <i class="fas fa-users mr-3 text-purple-500"></i>Manage Team
                                        </a>
                                        <hr class="my-2">
                                        <a href="#" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                            <i class="fas fa-trash mr-3"></i>Delete Project
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Progress Bar -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-gray-700">Progress</span>
                            <span class="text-sm font-bold text-gray-900" x-text="project.progress + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div :style="'width: ' + project.progress + '%'" 
                                 :class="getProgressColor(project.progress)" 
                                 class="h-3 rounded-full transition-all duration-500 ease-out"></div>
                        </div>
                    </div>
                    
                    <!-- Enhanced Project Info -->
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-calendar mr-3 text-blue-500"></i>
                            <span x-text="project.startDate"></span> - <span x-text="project.endDate"></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-user mr-3 text-green-500"></i>
                            <span x-text="project.manager"></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-tasks mr-3 text-purple-500"></i>
                            <span x-text="project.tasksCompleted"></span> of <span x-text="project.totalTasks"></span> tasks
                        </div>
                    </div>
                    
                    <!-- Enhanced Team Members -->
                    <div class="flex items-center justify-between">
                        <div class="flex -space-x-2">
                            <template x-for="member in project.team.slice(0, 3)" :key="member.id">
                                <img :src="member.avatar" :alt="member.name" 
                                     :title="member.name"
                                     class="w-10 h-10 rounded-full border-3 border-white shadow-lg hover:scale-110 transition-transform">
                            </template>
                            <div x-show="project.team.length > 3" 
                                 class="w-10 h-10 rounded-full border-3 border-white bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shadow-lg">
                                +<span x-text="project.team.length - 3"></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button @click="viewProject(project.id)" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                View Details
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Enhanced List View -->
        <div x-show="viewMode === 'list'" class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden animate-fade-in">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/80">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Project
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Progress
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Manager
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Due Date
                            </th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="project in filteredProjects" :key="project.id">
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <div class="h-12 w-12 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center shadow-lg">
                                                <i class="fas fa-project-diagram text-white text-lg"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-semibold text-gray-900" x-text="project.name"></div>
                                            <div class="text-sm text-gray-500" x-text="project.description"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusColor(project.status)" 
                                          class="px-3 py-1 text-xs font-medium rounded-full" 
                                          x-text="project.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-20 bg-gray-200 rounded-full h-2 mr-3">
                                            <div :style="'width: ' + project.progress + '%'" 
                                                 :class="getProgressColor(project.progress)" 
                                                 class="h-2 rounded-full"></div>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900" x-text="project.progress + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <img :src="project.managerAvatar" :alt="project.manager" 
                                             class="h-10 w-10 rounded-full mr-3 border-2 border-white shadow-lg">
                                        <span class="text-sm font-semibold text-gray-900" x-text="project.manager"></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900" x-text="project.endDate"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <button @click="viewProject(project.id)" 
                                                class="text-blue-600 hover:text-blue-900 p-2 hover:bg-blue-100 rounded-lg transition-colors">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="editProject(project.id)" 
                                                class="text-green-600 hover:text-green-900 p-2 hover:bg-green-100 rounded-lg transition-colors">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="deleteProject(project.id)" 
                                                class="text-red-600 hover:text-red-900 p-2 hover:bg-red-100 rounded-lg transition-colors">
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

    <!-- Enhanced Create Project Modal -->
    <div x-show="showCreateModal" x-transition class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full mx-4 animate-slide-up">
            <div class="p-8">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-2xl font-bold text-gray-900">Create New Project</h3>
                    <button @click="closeCreateModal" class="text-gray-400 hover:text-gray-600 p-2 hover:bg-gray-100 rounded-xl transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <form @submit.prevent="createProject">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Project Name</label>
                            <input type="text" x-model="newProject.name" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                            <select x-model="newProject.status" 
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="on-hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                            <textarea x-model="newProject.description" rows="3"
                                      class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                            <input type="date" x-model="newProject.startDate" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                            <input type="date" x-model="newProject.endDate" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Project Manager</label>
                            <select x-model="newProject.manager" 
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                <option value="John Doe">John Doe</option>
                                <option value="Jane Smith">Jane Smith</option>
                                <option value="Mike Johnson">Mike Johnson</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
                            <select x-model="newProject.priority" 
                                    class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-4 mt-8">
                        <button type="button" @click="closeCreateModal" 
                                class="px-6 py-3 text-gray-600 hover:text-gray-800 font-medium">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 font-medium transition-all duration-300 transform hover:scale-105">
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
                        description: 'Complete overhaul of company website with modern design and enhanced user experience',
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
                        description: 'Native mobile application for iOS and Android with cross-platform compatibility',
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
                        description: 'Migrate legacy database to new cloud infrastructure with zero downtime',
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
                        description: 'Integrate third-party APIs for enhanced functionality and data synchronization',
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
                    
                    if (this.searchQuery) {
                        filtered = filtered.filter(project => 
                            project.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                            project.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                        );
                    }
                    
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
