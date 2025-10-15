


<div class="shared-card-grid" x-data="sharedCardGrid()">
    <!-- Grid Header -->
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4">
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
        
        <!-- Grid Controls -->
        <div class="flex items-center space-x-3">
            <!-- View Density -->
            <div class="flex items-center space-x-1 bg-gray-100 rounded-lg p-1">
                <button @click="setDensity('compact')" 
                        :class="density === 'compact' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                        class="px-2 py-1 text-xs font-medium rounded-md transition-colors">
                    <i class="fas fa-th"></i>
                </button>
                <button @click="setDensity('normal')" 
                        :class="density === 'normal' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                        class="px-2 py-1 text-xs font-medium rounded-md transition-colors">
                    <i class="fas fa-th-large"></i>
                </button>
                <button @click="setDensity('comfortable')" 
                        :class="density === 'comfortable' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                        class="px-2 py-1 text-xs font-medium rounded-md transition-colors">
                    <i class="fas fa-th-list"></i>
                </button>
            </div>
            
            <!-- Sort Options -->
            <?php if(isset($sortOptions)): ?>
                <select x-model="sortField" 
                        @change="sortBy(sortField)"
                        class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <?php $__currentLoopData = $sortOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($option['value']); ?>"><?php echo e($option['label']); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Grid Container -->
    <div :class="{
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4': density === 'comfortable',
        'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3': density === 'normal',
        'grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 xl:grid-cols-8 gap-2': density === 'compact'
    }">
        <?php if(isset($items) && count($items) > 0): ?>
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="bg-white rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200 cursor-pointer"
                     :class="{
                         'ring-2 ring-blue-500': selectedItems.includes('<?php echo e($item['id'] ?? $item->id); ?>'),
                         'p-4': density === 'comfortable',
                         'p-3': density === 'normal',
                         'p-2': density === 'compact'
                     }"
                     @click="toggleItem('<?php echo e($item['id'] ?? $item->id); ?>')">
                    
                    <?php if(isset($showBulkActions) && $showBulkActions): ?>
                        <div class="flex items-start justify-between mb-2">
                            <input type="checkbox" 
                                   :checked="selectedItems.includes('<?php echo e($item['id'] ?? $item->id); ?>')"
                                   @click.stop="toggleItem('<?php echo e($item['id'] ?? $item->id); ?>')"
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            
                            <?php if(isset($showActions) && $showActions): ?>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click.stop="open = !open" 
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
                                        <?php if(isset($actions)): ?>
                                            <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php if(isset($action['condition']) && !$action['condition']($item)): ?>
                                                    <?php continue; ?>
                                                <?php endif; ?>
                                                
                                                <?php if($action['type'] === 'link'): ?>
                                                    <a href="<?php echo e($action['url']($item)); ?>" 
                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="<?php echo e($action['icon']); ?> mr-2"></i><?php echo e($action['label']); ?>

                                                    </a>
                                                <?php elseif($action['type'] === 'button'): ?>
                                                    <button @click="<?php echo e($action['handler']); ?>('<?php echo e($item['id'] ?? $item->id); ?>')"
                                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                        <i class="<?php echo e($action['icon']); ?> mr-2"></i><?php echo e($action['label']); ?>

                                                    </button>
                                                <?php endif; ?>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Card Content -->
                    <div class="space-y-2">
                        <!-- Title -->
                        <h4 class="font-medium text-gray-900 truncate" 
                            :class="{
                                'text-sm': density === 'compact',
                                'text-base': density === 'normal',
                                'text-lg': density === 'comfortable'
                            }">
                            <?php echo e($item['title'] ?? $item['name'] ?? $item->title ?? $item->name ?? __('app.untitled')); ?>

                        </h4>
                        
                        <!-- Subtitle/Description -->
                        <?php if(isset($item['description']) || isset($item->description)): ?>
                            <p class="text-gray-600 text-sm line-clamp-2" 
                               :class="{
                                   'text-xs': density === 'compact',
                                   'text-sm': density === 'normal',
                                   'text-base': density === 'comfortable'
                               }">
                                <?php echo e($item['description'] ?? $item->description); ?>

                            </p>
                        <?php endif; ?>
                        
                        <!-- Metadata -->
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <?php if(isset($item['status']) || isset($item->status)): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    <?php if(($item['status'] ?? $item->status) === 'active'): ?> bg-green-100 text-green-800
                                    <?php elseif(($item['status'] ?? $item->status) === 'inactive'): ?> bg-red-100 text-red-800
                                    <?php elseif(($item['status'] ?? $item->status) === 'pending'): ?> bg-yellow-100 text-yellow-800
                                    <?php else: ?> bg-gray-100 text-gray-800 <?php endif; ?>">
                                    <?php echo e($item['status'] ?? $item->status); ?>

                                </span>
                            <?php endif; ?>
                            
                            <?php if(isset($item['created_at']) || isset($item->created_at)): ?>
                                <span><?php echo e(\Carbon\Carbon::parse($item['created_at'] ?? $item->created_at)->format('M d')); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Progress Bar (if applicable) -->
                        <?php if(isset($item['progress']) || isset($item->progress)): ?>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" 
                                     :style="`width: ${ <?php echo e($item['progress'] ?? $item->progress ?? 0); ?> }%`"></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Tags (if applicable) -->
                        <?php if(isset($item['tags']) || isset($item->tags)): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php $__currentLoopData = collect($item['tags'] ?? $item->tags ?? [])->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        <?php echo e($tag); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php if(collect($item['tags'] ?? $item->tags ?? [])->count() > 3): ?>
                                    <span class="text-xs text-gray-500">+<?php echo e(collect($item['tags'] ?? $item->tags ?? [])->count() - 3); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php else: ?>
            <!-- Empty State -->
            <div class="col-span-full flex flex-col items-center justify-center py-12">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo e(__('app.no_items_found')); ?></h3>
                <p class="text-gray-500 text-center mb-6"><?php echo e(__('app.no_items_description')); ?></p>
                <?php if(isset($emptyStateAction)): ?>
                    <button @click="<?php echo e($emptyStateAction['handler']); ?>" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                        <i class="<?php echo e($emptyStateAction['icon']); ?> mr-2"></i><?php echo e($emptyStateAction['label']); ?>

                    </button>
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
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sharedCardGrid', () => ({
        // State
        selectedItems: [],
        density: '<?php echo e($defaultDensity ?? 'normal'); ?>',
        sortField: '<?php echo e($sortField ?? 'created_at'); ?>',
        totalItems: <?php echo e($totalItems ?? 0); ?>,
        
        // Methods
        toggleItem(itemId) {
            if (this.selectedItems.includes(itemId)) {
                this.selectedItems = this.selectedItems.filter(id => id !== itemId);
            } else {
                this.selectedItems.push(itemId);
            }
        },
        
        setDensity(density) {
            this.density = density;
            // Save preference
            localStorage.setItem('card-grid-density', density);
        },
        
        sortBy(field) {
            this.sortField = field;
            // Emit sort event
            this.$dispatch('grid-sort', {
                field: this.sortField
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
                    this.$dispatch('grid-refresh');
                } else {
                    alert('<?php echo e(__("app.failed_to_delete_items")); ?>');
                }
            } catch (error) {
                console.error('Error deleting items:', error);
                alert('<?php echo e(__("app.failed_to_delete_items")); ?>');
            }
        },
        
        // Initialize
        init() {
            // Load saved density preference
            const savedDensity = localStorage.getItem('card-grid-density');
            if (savedDensity) {
                this.density = savedDensity;
            }
        }
    }));
});
</script>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/card-grid.blade.php ENDPATH**/ ?>