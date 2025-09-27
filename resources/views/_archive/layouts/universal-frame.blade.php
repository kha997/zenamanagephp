{{-- Universal Page Frame Template --}}
{{-- Follows ZenaManage UX/UI Design Rules --}}
{{-- Structure: Header → Global Nav → Page Nav → KPI Strip → Alert Bar → Main Content → Activity --}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="universalFrame()">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'ZenaManage')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Custom Styles -->
    <style>
        /* Universal Frame Styles */
        .universal-header {
            height: 4rem; /* 64px */
            z-index: 50;
        }
        
        .global-nav {
            height: 3.5rem; /* 56px */
            z-index: 40;
        }
        
        .page-nav {
            height: 3rem; /* 48px */
            z-index: 30;
        }
        
        .kpi-strip {
            min-height: 6rem; /* 96px for 1 row, 12rem for 2 rows */
        }
        
        .alert-bar {
            min-height: 2.5rem; /* 40px */
        }
        
        .main-content {
            min-height: calc(100vh - 4rem - 3.5rem - 3rem - 6rem - 2.5rem);
        }
        
        .activity-panel {
            min-height: 4rem; /* 64px */
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .universal-header {
                height: 3.5rem; /* 56px */
            }
            
            .global-nav {
                height: 3rem; /* 48px */
            }
            
            .page-nav {
                height: 2.5rem; /* 40px */
            }
            
            .kpi-strip {
                min-height: 8rem; /* 128px for mobile stacking */
            }
        }
        
        /* Focus Management */
        .focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }
        
        /* Theme Support */
        .theme-light {
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
        }
        
        .theme-dark {
            --bg-primary: #111827;
            --bg-secondary: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --border-color: #374151;
        }
    </style>
</head>

<body class="bg-gray-50 theme-light" :class="theme === 'dark' ? 'theme-dark' : 'theme-light'">
    <!-- Universal Header (Fixed) -->
    @include('components.universal-header')
    
    <!-- Global Navigation Row (Role-aware) -->
    @include('components.universal-navigation')
    
    <!-- Page Navigation Row (Local) -->
    <div class="page-nav bg-white border-b border-gray-200 sticky top-28 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-full">
                <!-- Breadcrumbs -->
                <nav aria-label="breadcrumb" class="flex items-center space-x-2 text-sm text-gray-500">
                    <span>@yield('breadcrumb-root', 'Dashboard')</span>
                    @if(isset($breadcrumbs))
                        @foreach($breadcrumbs as $breadcrumb)
                            <i class="fas fa-chevron-right text-xs"></i>
                            <span>{{ $breadcrumb }}</span>
                        @endforeach
                    @endif
                </nav>
                
                <!-- Local Tabs -->
                @if(isset($localTabs))
                    <div class="flex items-center space-x-1">
                        @foreach($localTabs as $tab)
                            <button @click="setActiveTab('{{ $tab['key'] }}')" 
                                    class="px-3 py-2 text-sm font-medium rounded-md transition-colors"
                                    :class="activeTab === '{{ $tab['key'] }}' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900'">
                                {{ $tab['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
                
                <!-- Primary Contextual Actions -->
                <div class="flex items-center space-x-2">
                    @yield('contextual-actions')
                </div>
            </div>
        </div>
    </div>
    
    <!-- KPI Strip (Above Alert Bar) -->
    @include('components.kpi-strip')
    
    <!-- Alert Bar (Page-scoped) -->
    @include('components.alert-bar')
    
    <!-- Main Content Area -->
    <main class="main-content max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">@yield('page-title', 'Dashboard')</h1>
            @if(isset($pageDescription))
                <p class="mt-2 text-gray-600">{{ $pageDescription }}</p>
            @endif
        </div>
        
        <!-- Main Content -->
        @yield('content')
    </main>
    
    <!-- Activity / History Panel -->
    @include('components.activity-panel')
    
    <!-- Mobile Components -->
    @include('components.mobile-fab')
    @include('components.mobile-drawer')
    @include('components.mobile-navigation')
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('universalFrame', () => ({
                // Theme Management
                theme: localStorage.getItem('theme') || 'light',
                
                // Tab Management
                activeTab: 'overview',
                
                // KPI Management
                kpiCards: [],
                kpiRows: 1, // 1 or 2 rows
                
                // Alert Management
                alerts: [],
                
                // Activity Management
                activities: [],
                activityCollapsed: true,
                
                // Mobile Management
                mobileMenuOpen: false,
                
                init() {
                    this.loadUserPreferences();
                    this.loadKPIs();
                    this.loadAlerts();
                    this.loadActivities();
                    this.setupKeyboardShortcuts();
                },
                
                // Theme Toggle
                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    document.body.className = this.theme === 'dark' ? 'theme-dark' : 'theme-light';
                },
                
                // Tab Management
                setActiveTab(tab) {
                    this.activeTab = tab;
                },
                
                // KPI Management
                loadKPIs() {
                    // This will be implemented with actual API calls
                    this.kpiCards = [
                        { id: 1, title: 'Active Projects', value: '12', delta: '+2', period: 'vs last month', link: '/app/projects?status=active' },
                        { id: 2, title: 'Overdue Tasks', value: '5', delta: '-1', period: 'vs last week', link: '/app/tasks?status=overdue' },
                        { id: 3, title: 'Team Members', value: '8', delta: '+1', period: 'vs last month', link: '/app/team' },
                        { id: 4, title: 'Documents', value: '24', delta: '+3', period: 'vs last week', link: '/app/documents' }
                    ];
                },
                
                // Alert Management
                loadAlerts() {
                    // This will be implemented with actual API calls
                    this.alerts = [
                        { id: 1, level: 'critical', message: 'Project deadline approaching', action: 'resolve' },
                        { id: 2, level: 'high', message: 'Team member offline', action: 'acknowledge' }
                    ];
                },
                
                // Activity Management
                loadActivities() {
                    // This will be implemented with actual API calls
                    this.activities = [
                        { id: 1, action: 'Created project', user: 'John Doe', time: '2 hours ago' },
                        { id: 2, action: 'Updated task', user: 'Jane Smith', time: '4 hours ago' }
                    ];
                },
                
                // User Preferences
                loadUserPreferences() {
                    const preferences = JSON.parse(localStorage.getItem('userPreferences') || '{}');
                    this.kpiRows = preferences.kpiRows || 1;
                    this.activityCollapsed = preferences.activityCollapsed !== false;
                },
                
                saveUserPreferences() {
                    const preferences = {
                        kpiRows: this.kpiRows,
                        activityCollapsed: this.activityCollapsed
                    };
                    localStorage.setItem('userPreferences', JSON.stringify(preferences));
                },
                
                // Keyboard Shortcuts
                setupKeyboardShortcuts() {
                    document.addEventListener('keydown', (e) => {
                        // Search shortcut (/)
                        if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                            e.preventDefault();
                            this.focusSearch();
                        }
                        
                        // Save shortcut (Ctrl/Cmd+S)
                        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                            e.preventDefault();
                            this.saveCurrentView();
                        }
                        
                        // Filters shortcut (F)
                        if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
                            e.preventDefault();
                            this.toggleFilters();
                        }
                        
                        // Escape key
                        if (e.key === 'Escape') {
                            this.closeModals();
                        }
                    });
                },
                
                // Action Methods
                focusSearch() {
                    const searchInput = document.querySelector('[data-search-input]');
                    if (searchInput) {
                        searchInput.focus();
                    }
                },
                
                saveCurrentView() {
                    // Implement save current view functionality
                    console.log('Saving current view...');
                },
                
                toggleFilters() {
                    // Implement toggle filters functionality
                    console.log('Toggling filters...');
                },
                
                closeModals() {
                    this.mobileMenuOpen = false;
                    // Close any open modals/drawers
                },
                
                // Alert Actions
                resolveAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                acknowledgeAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                muteAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                // Activity Actions
                toggleActivity() {
                    this.activityCollapsed = !this.activityCollapsed;
                    this.saveUserPreferences();
                }
            }));
        });
    </script>
</body>
</html>
