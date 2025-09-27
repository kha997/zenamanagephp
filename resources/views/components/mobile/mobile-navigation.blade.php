{{-- Mobile Navigation Component --}}
<div class="mobile-navigation-container">
    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 md:hidden">
        <div class="flex justify-around items-center py-2">
            <!-- Dashboard -->
            <a 
                href="#" 
                @click="setActiveTab('dashboard')"
                :class="activeTab === 'dashboard' ? 'text-blue-600' : 'text-gray-500'"
                class="flex flex-col items-center py-2 px-3 transition-colors"
            >
                <i class="fas fa-tachometer-alt text-lg mb-1"></i>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            
            <!-- Projects -->
            <a 
                href="#" 
                @click="setActiveTab('projects')"
                :class="activeTab === 'projects' ? 'text-blue-600' : 'text-gray-500'"
                class="flex flex-col items-center py-2 px-3 transition-colors"
            >
                <i class="fas fa-project-diagram text-lg mb-1"></i>
                <span class="text-xs font-medium">Projects</span>
            </a>
            
            <!-- Tasks -->
            <a 
                href="#" 
                @click="setActiveTab('tasks')"
                :class="activeTab === 'tasks' ? 'text-blue-600' : 'text-gray-500'"
                class="flex flex-col items-center py-2 px-3 transition-colors"
            >
                <i class="fas fa-tasks text-lg mb-1"></i>
                <span class="text-xs font-medium">Tasks</span>
            </a>
            
            <!-- Calendar -->
            <a 
                href="#" 
                @click="setActiveTab('calendar')"
                :class="activeTab === 'calendar' ? 'text-blue-600' : 'text-gray-500'"
                class="flex flex-col items-center py-2 px-3 transition-colors"
            >
                <i class="fas fa-calendar-alt text-lg mb-1"></i>
                <span class="text-xs font-medium">Calendar</span>
            </a>
            
            <!-- Team -->
            <a 
                href="#" 
                @click="setActiveTab('team')"
                :class="activeTab === 'team' ? 'text-blue-600' : 'text-gray-500'"
                class="flex flex-col items-center py-2 px-3 transition-colors"
            >
                <i class="fas fa-users text-lg mb-1"></i>
                <span class="text-xs font-medium">Team</span>
            </a>
        </div>
    </nav>
    
    <!-- Mobile Header -->
    <header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-40 md:hidden">
        <div class="flex items-center justify-between px-4 py-3">
            <!-- Left: Menu Button -->
            <button 
                @click="toggleMobileMenu()"
                class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
            >
                <i class="fas fa-bars text-lg"></i>
            </button>
            
            <!-- Center: Title -->
            <h1 class="text-lg font-bold text-gray-900" x-text="currentPageTitle"></h1>
            
            <!-- Right: Actions -->
            <div class="flex items-center space-x-2">
                <button class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-search text-lg"></i>
                </button>
                <button class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-bell text-lg"></i>
                </button>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu Overlay -->
    <div 
        x-show="mobileMenuOpen"
        @click="mobileMenuOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 z-50"
    ></div>
    
    <!-- Mobile Menu Panel -->
    <div 
        x-show="mobileMenuOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 left-0 h-full w-72 bg-white shadow-xl z-50 overflow-y-auto"
    >
        <!-- Menu Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cube text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Menu</h2>
            </div>
            <button 
                @click="mobileMenuOpen = false"
                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- Menu Items -->
        <nav class="p-4">
            <div class="space-y-1">
                <a 
                    href="#" 
                    @click="navigateTo('dashboard')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-tachometer-alt text-gray-400 w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('projects')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-project-diagram text-gray-400 w-5"></i>
                    <span>Projects</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('tasks')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-tasks text-gray-400 w-5"></i>
                    <span>Tasks</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('calendar')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-calendar-alt text-gray-400 w-5"></i>
                    <span>Calendar</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('documents')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-file-alt text-gray-400 w-5"></i>
                    <span>Documents</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('team')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-users text-gray-400 w-5"></i>
                    <span>Team</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('templates')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-layer-group text-gray-400 w-5"></i>
                    <span>Templates</span>
                </a>
                
                <a 
                    href="#" 
                    @click="navigateTo('settings')"
                    class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                >
                    <i class="fas fa-cog text-gray-400 w-5"></i>
                    <span>Settings</span>
                </a>
            </div>
        </nav>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mobileNavigation', () => ({
        activeTab: 'dashboard',
        mobileMenuOpen: false,
        
        get currentPageTitle() {
            const titles = {
                dashboard: 'Dashboard',
                projects: 'Projects',
                tasks: 'Tasks',
                calendar: 'Calendar',
                documents: 'Documents',
                team: 'Team',
                templates: 'Templates',
                settings: 'Settings'
            };
            return titles[this.activeTab] || 'ZenaManage';
        },
        
        setActiveTab(tab) {
            this.activeTab = tab;
            this.mobileMenuOpen = false;
        },
        
        toggleMobileMenu() {
            this.mobileMenuOpen = !this.mobileMenuOpen;
        },
        
        navigateTo(page) {
            this.activeTab = page;
            this.mobileMenuOpen = false;
            
            // In a real app, this would trigger navigation
            console.log(`Navigating to: ${page}`);
        }
    }));
});
</script>