
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['projects' => [], 'users' => [], 'filters' => []]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['projects' => [], 'users' => [], 'filters' => []]); ?>
<?php foreach (array_filter((['projects' => [], 'users' => [], 'filters' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Search -->
        <div class="relative">
            <input type="text" 
                   placeholder="<?php echo e(__('projects.search_placeholder')); ?>"
                   value="<?php echo e($filters['search'] ?? ''); ?>"
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>

        <!-- Status Filter -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value=""><?php echo e(__('projects.all_status')); ?></option>
            <option value="planning" <?php echo e(($filters['status'] ?? '') == 'planning' ? 'selected' : ''); ?>><?php echo e(__('projects.status.planning')); ?></option>
            <option value="active" <?php echo e(($filters['status'] ?? '') == 'active' ? 'selected' : ''); ?>><?php echo e(__('projects.status.active')); ?></option>
            <option value="on_hold" <?php echo e(($filters['status'] ?? '') == 'on_hold' ? 'selected' : ''); ?>><?php echo e(__('projects.status.on_hold')); ?></option>
            <option value="completed" <?php echo e(($filters['status'] ?? '') == 'completed' ? 'selected' : ''); ?>><?php echo e(__('projects.status.completed')); ?></option>
            <option value="cancelled" <?php echo e(($filters['status'] ?? '') == 'cancelled' ? 'selected' : ''); ?>><?php echo e(__('projects.status.cancelled')); ?></option>
        </select>

        <!-- Priority Filter -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value=""><?php echo e(__('projects.all_priorities')); ?></option>
            <option value="high" <?php echo e(($filters['priority'] ?? '') == 'high' ? 'selected' : ''); ?>><?php echo e(__('projects.priority.high')); ?></option>
            <option value="medium" <?php echo e(($filters['priority'] ?? '') == 'medium' ? 'selected' : ''); ?>><?php echo e(__('projects.priority.medium')); ?></option>
            <option value="low" <?php echo e(($filters['priority'] ?? '') == 'low' ? 'selected' : ''); ?>><?php echo e(__('projects.priority.low')); ?></option>
        </select>

        <!-- Sort Options -->
        <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="name" <?php echo e(($filters['sort'] ?? '') == 'name' ? 'selected' : ''); ?>><?php echo e(__('projects.sort.name')); ?></option>
            <option value="created_at" <?php echo e(($filters['sort'] ?? '') == 'created_at' ? 'selected' : ''); ?>><?php echo e(__('projects.sort.date')); ?></option>
            <option value="priority" <?php echo e(($filters['sort'] ?? '') == 'priority' ? 'selected' : ''); ?>><?php echo e(__('projects.sort.priority')); ?></option>
            <option value="progress" <?php echo e(($filters['sort'] ?? '') == 'progress' ? 'selected' : ''); ?>><?php echo e(__('projects.sort.progress')); ?></option>
        </select>
    </div>
    
    <!-- View Mode Toggle -->
    <div class="mt-4 flex items-center justify-between">
        <div class="flex items-center space-x-2">
            <span class="text-sm font-medium text-gray-700"><?php echo e(__('projects.view_mode')); ?>:</span>
            <button class="px-3 py-1 text-sm font-medium rounded-md <?php echo e(($filters['view_mode'] ?? 'table') == 'table' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'); ?>">
                <i class="fas fa-table mr-1"></i><?php echo e(__('projects.table_view')); ?>

            </button>
            <button class="px-3 py-1 text-sm font-medium rounded-md <?php echo e(($filters['view_mode'] ?? 'table') == 'cards' ? 'bg-blue-100 text-blue-700' : 'text-gray-500 hover:text-gray-700'); ?>">
                <i class="fas fa-th-large mr-1"></i><?php echo e(__('projects.card_view')); ?>

            </button>
        </div>
        
        <?php if(count($filters) > 0): ?>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500"><?php echo e(__('projects.filters_applied', ['count' => count(array_filter($filters))])); ?></span>
            <button class="text-sm text-blue-600 hover:text-blue-800"><?php echo e(__('projects.clear_filters')); ?></button>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/projects/filters.blade.php ENDPATH**/ ?>