


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'icon' => 'fas fa-inbox',
    'title' => 'No items found',
    'description' => 'There are no items to display at the moment.',
    'action' => null,
    'actionText' => null,
    'actionIcon' => null,
    'actionHandler' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'minimal', 'illustrated'
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'icon' => 'fas fa-inbox',
    'title' => 'No items found',
    'description' => 'There are no items to display at the moment.',
    'action' => null,
    'actionText' => null,
    'actionIcon' => null,
    'actionHandler' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'minimal', 'illustrated'
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'icon' => 'fas fa-inbox',
    'title' => 'No items found',
    'description' => 'There are no items to display at the moment.',
    'action' => null,
    'actionText' => null,
    'actionIcon' => null,
    'actionHandler' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'minimal', 'illustrated'
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
    $sizeClasses = [
        'sm' => 'py-8',
        'md' => 'py-12',
        'lg' => 'py-16'
    ];
    
    $iconSizes = [
        'sm' => 'text-3xl',
        'md' => 'text-4xl',
        'lg' => 'text-6xl'
    ];
    
    $titleSizes = [
        'sm' => 'text-lg',
        'md' => 'text-lg',
        'lg' => 'text-xl'
    ];
    
    $emptyStateClasses = [
        'empty-state',
        'text-center',
        $sizeClasses[$size]
    ];
?>

<div class="<?php echo e(implode(' ', array_filter($emptyStateClasses))); ?>">
    
    <div class="flex justify-center mb-4">
        <?php if($variant === 'illustrated'): ?>
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="<?php echo e($icon); ?> <?php echo e($iconSizes[$size]); ?> text-gray-400"></i>
            </div>
        <?php else: ?>
            <i class="<?php echo e($icon); ?> <?php echo e($iconSizes[$size]); ?> text-gray-300"></i>
        <?php endif; ?>
    </div>
    
    
    <h3 class="<?php echo e($titleSizes[$size]); ?> font-medium text-gray-900 mb-2">
        <?php echo e($title); ?>

    </h3>
    
    
    <p class="text-gray-500 mb-6 max-w-sm mx-auto">
        <?php echo e($description); ?>

    </p>
    
    
    <?php if($action || $actionHandler): ?>
        <div class="flex justify-center">
            <?php if($action): ?>
                <?php echo e($action); ?>

            <?php elseif($actionHandler): ?>
                <button @click="<?php echo e($actionHandler); ?>" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php if($actionIcon): ?>
                        <i class="<?php echo e($actionIcon); ?> mr-2"></i>
                    <?php endif; ?>
                    <?php echo e($actionText ?? 'Get Started'); ?>

                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    
    <?php if($slot->isNotEmpty()): ?>
        <div class="mt-6">
            <?php echo e($slot); ?>

        </div>
    <?php endif; ?>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/empty-state.blade.php ENDPATH**/ ?>