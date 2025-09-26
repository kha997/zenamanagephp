@extends('layouts.admin-base')

@section('title', \App\Services\BreadcrumbService::getPageTitle())

@section('content')
<div x-data="adminSPA()" class="min-h-screen bg-gray-50">
    <!-- Include Admin Header Component -->
    @include('components.admin-header')
    
    <!-- Admin Navigation Menu -->
    <div class="bg-white shadow-sm border-b sticky top-20 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Navigation Menu -->
            <nav class="border-t border-gray-200 py-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <!-- Dashboard -->
                        <button @click="navigateTo('dashboard')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'dashboard' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </button>
                        
                        <!-- Users -->
                        <button @click="navigateTo('users')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'users' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-users"></i>
                            <span>Users</span>
                        </button>
                        
                        <!-- Tenants -->
                        <button @click="navigateTo('tenants')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'tenants' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-building"></i>
                            <span>Tenants</span>
                        </button>
                        
                        <!-- Projects -->
                        <button @click="navigateTo('projects')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'projects' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-project-diagram"></i>
                            <span>Projects</span>
                        </button>
                        
                        <!-- Tasks -->
                        <button @click="navigateTo('tasks')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'tasks' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-tasks"></i>
                            <span>Tasks</span>
                        </button>
                        
                        <!-- Security -->
                        <button @click="navigateTo('security')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'security' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-shield-alt"></i>
                            <span>Security</span>
                        </button>
                        
                        <!-- Alerts -->
                        <button @click="navigateTo('alerts')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'alerts' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Alerts</span>
                            <span class="notification-badge bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold">3</span>
                        </button>
                        
                        <!-- Activities -->
                        <button @click="navigateTo('activities')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'activities' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-history"></i>
                            <span>Activities</span>
                        </button>
                        
                        <!-- Analytics -->
                        <button @click="navigateTo('analytics')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'analytics' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-chart-bar"></i>
                            <span>Analytics</span>
                        </button>
                        
                        <!-- Maintenance -->
                        <button @click="navigateTo('maintenance')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'maintenance' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-tools"></i>
                            <span>Maintenance</span>
                        </button>
                        
                        <!-- Settings -->
                        <button @click="navigateTo('settings')" class="admin-nav-button flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold" :class="currentView === 'settings' ? 'active text-white' : 'text-gray-600 hover:text-gray-900'">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </button>
                    </div>
                    
                    <!-- Admin Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- User menu moved to header -->
                    </div>
                </div>
            </nav>
            
            <!-- Action Buttons Row -->
            <div class="border-t border-gray-200 py-3">
                <div class="flex items-center justify-end">
                    <div class="flex items-center space-x-4">
                        <!-- Action buttons moved to header -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Area -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                <span>Admin</span>
                <i class="fas fa-chevron-right text-xs"></i>
                <span x-text="getBreadcrumbTitle()"></span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900" x-text="getPageTitle()"></h1>
            <p class="mt-2 text-gray-600" x-text="getPageDescription()"></p>
        </div>
        <!-- Dashboard View -->
        <div x-show="currentView === 'dashboard'" x-transition>
            @include('admin.dashboard-content')
        </div>
        
        <!-- Users View -->
        <div x-show="currentView === 'users'" x-transition>
            @include('admin.users-content')
        </div>
        
        <!-- Tenants View -->
        <div x-show="currentView === 'tenants'" x-transition>
            @include('admin.tenants-content')
        </div>
        
        <!-- Projects View -->
        <div x-show="currentView === 'projects'" x-transition>
            @include('admin.projects-content')
        </div>
        
        <!-- Tasks View -->
        <div x-show="currentView === 'tasks'" x-transition>
            @include('admin.tasks-content')
        </div>
        
        <!-- Security View -->
        <div x-show="currentView === 'security'" x-transition>
            @include('admin.security-content')
        </div>
        
        <!-- Alerts View -->
        <div x-show="currentView === 'alerts'" x-transition>
            @include('admin.alerts-content')
        </div>
        
        <!-- Activities View -->
        <div x-show="currentView === 'activities'" x-transition>
            @include('admin.activities-content')
        </div>
        
        <!-- Analytics View -->
        <div x-show="currentView === 'analytics'" x-transition>
            @include('admin.analytics-content')
        </div>
        
        <!-- Maintenance View -->
        <div x-show="currentView === 'maintenance'" x-transition>
            @include('admin.maintenance-content')
        </div>
        
        <!-- Settings View -->
        <div x-show="currentView === 'settings'" x-transition>
            @include('admin.settings-content')
        </div>
    </main>
</div>

<style>
    /* Custom styles for admin navigation buttons */
    .admin-nav-button {
        transition: all 0.2s ease-in-out;
        position: relative;
        overflow: hidden;
    }

    .admin-nav-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.3) 50%, rgba(255,255,255,0) 100%);
        transition: all 0.5s ease-in-out;
    }

    .admin-nav-button:hover::before {
        left: 100%;
    }

    .admin-nav-button.active {
        background-color: #dc2626; /* Red-600 */
        color: white;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .admin-nav-button:hover:not(.active) {
        background-color: #fef2f2; /* Red-50 */
        color: #991b1b; /* Red-800 */
    }

    /* Notification Badge Styling */
    .notification-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        transform: translate(50%, -50%);
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 6px;
        font-size: 0.75rem;
        font-weight: 700;
        border-radius: 9999px;
        background-color: #ef4444;
        color: white;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('adminSPA', () => ({
            currentView: 'dashboard',
            refreshing: false,

            init() {
                this.loadInitialView();
                window.addEventListener('popstate', (event) => {
                    if (event.state && event.state.view) {
                        this.currentView = event.state.view;
                    } else {
                        this.loadInitialView();
                    }
                });
                
                // Listen for navigation events from header
                window.addEventListener('admin-navigate', (event) => {
                    this.navigateTo(event.detail.view);
                });
            },

            loadInitialView() {
                const path = window.location.pathname;
                const view = path.split('/').pop();
                if (['users', 'tenants', 'projects', 'tasks', 'security', 'alerts', 'activities', 'analytics', 'maintenance', 'settings'].includes(view)) {
                    this.currentView = view;
                } else {
                    this.currentView = 'dashboard';
                }
                window.history.replaceState({ view: this.currentView }, '', `/admin/${this.currentView}`);
            },

            navigateTo(view) {
                this.currentView = view;
                window.history.pushState({ view: view }, '', `/admin/${view}`);
            },

            refreshData() {
                this.refreshing = true;
                // Simulate data fetching
                setTimeout(() => {
                    console.log('Admin data refreshed!');
                    this.refreshing = false;
                    // In a real application, you would re-fetch data for the currentView
                }, 1500);
            },
            
            getBreadcrumbTitle() {
                const titles = {
                    'dashboard': 'Dashboard',
                    'users': 'Users',
                    'tenants': 'Tenants',
                    'projects': 'Projects',
                    'tasks': 'Tasks',
                    'security': 'Security',
                    'alerts': 'Alerts',
                    'activities': 'Activities',
                    'analytics': 'Analytics',
                    'maintenance': 'Maintenance',
                    'settings': 'Settings'
                };
                return titles[this.currentView] || 'Dashboard';
            },
            
            getPageTitle() {
                const titles = {
                    'dashboard': 'Admin Dashboard',
                    'users': 'User Management',
                    'tenants': 'Tenant Management',
                    'projects': 'Project Management',
                    'tasks': 'System-wide Task Monitoring',
                    'security': 'Security Management',
                    'alerts': 'System Alerts',
                    'activities': 'Activity Monitoring',
                    'analytics': 'Advanced Analytics',
                    'maintenance': 'System Maintenance',
                    'settings': 'System Settings'
                };
                return titles[this.currentView] || 'Admin Dashboard';
            },
            
            getPageDescription() {
                const descriptions = {
                    'dashboard': 'Monitor system performance and key metrics',
                    'users': 'Manage user accounts and permissions',
                    'tenants': 'Manage tenant organizations and settings',
                    'projects': 'Oversee all projects across tenants',
                    'tasks': 'Monitor and investigate tasks across all tenants for system oversight',
                    'security': 'Manage security policies and monitor threats',
                    'alerts': 'View and manage system alerts and notifications',
                    'activities': 'Monitor user and system activities',
                    'analytics': 'Advanced reporting and analytics dashboard',
                    'maintenance': 'System administration and maintenance tools',
                    'settings': 'Configure system-wide settings'
                };
                return descriptions[this.currentView] || 'Monitor system performance and key metrics';
            }
        }));
    });
</script>
@endsection
