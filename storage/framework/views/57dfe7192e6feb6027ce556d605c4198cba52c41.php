
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['items' => []]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['items' => []]); ?>
<?php foreach (array_filter((['items' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<nav class="zena-breadcrumb" aria-label="Breadcrumb">
    <ol class="zena-breadcrumb-list">
        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="zena-breadcrumb-item">
                <?php if($index === count($items) - 1): ?>
                    
                    <span class="zena-breadcrumb-current" aria-current="page">
                        <?php if(isset($item['icon'])): ?>
                            <i class="<?php echo e($item['icon']); ?> mr-2"></i>
                        <?php endif; ?>
                        <?php echo e($item['label']); ?>

                    </span>
                <?php else: ?>
                    
                    <a href="<?php echo e($item['url']); ?>" class="zena-breadcrumb-link">
                        <?php if(isset($item['icon'])): ?>
                            <i class="<?php echo e($item['icon']); ?> mr-2"></i>
                        <?php endif; ?>
                        <?php echo e($item['label']); ?>

                    </a>
                <?php endif; ?>
            </li>
            
            <?php if($index < count($items) - 1): ?>
                
                <li class="zena-breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </li>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ol>
</nav>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/breadcrumb.blade.php ENDPATH**/ ?>