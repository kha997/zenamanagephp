<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="tenant-id" content="{{ auth()->user()->tenant_id ?? '' }}">
    <title>@yield('title', 'Dashboard') - ZenaManage</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        // Suppress Tailwind CDN warning only if Tailwind CDN is present
        if (typeof tailwind !== 'undefined') {
            tailwind.config = { suppressWarnings: true };
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
    @yield('head')
    
    <!-- App Layout Alpine.js Component -->
    <script>
        // Use Alpine.data to define global components
        document.addEventListener('alpine:init', () => {
            Alpine.data('appLayout', () => ({
                // Notifications
                showNotifications: false,
                unreadNotifications: 0,
                notifications: [],
                
                // Alerts - Load from real API
                alerts: [],
                
                // Methods
                dismissAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                toggleNotifications() {
                    this.showNotifications = !this.showNotifications;
                }
            }));
        });
    </script>
    
    <style>
        body.loading {
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-gray-50" x-data="appLayout()">
    {{-- React HeaderShell --}}
    <x-shared.header-wrapper
        variant="app"
        :user="Auth::user()"
        :tenant="Auth::user()?->tenant"
        :navigation="app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'app')"
        :notifications="app(App\Services\HeaderService::class)->getNotifications(Auth::user())"
        :unread-count="app(App\Services\HeaderService::class)->getUnreadCount(Auth::user())"
        :theme="app(App\Services\HeaderService::class)->getUserTheme(Auth::user())"
        :breadcrumbs="app(App\Services\HeaderService::class)->getBreadcrumbs(request()->route()->getName(), request()->route()->parameters())"
    />
    {{-- Theme initialization script --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved theme
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.classList.toggle('dark', savedTheme === 'dark');
        });
    </script>
    
    <!-- Main Content with proper spacing -->
    <main class="pt-20">
        <!-- KPI Strip (if provided by page) -->
        @yield('kpi-strip')
        
        <!-- Alert Bar (if provided by page) -->
        @yield('alert-bar')
        
        <!-- Page Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            @yield('content')
        </div>
        
        <!-- Activity/History (if provided by page) -->
        @yield('activity')
    </main>

    <!-- Alpine.js Data -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appLayout', () => ({
                // Notifications
                showNotifications: false,
                unreadNotifications: 0,
                notifications: [],
                
                // Alerts - Load from real API
                alerts: [],
                
                // Methods
                dismissAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                toggleNotifications() {
                    this.showNotifications = !this.showNotifications;
                }
            }));
        });
    </script>
    
    @stack('scripts')
</body>
</html>