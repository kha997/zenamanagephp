


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title' => null,
    'subtitle' => null,
    'header' => null,
    'footer' => null,
    'variant' => 'default', // 'default', 'bordered', 'elevated', 'flat'
    'size' => 'md', // 'sm', 'md', 'lg'
    'padding' => null, // 'none', 'sm', 'md', 'lg'
    'hover' => false,
    'clickable' => false,
    'loading' => false,
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title' => null,
    'subtitle' => null,
    'header' => null,
    'footer' => null,
    'variant' => 'default', // 'default', 'bordered', 'elevated', 'flat'
    'size' => 'md', // 'sm', 'md', 'lg'
    'padding' => null, // 'none', 'sm', 'md', 'lg'
    'hover' => false,
    'clickable' => false,
    'loading' => false,
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'title' => null,
    'subtitle' => null,
    'header' => null,
    'footer' => null,
    'variant' => 'default', // 'default', 'bordered', 'elevated', 'flat'
    'size' => 'md', // 'sm', 'md', 'lg'
    'padding' => null, // 'none', 'sm', 'md', 'lg'
    'hover' => false,
    'clickable' => false,
    'loading' => false,
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
    $isBordered = $variant === 'bordered';
    $isElevated = $variant === 'elevated';
    $isFlat = $variant === 'flat';
    
    $sizeClasses = [
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8'
    ];
    
    $paddingClass = $padding ? $sizeClasses[$padding] : $sizeClasses[$size];
    
    $cardClasses = [
        'card',
        $isBordered ? 'bordered' : '',
        $isElevated ? 'elevated' : '',
        $isFlat ? 'flat' : '',
        $hover ? 'hover' : '',
        $clickable ? 'clickable' : '',
        $loading ? 'loading' : ''
    ];
?>

<div class="<?php echo e(implode(' ', array_filter($cardClasses))); ?>" 
     <?php if($clickable): ?> onclick="<?php echo e($clickable); ?>" <?php endif; ?>
     x-data="cardComponent()">
    
    
    <?php if($loading): ?>
        <div class="card-loading-overlay">
            <div class="card-loading-spinner">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
            </div>
        </div>
    <?php endif; ?>
    
    
    <?php if($header || $title || $subtitle): ?>
        <div class="card-header">
            <?php if($header): ?>
                <?php echo e($header); ?>

            <?php else: ?>
                <div class="card-header-content">
                    <?php if($title): ?>
                        <h3 class="card-title"><?php echo e($title); ?></h3>
                    <?php endif; ?>
                    <?php if($subtitle): ?>
                        <p class="card-subtitle"><?php echo e($subtitle); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    
    <div class="card-body <?php echo e($paddingClass); ?>">
        <?php echo e($slot); ?>

    </div>
    
    
    <?php if($footer): ?>
        <div class="card-footer">
            <?php echo e($footer); ?>

        </div>
    <?php endif; ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cardComponent', () => ({
        loading: <?php echo e($loading ? 'true' : 'false'); ?>,
        
        init() {
            // Initialize card component
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/card-standardized.blade.php ENDPATH**/ ?>