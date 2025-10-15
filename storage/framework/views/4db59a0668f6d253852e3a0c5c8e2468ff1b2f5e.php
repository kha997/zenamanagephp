


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'icon' => 'fas fa-plus',
    'label' => null,
    'position' => 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger'
    'href' => null,
    'target' => null,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'icon' => 'fas fa-plus',
    'label' => null,
    'position' => 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger'
    'href' => null,
    'target' => null,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'icon' => 'fas fa-plus',
    'label' => null,
    'position' => 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger'
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
    
    $sizeClasses = [
        'sm' => 'w-12 h-12',
        'md' => 'w-14 h-14',
        'lg' => 'w-16 h-16'
    ];
    
    $iconSizes = [
        'sm' => 'text-lg',
        'md' => 'text-xl',
        'lg' => 'text-2xl'
    ];
    
    $positionClasses = [
        'bottom-right' => 'bottom-6 right-6',
        'bottom-left' => 'bottom-6 left-6',
        'top-right' => 'top-6 right-6',
        'top-left' => 'top-6 left-6'
    ];
    
    $variantClasses = [
        'primary' => 'bg-blue-600 hover:bg-blue-700 text-white shadow-lg hover:shadow-xl',
        'secondary' => 'bg-gray-600 hover:bg-gray-700 text-white shadow-lg hover:shadow-xl',
        'success' => 'bg-green-600 hover:bg-green-700 text-white shadow-lg hover:shadow-xl',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-lg hover:shadow-xl'
    ];
    
    $fabClasses = [
        'fab',
        'fixed',
        'z-fab',
        $sizeClasses[$size],
        $positionClasses[$position],
        $variantClasses[$variant] ?? $variantClasses['primary'],
        'rounded-full',
        'flex',
        'items-center',
        'justify-center',
        'transition-all',
        'duration-300',
        'ease-in-out',
        'focus:outline-none',
        'focus:ring-4',
        'focus:ring-blue-300',
        'focus:ring-opacity-50'
    ];
?>

<?php if($isLink): ?>
    <a href="<?php echo e($href); ?>"
       <?php if($target): ?> target="<?php echo e($target); ?>" <?php endif; ?>
       class="<?php echo e(implode(' ', array_filter($fabClasses))); ?>"
       aria-label="<?php echo e($label ?? 'Action'); ?>"
       <?php echo e($attributes); ?>>
        
        <i class="<?php echo e($icon); ?> <?php echo e($iconSizes[$size]); ?>"></i>
        
        <?php if($label): ?>
            <span class="fab-label"><?php echo e($label); ?></span>
        <?php endif; ?>
    </a>
<?php else: ?>
    <button class="<?php echo e(implode(' ', array_filter($fabClasses))); ?>"
            aria-label="<?php echo e($label ?? 'Action'); ?>"
            <?php echo e($attributes); ?>>
        
        <i class="<?php echo e($icon); ?> <?php echo e($iconSizes[$size]); ?>"></i>
        
        <?php if($label): ?>
            <span class="fab-label"><?php echo e($label); ?></span>
        <?php endif; ?>
    </button>
<?php endif; ?>

<style>
.fab {
    @apply transform hover:scale-105 active:scale-95;
}

.fab-label {
    @apply absolute right-full mr-3 px-3 py-1 bg-gray-900 text-white text-sm rounded-md opacity-0 pointer-events-none transition-opacity duration-200;
    white-space: nowrap;
}

.fab:hover .fab-label {
    @apply opacity-100;
}

.fab-label::after {
    content: '';
    @apply absolute left-full top-1/2 transform -translate-y-1/2 border-4 border-transparent border-l-gray-900;
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/fab.blade.php ENDPATH**/ ?>