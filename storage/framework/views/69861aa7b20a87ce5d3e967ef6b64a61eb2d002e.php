


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $isAdmin = $variant === 'admin';
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
    
    // Default navigation based on variant
    if (empty($navigation)) {
        if ($isAdmin) {
            $navigation = [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'admin.dashboard'],
                ['key' => 'users', 'label' => 'Users', 'icon' => 'fas fa-users', 'route' => 'admin.users.index'],
                ['key' => 'tenants', 'label' => 'Tenants', 'icon' => 'fas fa-building', 'route' => 'admin.tenants.index'],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'admin.projects.index'],
                ['key' => 'security', 'label' => 'Security', 'icon' => 'fas fa-shield-alt', 'route' => 'admin.security.index'],
                ['key' => 'alerts', 'label' => 'Alerts', 'icon' => 'fas fa-exclamation-triangle', 'route' => 'admin.alerts.index', 'badge' => $alertCount],
                ['key' => 'activities', 'label' => 'Activities', 'icon' => 'fas fa-history', 'route' => 'admin.activities.index'],
                ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'fas fa-chart-bar', 'route' => 'admin.analytics.index'],
                ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'fas fa-tools', 'route' => 'admin.maintenance.index'],
                ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'route' => 'admin.settings.index']
            ];
        } else {
            $navigation = [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'app.dashboard'],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'app.projects.index'],
                ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'fas fa-tasks', 'route' => 'app.tasks.index'],
                ['key' => 'team', 'label' => 'Team', 'icon' => 'fas fa-users', 'route' => 'app.team.index'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fas fa-chart-bar', 'route' => 'app.reports.index']
            ];
        }
    }
?>

<header x-data="headerShellComponent()" 
        class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 <?php echo e($isAdmin ? 'z-admin-header' : 'z-header'); ?>">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-full">
            <!-- Left Side: Logo + Brand + Greeting -->
            <div class="flex items-center space-x-4">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <?php if($isAdmin): ?>
                        <div class="w-10 h-10 bg-gradient-to-br from-red-600 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                            <span class="text-white font-bold text-lg">Z</span>
                        </div>
                        <div class="flex flex-col">
                            <h1 class="text-xl font-bold text-gray-900 leading-tight">ZenaManage</h1>
                            <p class="text-xs text-gray-500 leading-tight">Admin Panel</p>
                        </div>
                    <?php else: ?>
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-cube text-white text-sm"></i>
                        </div>
                        <span class="text-xl font-bold text-gray-900">ZenaManage</span>
                    <?php endif; ?>
                </div>
                
                <!-- Greeting (Hidden on mobile, only for app variant) -->
                <?php if(!$isAdmin): ?>
                    <div class="hidden md:block">
                        <span class="text-sm text-gray-600">
                            Hello, <span class="font-medium text-gray-900"><?php echo e($user->first_name ?? 'User'); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Center: Navigation Menu -->
            <nav class="hidden <?php echo e($isAdmin ? 'md:flex' : 'lg:flex'); ?> items-center space-x-<?php echo e($isAdmin ? '1' : '8'); ?>">
                <?php $__currentLoopData = $navigation; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $isActive = request()->is(str_replace('.', '/', $item['route']) . '*');
                        $hasBadge = isset($item['badge']) && $item['badge'] > 0;
                    ?>
                    
                    <a href="<?php echo e(route($item['route'])); ?>" 
                       class="<?php echo e($isAdmin ? 'admin-nav-button flex items-center space-x-2 px-4 py-2 rounded-lg text-sm font-semibold transition-colors' : 'text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors'); ?> <?php echo e($isActive ? ($isAdmin ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-900') : ($isAdmin ? 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' : '')); ?>">
                        <i class="<?php echo e($item['icon']); ?> <?php echo e(!$isAdmin ? 'mr-2' : ''); ?>"></i>
                        <span><?php echo e($item['label']); ?></span>
                        <?php if($hasBadge): ?>
                            <span class="notification-badge bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold ml-1"><?php echo e($item['badge']); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>
            
            <!-- Right Side: Actions -->
            <div class="flex items-center space-x-3">
                <?php if(!$isAdmin): ?>
                    <!-- Notification Dropdown (App only) -->
                    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.notification-dropdown','data' => ['notifications' => $notifications,'unreadCount' => $unreadCount]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.notification-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notifications),'unread-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($unreadCount)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                    
                    <!-- Theme Toggle (App only) -->
                    <button @click="toggleTheme()" 
                            class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Toggle theme">
                        <i class="fas fa-sun" x-show="theme === 'light'"></i>
                        <i class="fas fa-moon" x-show="theme === 'dark'"></i>
                    </button>
                    
                    <!-- Focus Mode Toggle (App only) -->
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
                <?php else: ?>
                    <!-- Quick Actions (Admin only) -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="fas fa-plus"></i>
                            <span>Quick Actions</span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-dropdown">
                            <div class="py-1">
                                <a href="<?php echo e(route('admin.users.create')); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user-plus mr-3 text-gray-400"></i>
                                    Create User
                                </a>
                                <a href="<?php echo e(route('admin.tenants.create')); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-building mr-3 text-gray-400"></i>
                                    Create Tenant
                                </a>
                                <a href="<?php echo e(route('admin.projects.create')); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-project-diagram mr-3 text-gray-400"></i>
                                    Create Project
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- User Avatar Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <!-- Avatar -->
                        <div class="w-8 h-8 <?php echo e($isAdmin ? 'bg-red-600' : 'bg-blue-600'); ?> rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-medium">
                                <?php echo e(strtoupper(substr($user->first_name ?? 'U', 0, 1))); ?>

                            </span>
                        </div>
                        <!-- Name (Hidden on mobile) -->
                        <span class="hidden md:block text-sm font-medium text-gray-900">
                            <?php echo e($user->first_name ?? 'User'); ?> <?php echo e($user->last_name ?? ''); ?>

                        </span>
                        <!-- Dropdown Arrow -->
                        <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                    </button>
                    
                    <!-- User Menu Dropdown -->
                    <div x-show="open" 
                         x-transition
                         @click.away="open = false"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-dropdown">
                        <div class="py-1">
                            <!-- Profile -->
                            <a href="<?php echo e($isAdmin ? route('admin.profile') : route('app.profile')); ?>" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-3 text-gray-400"></i>
                                Profile
                            </a>
                            
                            <!-- Settings -->
                            <a href="<?php echo e($isAdmin ? route('admin.settings.index') : route('app.settings.index')); ?>" 
                               class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-cog mr-3 text-gray-400"></i>
                                Settings
                            </a>
                            
                            <?php if($isAdmin): ?>
                                <!-- Switch to App -->
                                <a href="<?php echo e(route('app.dashboard')); ?>" 
                                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-exchange-alt mr-3 text-gray-400"></i>
                                    Switch to App
                                </a>
                            <?php else: ?>
                                <!-- Switch Tenant (if applicable) -->
                                <?php if($user && $user->hasRole('super_admin')): ?>
                                    <a href="<?php echo e(route('admin.tenants.index')); ?>" 
                                       class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-building mr-3 text-gray-400"></i>
                                        Manage Tenants
                                    </a>
                                <?php endif; ?>
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

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('headerShellComponent', () => ({
        theme: '<?php echo e($theme); ?>',
        
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            // Apply theme changes
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            // Store preference
            localStorage.setItem('theme', this.theme);
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
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_legacy/headers/header-shell-legacy.blade.php ENDPATH**/ ?>