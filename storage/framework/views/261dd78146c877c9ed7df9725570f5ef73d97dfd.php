<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> - ZenaManage</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <script>
        // Suppress Tailwind CDN warning
        tailwind.config = { suppressWarnings: true };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
    <?php echo $__env->yieldContent('head'); ?>
    
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
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.header-wrapper','data' => ['variant' => 'app','user' => Auth::user(),'tenant' => Auth::user()->tenant ?? null,'notifications' => $notifications ?? [],'unreadCount' => $unreadCount ?? 0,'theme' => $theme ?? 'light']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.header-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'app','user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()),'tenant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()->tenant ?? null),'notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notifications ?? []),'unread-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($unreadCount ?? 0),'theme' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($theme ?? 'light')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

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
        <?php echo $__env->yieldContent('content'); ?>
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
</html><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/app.blade.php ENDPATH**/ ?>