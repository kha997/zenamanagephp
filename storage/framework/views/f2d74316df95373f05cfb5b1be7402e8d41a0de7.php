
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

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php $__empty_1 = true; $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <!-- Project Card -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition duration-200">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900"><?php echo e($project->name); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo e(__('projects.status.' . ($project->status ?? 'active'))); ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="px-2 py-1 <?php echo e($project->status == 'active' ? 'bg-green-100 text-green-800' : ($project->status == 'completed' ? 'bg-blue-100 text-blue-800' : ($project->status == 'on_hold' ? 'bg-yellow-100 text-yellow-800' : ($project->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')))); ?> text-xs font-medium rounded-full">
                        <?php echo e(__('projects.status.' . ($project->status ?? 'active'))); ?>

                    </span>
                    <span class="px-2 py-1 <?php echo e($project->priority == 'high' ? 'bg-red-100 text-red-800' : ($project->priority == 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800')); ?> text-xs font-medium rounded-full">
                        <?php echo e(__('projects.priority.' . ($project->priority ?? 'medium'))); ?>

                    </span>
                </div>
            </div>

            <p class="text-gray-600 text-sm mb-4"><?php echo e(Str::limit($project->description ?? __('projects.no_description'), 100)); ?></p>

            <div class="mb-4">
                <div class="flex items-center justify-between text-sm text-gray-600 mb-2">
                    <span><?php echo e(__('projects.progress')); ?></span>
                    <span><?php echo e($project->progress ?? 0); ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo e($project->progress ?? 0); ?>%"></div>
                </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                <span><i class="fas fa-users mr-1"></i><?php echo e($project->team_size ?? 1); ?> <?php echo e(__('projects.members')); ?></span>
                <span><i class="fas fa-calendar mr-1"></i><?php echo e($project->created_at ? $project->created_at->diffForHumans() : __('projects.unknown')); ?></span>
            </div>

            <div class="flex space-x-2">
                <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    <?php echo e(__('projects.view_details')); ?>

                </button>
                <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-md transition duration-200" aria-label="<?php echo e(__('projects.edit_project')); ?>">
                    <i class="fas fa-edit"></i>
                </button>
            </div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <!-- Empty State -->
    <div class="col-span-full flex flex-col items-center justify-center py-12">
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
    </div>
    <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/projects/card-grid.blade.php ENDPATH**/ ?>