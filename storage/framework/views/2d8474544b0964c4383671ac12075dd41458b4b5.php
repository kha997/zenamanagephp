
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['projects' => []]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['projects' => []]); ?>
<?php foreach (array_filter((['projects' => []]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.name')); ?>

                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.status')); ?>

                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.priority')); ?>

                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.progress')); ?>

                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.team')); ?>

                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.created')); ?>

                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?php echo e(__('projects.table.actions')); ?>

                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-gray-50 transition duration-150 ease-in-out">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-project-diagram text-blue-600"></i>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900"><?php echo e($project->name); ?></div>
                                <div class="text-sm text-gray-500"><?php echo e(Str::limit($project->description ?? '', 50)); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo e($project->status == 'active' ? 'bg-green-100 text-green-800' : ($project->status == 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status == 'on_hold' ? 'bg-yellow-100 text-yellow-800' : ($project->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')))); ?>">
                            <?php echo e(__('projects.status.' . ($project->status ?? 'active'))); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo e($project->priority == 'high' ? 'bg-red-100 text-red-800' : ($project->priority == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?>">
                            <?php echo e(__('projects.priority.' . ($project->priority ?? 'medium'))); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo e($project->progress ?? 0); ?>%"></div>
                            </div>
                            <span class="text-sm text-gray-900"><?php echo e($project->progress ?? 0); ?>%</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <div class="flex items-center">
                            <i class="fas fa-users mr-1 text-gray-400"></i>
                            <?php echo e($project->team_size ?? 1); ?> <?php echo e(__('projects.members')); ?>

                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php echo e($project->created_at ? $project->created_at->diffForHumans() : __('projects.unknown')); ?>

                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <div class="flex items-center justify-end space-x-2">
                            <button class="text-blue-600 hover:text-blue-900" aria-label="<?php echo e(__('projects.view_details')); ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-gray-600 hover:text-gray-900" aria-label="<?php echo e(__('projects.edit_project')); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-900" aria-label="<?php echo e(__('projects.delete_project')); ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-project-diagram','title' => __('projects.empty.title'),'description' => __('projects.empty.description'),'actionText' => __('projects.empty.action'),'actionUrl' => route('app.projects.create')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('fas fa-project-diagram'),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('projects.empty.title')),'description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('projects.empty.description')),'action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('projects.empty.action')),'action-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('app.projects.create'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/projects/table.blade.php ENDPATH**/ ?>