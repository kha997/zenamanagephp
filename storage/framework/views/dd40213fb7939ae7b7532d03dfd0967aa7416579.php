


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'type' => 'button', // 'button', 'submit', 'reset'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'
    'size' => 'md', // 'xs', 'sm', 'md', 'lg', 'xl'
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right', 'only'
    'href' => null,
    'target' => null,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'type' => 'button', // 'button', 'submit', 'reset'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'
    'size' => 'md', // 'xs', 'sm', 'md', 'lg', 'xl'
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right', 'only'
    'href' => null,
    'target' => null,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'type' => 'button', // 'button', 'submit', 'reset'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'
    'size' => 'md', // 'xs', 'sm', 'md', 'lg', 'xl'
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right', 'only'
    'href' => null,
    'target' => null,
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
    $isLink = !empty($href);
    $hasIcon = !empty($icon);
    $isIconOnly = $hasIcon && $iconPosition === 'only';
    
    $sizeClasses = [
        'xs' => 'px-2 py-1 text-xs',
        'sm' => 'px-3 py-2 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
        'xl' => 'px-8 py-4 text-lg'
    ];
    
    $variantClasses = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'warning' => 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500',
        'info' => 'bg-cyan-600 text-white hover:bg-cyan-700 focus:ring-cyan-500',
        'ghost' => 'bg-transparent text-gray-700 hover:bg-gray-100 focus:ring-gray-500',
        'link' => 'bg-transparent text-blue-600 hover:text-blue-800 focus:ring-blue-500 underline'
    ];
    
    $baseClasses = [
        'btn',
        $sizeClasses[$size],
        $variantClasses[$variant] ?? $variantClasses['primary'],
        $disabled || $loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
        $isIconOnly ? 'p-2' : '',
        'inline-flex items-center justify-center border border-transparent font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-200'
    ];
?>

<?php if($isLink): ?>
    <a href="<?php echo e($href); ?>"
       <?php if($target): ?> target="<?php echo e($target); ?>" <?php endif; ?>
       <?php if($disabled || $loading): ?> onclick="return false;" <?php endif; ?>
       class="<?php echo e(implode(' ', array_filter($baseClasses))); ?>"
       <?php echo e($attributes); ?>>
        
        
        <?php if($loading): ?>
            <i class="fas fa-spinner fa-spin mr-2"></i>
        <?php endif; ?>
        
        
        <?php if($hasIcon && ($iconPosition === 'left' || $iconPosition === 'only')): ?>
            <i class="<?php echo e($icon); ?> <?php echo e($isIconOnly ? '' : 'mr-2'); ?>"></i>
        <?php endif; ?>
        
        
        <?php if(!$isIconOnly): ?>
            <?php echo e($slot); ?>

        <?php endif; ?>
        
        
        <?php if($hasIcon && $iconPosition === 'right'): ?>
            <i class="<?php echo e($icon); ?> ml-2"></i>
        <?php endif; ?>
    </a>
<?php else: ?>
    <button type="<?php echo e($type); ?>"
            <?php if($disabled || $loading): ?> disabled <?php endif; ?>
            class="<?php echo e(implode(' ', array_filter($baseClasses))); ?>"
            <?php echo e($attributes); ?>>
        
        
        <?php if($loading): ?>
            <i class="fas fa-spinner fa-spin mr-2"></i>
        <?php endif; ?>
        
        
        <?php if($hasIcon && ($iconPosition === 'left' || $iconPosition === 'only')): ?>
            <i class="<?php echo e($icon); ?> <?php echo e($isIconOnly ? '' : 'mr-2'); ?>"></i>
        <?php endif; ?>
        
        
        <?php if(!$isIconOnly): ?>
            <?php echo e($slot); ?>

        <?php endif; ?>
        
        
        <?php if($hasIcon && $iconPosition === 'right'): ?>
            <i class="<?php echo e($icon); ?> ml-2"></i>
        <?php endif; ?>
    </button>
<?php endif; ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/button-standardized.blade.php ENDPATH**/ ?>