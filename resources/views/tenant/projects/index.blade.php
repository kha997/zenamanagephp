{{-- Tenant Project Management Page --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="projectManagement()">
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
                        @click="refreshProjects()"
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
                <a href="#" class="text-blue-600 border-b-2 border-blue-600 px-1 py-2 text-sm font-medium">
                    Projects
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
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
                <h1 class="text-3xl font-bold text-gray-900">Projects</h1>
                <p class="text-lg text-gray-600 mt-2">
                    Manage your projects and track progress
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <button 
                    @click="exportProjects()"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md font-medium transition-colors"
                >
                    <i class="fas fa-download mr-2"></i>
                    Export
                </button>
                <button 
                    @click="createProject()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors"
                >
                    <i class="fas fa-plus mr-2"></i>
                    New Project
                </button>
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
                            @input.debounce.300ms="searchProjects()"
                            placeholder="Search projects by name, description, or team member..."
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="flex gap-3">
                    <select 
                        x-model="statusFilter"
                        @change="filterProjects()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Status</option>
                        <option value="planning">Planning</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                    </select>
                    
                    <select 
                        x-model="priorityFilter"
                        @change="filterProjects()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Priorities</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                    
                    <select 
                        x-model="teamFilter"
                        @change="filterProjects()"
                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">All Teams</option>
                        <option value="development">Development</option>
                        <option value="design">Design</option>
                        <option value="marketing">Marketing</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <template x-for="project in filteredProjects" :key="project.id">
                <div class="project-card bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                    <!-- Card Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center">
                                    <i class="fas fa-folder text-gray-600 text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="project.name"></h3>
                                    <p class="text-sm text-gray-500" x-text="project.team"></p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span 
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="getStatusColor(project.status)"
                                    x-text="project.status"
                                ></span>
                                <div class="relative">
                                    <button 
                                        @click="toggleProjectMenu(project.id)"
                                        class="p-1 text-gray-400 hover:text-gray-600"
                                    >
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div 
                                        x-show="activeProjectMenu === project.id"
                                        @click.away="activeProjectMenu = null"
                                        class="absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-10"
                                    >
                                        <div class="py-1">
                                            <button @click="viewProject(project.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </button>
                                            <button @click="editProject(project.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-edit mr-2"></i>Edit Project
                                            </button>
                                            <button @click="manageTasks(project.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-tasks mr-2"></i>Manage Tasks
                                            </button>
                                            <button @click="manageTeam(project.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-users mr-2"></i>Manage Team
                                            </button>
                                            <div class="border-t border-gray-200"></div>
                                            <button @click="archiveProject(project.id)" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <i class="fas fa-archive mr-2"></i>Archive Project
                                            </button>
                                            <button @click="deleteProject(project.id)" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash mr-2"></i>Delete Project
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="px-6 py-4">
                        <div class="space-y-3">
                            <!-- Description -->
                            <p class="text-sm text-gray-600" x-text="project.description"></p>
                            
                            <!-- Progress -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700">Progress</span>
                                    <span class="text-sm font-medium text-gray-900" x-text="project.progress + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="`width: ${project.progress}%`"></div>
                                </div>
                            </div>

                            <!-- Project Details -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">Priority:</span>
                                    <span 
                                        class="ml-1 font-medium"
                                        :class="getPriorityColor(project.priority)"
                                        x-text="project.priority"
                                    ></span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Tasks:</span>
                                    <span class="ml-1 font-medium text-gray-900" x-text="project.tasks_count"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Start Date:</span>
                                    <span class="ml-1 font-medium text-gray-900" x-text="formatDate(project.start_date)"></span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Due Date:</span>
                                    <span class="ml-1 font-medium text-gray-900" x-text="formatDate(project.due_date)"></span>
                                </div>
                            </div>

                            <!-- Team Members -->
                            <div>
                                <span class="text-sm text-gray-500">Team:</span>
                                <div class="flex items-center mt-1">
                                    <template x-for="member in project.team_members" :key="member.id">
                                        <div class="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center mr-1" :title="member.name">
                                            <i class="fas fa-user text-gray-600 text-xs"></i>
                                        </div>
                                    </template>
                                    <span class="text-xs text-gray-500 ml-2" x-text="project.team_members.length + ' members'"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-xs text-gray-500">
                                Last updated: <span x-text="formatDate(project.updated_at)"></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button 
                                    @click="viewProject(project.id)"
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                                >
                                    View Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State -->
        <div x-show="filteredProjects.length === 0" class="text-center py-12">
            <i class="fas fa-folder-open text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No projects found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
            <button 
                @click="createProject()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-medium transition-colors"
            >
                <i class="fas fa-plus mr-2"></i>Create First Project
            </button>
        </div>
    </main>

    <script>
        function projectManagement() {
            return {
                refreshing: false,
                searchQuery: '',
                statusFilter: '',
                priorityFilter: '',
                teamFilter: '',
                activeProjectMenu: null,
                
                projects: [
                    {
                        id: 1,
                        name: 'Website Redesign',
                        description: 'Complete overhaul of company website with modern design and improved UX',
                        status: 'in_progress',
                        priority: 'high',
                        team: 'Design Team',
                        progress: 75,
                        tasks_count: 24,
                        start_date: '2025-09-01T00:00:00Z',
                        due_date: '2025-10-15T00:00:00Z',
                        updated_at: '2025-09-24T10:30:00Z',
                        team_members: [
                            { id: 1, name: 'John Doe' },
                            { id: 2, name: 'Jane Smith' },
                            { id: 3, name: 'Bob Johnson' }
                        ]
                    },
                    {
                        id: 2,
                        name: 'Mobile App Development',
                        description: 'iOS and Android app development for customer engagement',
                        status: 'in_progress',
                        priority: 'high',
                        team: 'Development Team',
                        progress: 45,
                        tasks_count: 18,
                        start_date: '2025-08-15T00:00:00Z',
                        due_date: '2025-11-30T00:00:00Z',
                        updated_at: '2025-09-24T09:15:00Z',
                        team_members: [
                            { id: 4, name: 'Alice Brown' },
                            { id: 5, name: 'Charlie Wilson' }
                        ]
                    },
                    {
                        id: 3,
                        name: 'Marketing Campaign',
                        description: 'Q4 marketing campaign launch with social media integration',
                        status: 'planning',
                        priority: 'medium',
                        team: 'Marketing Team',
                        progress: 20,
                        tasks_count: 12,
                        start_date: '2025-10-01T00:00:00Z',
                        due_date: '2025-12-31T00:00:00Z',
                        updated_at: '2025-09-24T08:45:00Z',
                        team_members: [
                            { id: 6, name: 'David Lee' },
                            { id: 7, name: 'Emma Davis' }
                        ]
                    },
                    {
                        id: 4,
                        name: 'Database Migration',
                        description: 'Migrate legacy database to new cloud infrastructure',
                        status: 'on_hold',
                        priority: 'low',
                        team: 'Development Team',
                        progress: 30,
                        tasks_count: 8,
                        start_date: '2025-07-01T00:00:00Z',
                        due_date: '2025-09-30T00:00:00Z',
                        updated_at: '2025-09-20T14:30:00Z',
                        team_members: [
                            { id: 8, name: 'Frank Miller' }
                        ]
                    },
                    {
                        id: 5,
                        name: 'Customer Support Portal',
                        description: 'New customer support portal with ticketing system',
                        status: 'completed',
                        priority: 'medium',
                        team: 'Development Team',
                        progress: 100,
                        tasks_count: 15,
                        start_date: '2025-06-01T00:00:00Z',
                        due_date: '2025-08-31T00:00:00Z',
                        updated_at: '2025-08-31T16:00:00Z',
                        team_members: [
                            { id: 9, name: 'Grace Taylor' },
                            { id: 10, name: 'Henry Anderson' }
                        ]
                    }
                ],

                get filteredProjects() {
                    let filtered = this.projects;
                    
                    // Search filter
                    if (this.searchQuery) {
                        const query = this.searchQuery.toLowerCase();
                        filtered = filtered.filter(project => 
                            project.name.toLowerCase().includes(query) ||
                            project.description.toLowerCase().includes(query) ||
                            project.team.toLowerCase().includes(query)
                        );
                    }
                    
                    // Status filter
                    if (this.statusFilter) {
                        filtered = filtered.filter(project => project.status === this.statusFilter);
                    }
                    
                    // Priority filter
                    if (this.priorityFilter) {
                        filtered = filtered.filter(project => project.priority === this.priorityFilter);
                    }
                    
                    // Team filter
                    if (this.teamFilter) {
                        filtered = filtered.filter(project => project.team.toLowerCase().includes(this.teamFilter));
                    }
                    
                    return filtered;
                },

                searchProjects() {
                    // Search logic is handled by the computed property
                },

                filterProjects() {
                    // Filter logic is handled by the computed property
                },

                toggleProjectMenu(projectId) {
                    this.activeProjectMenu = this.activeProjectMenu === projectId ? null : projectId;
                },

                getStatusColor(status) {
                    const colors = {
                        'planning': 'bg-yellow-100 text-yellow-800',
                        'in_progress': 'bg-blue-100 text-blue-800',
                        'on_hold': 'bg-orange-100 text-orange-800',
                        'completed': 'bg-green-100 text-green-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                getPriorityColor(priority) {
                    const colors = {
                        'high': 'text-red-600',
                        'medium': 'text-yellow-600',
                        'low': 'text-green-600'
                    };
                    return colors[priority] || 'text-gray-600';
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString();
                },

                async refreshProjects() {
                    this.refreshing = true;
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    this.refreshing = false;
                },

                createProject() {
                    console.log('Creating new project...');
                    // Implement create project logic
                },

                viewProject(projectId) {
                    console.log('Viewing project:', projectId);
                    // Implement view project logic
                },

                editProject(projectId) {
                    console.log('Editing project:', projectId);
                    // Implement edit project logic
                },

                manageTasks(projectId) {
                    console.log('Managing tasks for project:', projectId);
                    // Implement manage tasks logic
                },

                manageTeam(projectId) {
                    console.log('Managing team for project:', projectId);
                    // Implement manage team logic
                },

                archiveProject(projectId) {
                    if (confirm('Are you sure you want to archive this project?')) {
                        const project = this.projects.find(p => p.id === projectId);
                        if (project) {
                            project.status = 'archived';
                        }
                    }
                },

                deleteProject(projectId) {
                    if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                        this.projects = this.projects.filter(p => p.id !== projectId);
                    }
                },

                exportProjects() {
                    console.log('Exporting projects...');
                    // Implement export logic
                }
            }
        }
    </script>

    <style>
        .project-card {
            transition: transform 0.2s ease-in-out;
        }

        .project-card:hover {
            transform: translateY(-2px);
        }
    </style>
</body>
</html>
