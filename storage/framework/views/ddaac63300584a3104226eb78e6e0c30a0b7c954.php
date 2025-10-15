


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'kpis' => [],
    'variant' => 'default', // default, compact, detailed
    'columns' => null, // auto-calculate if null
    'showChanges' => true,
    'showIcons' => true
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'kpis' => [],
    'variant' => 'default', // default, compact, detailed
    'columns' => null, // auto-calculate if null
    'showChanges' => true,
    'showIcons' => true
]); ?>
<?php foreach (array_filter(([
    'kpis' => [],
    'variant' => 'default', // default, compact, detailed
    'columns' => null, // auto-calculate if null
    'showChanges' => true,
    'showIcons' => true
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $kpiCount = count($kpis);
    $columns = $columns ?? min($kpiCount, 6); // Max 6 columns
    $gridCols = match($columns) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        5 => 'grid-cols-5',
        6 => 'grid-cols-6',
        default => 'grid-cols-3'
    };
    
    $variantClasses = match($variant) {
        'compact' => 'p-4',
        'detailed' => 'p-6',
        default => 'p-5'
    };
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 <?php echo e($variantClasses); ?>">
    <div class="grid <?php echo e($gridCols); ?> gap-4">
        <?php $__currentLoopData = $kpis; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kpi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="flex items-center">
                <?php if($showIcons && isset($kpi['icon'])): ?>
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-<?php echo e($kpi['color'] ?? 'blue'); ?>-500 rounded-md flex items-center justify-center">
                            <i class="fas <?php echo e($kpi['icon']); ?> text-white text-sm"></i>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="<?php echo e($showIcons ? 'ml-4' : ''); ?> flex-1">
                    <p class="text-sm font-medium text-gray-500"><?php echo e($kpi['title'] ?? 'KPI'); ?></p>
                    <div class="flex items-baseline">
                        <p class="text-2xl font-semibold text-gray-900"><?php echo e($kpi['value'] ?? '0'); ?></p>
                        <?php if($showChanges && isset($kpi['change'])): ?>
                            <?php
                                $changeType = $kpi['change_type'] ?? 'neutral';
                                $changeClasses = match($changeType) {
                                    'positive' => 'text-green-600',
                                    'negative' => 'text-red-600',
                                    default => 'text-gray-600'
                                };
                            ?>
                            <span class="ml-2 text-sm font-medium <?php echo e($changeClasses); ?>">
                                <?php echo e($kpi['change']); ?>

                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($kpi['description'])): ?>
                        <p class="text-xs text-gray-400 mt-1"><?php echo e($kpi['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/kpi-strip.blade.php ENDPATH**/ ?>