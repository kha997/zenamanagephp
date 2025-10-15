


<div class="shared-table" x-data="sharedTable()">
    <!-- Table Container -->
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">
                    <?php echo e($title ?? __('app.items')); ?>

                    <span class="text-sm font-normal text-gray-500" x-text="`(${totalItems})`"></span>
                </h3>
                
                <?php if(isset($showBulkActions) && $showBulkActions): ?>
                    <div class="flex items-center space-x-2" x-show="selectedItems.length > 0">
                        <span class="text-sm text-gray-500" x-text="`${selectedItems.length} <?php echo e(__('app.selected')); ?>`"></span>
                        <button @click="bulkDelete()" 
                                class="px-3 py-1 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                            <i class="fas fa-trash mr-1"></i><?php echo e(__('app.delete_selected')); ?>

                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <!-- Table Header -->
                <thead class="bg-gray-50">
                    <tr>
                        <?php if(isset($showBulkActions) && $showBulkActions): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" 
                                       @change="toggleAllItems($event.target.checked)"
                                       :checked="selectedItems.length === totalItems && totalItems > 0"
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </th>
                        <?php endif; ?>
                        
                        <?php if(isset($columns)): ?>
                            <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span><?php echo e($column['label']); ?></span>
                                        <?php if(isset($column['sortable']) && $column['sortable']): ?>
                                            <button @click="sortBy('<?php echo e($column['key']); ?>')" 
                                                    class="text-gray-400 hover:text-gray-600">
                                                <i class="fas fa-sort" 
                                                   :class="{
                                                       'text-blue-600': sortField === '<?php echo e($column['key']); ?>',
                                                       'fa-sort-up': sortField === '<?php echo e($column['key']); ?>' && sortDirection === 'asc',
                                                       'fa-sort-down': sortField === '<?php echo e($column['key']); ?>' && sortDirection === 'desc'
                                                   }"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </th>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php endif; ?>
                        
                        <?php if(isset($showActions) && $showActions): ?>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('app.actions')); ?>

                            </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                
                <!-- Table Body -->
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(isset($items) && count($items) > 0): ?>
                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <?php if(isset($showBulkActions) && $showBulkActions): ?>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" 
                                               value="<?php echo e($item['id'] ?? $item->id); ?>"
                                               @change="toggleItem('<?php echo e($item['id'] ?? $item->id); ?>', $event.target.checked)"
                                               :checked="selectedItems.includes('<?php echo e($item['id'] ?? $item->id); ?>')"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </td>
                                <?php endif; ?>
                                
                                <?php if(isset($columns)): ?>
                                    <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php if(isset($column['component'])): ?>
                                                <?php echo $__env->make($column['component'], ['item' => $item, 'column' => $column], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                                            <?php elseif(isset($column['format'])): ?>
                                                <?php if($column['format'] === 'date'): ?>
                                                    <?php echo e($item[$column['key']] ? \Carbon\Carbon::parse($item[$column['key']])->format('M d, Y') : '-'); ?>

                                                <?php elseif($column['format'] === 'datetime'): ?>
                                                    <?php echo e($item[$column['key']] ? \Carbon\Carbon::parse($item[$column['key']])->format('M d, Y H:i') : '-'); ?>

                                                <?php elseif($column['format'] === 'currency'): ?>
                                                    <?php echo e($item[$column['key']] ? '$' . number_format($item[$column['key']], 2) : '-'); ?>

                                                <?php elseif($column['format'] === 'percentage'): ?>
                                                    <?php echo e($item[$column['key']] ? $item[$column['key']] . '%' : '-'); ?>

                                                <?php elseif($column['format'] === 'status'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php if($item[$column['key']] === 'active'): ?> bg-green-100 text-green-800
                                                        <?php elseif($item[$column['key']] === 'inactive'): ?> bg-red-100 text-red-800
                                                        <?php elseif($item[$column['key']] === 'pending'): ?> bg-yellow-100 text-yellow-800
                                                        <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?>">
                                                        <?php echo e($item[$column['key']] ?? '-'); ?>

                                                    </span>
                                                <?php elseif($column['format'] === 'badge'): ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($column['badge_class'] ?? 'bg-gray-100 text-gray-800'); ?>">
                                                        <?php echo e($item[$column['key']] ?? '-'); ?>

                                                    </span>
                                                <?php else: ?>
                                                    <?php echo e($item[$column['key']] ?? '-'); ?>

                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo e($item[$column['key']] ?? '-'); ?>

                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php endif; ?>
                                
                                <?php if(isset($showActions) && $showActions): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <?php if(isset($actions)): ?>
                                                <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <?php if(isset($action['condition']) && !$action['condition']($item)): ?>
                                                        <?php continue; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($action['type'] === 'link'): ?>
                                                        <a href="<?php echo e($action['url']($item)); ?>" 
                                                           class="text-blue-600 hover:text-blue-900 transition-colors">
                                                            <i class="<?php echo e($action['icon']); ?>"></i>
                                                        </a>
                                                    <?php elseif($action['type'] === 'button'): ?>
                                                        <button @click="<?php echo e($action['handler']); ?>('<?php echo e($item['id'] ?? $item->id); ?>')"
                                                                class="text-blue-600 hover:text-blue-900 transition-colors">
                                                            <i class="<?php echo e($action['icon']); ?>"></i>
                                                        </button>
                                                    <?php elseif($action['type'] === 'dropdown'): ?>
                                                        <div class="relative" x-data="{ open: false }">
                                                            <button @click="open = !open" 
                                                                    class="text-gray-400 hover:text-gray-600">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            
                                                            <div x-show="open" 
                                                                 @click.away="open = false"
                                                                 x-transition:enter="transition ease-out duration-100"
                                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                                 x-transition:leave="transition ease-in duration-75"
                                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                                                <?php $__currentLoopData = $action['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <a href="<?php echo e($item['url']($item)); ?>" 
                                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                        <i class="<?php echo e($item['icon']); ?> mr-2"></i><?php echo e($item['label']); ?>

                                                                    </a>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo e((isset($showBulkActions) && $showBulkActions ? 1 : 0) + (isset($columns) ? count($columns) : 0) + (isset($showActions) && $showActions ? 1 : 0)); ?>" 
                                class="px-6 py-12 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                                    <p><?php echo e(__('app.no_items_found')); ?></p>
                                    <?php if(isset($emptyStateAction)): ?>
                                        <button @click="<?php echo e($emptyStateAction['handler']); ?>" 
                                                class="mt-4 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                                            <i class="<?php echo e($emptyStateAction['icon']); ?> mr-2"></i><?php echo e($emptyStateAction['label']); ?>

                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if(isset($pagination) && $pagination): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <?php echo e($pagination); ?>

            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sharedTable', () => ({
        // State
        selectedItems: [],
        sortField: '<?php echo e($sortField ?? 'created_at'); ?>',
        sortDirection: '<?php echo e($sortDirection ?? 'desc'); ?>',
        totalItems: <?php echo e($totalItems ?? 0); ?>,
        
        // Methods
        toggleAllItems(checked) {
            if (checked) {
                // Select all items
                this.selectedItems = <?php echo json_encode(collect($items ?? [])->pluck('id')->toArray(), 15, 512) ?>;
            } else {
                this.selectedItems = [];
            }
        },
        
        toggleItem(itemId, checked) {
            if (checked) {
                if (!this.selectedItems.includes(itemId)) {
                    this.selectedItems.push(itemId);
                }
            } else {
                this.selectedItems = this.selectedItems.filter(id => id !== itemId);
            }
        },
        
        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
            
            // Emit sort event
            this.$dispatch('table-sort', {
                field: this.sortField,
                direction: this.sortDirection
            });
        },
        
        async bulkDelete() {
            if (this.selectedItems.length === 0) {
                return;
            }
            
            if (!confirm(`<?php echo e(__('app.confirm_delete_selected', ['count' => ''])); ?>${this.selectedItems.length} <?php echo e(__('app.items')); ?>?`)) {
                return;
            }
            
            try {
                const response = await fetch('/api/v1/app/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        ids: this.selectedItems,
                        type: '<?php echo e($bulkDeleteType ?? 'default'); ?>'
                    })
                });
                
                if (response.ok) {
                    this.selectedItems = [];
                    // Reload the page or emit refresh event
                    this.$dispatch('table-refresh');
                } else {
                    alert('<?php echo e(__("app.failed_to_delete_items")); ?>');
                }
            } catch (error) {
                console.error('Error deleting items:', error);
                alert('<?php echo e(__("app.failed_to_delete_items")); ?>');
            }
        }
    }));
});
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/table.blade.php ENDPATH**/ ?>