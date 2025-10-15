<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - ZenaManage</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Suppress Tailwind CDN warning
        tailwind.config = { suppressWarnings: true };
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
    <!-- Unified HeaderShell -->
    <x-shared.header-wrapper 
        variant="app"
        :user="Auth::user()"
        :tenant="Auth::user()->tenant ?? null"
        :notifications="$notifications ?? []"
        :unread-count="$unreadCount ?? 0"
        :theme="$theme ?? 'light'"
    />

    <script>
        // Shared Header component logic
        document.addEventListener('alpine:init', () => {
            Alpine.data('sharedHeaderComponent', () => ({
                // Header State
                notificationsOpen: false,
                userMenuOpen: false,
                theme: 'light',
                alerts: [],
                
                // Header Actions
                toggleNotifications() {
                    // Close other dropdowns
                    this.userMenuOpen = false;
                    this.closeOtherDropdowns();
                    this.notificationsOpen = !this.notificationsOpen;
                },
                
                toggleUserMenu() {
                    // Close other dropdowns
                    this.notificationsOpen = false;
                    this.closeOtherDropdowns();
                    this.userMenuOpen = !this.userMenuOpen;
                },
                
                closeOtherDropdowns() {
                    // Close focus mode dropdown if open
                    const focusModeComponent = document.querySelector('[data-focus-mode-toggle]');
                    if (focusModeComponent && focusModeComponent._x_dataStack) {
                        const focusData = focusModeComponent._x_dataStack[0];
                        if (focusData && focusData.isActive) {
                            focusData.isActive = false;
                        }
                    }
                },
                
                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    // Apply theme to document
                    document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    // Save theme preference
                    localStorage.setItem('theme', this.theme);
                },
                
                resolveAlert(alertId) {
                    this.alerts = this.alerts.filter(alert => alert.id !== alertId);
                },
                
                acknowledgeAlert(alertId) {
                    // Mark alert as acknowledged (could send to server)
                    console.log('Alert acknowledged:', alertId);
                },
                
                init() {
                    // Load saved theme
                    const savedTheme = localStorage.getItem('theme');
                    if (savedTheme) {
                        this.theme = savedTheme;
                        document.documentElement.classList.toggle('dark', this.theme === 'dark');
                    }
                }
            }));
        });
    </script>
    
    <!-- Main Content with proper spacing -->
    <main class="pt-20">
        @yield('content')
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
</body>
</html>