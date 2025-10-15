


<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
            <!-- Left Side: Search and Filters -->
            <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                <!-- Search -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text" 
                           name="search" 
                           placeholder="<?php echo e($searchPlaceholder ?? __('app.search_placeholder')); ?>"
                           value="<?php echo e(request('search')); ?>"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                           x-data="searchComponent()"
                           @input.debounce.300ms="search($event.target.value)">
                </div>
                
                <!-- Filters -->
                <?php if(isset($filters) && count($filters) > 0): ?>
                <div class="flex items-center space-x-2">
                    <button @click="filtersOpen = !filtersOpen" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-filter mr-2"></i>
                        <?php echo e(__('app.filters')); ?>

                        <?php if(isset($activeFilters) && count($activeFilters) > 0): ?>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo e(count($activeFilters)); ?>

                            </span>
                        <?php endif; ?>
                    </button>
                    
                    <?php if(isset($activeFilters) && count($activeFilters) > 0): ?>
                    <button onclick="clearFilters()" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-times mr-2"></i>
                        <?php echo e(__('app.clear_filters')); ?>

                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Side: View Toggle and Actions -->
            <div class="flex items-center space-x-4">
                <!-- View Toggle -->
                <?php if(isset($viewMode)): ?>
                <div class="flex items-center bg-gray-100 rounded-lg p-1">
                    <button onclick="setViewMode('table')" 
                            class="view-toggle <?php echo e(($viewMode ?? 'table') === 'table' ? 'view-toggle-active' : ''); ?>">
                        <i class="fas fa-table"></i>
                    </button>
                    <button onclick="setViewMode('card')" 
                            class="view-toggle <?php echo e(($viewMode ?? 'table') === 'card' ? 'view-toggle-active' : ''); ?>">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Export -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-download mr-2"></i>
                        <?php echo e(__('app.export')); ?>

                        <i class="fas fa-chevron-down ml-2 text-xs"></i>
                    </button>
                    
                    <!-- Export Dropdown -->
                    <div x-show="open" 
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-dropdown-menu">
                        <div class="py-1">
                            <button onclick="exportData('csv')" 
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-csv mr-3"></i><?php echo e(__('app.export_csv')); ?>

                            </button>
                            <button onclick="exportData('excel')" 
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-excel mr-3"></i><?php echo e(__('app.export_excel')); ?>

                            </button>
                            <button onclick="exportData('pdf')" 
                                    class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-file-pdf mr-3"></i><?php echo e(__('app.export_pdf')); ?>

                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Refresh -->
                <button onclick="refreshData()" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        
        <!-- Filters Panel -->
        <?php if(isset($filters) && count($filters) > 0): ?>
        <div x-show="filtersOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="mt-4 pt-4 border-t border-gray-200">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php $__currentLoopData = $filters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        <?php echo e($filter['label']); ?>

                    </label>
                    <select name="<?php echo e($filter['name']); ?>" 
                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <option value=""><?php echo e($filter['placeholder'] ?? __('app.all')); ?></option>
                        <?php $__currentLoopData = $filter['options']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($value); ?>" <?php echo e(request($filter['name']) == $value ? 'selected' : ''); ?>>
                            <?php echo e($label); ?>

                        </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
                <button onclick="applyFilters()" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php echo e(__('app.apply_filters')); ?>

                </button>
                <button onclick="clearFilters()" 
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <?php echo e(__('app.clear_filters')); ?>

                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Styles -->
<style>
.view-toggle {
    @apply px-3 py-2 text-sm font-medium rounded-md transition-colors;
}

.view-toggle-active {
    @apply bg-white text-blue-600 shadow-sm;
}

.view-toggle:not(.view-toggle-active) {
    @apply text-gray-500 hover:text-gray-700;
}
</style>

<!-- Alpine.js Component -->
<script>
function searchComponent() {
    return {
        search(value) {
            // Implement search functionality
            const url = new URL(window.location);
            if (value) {
                url.searchParams.set('search', value);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }
    }
}

function setViewMode(mode) {
    const url = new URL(window.location);
    url.searchParams.set('view_mode', mode);
    window.location.href = url.toString();
}

function clearFilters() {
    const url = new URL(window.location);
    // Remove filter parameters
    const filterParams = ['status', 'priority', 'type', 'lifecycle_stage'];
    filterParams.forEach(param => url.searchParams.delete(param));
    window.location.href = url.toString();
}

function applyFilters() {
    const form = document.querySelector('form[data-filters]');
    if (form) {
        form.submit();
    }
}

function exportData(format) {
    const url = new URL(window.location);
    url.searchParams.set('export', format);
    window.open(url.toString(), '_blank');
}

function refreshData() {
    window.location.reload();
}
</script><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/toolbar.blade.php ENDPATH**/ ?>