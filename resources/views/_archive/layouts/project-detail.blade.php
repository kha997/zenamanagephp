<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card {
            @apply bg-white rounded-xl shadow-sm border border-gray-200 p-6;
        }
        .metric-card {
            @apply text-white rounded-xl shadow-lg;
        }
        .metric-card.green { @apply bg-gradient-to-r from-green-500 to-green-600; }
        .metric-card.blue { @apply bg-gradient-to-r from-blue-500 to-blue-600; }
        .metric-card.orange { @apply bg-gradient-to-r from-orange-500 to-orange-600; }
        .metric-card.purple { @apply bg-gradient-to-r from-purple-500 to-purple-600; }
        .metric-card.red { @apply bg-gradient-to-r from-red-500 to-red-600; }
        .metric-card.pink { @apply bg-gradient-to-r from-pink-500 to-pink-600; }
        .metric-card.indigo { @apply bg-gradient-to-r from-indigo-500 to-indigo-600; }
        .metric-card.teal { @apply bg-gradient-to-r from-teal-500 to-teal-600; }
    </style>
</head>
<body class="bg-gray-50">
    <div x-data="projectDetail()">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <button 
                            @click="goBack()"
                            class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <i class="fas fa-arrow-left text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">@yield('page-title')</h1>
                            <p class="text-gray-600 mt-1">@yield('page-description')</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button 
                            @click="refreshProject()"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        >
                            <i class="fas fa-sync-alt mr-2"></i>Refresh
                        </button>
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                @yield('user-initials')
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900">@yield('user-name')</div>
                                <div class="text-xs text-gray-500">Project Manager</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Navigation Tabs -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <nav class="flex space-x-8">
                    <button 
                        @click="activeTab = 'overview'"
                        :class="activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-chart-pie mr-2"></i>Overview
                    </button>
                    <button 
                        @click="activeTab = 'tasks'"
                        :class="activeTab === 'tasks' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-tasks mr-2"></i>Tasks
                    </button>
                    <button 
                        @click="activeTab = 'team'"
                        :class="activeTab === 'team' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-users mr-2"></i>Team
                    </button>
                    <button 
                        @click="activeTab = 'documents'"
                        :class="activeTab === 'documents' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-file-alt mr-2"></i>Documents
                    </button>
                    <button 
                        @click="activeTab = 'timeline'"
                        :class="activeTab === 'timeline' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-calendar-alt mr-2"></i>Timeline
                    </button>
                    <button 
                        @click="activeTab = 'settings'"
                        :class="activeTab === 'settings' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors"
                    >
                        <i class="fas fa-cog mr-2"></i>Settings
                    </button>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            @yield('content')
        </div>
    </div>

    <script>
        function projectDetail() {
            return {
                activeTab: 'overview',
                
                goBack() {
                    window.history.back();
                },
                
                refreshProject() {
                    // Refresh project data
                    location.reload();
                }
            }
        }
    </script>
</body>
</html>
