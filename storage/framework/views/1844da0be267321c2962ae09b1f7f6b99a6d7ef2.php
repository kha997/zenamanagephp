


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => 'app', // 'app' or 'admin'
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'sidebar' => null,
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'theme' => 'light',
    'sticky' => true,
    'condensedOnScroll' => true
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => 'app', // 'app' or 'admin'
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'sidebar' => null,
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'theme' => 'light',
    'sticky' => true,
    'condensedOnScroll' => true
]); ?>
<?php foreach (array_filter(([
    'variant' => 'app', // 'app' or 'admin'
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
    'actions' => null,
    'sidebar' => null,
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'theme' => 'light',
    'sticky' => true,
    'condensedOnScroll' => true
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
    
    // Default page title based on current route
    if (!$title) {
        $routeName = request()->route()->getName();
        $title = match(true) {
            str_contains($routeName, 'dashboard') => $isAdmin ? 'Admin Dashboard' : 'Dashboard',
            str_contains($routeName, 'projects') => 'Projects',
            str_contains($routeName, 'tasks') => 'Tasks',
            str_contains($routeName, 'users') => 'Users',
            str_contains($routeName, 'tenants') => 'Tenants',
            str_contains($routeName, 'settings') => 'Settings',
            default => ucfirst(str_replace(['app.', 'admin.'], '', $routeName))
        };
    }
    
    // Default subtitle based on variant
    if (!$subtitle) {
        $subtitle = $isAdmin 
            ? 'System overview and management' 
            : 'Welcome back, ' . ($user->first_name ?? 'User');
    }
?>

<div class="min-h-screen bg-gray-50" 
     x-data="layoutWrapperComponent()" 
     :class="{ 'theme-dark': theme === 'dark' }"
     data-theme="<?php echo e($theme); ?>">
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.header-standardized','data' => ['variant' => ''.e($variant).'','user' => $user,'tenant' => $tenant,'notifications' => $notifications,'showNotifications' => $showNotifications,'showUserMenu' => $showUserMenu,'theme' => ''.e($theme).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.header-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => ''.e($variant).'','user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($user),'tenant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tenant),'notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($notifications),'show-notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showNotifications),'show-user-menu' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showUserMenu),'theme' => ''.e($theme).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    
    
    <div class="flex min-h-screen">
        
        <?php if($sidebar): ?>
            <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:fixed lg:inset-y-0 lg:pt-header lg:pb-0 lg:overflow-y-auto bg-white border-r border-gray-200">
                <?php echo e($sidebar); ?>

            </aside>
        <?php endif; ?>
        
        
        <main class="flex-1 <?php echo e($sidebar ? 'lg:ml-64' : ''); ?>">
            
            <div class="bg-white shadow-sm border-b border-gray-200 <?php echo e($sticky ? 'sticky top-header z-10' : ''); ?>">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            
                            <?php if(!empty($breadcrumbs)): ?>
                                <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-2">
                                    <?php $__currentLoopData = $breadcrumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $crumb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php if($index > 0): ?>
                                            <i class="fas fa-chevron-right text-xs"></i>
                                        <?php endif; ?>
                                        <?php if($crumb['url'] ?? false): ?>
                                            <a href="<?php echo e($crumb['url']); ?>" class="hover:text-gray-700">
                                                <?php echo e($crumb['label']); ?>

                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-900 font-medium"><?php echo e($crumb['label']); ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </nav>
                            <?php endif; ?>
                            
                            
                            <h1 class="text-2xl font-bold text-gray-900 truncate">
                                <?php echo e($title); ?>

                            </h1>
                            
                            
                            <?php if($subtitle): ?>
                                <p class="mt-1 text-sm text-gray-600">
                                    <?php echo e($subtitle); ?>

                                </p>
                            <?php endif; ?>
                        </div>
                        
                        
                        <?php if($actions): ?>
                            <div class="flex items-center space-x-3 ml-4">
                                <?php echo e($actions); ?>

                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <?php if(session('success')): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?php echo e(session('success')); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                
                <?php if(session('error')): ?>
                    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?php echo e(session('error')); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                
                <?php if(session('warning')): ?>
                    <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?php echo e(session('warning')); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                
                <?php if(session('info')): ?>
                    <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium"><?php echo e(session('info')); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                
                <?php echo e($slot); ?>

            </div>
        </main>
    </div>
    
    
    <?php if($sidebar): ?>
        <div x-show="mobileSidebarOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
             @click="mobileSidebarOpen = false">
        </div>
        
        
        <div x-show="mobileSidebarOpen" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 w-64 bg-white z-50 lg:hidden">
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Menu</h2>
                <button @click="mobileSidebarOpen = false" 
                        class="p-2 text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <?php echo e($sidebar); ?>

            </div>
        </div>
    <?php endif; ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('layoutWrapperComponent', () => ({
        theme: '<?php echo e($theme); ?>',
        mobileSidebarOpen: false,
        
        init() {
            // Initialize theme
            this.applyTheme();
            
            // Handle scroll for condensed header
            if (<?php echo e($condensedOnScroll ? 'true' : 'false'); ?>) {
                this.handleScroll();
            }
        },
        
        applyTheme() {
            document.documentElement.setAttribute('data-theme', this.theme);
        },
        
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            this.applyTheme();
        },
        
        handleScroll() {
            // This will be handled by the header component
        },
        
        toggleMobileSidebar() {
            this.mobileSidebarOpen = !this.mobileSidebarOpen;
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/layout-wrapper.blade.php ENDPATH**/ ?>