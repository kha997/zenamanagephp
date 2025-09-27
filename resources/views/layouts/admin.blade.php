<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Super Admin') - ZenaManage</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-50" x-data="{ 
    sidebarCollapsed: false,
    toggleSidebar() { 
        this.sidebarCollapsed = !this.sidebarCollapsed; 
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
    }
}" x-init="
    const saved = localStorage.getItem('sidebarCollapsed');
    if (saved !== null) {
        this.sidebarCollapsed = JSON.parse(saved);
    }
    console.log('Body initialized, sidebar collapsed:', this.sidebarCollapsed);
">
    <!-- Topbar -->
    @include('layouts.partials._topbar')
    
    <div class="flex">
        <!-- Sidebar - Desktop -->
        <div class="hidden lg:block">
            @include('layouts.partials._sidebar')
        </div>
                    
        
        <!-- Main Content -->
        <main class="flex-1 transition-all duration-300 pb-16 lg:pb-0" 
              :class="sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'">
            <!-- Breadcrumb -->
            <nav class="bg-white border-b border-gray-200 px-6 py-3">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="/admin" class="hover:text-gray-700">Super Admin</a></li>
                    @yield('breadcrumb')
                </ol>
            </nav>
            
            <!-- Page Content -->
            <div class="p-6">
                @yield('content')
            </div>
        </main>
    </div>
    
    <!-- Mobile Bottom Navigation -->
    <div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40">
        <div class="flex items-center justify-around py-2">
            <a href="/admin/dashboard" 
               class="flex flex-col items-center py-2 px-3 text-xs {{ request()->is('admin/dashboard') ? 'text-blue-600' : 'text-gray-500' }}">
                <i class="fas fa-tachometer-alt text-lg mb-1"></i>
                <span>Dashboard</span>
            </a>
            <a href="/admin/tenants" 
               class="flex flex-col items-center py-2 px-3 text-xs {{ request()->is('admin/tenants*') ? 'text-blue-600' : 'text-gray-500' }}">
                <i class="fas fa-building text-lg mb-1"></i>
                <span>Tenants</span>
            </a>
            <a href="/admin/users" 
               class="flex flex-col items-center py-2 px-3 text-xs {{ request()->is('admin/users*') ? 'text-blue-600' : 'text-gray-500' }}">
                <i class="fas fa-users text-lg mb-1"></i>
                <span>Users</span>
            </a>
            <div class="relative">
                <button @click="showMobileMenu = !showMobileMenu" 
                        class="flex flex-col items-center py-2 px-3 text-xs text-gray-500">
                    <i class="fas fa-ellipsis-h text-lg mb-1"></i>
                    <span>More</span>
                </button>
                
                <!-- Mobile Menu Dropdown -->
                <div x-show="showMobileMenu" @click.away="showMobileMenu = false" 
                     class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200">
                    <div class="py-1">
                        <a href="/admin/security" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-shield-alt mr-2"></i>Security
                        </a>
                        <a href="/admin/settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </a>
                        <a href="/admin/billing" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-credit-card mr-2"></i>Billing
                        </a>
                        <a href="/admin/maintenance" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-tools mr-2"></i>Maintenance
                        </a>
                        <a href="/admin/alerts" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Alerts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('layouts.partials._footer')
    
    <!-- Modals -->
    <div x-show="showModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" x-text="modalTitle"></h3>
                    <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
        </div>
                <div x-html="modalContent"></div>
                <div class="flex justify-end space-x-3 mt-6">
                    <button @click="closeModal" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button @click="executeModalAction" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
        
    <!-- Loading Overlay -->
    <div x-show="isLoading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
            <span class="text-gray-700">Loading...</span>
        </div>
        </div>
    
    @stack('scripts')
</body>
</html>
