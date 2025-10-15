

<?php
    // Default colors by semantic meaning
    $colors = [
        'users' => '#3B82F6',      // blue-500
        'tenants' => '#10B981',    // emerald-500  
        'errors' => '#EF4444',     // red-500
        'queue' => '#F59E0B',      // amber-500
        'storage' => '#8B5CF6',    // violet-500
        'active' => '#10B981'       // emerald-500
    ];
    
    $sparklineColor = $color ?? ($colors[$type] ?? '#6B7280');
    $svgId = $id ?? 'sparkline-' . Str::random(8);
    
    // Process data: ensure it's array and has minimum points
    $sparklineData = is_array($data) ? $data : [];
    if (empty($sparklineData)) {
        $sparklineData = [0, 0, 0, 0, 0, 0, 0]; // Default flat line
    }
    
    // Normalize data - ensure minimum 7 points
    while (count($sparklineData) < 7) {
        $sparklineData = array_merge($sparklineData, $sparklineData);
    }
    $sparklineData = array_slice($sparklineData, 0, 7);
    
    // Calculate SVG dimensions (80x24 as requested)
    $svgWidth = 80;
    $svgHeight = 24;
    $margin = 2;
    
    // Calculate path coordinates
    $chartWidth = $svgWidth - ($margin * 2);
    $chartHeight = $svgHeight - ($margin * 2);
    
    // Find min/max for scaling
    $minValue = min($sparklineData);
    $maxValue = max($sparklineData);
    $valueRange = ($maxValue - $minValue) ?: 1; // Avoid division by zero
    
    // Generate SVG path points
    $pathPoints = [];
    $areaPoints = [];
    
    foreach ($sparklineData as $index => $value) {
        $x = $margin + ($index / (count($sparklineData) - 1)) * $chartWidth;
        $y = $margin + (($maxValue - $value) / $valueRange) * $chartHeight;
        
        // Ensure y is within bounds
        $y = max($margin, min($svgHeight - $margin, $y));
        
        $pathPoints[] = round($x, 1) . ',' . round($y, 1);
        $areaPoints[] = round($x, 1) . ',' . round($y, 1);
    }
    
    // Complete area polygon (fill to bottom)
    $areaPoints[] = ($margin + $chartWidth) . ',' . ($svgHeight - $margin);
    $areaPoints[] = $margin . ',' . ($svgHeight - $margin);
    
    $pathData = 'M' . implode(' L', $pathPoints);
    $areaData = implode(' ', $areaPoints);
    
?>

<svg 
    id="<?php echo e($svgId); ?>" 
    width="<?php echo e($svgWidth); ?>" 
    height="<?php echo e($svgHeight); ?>" 
    viewBox="0 0 <?php echo e($svgWidth); ?> <?php echo e($svgHeight); ?>"
    class="sparkline-svg"
    aria-hidden="true">
    
    
    <polygon 
        points="<?php echo e($areaData); ?>"
        fill="<?php echo e($sparklineColor); ?>"
        fill-opacity="0.2"
        opacity="0.6"/>
    
    
    <path 
        d="<?php echo e($pathData); ?>"
        fill="none"
        stroke="<?php echo e($sparklineColor); ?>"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"/>
    
    
    <?php $__currentLoopData = $sparklineData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
            $x = $margin + ($index / (count($sparklineData) - 1)) * $chartWidth;
            $y = $margin + (($maxValue - $value) / $valueRange) * $chartHeight;
            // Show only endpoints and midpoints to avoid clutter
            $showPoint = in_array($index, [0, floor(count($sparklineData)/2), count($sparklineData)-1]);
        ?>
        <?php if($showPoint): ?>
            <circle 
                cx="<?php echo e(round($x, 1)); ?>" 
                cy="<?php echo e(round($y, 1)); ?>" 
                r="1" 
                fill="<?php echo e($sparklineColor); ?>"
                opacity="0.8"/>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</svg>


<span class="sr-only">
    <?php if($type === 'users'): ?>
        User count trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php elseif($type === 'tenants'): ?>
        Tenant count trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php elseif($type === 'errors'): ?>
        Error rate trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php elseif($type === 'queue'): ?>
        Queue jobs trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php elseif($type === 'storage'): ?>
        Storage usage trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php else: ?>
        Data trend: <?php echo e($sparklineData[0]); ?> to <?php echo e(end($sparklineData)); ?>, <?php echo e(count($sparklineData)); ?> data points
    <?php endif; ?>
</span>


<style scoped>
.sparkline-svg {
    transition: opacity 0.2s ease;
}

.sparkline-svg:hover {
    opacity: 0.8;
}

/* Pulse animation for active data points */
@keyframes pulse-sparkline {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; }
}

.sparkline-svg circle {
    animation: pulse-sparkline 2s ease-in-out infinite;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .sparkline-svg {
        stroke-width: 2;
        opacity: 1;
    }
}

/* Reduced motion override */
@media (prefers-reduced-motion: reduce) {
    .sparkline-svg circle {
        animation: none;
    }
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_kpi_sparkline.blade.php ENDPATH**/ ?>