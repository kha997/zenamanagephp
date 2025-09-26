{{-- Tenant Dashboard Main Page --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50" x-data="tenantDashboard()">
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
                        @click="refreshDashboard()"
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
                <a href="#" class="text-blue-600 border-b-2 border-blue-600 px-1 py-2 text-sm font-medium">
                    Dashboard
                </a>
                <a href="#" class="text-gray-500 hover:text-gray-700 px-1 py-2 text-sm font-medium">
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
        <!-- Page Title -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-lg text-gray-600 mt-2">
                Overview of your projects and team activities
            </p>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Active Projects -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600">Active Projects</p>
                        <p class="text-2xl font-bold text-blue-900" x-text="stats.activeProjects">12</p>
                        <p class="text-xs text-blue-600 mt-1">
                            <span class="text-green-600">+2</span> this month
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-folder text-blue-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Tasks Completed -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600">Tasks Completed</p>
                        <p class="text-2xl font-bold text-green-900" x-text="stats.tasksCompleted">247</p>
                        <p class="text-xs text-green-600 mt-1">
                            <span class="text-green-600">+15%</span> this week
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600">Team Members</p>
                        <p class="text-2xl font-bold text-purple-900" x-text="stats.teamMembers">8</p>
                        <p class="text-xs text-purple-600 mt-1">
                            <span class="text-green-600">All active</span>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-orange-600">Documents</p>
                        <p class="text-2xl font-bold text-orange-900" x-text="stats.documents">156</p>
                        <p class="text-xs text-orange-600 mt-1">
                            <span class="text-green-600">+8</span> this week
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-file-alt text-orange-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Recent Projects -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Recent Projects</h2>
                            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <template x-for="project in recentProjects" :key="project.id">
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                            <i class="fas fa-folder text-gray-600"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900" x-text="project.name"></h3>
                                            <p class="text-xs text-gray-500" x-text="project.description"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-900" x-text="project.progress + '%'"></div>
                                            <div class="w-16 bg-gray-200 rounded-full h-2 mt-1">
                                                <div class="bg-blue-600 h-2 rounded-full" :style="`width: ${project.progress}%`"></div>
                                            </div>
                                        </div>
                                        <span 
                                            class="px-2 py-1 text-xs font-medium rounded-full"
                                            :class="getProjectStatusColor(project.status)"
                                            x-text="project.status"
                                        ></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Tasks -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900">Upcoming Tasks</h2>
                            <a href="#" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View All
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <template x-for="task in upcomingTasks" :key="task.id">
                                <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <input 
                                            type="checkbox" 
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        >
                                        <div>
                                            <h4 class="text-sm font-medium text-gray-900" x-text="task.title"></h4>
                                            <p class="text-xs text-gray-500" x-text="task.project"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span 
                                            class="px-2 py-1 text-xs font-medium rounded-full"
                                            :class="getTaskPriorityColor(task.priority)"
                                            x-text="task.priority"
                                        ></span>
                                        <span class="text-xs text-gray-500" x-text="formatDate(task.due_date)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Quick Actions -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="#" class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-plus text-blue-600 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">New Project</span>
                            </a>
                            
                            <a href="#" class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-tasks text-green-600 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">New Task</span>
                            </a>
                            
                            <a href="#" class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-plus text-purple-600 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Invite Member</span>
                            </a>
                            
                            <a href="#" class="flex items-center space-x-3 p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-upload text-orange-600 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-900">Upload Document</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Team Activity -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Team Activity</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <template x-for="activity in teamActivities" :key="activity.id">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-900" x-text="activity.description"></p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="formatTime(activity.created_at)"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Project Progress -->
                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Project Progress</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <template x-for="project in projectProgress" :key="project.id">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-medium text-gray-900" x-text="project.name"></span>
                                        <span class="text-sm text-gray-500" x-text="project.progress + '%'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="`width: ${project.progress}%`"></div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function tenantDashboard() {
            return {
                refreshing: false,
                stats: {
                    activeProjects: 12,
                    tasksCompleted: 247,
                    teamMembers: 8,
                    documents: 156
                },
                recentProjects: [
                    {
                        id: 1,
                        name: 'Website Redesign',
                        description: 'Complete overhaul of company website',
                        progress: 75,
                        status: 'in_progress'
                    },
                    {
                        id: 2,
                        name: 'Mobile App Development',
                        description: 'iOS and Android app development',
                        progress: 45,
                        status: 'in_progress'
                    },
                    {
                        id: 3,
                        name: 'Marketing Campaign',
                        description: 'Q4 marketing campaign launch',
                        progress: 90,
                        status: 'in_progress'
                    }
                ],
                upcomingTasks: [
                    {
                        id: 1,
                        title: 'Review design mockups',
                        project: 'Website Redesign',
                        priority: 'high',
                        due_date: '2025-09-25T10:00:00Z'
                    },
                    {
                        id: 2,
                        title: 'Update project documentation',
                        project: 'Mobile App Development',
                        priority: 'medium',
                        due_date: '2025-09-26T14:00:00Z'
                    },
                    {
                        id: 3,
                        title: 'Prepare marketing materials',
                        project: 'Marketing Campaign',
                        priority: 'high',
                        due_date: '2025-09-27T09:00:00Z'
                    }
                ],
                teamActivities: [
                    {
                        id: 1,
                        description: 'John completed task "Review design mockups"',
                        created_at: '2025-09-24T10:30:00Z'
                    },
                    {
                        id: 2,
                        description: 'Sarah uploaded new project document',
                        created_at: '2025-09-24T09:15:00Z'
                    },
                    {
                        id: 3,
                        description: 'Mike created new project "Mobile App Development"',
                        created_at: '2025-09-24T08:45:00Z'
                    }
                ],
                projectProgress: [
                    {
                        id: 1,
                        name: 'Website Redesign',
                        progress: 75
                    },
                    {
                        id: 2,
                        name: 'Mobile App Development',
                        progress: 45
                    },
                    {
                        id: 3,
                        name: 'Marketing Campaign',
                        progress: 90
                    },
                    {
                        id: 4,
                        name: 'Database Migration',
                        progress: 30
                    }
                ],

                async refreshDashboard() {
                    this.refreshing = true;
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    this.refreshing = false;
                },

                getProjectStatusColor(status) {
                    const colors = {
                        'in_progress': 'bg-blue-100 text-blue-800',
                        'completed': 'bg-green-100 text-green-800',
                        'on_hold': 'bg-yellow-100 text-yellow-800',
                        'cancelled': 'bg-red-100 text-red-800'
                    };
                    return colors[status] || 'bg-gray-100 text-gray-800';
                },

                getTaskPriorityColor(priority) {
                    const colors = {
                        'high': 'bg-red-100 text-red-800',
                        'medium': 'bg-yellow-100 text-yellow-800',
                        'low': 'bg-green-100 text-green-800'
                    };
                    return colors[priority] || 'bg-gray-100 text-gray-800';
                },

                formatDate(dateString) {
                    const date = new Date(dateString);
                    return date.toLocaleDateString();
                },

                formatTime(timestamp) {
                    const date = new Date(timestamp);
                    const now = new Date();
                    const diff = now - date;
                    const minutes = Math.floor(diff / 60000);
                    
                    if (minutes < 1) return 'Just now';
                    if (minutes < 60) return `${minutes}m ago`;
                    if (minutes < 1440) return `${Math.floor(minutes / 60)}h ago`;
                    return `${Math.floor(minutes / 1440)}d ago`;
                }
            }
        }
    </script>
</body>
</html>
