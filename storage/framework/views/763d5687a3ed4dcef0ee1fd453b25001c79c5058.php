


<div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e($column['label']); ?>

                    </th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if(isset($actions)): ?>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('app.actions')); ?>

                    </th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr class="hover:bg-gray-50">
                    <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php if(isset($column['component'])): ?>
                            <?php if (isset($component)) { $__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9 = $component; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => $column['component']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\DynamicComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => $item]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9)): ?>
<?php $component = $__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9; ?>
<?php unset($__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9); ?>
<?php endif; ?>
                        <?php else: ?>
                            <?php echo e($item[$column['key']] ?? ''); ?>

                        <?php endif; ?>
                    </td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if(isset($actions)): ?>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <?php echo e($actions($item)); ?>

                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
    
    <!-- Mobile Card View -->
    <div class="sm:hidden">
        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="border-b border-gray-200 last:border-b-0">
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <?php if(isset($columns[0])): ?>
                            <h3 class="text-sm font-medium text-gray-900 truncate">
                                <?php echo e($item[$columns[0]['key']] ?? ''); ?>

                            </h3>
                        <?php endif; ?>
                    </div>
                    <?php if(isset($actions)): ?>
                    <div class="ml-4 flex-shrink-0">
                        <?php echo e($actions($item)); ?>

                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="space-y-2">
                    <?php $__currentLoopData = array_slice($columns, 1); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500"><?php echo e($column['label']); ?>:</span>
                        <span class="text-xs text-gray-900">
                            <?php if(isset($column['component'])): ?>
                                <?php if (isset($component)) { $__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9 = $component; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => $column['component']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\DynamicComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => $item]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9)): ?>
<?php $component = $__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9; ?>
<?php unset($__componentOriginal3bf0a20793be3eca9a779778cf74145887b021b9); ?>
<?php endif; ?>
                            <?php else: ?>
                                <?php echo e($item[$column['key']] ?? ''); ?>

                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    
    <!-- Empty State -->
    <?php if(count($items) === 0): ?>
    <div class="text-center py-12">
        <div class="mx-auto h-12 w-12 text-gray-400">
            <i class="fas fa-inbox text-4xl"></i>
        </div>
        <h3 class="mt-2 text-sm font-medium text-gray-900"><?php echo e($emptyTitle ?? __('app.no_data')); ?></h3>
        <p class="mt-1 text-sm text-gray-500"><?php echo e($emptyMessage ?? __('app.no_data_message')); ?></p>
        <?php if(isset($emptyAction)): ?>
        <div class="mt-6">
            <?php echo e($emptyAction); ?>

        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if(isset($pagination) && $pagination): ?>
<div class="mt-6">
    <?php echo e($pagination); ?>

</div>
<?php endif; ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/responsive-table.blade.php ENDPATH**/ ?>