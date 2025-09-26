<!-- Professional Header Component -->
<header x-data="headerComponent()" class="bg-white shadow-lg border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo and Brand -->
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center shadow-lg">
                        <span class="text-white font-bold text-lg">Z</span>
                    </div>
                    <div class="flex flex-col">
                        <h1 class="text-xl font-bold text-gray-900 leading-tight">ZenaManage</h1>
                        <p class="text-xs text-gray-500 leading-tight">Project Management System</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu (for non-admin pages) -->
            <nav class="hidden md:flex items-center space-x-8" x-show="!isAdminPage">
                <a href="/app/dashboard" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="/app/projects" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-project-diagram mr-2"></i>Projects
                </a>
                <a href="/app/tasks" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-tasks mr-2"></i>Tasks
                </a>
                <a href="/app/team" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-users mr-2"></i>Team
                </a>
                <a href="/app/reports" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>Reports
                </a>
            </nav>

            <!-- Right Side Actions -->
            <div class="flex items-center space-x-4">
            <!-- User Greeting (for non-admin pages) -->
            <div class="hidden md:block flex-1 flex justify-center" x-show="!isAdminPage">
                <h3 class="text-lg font-semibold text-gray-800">
                    <span class="text-blue-600">Xin chào,</span> 
                    <span class="text-gray-900" x-text="userName || 'John Doe'">John Doe</span>
                </h3>
            </div>

            <!-- Search (for non-admin pages) -->
            <div class="hidden md:block" x-show="!isAdminPage">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" placeholder="Search..." 
                           class="block w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

                <!-- Notifications -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg">
                        <i class="fas fa-bell text-lg"></i>
                        <span class="absolute -top-1 -right-1 h-5 w-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">3</span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div x-show="open" @click.away="open = false" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <div class="p-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-project-diagram text-blue-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">New Project Assigned</p>
                                        <p class="text-xs text-gray-500">You have been assigned to "Website Redesign" project</p>
                                        <p class="text-xs text-gray-400 mt-1">2 minutes ago</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 hover:bg-gray-50 border-b border-gray-100">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-green-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">Task Completed</p>
                                        <p class="text-xs text-gray-500">"Update Documentation" has been completed</p>
                                        <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                        <i class="fas fa-exclamation-triangle text-yellow-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">Deadline Approaching</p>
                                        <p class="text-xs text-gray-500">"Mobile App Development" deadline in 2 days</p>
                                        <p class="text-xs text-gray-400 mt-1">3 hours ago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 border-t border-gray-200">
                            <a href="/app/notifications" class="text-sm text-blue-600 hover:text-blue-800 font-medium">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 text-gray-700 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-lg p-2">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">JD</span>
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-medium text-gray-900">John Doe</p>
                            <p class="text-xs text-gray-500">Project Manager</p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div x-show="open" @click.away="open = false" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-4 border-b border-gray-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                                    <span class="text-white font-bold">JD</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">John Doe</p>
                                    <p class="text-xs text-gray-500">john.doe@company.com</p>
                                    <p class="text-xs text-blue-600">Project Manager</p>
                                </div>
                            </div>
                        </div>
                        <div class="py-1">
                            <a href="/app/profile" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-3 text-gray-400"></i>
                                Profile Settings
                            </a>
                            <a href="/app/preferences" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Preferences
                            </a>
                            <a href="/app/help" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-question-circle mr-3 text-gray-400"></i>
                                Help & Support
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="/admin" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-shield-alt mr-3 text-gray-400"></i>
                                Admin Panel
                            </a>
                            <div class="border-t border-gray-200 my-1"></div>
                            <a href="/logout" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mobile Menu Button - REMOVED -->
                <!-- Hamburger button removed as navigation is already available -->
            </div>
        </div>

        <!-- Mobile Navigation Menu - REMOVED -->
        <!-- Mobile menu removed as navigation is already available in main layout -->
    </div>
</header>

<script>
// Header component logic
document.addEventListener('alpine:init', () => {
    Alpine.data('headerComponent', () => ({
        // mobileMenuOpen removed - no longer needed
        userName: 'John Doe',
        
        get isAdminPage() {
            return window.location.pathname.startsWith('/admin');
        },
        
        init() {
            // Initialize header component
            console.log('Header component initialized');
            
            // Get user name from session or auth
            this.getUserName();
        },
        
        getUserName() {
            // Try to get user name from various sources
            try {
                // Check if user data is available in session
                if (window.userData && window.userData.name) {
                    this.userName = window.userData.name;
                } else if (window.Auth && window.Auth.user && window.Auth.user.name) {
                    this.userName = window.Auth.user.name;
                } else {
                    // Default to John Doe
                    this.userName = 'John Doe';
                }
            } catch (error) {
                console.log('Using default user name');
                this.userName = 'John Doe';
            }
        }
    }));
});
</script>
