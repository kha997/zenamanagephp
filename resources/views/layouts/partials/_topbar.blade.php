{{-- Admin Topbar --}}
<header class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-40">
    <div class="px-6 py-4">
        <div class="flex items-center justify-between">
            <!-- Left Side -->
            <div class="flex items-center space-x-4">
                <button @click="toggleSidebar" class="text-gray-600 hover:text-gray-900 lg:hidden">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="flex items-center">
                    <i class="fas fa-crown text-yellow-500 text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold text-gray-900">Super Admin</h1>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                        <i class="fas fa-circle text-green-500 mr-1"></i>
                        System Online
                    </span>
                </div>
            </div>
            
            <!-- Right Side -->
            <div class="flex items-center space-x-4">
                <!-- Global Search -->
                <div class="hidden md:block relative">
                    <input type="text" 
                           x-model="globalSearchQuery"
                           @input.debounce.250ms="performGlobalSearch"
                           placeholder="Search tenants, users, errors..." 
                           class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           aria-label="Global Search">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                    
                    <!-- Global Search Results Dropdown -->
                    <div x-show="showGlobalSearchResults" 
                         @click.away="showGlobalSearchResults = false"
                         class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-80 overflow-y-auto">
                        <div class="p-3 border-b border-gray-200">
                            <h3 class="text-sm font-semibold text-gray-900">Global Search Results</h3>
                        </div>
                        
                        <!-- Tenants Results -->
                        <div x-show="globalSearchResults.tenants.length > 0" class="p-3 border-b border-gray-100">
                            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Tenants</h4>
                            <template x-for="result in globalSearchResults.tenants" :key="result.id">
                                <div class="p-2 hover:bg-gray-50 cursor-pointer rounded"
                                     @click="selectGlobalSearchResult(result)">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-building text-blue-600"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="result.name"></p>
                                            <p class="text-xs text-gray-500" x-text="result.domain"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Users Results -->
                        <div x-show="globalSearchResults.users.length > 0" class="p-3 border-b border-gray-100">
                            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Users</h4>
                            <template x-for="result in globalSearchResults.users" :key="result.id">
                                <div class="p-2 hover:bg-gray-50 cursor-pointer rounded"
                                     @click="selectGlobalSearchResult(result)">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-user text-green-600"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="result.name"></p>
                                            <p class="text-xs text-gray-500" x-text="result.email"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <!-- Errors Results -->
                        <div x-show="globalSearchResults.errors.length > 0" class="p-3 border-b border-gray-100">
                            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Errors</h4>
                            <template x-for="result in globalSearchResults.errors" :key="result.id">
                                <div class="p-2 hover:bg-gray-50 cursor-pointer rounded"
                                     @click="selectGlobalSearchResult(result)">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="result.message"></p>
                                            <p class="text-xs text-gray-500" x-text="result.time"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <div x-show="globalSearchResults.tenants.length === 0 && globalSearchResults.users.length === 0 && globalSearchResults.errors.length === 0 && globalSearchQuery.length > 0" 
                             class="p-3 text-center text-gray-500 text-sm">
                            No results found
                        </div>
                    </div>
                </div>
                
                <!-- Notifications -->
                <div class="relative">
                    <button @click="showNotifications = !showNotifications" 
                            class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i class="fas fa-bell text-xl"></i>
                        <span x-show="unreadNotifications > 0" x-text="unreadNotifications" 
                              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"></span>
                    </button>
                    
                    <!-- Notifications Dropdown -->
                    <div x-show="showNotifications" @click.away="showNotifications = false" 
                         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                                <button @click="markAllNotificationsAsRead" 
                                        class="text-sm text-blue-600 hover:text-blue-800">
                                    Mark all read
                                </button>
                            </div>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <template x-for="notification in notifications" :key="notification.id">
                                <div class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                                     @click="markNotificationAsRead(notification.id)">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <i :class="notification.type === 'warning' ? 'fas fa-exclamation-triangle text-yellow-500' : 'fas fa-info-circle text-blue-500'"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900" x-text="notification.title"></p>
                                            <p class="text-sm text-gray-500" x-text="notification.message"></p>
                                            <p class="text-xs text-gray-400 mt-1" x-text="notification.time"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="notifications.length === 0" class="p-4 text-center text-gray-500">
                                No notifications
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="relative">
                    <button @click="showUserMenu = !showUserMenu" 
                            class="flex items-center space-x-2 text-gray-700 hover:text-gray-900 focus:outline-none">
                        <img src="https://ui-avatars.com/api/?name=Super+Admin&background=3b82f6&color=ffffff" 
                             alt="Super Admin" class="h-8 w-8 rounded-full">
                        <span class="hidden md:block text-sm font-medium">Super Admin</span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div x-show="showUserMenu" @click.away="showUserMenu = false" 
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <hr class="my-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
