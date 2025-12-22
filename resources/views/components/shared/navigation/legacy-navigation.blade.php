<!-- Enhanced Navigation -->
<nav class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <!-- Logo -->
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <h1 class="text-2xl font-bold text-blue-600">ZenaManage</h1>
                </div>
            </div>

            <!-- Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="/app" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="/app/projects" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/projects*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-project-diagram mr-2"></i>Projects
                    <span class="ml-2 bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded-full">3</span>
                </a>
                <a href="/app/tasks" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/tasks*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-tasks mr-2"></i>Tasks
                    <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded-full">5</span>
                </a>
                <a href="/app/calendar" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/calendar*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-calendar mr-2"></i>Calendar
                </a>
                <a href="/app/team" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/team*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-users mr-2"></i>Team
                </a>
                <a href="/app/documents" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/documents*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-file-alt mr-2"></i>Documents
                </a>
                <a href="/app/templates" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/templates*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-layer-group mr-2"></i>Templates
                </a>
                <a href="/app/settings" class="flex items-center px-3 py-2 rounded-md text-sm font-medium {{ request()->is('app/settings*') ? 'bg-blue-100 text-blue-700 border-b-2 border-blue-500' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-cog mr-2"></i>Settings
                </a>
            </div>

            <!-- Right side actions -->
            <div class="flex items-center space-x-4">
                <!-- Search -->
                <div class="relative hidden md:block">
                    <input type="text" placeholder="Search..." 
                           class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>

                <!-- Notifications -->
                <div class="relative">
                    <button @click="toggleNotifications()" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md">
                        <i class="fas fa-bell text-lg"></i>
                        <span x-show="unreadNotifications > 0" x-text="unreadNotifications" 
                              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"></span>
                    </button>

                    <!-- Notifications Dropdown -->
                    <div x-show="showNotifications" @click.away="showNotifications = false" 
                         class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg z-50 border border-gray-200">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Notifications</h3>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <template x-for="notification in notifications" :key="notification.id">
                                <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <i :class="notification.icon" class="text-blue-500"></i>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <p class="text-sm font-medium text-gray-900" x-text="notification.title"></p>
                                            <p class="text-sm text-gray-600" x-text="notification.message"></p>
                                            <p class="text-xs text-gray-500 mt-1" x-text="notification.time"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="relative">
                    <button class="flex items-center space-x-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md p-2">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-medium">DU</span>
                        </div>
                        <span class="hidden md:block text-sm font-medium">Demo User</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <div class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="/app" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="/app/projects" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/projects*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-project-diagram mr-2"></i>Projects
                    <span class="ml-2 bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded-full">3</span>
                </a>
                <a href="/app/tasks" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/tasks*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-tasks mr-2"></i>Tasks
                    <span class="ml-2 bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded-full">5</span>
                </a>
                <a href="/app/calendar" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/calendar*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-calendar mr-2"></i>Calendar
                </a>
                <a href="/app/team" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/team*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-users mr-2"></i>Team
                </a>
                <a href="/app/documents" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/documents*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-file-alt mr-2"></i>Documents
                </a>
                <a href="/app/templates" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/templates*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-layer-group mr-2"></i>Templates
                </a>
                <a href="/app/settings" class="block px-3 py-2 rounded-md text-base font-medium {{ request()->is('app/settings*') ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                    <i class="fas fa-cog mr-2"></i>Settings
                </a>
            </div>
        </div>
    </div>
</nav>