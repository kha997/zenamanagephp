
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Optimization Test - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .mobile-only { display: block; }
            .desktop-only { display: none; }
        }
        
        @media (min-width: 769px) {
            .mobile-only { display: none; }
            .desktop-only { display: block; }
        }
        
        /* Content padding for mobile */
        .mobile-content {
            padding-top: 60px;
            padding-bottom: 80px;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="mobileOptimization()">
    <!-- Mobile Header -->
    <div class="mobile-only fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-40">
        <div class="flex items-center justify-between px-4 py-3">
            <button @click="toggleMobileMenu()" class="p-2 text-gray-600 hover:text-gray-800">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <h1 class="text-lg font-bold text-gray-900">Mobile Test</h1>
            <div class="flex items-center space-x-2">
                <button class="p-2 text-gray-600 hover:text-gray-800">
                    <i class="fas fa-search text-lg"></i>
                </button>
                <button class="p-2 text-gray-600 hover:text-gray-800">
                    <i class="fas fa-bell text-lg"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Desktop Header -->
    <div class="desktop-only bg-white border-b border-gray-200 p-4">
        <h1 class="text-2xl font-bold text-gray-900">Mobile Optimization Test</h1>
        <p class="text-gray-600 mt-2">This page demonstrates mobile optimization features</p>
    </div>
    
    <!-- Main Content -->
    <div class="mobile-content p-4">
        <!-- Page Description -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-mobile-alt text-blue-500 mt-1"></i>
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">Mobile Optimization Features</h3>
                    <p class="text-blue-700 mt-1">
                        This page demonstrates FAB, Mobile Navigation, Responsive Design, and Touch Interactions
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <!-- FAB Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">FAB</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Floating Action Button for quick actions on mobile devices.
                </p>
            </div>
            
            <!-- Mobile Navigation Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bars text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Mobile Nav</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Bottom navigation bar for easy mobile navigation.
                </p>
            </div>
            
            <!-- Responsive Design Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-mobile-alt text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Responsive</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Responsive design that adapts to different screen sizes.
                </p>
            </div>
        </div>
        
        <!-- Sample Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Project Alpha</h4>
                <p class="text-gray-600 text-sm mb-3">Website redesign project</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">75% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Task Beta</h4>
                <p class="text-gray-600 text-sm mb-3">Mobile app development</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">25% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 25%"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Document Gamma</h4>
                <p class="text-gray-600 text-sm mb-3">Requirements document</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">100% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mobile Performance Metrics</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">95%</div>
                    <div class="text-sm text-green-800">Mobile Usability</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">2.1s</div>
                    <div class="text-sm text-blue-800">Load Time</div>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">98%</div>
                    <div class="text-sm text-purple-800">Touch Target Size</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <nav class="mobile-only fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50">
        <div class="flex justify-around items-center py-2">
            <a href="#" @click="setActiveTab('dashboard')" :class="activeTab === 'dashboard' ? 'text-blue-600' : 'text-gray-500'" class="flex flex-col items-center py-2 px-3 transition-colors">
                <i class="fas fa-tachometer-alt text-lg mb-1"></i>
                <span class="text-xs font-medium">Dashboard</span>
            </a>
            <a href="#" @click="setActiveTab('projects')" :class="activeTab === 'projects' ? 'text-blue-600' : 'text-gray-500'" class="flex flex-col items-center py-2 px-3 transition-colors">
                <i class="fas fa-project-diagram text-lg mb-1"></i>
                <span class="text-xs font-medium">Projects</span>
            </a>
            <a href="#" @click="setActiveTab('tasks')" :class="activeTab === 'tasks' ? 'text-blue-600' : 'text-gray-500'" class="flex flex-col items-center py-2 px-3 transition-colors">
                <i class="fas fa-tasks text-lg mb-1"></i>
                <span class="text-xs font-medium">Tasks</span>
            </a>
            <a href="#" @click="setActiveTab('calendar')" :class="activeTab === 'calendar' ? 'text-blue-600' : 'text-gray-500'" class="flex flex-col items-center py-2 px-3 transition-colors">
                <i class="fas fa-calendar-alt text-lg mb-1"></i>
                <span class="text-xs font-medium">Calendar</span>
            </a>
            <a href="#" @click="setActiveTab('team')" :class="activeTab === 'team' ? 'text-blue-600' : 'text-gray-500'" class="flex flex-col items-center py-2 px-3 transition-colors">
                <i class="fas fa-users text-lg mb-1"></i>
                <span class="text-xs font-medium">Team</span>
            </a>
        </div>
    </nav>
    
    <!-- FAB Button -->
    <button @click="toggleFabMenu()" :class="{ 'rotate-45': fabMenuOpen }" class="mobile-only fixed bottom-20 right-6 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-300 z-50">
        <i class="fas" :class="fabMenuOpen ? 'fa-times' : 'fa-plus'" class="text-xl"></i>
    </button>
    
    <!-- FAB Menu Items -->
    <div x-show="fabMenuOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="mobile-only fixed bottom-32 right-6 space-y-3 z-40">
        <button @click="quickAdd('project')" class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-project-diagram text-blue-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Project</span>
        </button>
        <button @click="quickAdd('task')" class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-tasks text-green-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Task</span>
        </button>
        <button @click="quickAdd('document')" class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors">
            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fas fa-file-alt text-purple-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Document</span>
        </button>
    </div>
    
    <!-- FAB Overlay -->
    <div x-show="fabMenuOpen" @click="fabMenuOpen = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mobile-only fixed inset-0 bg-black bg-opacity-25 z-30"></div>
    
    <!-- Mobile Menu Overlay -->
    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="mobile-only fixed inset-0 bg-black bg-opacity-50 z-50"></div>
    
    <!-- Mobile Menu Panel -->
    <div x-show="mobileMenuOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="mobile-only fixed top-0 left-0 h-full w-72 bg-white shadow-xl z-50 overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b border-gray-200">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cube text-white text-sm"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">Menu</h2>
            </div>
            <button @click="mobileMenuOpen = false" class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <nav class="p-4">
            <div class="space-y-1">
                <a href="#" @click="navigateTo('dashboard')" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-tachometer-alt text-gray-400 w-5"></i>
                    <span>Dashboard</span>
                </a>
                <a href="#" @click="navigateTo('projects')" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-project-diagram text-gray-400 w-5"></i>
                    <span>Projects</span>
                </a>
                <a href="#" @click="navigateTo('tasks')" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-tasks text-gray-400 w-5"></i>
                    <span>Tasks</span>
                </a>
                <a href="#" @click="navigateTo('calendar')" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-calendar-alt text-gray-400 w-5"></i>
                    <span>Calendar</span>
                </a>
                <a href="#" @click="navigateTo('team')" class="flex items-center space-x-3 px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                    <i class="fas fa-users text-gray-400 w-5"></i>
                    <span>Team</span>
                </a>
            </div>
        </nav>
    </div>
    
    <script>
        function mobileOptimization() {
            return {
                activeTab: 'dashboard',
                fabMenuOpen: false,
                mobileMenuOpen: false,
                
                setActiveTab(tab) {
                    this.activeTab = tab;
                },
                
                toggleFabMenu() {
                    this.fabMenuOpen = !this.fabMenuOpen;
                },
                
                toggleMobileMenu() {
                    this.mobileMenuOpen = !this.mobileMenuOpen;
                },
                
                quickAdd(type) {
                    this.fabMenuOpen = false;
                    const messages = {
                        project: 'Quick Add Project - This would open a project creation form',
                        task: 'Quick Add Task - This would open a task creation form',
                        document: 'Quick Add Document - This would open a document upload form'
                    };
                    alert(messages[type] || 'Quick add action triggered');
                },
                
                navigateTo(page) {
                    this.activeTab = page;
                    this.mobileMenuOpen = false;
                    console.log(`Navigating to: ${page}`);
                }
            }
        }
    </script>
</body>
</html><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-mobile-optimization.blade.php ENDPATH**/ ?>