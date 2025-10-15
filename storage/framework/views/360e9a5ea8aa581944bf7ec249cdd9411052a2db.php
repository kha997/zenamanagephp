


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'customActions' => null,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'customActions' => null,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'notifications' => [],
    'showNotifications' => true,
    'showUserMenu' => true,
    'customActions' => null,
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
    
    // Default navigation items based on variant
    $navItems = $isAdmin ? [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard', 'icon' => 'fas fa-tachometer-alt'],
        ['label' => 'Users', 'url' => '/admin/users', 'icon' => 'fas fa-users'],
        ['label' => 'Tenants', 'url' => '/admin/tenants', 'icon' => 'fas fa-building'],
        ['label' => 'Settings', 'url' => '/admin/settings', 'icon' => 'fas fa-cog']
    ] : [
        ['label' => 'Dashboard', 'url' => '/app/dashboard', 'icon' => 'fas fa-tachometer-alt'],
        ['label' => 'Projects', 'url' => '/app/projects', 'icon' => 'fas fa-project-diagram'],
        ['label' => 'Tasks', 'url' => '/app/tasks', 'icon' => 'fas fa-tasks'],
        ['label' => 'Team', 'url' => '/app/team', 'icon' => 'fas fa-users']
    ];
    
    $brandText = $isAdmin ? 'ZenaManage Admin Panel' : 'ZenaManage';
    $greeting = $isAdmin ? $user->name : "Hello, {$user->name}";
?>

<header class="header-shell" 
        x-data="headerComponent()" 
        :class="{ 'condensed': scrolled }"
        @scroll.window="handleScroll()">
    
    <div class="header-container">
        
        <div class="header-logo">
            <a href="<?php echo e($isAdmin ? route('admin.dashboard') : route('app.dashboard')); ?>" 
               class="flex items-center space-x-2">
                <img class="h-8 w-auto" 
                     src="<?php echo e(asset('images/logo.svg')); ?>" 
                     alt="ZenaManage"
                     loading="lazy">
                <span class="text-xl font-bold text-header-fg"><?php echo e($brandText); ?></span>
            </a>
        </div>

        
        <nav class="header-nav">
            <?php $__currentLoopData = $navItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(isset($item['route']) ? route($item['route']) : $item['url']); ?>" 
                   class="header-nav-item <?php echo e(isset($item['route']) && request()->routeIs($item['route']) ? 'active' : ''); ?>"
                   title="<?php echo e($item['label']); ?>">
                    <i class="<?php echo e($item['icon']); ?> mr-2"></i>
                    <span class="hidden lg:inline"><?php echo e($item['label']); ?></span>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </nav>

        
        <div class="header-actions">
            
            <?php if($customActions): ?>
                <?php echo e($customActions); ?>

            <?php endif; ?>

            
            <?php if($showNotifications): ?>
                <div class="relative" x-data="{ open: false }">
                    <button class="header-action-btn" 
                            @click="open = !open; $refs.notificationPanel.focus()"
                            :class="{ 'bg-header-bg-hover': open }"
                            aria-label="Notifications">
                        <i class="fas fa-bell"></i>
                        <?php if(count($notifications) > 0): ?>
                            <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                                <?php echo e(count($notifications)); ?>

                            </span>
                        <?php endif; ?>
                    </button>
                    
                    
                    <div x-show="open" 
                         x-ref="notificationPanel"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         @click.away="open = false"
                         class="header-dropdown"
                         role="menu"
                         tabindex="-1">
                        
                        <div class="px-4 py-3 border-b border-header-border">
                            <h3 class="text-sm font-medium text-header-fg">Notifications</h3>
                        </div>
                        
                        <div class="max-h-64 overflow-y-auto">
                            <?php $__empty_1 = true; $__currentLoopData = $notifications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $notification): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <div class="px-4 py-3 hover:bg-header-bg-hover border-b border-header-border last:border-b-0">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-<?php echo e($notification['icon'] ?? 'info-circle'); ?> text-<?php echo e($notification['color'] ?? 'blue'); ?>-500"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-header-fg"><?php echo e($notification['message']); ?></p>
                                            <p class="text-xs text-header-fg-muted mt-1"><?php echo e($notification['time'] ?? 'Just now'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <div class="px-4 py-8 text-center">
                                    <i class="fas fa-bell-slash text-header-fg-muted text-2xl mb-2"></i>
                                    <p class="text-sm text-header-fg-muted">No notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if(count($notifications) > 0): ?>
                            <div class="px-4 py-2 border-t border-header-border">
                                <a href="<?php echo e($isAdmin ? route('admin.notifications') : route('app.notifications')); ?>" 
                                   class="text-sm text-nav-active hover:text-nav-hover font-medium">
                                    View all notifications
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            
            <?php if($showUserMenu && $user): ?>
                <div class="header-user-menu" x-data="{ open: false }">
                    <button class="flex items-center space-x-2 text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nav-active"
                            @click="open = !open; $refs.userMenu.focus()"
                            aria-label="User menu">
                        <img class="h-8 w-8 rounded-full object-cover" 
                             src="<?php echo e($user->avatar ?? asset('images/default-avatar.png')); ?>" 
                             alt="<?php echo e($user->name); ?>"
                             loading="lazy">
                        <span class="hidden md:inline text-header-fg"><?php echo e($greeting); ?></span>
                        <i class="fas fa-chevron-down text-header-fg-muted text-xs"></i>
                    </button>
                    
                    
                    <div x-show="open" 
                         x-ref="userMenu"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         @click.away="open = false"
                         class="header-dropdown"
                         role="menu"
                         tabindex="-1">
                        
                        <div class="px-4 py-3 border-b border-header-border">
                            <p class="text-sm font-medium text-header-fg"><?php echo e($user->name); ?></p>
                            <p class="text-xs text-header-fg-muted"><?php echo e($user->email); ?></p>
                            <?php if($tenant): ?>
                                <p class="text-xs text-header-fg-muted"><?php echo e($tenant->name); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="py-1">
                            <a href="<?php echo e($isAdmin ? route('admin.profile') : route('app.profile')); ?>" 
                               class="header-dropdown-item">
                                <i class="fas fa-user mr-2"></i>
                                Profile
                            </a>
                            <a href="<?php echo e($isAdmin ? '#' : route('app.settings')); ?>" 
                               class="header-dropdown-item">
                                <i class="fas fa-cog mr-2"></i>
                                Settings
                            </a>
                            <?php if(!$isAdmin): ?>
                                <a href="<?php echo e(route('app.team.index')); ?>" 
                                   class="header-dropdown-item">
                                    <i class="fas fa-users mr-2"></i>
                                    Team
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="border-t border-header-border py-1">
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" 
                                        class="header-dropdown-item w-full text-left">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            
            <button class="hamburger md:hidden" 
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    :class="{ 'active': mobileMenuOpen }"
                    aria-label="Toggle mobile menu">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </button>
        </div>
    </div>

    
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="mobile-overlay md:hidden"
         @click="mobileMenuOpen = false">
    </div>

    
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="mobile-sheet md:hidden"
         :class="{ 'open': mobileMenuOpen, 'closed': !mobileMenuOpen }">
        
        <div class="p-4">
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-2">
                    <img class="h-6 w-auto" 
                         src="<?php echo e(asset('images/logo.svg')); ?>" 
                         alt="ZenaManage"
                         loading="lazy">
                    <span class="text-lg font-bold text-header-fg"><?php echo e($brandText); ?></span>
                </div>
                <button @click="mobileMenuOpen = false" 
                        class="p-2 text-header-fg-muted hover:text-header-fg">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            
            <nav class="space-y-2">
                <?php $__currentLoopData = $navItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e($item['url']); ?>" 
                       class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg <?php echo e(request()->is(trim($item['url'], '/')) ? 'bg-nav-active-bg text-nav-active' : ''); ?>"
                       @click="mobileMenuOpen = false">
                        <i class="<?php echo e($item['icon']); ?> w-5"></i>
                        <span><?php echo e($item['label']); ?></span>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </nav>

            
            <?php if($user): ?>
                <div class="mt-6 pt-6 border-t border-header-border">
                    <div class="flex items-center space-x-3 mb-4">
                        <img class="h-10 w-10 rounded-full object-cover" 
                             src="<?php echo e($user->avatar ?? asset('images/default-avatar.png')); ?>" 
                             alt="<?php echo e($user->name); ?>"
                             loading="lazy">
                        <div>
                            <p class="text-sm font-medium text-header-fg"><?php echo e($user->name); ?></p>
                            <p class="text-xs text-header-fg-muted"><?php echo e($user->email); ?></p>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <a href="<?php echo e($isAdmin ? route('admin.profile') : route('app.profile')); ?>" 
                           class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg">
                            <i class="fas fa-user w-5"></i>
                            <span>Profile</span>
                        </a>
                        <a href="<?php echo e($isAdmin ? '#' : route('app.settings')); ?>" 
                           class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg">
                            <i class="fas fa-cog w-5"></i>
                            <span>Settings</span>
                        </a>
                        <form method="POST" action="<?php echo e(route('logout')); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit" 
                                    class="flex items-center space-x-3 px-3 py-2 text-header-fg hover:bg-header-bg-hover rounded-lg w-full text-left">
                                <i class="fas fa-sign-out-alt w-5"></i>
                                <span>Sign out</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('headerComponent', () => ({
        scrolled: false,
        mobileMenuOpen: false,
        
        init() {
            this.handleScroll();
        },
        
        handleScroll() {
            this.scrolled = window.scrollY > 10;
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/header-standardized.blade.php ENDPATH**/ ?>