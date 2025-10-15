


<div class="shared-filters" x-data="sharedFilters()">
    <!-- Filter Header -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <!-- Filter Toggle -->
            <button @click="toggleFilters()" 
                    class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="filtersOpen ? 'bg-blue-50 text-blue-700 border-blue-300' : ''">
                <i class="fas fa-filter"></i>
                <span><?php echo e(__('app.filters')); ?></span>
                <span x-show="activeFiltersCount > 0" 
                      class="bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
                      x-text="activeFiltersCount"></span>
            </button>
            
            <!-- Quick Filters -->
            <div class="flex items-center space-x-2">
                <?php if(isset($quickFilters)): ?>
                    <?php $__currentLoopData = $quickFilters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button @click="applyQuickFilter('<?php echo e($filter['key']); ?>', '<?php echo e($filter['value']); ?>')"
                                class="px-3 py-1 text-xs font-medium rounded-full border transition-colors"
                                :class="isQuickFilterActive('<?php echo e($filter['key']); ?>', '<?php echo e($filter['value']); ?>') ? 
                                    'bg-blue-100 text-blue-700 border-blue-300' : 
                                    'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'">
                            <?php echo e($filter['label']); ?>

                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="flex items-center space-x-2">
            <?php if(isset($showSaveView) && $showSaveView): ?>
                <button @click="showSaveViewModal = true" 
                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-save mr-1"></i><?php echo e(__('app.save_view')); ?>

                </button>
            <?php endif; ?>
            
            <?php if(isset($showExport) && $showExport): ?>
                <button @click="exportData()" 
                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-download mr-1"></i><?php echo e(__('app.export')); ?>

                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filter Panel -->
    <div x-show="filtersOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        
        <!-- Filter Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php if(isset($filters)): ?>
                <?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <?php echo e($filter['label']); ?>

                        </label>
                        
                        <?php if($filter['type'] === 'select'): ?>
                            <select x-model="activeFilters['<?php echo e($filter['name']); ?>']"
                                    @change="applyFilter('<?php echo e($filter['name']); ?>', $event.target.value)"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value=""><?php echo e($filter['placeholder'] ?? __('app.all')); ?></option>
                                <?php $__currentLoopData = $filter['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        <?php elseif($filter['type'] === 'date'): ?>
                            <input type="date" 
                                   x-model="activeFilters['<?php echo e($filter['name']); ?>']"
                                   @change="applyFilter('<?php echo e($filter['name']); ?>', $event.target.value)"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <?php elseif($filter['type'] === 'daterange'): ?>
                            <div class="flex space-x-2">
                                <input type="date" 
                                       x-model="activeFilters['<?php echo e($filter['name']); ?>_from']"
                                       @change="applyDateRangeFilter('<?php echo e($filter['name']); ?>')"
                                       placeholder="<?php echo e(__('app.from')); ?>"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <input type="date" 
                                       x-model="activeFilters['<?php echo e($filter['name']); ?>_to']"
                                       @change="applyDateRangeFilter('<?php echo e($filter['name']); ?>')"
                                       placeholder="<?php echo e(__('app.to')); ?>"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        <?php elseif($filter['type'] === 'multiselect'): ?>
                            <div class="relative">
                                <select x-model="activeFilters['<?php echo e($filter['name']); ?>']"
                                        @change="applyFilter('<?php echo e($filter['name']); ?>', $event.target.value)"
                                        multiple
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <?php $__currentLoopData = $filter['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        <?php elseif($filter['type'] === 'text'): ?>
                            <input type="text" 
                                   x-model="activeFilters['<?php echo e($filter['name']); ?>']"
                                   @input.debounce.300ms="applyFilter('<?php echo e($filter['name']); ?>', $event.target.value)"
                                   placeholder="<?php echo e($filter['placeholder'] ?? ''); ?>"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php endif; ?>
        </div>
        
        <!-- Filter Actions -->
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">
                    <?php echo e(__('app.active_filters', ['count' => '<span x-text="activeFiltersCount"></span>'])); ?>

                </span>
                <button @click="clearAllFilters()" 
                        x-show="activeFiltersCount > 0"
                        class="text-sm text-red-600 hover:text-red-800">
                    <?php echo e(__('app.clear_all')); ?>

                </button>
            </div>
            
            <div class="flex items-center space-x-2">
                <button @click="resetFilters()" 
                        class="px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                    <?php echo e(__('app.reset')); ?>

                </button>
                <button @click="applyAllFilters()" 
                        class="px-3 py-1 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                    <?php echo e(__('app.apply_filters')); ?>

                </button>
            </div>
        </div>
    </div>
    
    <!-- Save View Modal -->
    <div x-show="showSaveViewModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-modal-backdrop">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-medium text-gray-900 mb-4"><?php echo e(__('app.save_view')); ?></h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('app.view_name')); ?></label>
                    <input type="text" 
                           x-model="newViewName"
                           placeholder="<?php echo e(__('app.enter_view_name')); ?>"
                           class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?php echo e(__('app.description')); ?></label>
                    <textarea x-model="newViewDescription"
                              placeholder="<?php echo e(__('app.enter_description')); ?>"
                              rows="3"
                              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                </div>
            </div>
            
            <div class="flex items-center justify-end space-x-3 mt-6">
                <button @click="showSaveViewModal = false" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    <?php echo e(__('app.cancel')); ?>

                </button>
                <button @click="saveView()" 
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <?php echo e(__('app.save')); ?>

                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sharedFilters', () => ({
        // State
        filtersOpen: false,
        activeFilters: {},
        showSaveViewModal: false,
        newViewName: '',
        newViewDescription: '',
        
        // Computed Properties
        get activeFiltersCount() {
            return Object.values(this.activeFilters).filter(value => 
                value !== '' && value !== null && value !== undefined
            ).length;
        },
        
        // Methods
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        applyFilter(name, value) {
            this.activeFilters[name] = value;
            this.triggerFilterChange();
        },
        
        applyDateRangeFilter(name) {
            const from = this.activeFilters[`${name}_from`];
            const to = this.activeFilters[`${name}_to`];
            
            if (from && to) {
                this.activeFilters[name] = `${from}|${to}`;
            } else {
                delete this.activeFilters[name];
            }
            
            this.triggerFilterChange();
        },
        
        applyQuickFilter(key, value) {
            this.activeFilters[key] = value;
            this.triggerFilterChange();
        },
        
        isQuickFilterActive(key, value) {
            return this.activeFilters[key] === value;
        },
        
        clearAllFilters() {
            this.activeFilters = {};
            this.triggerFilterChange();
        },
        
        resetFilters() {
            this.activeFilters = {};
            this.filtersOpen = false;
            this.triggerFilterChange();
        },
        
        applyAllFilters() {
            this.triggerFilterChange();
        },
        
        triggerFilterChange() {
            // Emit custom event for parent components to listen
            this.$dispatch('filters-changed', this.activeFilters);
        },
        
        exportData() {
            // Emit custom event for parent components to handle export
            this.$dispatch('export-data', this.activeFilters);
        },
        
        async saveView() {
            if (!this.newViewName.trim()) {
                alert('<?php echo e(__("app.please_enter_view_name")); ?>');
                return;
            }
            
            try {
                const response = await fetch('/api/v1/app/saved-views', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: this.newViewName,
                        description: this.newViewDescription,
                        filters: this.activeFilters,
                        type: '<?php echo e($viewType ?? "default"); ?>'
                    })
                });
                
                if (response.ok) {
                    this.showSaveViewModal = false;
                    this.newViewName = '';
                    this.newViewDescription = '';
                    alert('<?php echo e(__("app.view_saved_successfully")); ?>');
                } else {
                    alert('<?php echo e(__("app.failed_to_save_view")); ?>');
                }
            } catch (error) {
                console.error('Error saving view:', error);
                alert('<?php echo e(__("app.failed_to_save_view")); ?>');
            }
        }
    }));
});
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/filters.blade.php ENDPATH**/ ?>