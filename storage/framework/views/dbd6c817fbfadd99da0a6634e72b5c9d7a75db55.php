

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title' => '',
    'value' => 0,
    'delta' => null,
    'deltaType' => 'neutral',
    'suffix' => '',
    'icon' => '',
    'variant' => 'gray',
    'sparkline' => null,
    'linkHref' => null,
    'loading' => false,
    'tooltip' => '',
    'ariaLabel' => ''
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title' => '',
    'value' => 0,
    'delta' => null,
    'deltaType' => 'neutral',
    'suffix' => '',
    'icon' => '',
    'variant' => 'gray',
    'sparkline' => null,
    'linkHref' => null,
    'loading' => false,
    'tooltip' => '',
    'ariaLabel' => ''
]); ?>
<?php foreach (array_filter(([
    'title' => '',
    'value' => 0,
    'delta' => null,
    'deltaType' => 'neutral',
    'suffix' => '',
    'icon' => '',
    'variant' => 'gray',
    'sparkline' => null,
    'linkHref' => null,
    'loading' => false,
    'tooltip' => '',
    'ariaLabel' => ''
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    // Variant classes
    $variants = [
        'blue' => 'bg-blue-50 text-blue-700 border-blue-100',
        'red' => 'bg-red-50 text-red-700 border-red-100',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
        'orange' => 'bg-orange-50 text-orange-700 border-orange-100',
        'gray' => 'bg-gray-50 text-gray-700 border-gray-100'
    ];
    
    $iconVariants = [
        'blue' => 'bg-blue-100 text-blue-600',
        'red' => 'bg-red-100 text-red-600',
        'amber' => 'bg-amber-100 text-amber-600',
        'indigo' => 'bg-indigo-100 text-indigo-600',
        'orange' => 'bg-orange-100 text-orange-600',
        'gray' => 'bg-gray-100 text-gray-600'
    ];
    
    // Calculate classes
    $containerClass = 'border rounded-lg p-6 transition-all duration-200 hover:shadow-md ' . ($variants[$variant] ?? $variants['gray']);
    $iconClass = 'inline-flex items-center justify-center w-8 h- h-8 rounded-full ' . ($iconVariants[$variant] ?? $iconVariants['gray']);
    
    // Delta formatting
    $deltaClasses = [
        'up' => 'bg-green-100 text-green-800',
        'down' => 'bg-red-100 text-red-800',
        'neutral' => 'bg-gray-100 text-gray-800'
    ];
    
    $deltaClass = $deltaClasses[$deltaType] ?? $deltaClasses['neutral'];
    
    // Delta icon and text
    $deltaIcon = match($deltaType) {
        'up' => 'fas fa-arrow-up',
        'down' => 'fas fa-arrow-down',
        default => 'fas fa-minus'
    };
    
    $deltaText = '';
    if ($delta !== null && $delta !== '') {
        $deltaNum = is_numeric($delta) ? (float)$delta : 0;
        if (abs($deltaNum) < 0.1) {
            $deltaText = '0%';
        } else {
            $sign = $deltaNum > 0 ? '+' : '';
            if ($suffix === '%') {
                $deltaText = $sign . number_format($deltaNum, 1) . '%';
            } else {
                $deltaText = $sign . number_format($deltaNum);
            }
        }
    }
?>

<div class="<?php echo e($containerClass); ?>" 
     <?php if($ariaLabel): ?> aria-label="<?php echo e($ariaLabel); ?>" <?php endif; ?>
     <?php if($tooltip): ?> title="<?php echo e($tooltip); ?>" <?php endif; ?>
     <?php if($linkHref): ?> onclick="window.location.href='<?php echo e($linkHref); ?>'" style="cursor: pointer;" <?php endif; ?>>
    <div class="flex items-center justify-between">
        <div class="flex-1">
            <div class="flex items-center">
                <div class="<?php echo e($iconClass); ?> mr-3">
                    <i class="fas fa-<?php echo e($icon); ?> text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium leading-none mb-1"><?php echo e($title); ?></h3>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold tabular-nums" aria-live="polite">
                            <?php if($loading): ?>
                                <div class="animate-pulse bg-gray-200 rounded w-12 h-8"></div>
                            <?php else: ?>
                                <?php echo e($value); ?>

                            <?php endif; ?>
                        </span>
                        <?php if($suffix && !$loading): ?>
                            <span class="text-sm font-normal opacity-80"><?php echo e($suffix); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if($delta !== null && !$loading): ?>
                <div class="mt-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($deltaClass); ?>">
                        <i class="<?php echo e($deltaIcon); ?> mr-1"></i>
                        <?php echo e($deltaText); ?>

                    </span>
                </div>
            <?php endif; ?>
            
            <?php if(!$loading): ?>
                
                <div class="mt-3">
                    <div class="min-h-[32px] flex items-center">
                        <?php if($sparkline && is_array($sparkline) && count($sparkline) > 1): ?>
                            <svg width="120" height="32" class="stroke-current opacity-60" viewBox="0 0 120 32">
                                <?php $__currentLoopData = $sparkline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($index < count($sparkline) - 1): ?>
                                        <line x1="<?php echo e($index * 10); ?>" y1="<?php echo e(16 - $value * 0.3); ?>" 
                                              x2="<?php echo e(($index + 1) * 10); ?>" y2="<?php echo e(16 - $sparkline[$index + 1] * 0.3); ?>" 
                                              stroke="currentColor" stroke-width="1.5"/>
                                    <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </svg>
                        <?php else: ?>
                            
                            <div class="w-30 h-8 opacity-30"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if($loading): ?>
            
            <div class="flex items-center space-x-3 animate-pulse">
                <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                <div class="flex-1">
                    <div class="h-3 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-6 bg-gray-200 rounded w-16"></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/stat-card.blade.php ENDPATH**/ ?>