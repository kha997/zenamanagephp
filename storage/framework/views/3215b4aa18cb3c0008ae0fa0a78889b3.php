


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'user' => null,
    'variant' => 'app'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'user' => null,
    'variant' => 'app'
]); ?>
<?php foreach (array_filter(([
    'user' => null,
    'variant' => 'app'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $user = $user ?? Auth::user();
    $isAdmin = $variant === 'admin';
?>

<div class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 <?php echo e($isAdmin ? 'bg-gradient-to-br from-red-600 to-orange-600' : 'bg-blue-600'); ?> rounded-lg flex items-center justify-center">
                    <?php if($isAdmin): ?>
                        <span class="text-white font-bold text-lg">Z</span>
                    <?php else: ?>
                        <i class="fas fa-cube text-white text-sm"></i>
                    <?php endif; ?>
                </div>
                <span class="text-xl font-bold text-gray-900">ZenaManage</span>
            </div>
            
            <!-- User Menu -->
            <?php if($user): ?>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-600">
                        Welcome, <?php echo e($user->name); ?>

                    </div>
                    <div class="relative" data-testid="user-menu" x-data="{ open: false }">
                        <button class="flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors"
                                @click="open = !open"
                                data-testid="user-menu-toggle">
                            <div class="w-8 h-8 <?php echo e($isAdmin ? 'bg-red-600' : 'bg-blue-600'); ?> rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    <?php echo e($user->first_name ? strtoupper(substr($user->first_name, 0, 1)) : 'U'); ?>

                                </span>
                            </div>
                            <span class="hidden md:block text-sm font-medium text-gray-900">
                                <?php echo e($user->first_name ? $user->first_name . ' ' . $user->last_name : $user->name); ?>

                            </span>
                            <i class="fas fa-chevron-down text-xs text-gray-500"></i>
                        </button>
                        
                        <!-- User Dropdown -->
                        <div x-show="open" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             @click.away="open = false"
                             class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                             data-testid="user-menu-dropdown">
                            <div class="py-1">
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-user mr-2"></i>Profile
                                </a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <hr class="my-1">
                                <form method="POST" action="<?php echo e(route('logout')); ?>">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" 
                                            data-testid="logout-link"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-sm text-gray-600">
                    Not logged in
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/simple-header.blade.php ENDPATH**/ ?>