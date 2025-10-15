


<header x-data="sharedHeaderComponent()" class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-header">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-full">
            <!-- Left Side: Logo + Brand + Greeting -->
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cube text-white text-sm"></i>
                    </div>
                    <span class="text-xl font-bold text-gray-900">ZenaManage</span>
                </div>
                
                <!-- Greeting (Hidden on mobile) -->
                <div class="hidden md:block">
                    <span class="text-sm text-gray-600">
                        Hello, <span class="font-medium text-gray-900"><?php echo e(optional(Auth::user())->first_name ?? 'User'); ?></span>
                    </span>
                </div>
            </div>
            
            <!-- Center: Navigation Menu (for non-admin pages) -->
            <nav class="hidden lg:flex items-center space-x-8">
                <a href="<?php echo e(route('app.dashboard')); ?>" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="<?php echo e(route('app.projects.index')); ?>" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-project-diagram mr-2"></i>Projects
                </a>
                <a href="<?php echo e(route('app.tasks.index')); ?>" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-tasks mr-2"></i>Tasks
                </a>
                <a href="<?php echo e(route('app.team.index')); ?>" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-users mr-2"></i>Team
                </a>
                <a href="<?php echo e(route('app.reports.index')); ?>" class="text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>Reports
                </a>
            </nav>
            
            <!-- Right Side: Notifications + Theme Toggle + User Avatar -->
            <div class="flex items-center space-x-3">
                <!-- Notification Dropdown -->
                <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.notification-dropdown','data' => ['notifications' => $notifications ?? [],'unreadCount' => $unreadCount ?? 0]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.notification-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notifications ?? []),'unread-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($unreadCount ?? 0)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                
                <!-- Theme Toggle -->
                <button @click="toggleTheme()" 
                        class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Toggle theme">
                    <i class="fas fa-sun" x-show="theme === 'light'"></i>
                    <i class="fas fa-moon" x-show="theme === 'dark'"></i>
                </button>
                
                <!-- Focus Mode Toggle -->
                <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.focus-toggle','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.focus-toggle'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                
                <!-- User Avatar Dropdown -->
                <div class="relative">
                    <button @click="toggleUserMenu" 
                            class="flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <!-- Avatar -->
                        <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-medium">
                                <?php echo e(strtoupper(substr(optional(Auth::user())->first_name ?? 'U', 0, 1))); ?>

                            </span>
                        </div>
                        <!-- Name (Hidden on mobile) -->
                        <span class="hidden md:block text-sm font-medium text-gray-900">
                            <?php echo e(optional(Auth::user())->first_name ?? 'User'); ?> <?php echo e(optional(Auth::user())->last_name ?? ''); ?>

                        </span>
                        <!-- Dropdown Arrow -->
                        <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                    </button>
                    
                    <!-- User Menu Dropdown -->
                    <div x-show="userMenuOpen" 
                         x-transition
                         @click.away="userMenuOpen = false"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-header-dropdown">
                        <div class="py-1">
                            <!-- Profile -->
                            <a href="<?php echo e(route('app.profile')); ?>" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-3 text-gray-400"></i>
                                Profile
                            </a>
                            
                            <!-- Settings -->
                            <a href="<?php echo e(route('app.settings.index')); ?>" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Settings
                            </a>
                            
                            <!-- Switch Tenant (if applicable) -->
                            <?php if(Auth::user() && Auth::user()->hasRole('super_admin')): ?>
                                <a href="<?php echo e(route('admin.tenants.index')); ?>" 
                                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-building mr-3 text-gray-400"></i>
                                    Switch Tenant
                                </a>
                            <?php endif; ?>
                            
                            <!-- Divider -->
                            <div class="border-t border-gray-200 my-1"></div>
                            
                            <!-- Logout -->
                            <form method="POST" action="<?php echo e(route('logout')); ?>" class="block">
                                <?php echo csrf_field(); ?>
                                <button type="submit" 
                                        class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-sign-out-alt mr-3 text-gray-400"></i>
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

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
                this.notificationsOpen = !this.notificationsOpen;
            },
            
            toggleUserMenu() {
                this.userMenuOpen = !this.userMenuOpen;
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
</script><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_legacy/headers/shared-header-legacy.blade.php ENDPATH**/ ?>