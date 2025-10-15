<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin - ' . config('app.name', 'ZenaManage'))</title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .admin-kpi-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .admin-kpi-card-success {
            background: linear-gradient(135deg, #2ed573 0%, #7bed9f 100%);
        }
        .admin-kpi-card-warning {
            background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);
        }
        .admin-kpi-card-info {
            background: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50" x-data="adminLayout()">
    <!-- HeaderShell Wrapper for Admin -->
    <x-shared.header-wrapper 
        variant="admin" 
        :user="Auth::user()" 
        :tenant="Auth::user()?->tenant"
        :navigation="app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'admin')"
        :notifications="app(App\Services\HeaderService::class)->getNotifications(Auth::user())"
        :unread-count="app(App\Services\HeaderService::class)->getUnreadCount(Auth::user())"
        :alert-count="app(App\Services\HeaderService::class)->getAlertCount(Auth::user())"
        :theme="app(App\Services\HeaderService::class)->getUserTheme(Auth::user())"
        :breadcrumbs="app(App\Services\HeaderService::class)->getBreadcrumbs(request()->route()->getName(), request()->route()->parameters())"
    />

    <!-- Main Content with proper spacing -->
    <main class="pt-20">
        <!-- KPI Strip -->
        @yield('kpi-strip')
        
        <!-- Alert Bar -->
        @yield('alert-bar')
        
        <!-- Page Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            @yield('content')
        </div>
        
        <!-- Activity/History -->
        @yield('activity')
    </main>

    <!-- Alpine.js Data -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('adminLayout', () => ({
                // Theme management
                theme: '{{ app(App\Services\HeaderService::class)->getUserTheme(Auth::user()) }}',
                
                // Methods
                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    // Update user preference via API
                    fetch('/api/user/preferences/theme', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ theme: this.theme })
                    });
                }
            }));
        });
    </script>
</body>
</html>
