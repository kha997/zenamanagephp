


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'item' => null,
    'column' => [],
    'index' => 0
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'item' => null,
    'column' => [],
    'index' => 0
]); ?>
<?php foreach (array_filter(([
    'item' => null,
    'column' => [],
    'index' => 0
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $key = $column['key'] ?? 'id';
    $format = $column['format'] ?? 'text';
    $value = $item[$key] ?? $item->{$key} ?? null;
    $class = $column['class'] ?? '';
?>

<div class="table-cell <?php echo e($class); ?>">
    <?php switch($format):
        case ('date'): ?>
            <span class="text-sm text-gray-900">
                <?php echo e($value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('datetime'): ?>
            <span class="text-sm text-gray-900">
                <?php echo e($value ? \Carbon\Carbon::parse($value)->format('M d, Y H:i') : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('time'): ?>
            <span class="text-sm text-gray-900">
                <?php echo e($value ? \Carbon\Carbon::parse($value)->format('H:i') : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('currency'): ?>
            <span class="text-sm text-gray-900 font-medium">
                <?php echo e($value ? '$' . number_format($value, 2) : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('percentage'): ?>
            <span class="text-sm text-gray-900">
                <?php echo e($value ? $value . '%' : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('number'): ?>
            <span class="text-sm text-gray-900 font-mono">
                <?php echo e($value ? number_format($value) : '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('status'): ?>
            <?php
                $statusConfig = $column['status_config'] ?? [];
                $statusClass = $statusConfig[$value] ?? 'bg-gray-100 text-gray-800';
            ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                <?php echo e($value ?? '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('badge'): ?>
            <?php
                $badgeClass = $column['badge_class'] ?? 'bg-gray-100 text-gray-800';
                $badgeColor = $column['badge_color'] ?? null;
                if ($badgeColor && $value) {
                    $badgeClass = "bg-{$badgeColor}-100 text-{$badgeColor}-800";
                }
            ?>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($badgeClass); ?>">
                <?php echo e($value ?? '-'); ?>

            </span>
            <?php break; ?>
            
        <?php case ('avatar'): ?>
            <div class="flex items-center">
                <div class="flex-shrink-0 h-8 w-8">
                    <?php if($value): ?>
                        <img class="h-8 w-8 rounded-full object-cover" 
                             src="<?php echo e($value); ?>" 
                             alt="<?php echo e($item['name'] ?? $item->name ?? 'Avatar'); ?>">
                    <?php else: ?>
                        <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                            <i class="fas fa-user text-gray-600 text-sm"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if(isset($column['show_name']) && $column['show_name']): ?>
                    <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">
                            <?php echo e($item['name'] ?? $item->name ?? 'Unknown'); ?>

                        </div>
                        <?php if(isset($column['show_email']) && $column['show_email']): ?>
                            <div class="text-sm text-gray-500">
                                <?php echo e($item['email'] ?? $item->email ?? ''); ?>

                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php break; ?>
            
        <?php case ('progress'): ?>
            <div class="flex items-center">
                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                    <div class="bg-blue-600 h-2 rounded-full" 
                         style="width: <?php echo e(min(100, max(0, $value ?? 0))); ?>%"></div>
                </div>
                <span class="text-sm text-gray-600 font-medium">
                    <?php echo e($value ?? 0); ?>%
                </span>
            </div>
            <?php break; ?>
            
        <?php case ('tags'): ?>
            <?php if($value && is_array($value)): ?>
                <div class="flex flex-wrap gap-1">
                    <?php $__currentLoopData = array_slice($value, 0, 3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            <?php echo e($tag); ?>

                        </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if(count($value) > 3): ?>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            +<?php echo e(count($value) - 3); ?>

                        </span>
                    <?php endif; ?>
                </div>
            <?php elseif($value): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    <?php echo e($value); ?>

                </span>
            <?php else: ?>
                <span class="text-gray-400">-</span>
            <?php endif; ?>
            <?php break; ?>
            
        <?php case ('boolean'): ?>
            <span class="inline-flex items-center">
                <?php if($value): ?>
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span class="ml-1 text-sm text-green-700">Yes</span>
                <?php else: ?>
                    <i class="fas fa-times-circle text-red-500"></i>
                    <span class="ml-1 text-sm text-red-700">No</span>
                <?php endif; ?>
            </span>
            <?php break; ?>
            
        <?php case ('link'): ?>
            <?php if($value): ?>
                <a href="<?php echo e($value); ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    <?php echo e($column['link_text'] ?? 'View'); ?>

                    <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                </a>
            <?php else: ?>
                <span class="text-gray-400">-</span>
            <?php endif; ?>
            <?php break; ?>
            
        <?php case ('json'): ?>
            <div class="text-sm">
                <?php if($value): ?>
                    <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto max-w-xs"><?php echo e(json_encode($value, JSON_PRETTY_PRINT)); ?></pre>
                <?php else: ?>
                    <span class="text-gray-400">-</span>
                <?php endif; ?>
            </div>
            <?php break; ?>
            
        <?php case ('html'): ?>
            <div class="text-sm">
                <?php echo $value ?? '-'; ?>

            </div>
            <?php break; ?>
            
        <?php case ('text'): ?>
        <?php default: ?>
            <span class="text-sm text-gray-900">
                <?php echo e($value ?? '-'); ?>

            </span>
            <?php break; ?>
    <?php endswitch; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/table-cell.blade.php ENDPATH**/ ?>