


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'title' => null,
    'subtitle' => null,
    'columns' => [],
    'items' => [],
    'actions' => [],
    'showBulkActions' => false,
    'showActions' => true,
    'showSearch' => false,
    'showFilters' => false,
    'pagination' => null,
    'emptyState' => null,
    'loading' => false,
    'sortable' => true,
    'sticky' => false,
    'variant' => 'default', // 'default', 'compact', 'bordered'
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'title' => null,
    'subtitle' => null,
    'columns' => [],
    'items' => [],
    'actions' => [],
    'showBulkActions' => false,
    'showActions' => true,
    'showSearch' => false,
    'showFilters' => false,
    'pagination' => null,
    'emptyState' => null,
    'loading' => false,
    'sortable' => true,
    'sticky' => false,
    'variant' => 'default', // 'default', 'compact', 'bordered'
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'title' => null,
    'subtitle' => null,
    'columns' => [],
    'items' => [],
    'actions' => [],
    'showBulkActions' => false,
    'showActions' => true,
    'showSearch' => false,
    'showFilters' => false,
    'pagination' => null,
    'emptyState' => null,
    'loading' => false,
    'sortable' => true,
    'sticky' => false,
    'variant' => 'default', // 'default', 'compact', 'bordered'
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
    $isCompact = $variant === 'compact';
    $isBordered = $variant === 'bordered';
    $hasItems = !empty($items) && count($items) > 0;
    
    // Default empty state
    if (!$emptyState) {
        $emptyState = [
            'icon' => 'fas fa-inbox',
            'title' => 'No items found',
            'description' => 'There are no items to display at the moment.',
            'action' => null
        ];
    }
?>

<div class="table-container" 
     x-data="standardizedTable()" 
     :class="{ 'loading': loading }">
    
    
    <div class="table-header">
        <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
                <?php if($title): ?>
                    <h3 class="table-title"><?php echo e($title); ?></h3>
                <?php endif; ?>
                <?php if($subtitle): ?>
                    <p class="table-subtitle"><?php echo e($subtitle); ?></p>
                <?php endif; ?>
            </div>
            
            
            <div class="flex items-center space-x-3">
                
                <?php if($showSearch): ?>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               x-model="searchQuery"
                               @input.debounce.300ms="handleSearch()"
                               placeholder="Search..."
                               class="table-search-input">
                    </div>
                <?php endif; ?>
                
                
                <?php if($showFilters): ?>
                    <button @click="toggleFilters()" 
                            :class="{ 'bg-blue-50 text-blue-700': filtersOpen }"
                            class="table-filter-btn">
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                        <span x-show="activeFiltersCount > 0" 
                              x-text="activeFiltersCount"
                              class="ml-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full"></span>
                    </button>
                <?php endif; ?>
                
                
                <?php if($showBulkActions && $hasItems): ?>
                    <div x-show="selectedItems.length > 0" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600" x-text="`${selectedItems.length} selected`"></span>
                        <button @click="bulkDelete()" 
                                class="table-bulk-action-btn">
                            <i class="fas fa-trash mr-1"></i>
                            Delete Selected
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        
        <?php if($showFilters): ?>
            <div x-show="filtersOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="table-filters-panel">
                
                <?php echo e($filters ?? ''); ?>

            </div>
        <?php endif; ?>
    </div>
    
    
    <div class="table-wrapper <?php echo e($isBordered ? 'bordered' : ''); ?>">
        
        <div x-show="loading" 
             class="table-loading-overlay">
            <div class="table-loading-spinner">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                <p class="mt-2 text-sm text-gray-500">Loading...</p>
            </div>
        </div>
        
        
        <div class="table-scroll-container">
            <table class="table <?php echo e($isCompact ? 'compact' : ''); ?> <?php echo e($sticky ? 'sticky-header' : ''); ?>">
                
                <thead class="table-head">
                    <tr>
                        
                        <?php if($showBulkActions && $hasItems): ?>
                            <th class="table-th bulk-select">
                                <input type="checkbox" 
                                       @change="toggleAllItems($event.target.checked)"
                                       :checked="selectedItems.length === totalItems && totalItems > 0"
                                       class="table-checkbox">
                            </th>
                        <?php endif; ?>
                        
                        
                        <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <th class="table-th <?php echo e($column['class'] ?? ''); ?>"
                                :class="{ 'sortable': <?php echo e($sortable && ($column['sortable'] ?? true) ? 'true' : 'false'); ?> }">
                                <div class="table-th-content">
                                    <span class="table-th-label"><?php echo e($column['label']); ?></span>
                                    
                                    <?php if($sortable && ($column['sortable'] ?? true)): ?>
                                        <button @click="sortBy('<?php echo e($column['key']); ?>')" 
                                                class="table-sort-btn"
                                                :class="{
                                                    'active': sortField === '<?php echo e($column['key']); ?>',
                                                    'asc': sortField === '<?php echo e($column['key']); ?>' && sortDirection === 'asc',
                                                    'desc': sortField === '<?php echo e($column['key']); ?>' && sortDirection === 'desc'
                                                }">
                                            <i class="fas fa-sort"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        
                        
                        <?php if($showActions): ?>
                            <th class="table-th actions">
                                <span class="table-th-label">Actions</span>
                            </th>
                        <?php endif; ?>
                    </tr>
                </thead>
                
                
                <tbody class="table-body">
                    <?php if($hasItems): ?>
                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="table-row" 
                                :class="{ 'selected': selectedItems.includes('<?php echo e($item['id'] ?? $item->id); ?>') }">
                                
                                
                                <?php if($showBulkActions): ?>
                                    <td class="table-td bulk-select">
                                        <input type="checkbox" 
                                               value="<?php echo e($item['id'] ?? $item->id); ?>"
                                               @change="toggleItem('<?php echo e($item['id'] ?? $item->id); ?>', $event.target.checked)"
                                               :checked="selectedItems.includes('<?php echo e($item['id'] ?? $item->id); ?>')"
                                               class="table-checkbox">
                                    </td>
                                <?php endif; ?>
                                
                                
                                <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $column): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <td class="table-td <?php echo e($column['class'] ?? ''); ?>">
                                        <?php if(isset($column['component'])): ?>
                                            <?php echo $__env->make($column['component'], ['item' => $item, 'column' => $column, 'index' => $index], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                                        <?php else: ?>
                                            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-cell','data' => ['item' => $item,'column' => $column,'index' => $index]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-cell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['item' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item),'column' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($column),'index' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($index)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                
                                
                                <?php if($showActions): ?>
                                    <td class="table-td actions">
                                        <div class="table-actions">
                                            <?php if(!empty($actions)): ?>
                                                <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <?php if(isset($action['condition']) && !$action['condition']($item)): ?>
                                                        <?php continue; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($action['type'] === 'link'): ?>
                                                        <a href="<?php echo e($action['url']($item)); ?>" 
                                                           class="table-action-link"
                                                           title="<?php echo e($action['title'] ?? $action['label']); ?>">
                                                            <i class="<?php echo e($action['icon']); ?>"></i>
                                                            <?php if(!$isCompact): ?>
                                                                <span class="ml-1"><?php echo e($action['label']); ?></span>
                                                            <?php endif; ?>
                                                        </a>
                                                    <?php elseif($action['type'] === 'button'): ?>
                                                        <button @click="<?php echo e($action['handler']); ?>('<?php echo e($item['id'] ?? $item->id); ?>')"
                                                                class="table-action-btn"
                                                                title="<?php echo e($action['title'] ?? $action['label']); ?>">
                                                            <i class="<?php echo e($action['icon']); ?>"></i>
                                                            <?php if(!$isCompact): ?>
                                                                <span class="ml-1"><?php echo e($action['label']); ?></span>
                                                            <?php endif; ?>
                                                        </button>
                                                    <?php elseif($action['type'] === 'dropdown'): ?>
                                                        <div class="relative" x-data="{ open: false }">
                                                            <button @click="open = !open" 
                                                                    class="table-action-btn"
                                                                    title="More actions">
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
                                                                 class="table-dropdown">
                                                                <?php $__currentLoopData = $action['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dropdownItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <a href="<?php echo e($dropdownItem['url']($item)); ?>" 
                                                                       class="table-dropdown-item">
                                                                        <i class="<?php echo e($dropdownItem['icon']); ?> mr-2"></i>
                                                                        <?php echo e($dropdownItem['label']); ?>

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
                            <td colspan="<?php echo e(($showBulkActions ? 1 : 0) + count($columns) + ($showActions ? 1 : 0)); ?>" 
                                class="table-empty-cell">
                                <div class="table-empty-state">
                                    <i class="<?php echo e($emptyState['icon']); ?> text-4xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo e($emptyState['title']); ?></h3>
                                    <p class="text-gray-500 mb-4"><?php echo e($emptyState['description']); ?></p>
                                    <?php if($emptyState['action']): ?>
                                        <button @click="<?php echo e($emptyState['action']['handler']); ?>" 
                                                class="table-empty-action-btn">
                                            <i class="<?php echo e($emptyState['action']['icon']); ?> mr-2"></i>
                                            <?php echo e($emptyState['action']['label']); ?>

                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    
    <?php if($pagination): ?>
        <div class="table-pagination">
            <?php echo e($pagination); ?>

        </div>
    <?php endif; ?>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('standardizedTable', () => ({
        // State
        selectedItems: [],
        sortField: '<?php echo e($sortField ?? 'created_at'); ?>',
        sortDirection: '<?php echo e($sortDirection ?? 'desc'); ?>',
        totalItems: <?php echo e(count($items ?? [])); ?>,
        searchQuery: '',
        filtersOpen: false,
        activeFiltersCount: 0,
        loading: <?php echo e($loading ? 'true' : 'false'); ?>,
        
        // Methods
        toggleAllItems(checked) {
            if (checked) {
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
            
            this.$dispatch('table-sort', {
                field: this.sortField,
                direction: this.sortDirection
            });
        },
        
        handleSearch() {
            this.$dispatch('table-search', {
                query: this.searchQuery
            });
        },
        
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        async bulkDelete() {
            if (this.selectedItems.length === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${this.selectedItems.length} selected items?`)) {
                return;
            }
            
            try {
                this.loading = true;
                
                const response = await fetch('/api/v1/bulk-delete', {
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
                    this.$dispatch('table-refresh');
                } else {
                    alert('Failed to delete items');
                }
            } catch (error) {
                console.error('Error deleting items:', error);
                alert('Failed to delete items');
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/table-standardized.blade.php ENDPATH**/ ?>