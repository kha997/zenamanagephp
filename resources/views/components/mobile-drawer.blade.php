{{-- Mobile Drawer Component --}}
<div class="mobile-drawer-container">
    <!-- Drawer Toggle Button -->
    <button 
        @click="toggleDrawer()"
        class="mobile-drawer-toggle fixed top-4 left-4 z-50 p-2 bg-white rounded-lg shadow-md text-gray-600 hover:text-gray-800 hover:bg-gray-50 transition-colors"
        aria-label="Open Navigation Menu"
    >
        <i class="fas fa-bars text-lg"></i>
    </button>
    
    <!-- Drawer Overlay -->
    <div 
        x-show="drawerOpen"
        @click="drawerOpen = false"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 z-40"
    ></div>
    
    <!-- Drawer Panel -->
    <div 
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 left-0 h-full w-80 bg-white shadow-xl z-50 overflow-y-auto"
    >
        <!-- Drawer Header -->
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cube text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">ZenaManage</h2>
            </div>
            <button 
                @click="drawerOpen = false"
                class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
            >
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!-- User Profile Section -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 bg-gray-300 rounded-full flex items-center justify-center">
                    <i class="fas fa-user text-gray-600"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">John Doe</h3>
                    <p class="text-sm text-gray-500">Project Manager</p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="p-4">
            <div class="space-y-2">
                <!-- Dashboard -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt text-gray-400 w-5"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Projects -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-project-diagram text-gray-400 w-5"></i>
                    <span>Projects</span>
                </a>
                
                <!-- Tasks -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-tasks text-gray-400 w-5"></i>
                    <span>Tasks</span>
                </a>
                
                <!-- Calendar -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-calendar-alt text-gray-400 w-5"></i>
                    <span>Calendar</span>
                </a>
                
                <!-- Documents -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-file-alt text-gray-400 w-5"></i>
                    <span>Documents</span>
                </a>
                
                <!-- Team -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-users text-gray-400 w-5"></i>
                    <span>Team</span>
                </a>
                
                <!-- Templates -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-layer-group text-gray-400 w-5"></i>
                    <span>Templates</span>
                </a>
                
                <!-- Settings -->
                <a href="#" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-cog text-gray-400 w-5"></i>
                    <span>Settings</span>
                </a>
            </div>
        </nav>
        
        <!-- Quick Actions Section -->
        <div class="p-4 border-t border-gray-200">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Quick Actions</h3>
            <div class="space-y-2">
                <button 
                    @click="quickAction('project')"
                    class="w-full flex items-center space-x-3 px-3 py-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                >
                    <i class="fas fa-plus text-sm"></i>
                    <span>New Project</span>
                </button>
                
                <button 
                    @click="quickAction('task')"
                    class="w-full flex items-center space-x-3 px-3 py-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                >
                    <i class="fas fa-plus text-sm"></i>
                    <span>New Task</span>
                </button>
                
                <button 
                    @click="quickAction('document')"
                    class="w-full flex items-center space-x-3 px-3 py-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                >
                    <i class="fas fa-plus text-sm"></i>
                    <span>Upload Document</span>
                </button>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="p-4 border-t border-gray-200 mt-auto">
            <button 
                @click="logout()"
                class="w-full flex items-center space-x-3 px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
            >
                <i class="fas fa-sign-out-alt text-sm"></i>
                <span>Logout</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mobileDrawer', () => ({
        drawerOpen: false,
        
        toggleDrawer() {
            this.drawerOpen = !this.drawerOpen;
        },
        
        quickAction(type) {
            this.drawerOpen = false;
            
            const messages = {
                project: 'Quick Action: New Project',
                task: 'Quick Action: New Task',
                document: 'Quick Action: Upload Document'
            };
            
            alert(messages[type] || 'Quick action triggered');
        },
        
        logout() {
            this.drawerOpen = false;
            if (confirm('Are you sure you want to logout?')) {
                alert('Logout functionality would be implemented here');
            }
        }
    }));
});
</script>