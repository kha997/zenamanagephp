<?php $__env->startSection('title', $client->name); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo e($client->name); ?></h1>
                <?php if($client->company): ?>
                <p class="mt-2 text-gray-600"><?php echo e($client->company); ?></p>
                <?php endif; ?>
                <div class="mt-2 flex items-center space-x-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php if($client->lifecycle_stage === 'lead'): ?> bg-green-100 text-green-800
                        <?php elseif($client->lifecycle_stage === 'prospect'): ?> bg-yellow-100 text-yellow-800
                        <?php elseif($client->lifecycle_stage === 'customer'): ?> bg-purple-100 text-purple-800
                        <?php else: ?> bg-gray-100 text-gray-800
                        <?php endif; ?>">
                        <?php echo e(__('clients.' . $client->lifecycle_stage)); ?>

                    </span>
                    <span class="text-sm text-gray-500">
                        <?php echo e(__('clients.created_on')); ?> <?php echo e($client->created_at->format('M d, Y')); ?>

                    </span>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?php echo e(route('quotes.create', ['client_id' => $client->id])); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    <?php echo e(__('clients.create_quote')); ?>

                </a>
                <a href="<?php echo e(route('clients.edit', $client)); ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-edit mr-2"></i>
                    <?php echo e(__('clients.edit')); ?>

                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Client Information -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.client_information')); ?></h3>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.name')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($client->name); ?></dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.email')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:<?php echo e($client->email); ?>" class="text-indigo-600 hover:text-indigo-900">
                                    <?php echo e($client->email); ?>

                                </a>
                            </dd>
                        </div>
                        <?php if($client->phone): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.phone')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="tel:<?php echo e($client->phone); ?>" class="text-indigo-600 hover:text-indigo-900">
                                    <?php echo e($client->phone); ?>

                                </a>
                            </dd>
                        </div>
                        <?php endif; ?>
                        <?php if($client->company): ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.company')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($client->company); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.lifecycle_stage')); ?></dt>
                            <dd class="mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php if($client->lifecycle_stage === 'lead'): ?> bg-green-100 text-green-800
                                    <?php elseif($client->lifecycle_stage === 'prospect'): ?> bg-yellow-100 text-yellow-800
                                    <?php elseif($client->lifecycle_stage === 'customer'): ?> bg-purple-100 text-purple-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e(__('clients.' . $client->lifecycle_stage)); ?>

                                </span>
                            </dd>
                        </div>
                        <?php if($client->formatted_address): ?>
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.address')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($client->formatted_address); ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if($client->notes): ?>
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500"><?php echo e(__('clients.notes')); ?></dt>
                            <dd class="mt-1 text-sm text-gray-900"><?php echo e($client->notes); ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Quotes History -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.quotes_history')); ?></h3>
                        <a href="<?php echo e(route('quotes.create', ['client_id' => $client->id])); ?>" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-plus mr-1"></i>
                            <?php echo e(__('clients.new_quote')); ?>

                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <?php if($client->quotes->count() > 0): ?>
                        <div class="space-y-4">
                            <?php $__currentLoopData = $client->quotes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3">
                                            <h4 class="text-sm font-medium text-gray-900">
                                                <a href="<?php echo e(route('quotes.show', $quote)); ?>" class="hover:text-indigo-600">
                                                    <?php echo e($quote->title); ?>

                                                </a>
                                            </h4>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                <?php if($quote->status === 'draft'): ?> bg-gray-100 text-gray-800
                                                <?php elseif($quote->status === 'sent'): ?> bg-blue-100 text-blue-800
                                                <?php elseif($quote->status === 'viewed'): ?> bg-yellow-100 text-yellow-800
                                                <?php elseif($quote->status === 'accepted'): ?> bg-green-100 text-green-800
                                                <?php elseif($quote->status === 'rejected'): ?> bg-red-100 text-red-800
                                                <?php else: ?> bg-gray-100 text-gray-800
                                                <?php endif; ?>">
                                                <?php echo e(__('quotes.' . $quote->status)); ?>

                                            </span>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                <?php if($quote->type === 'design'): ?> bg-purple-100 text-purple-800
                                                <?php else: ?> bg-orange-100 text-orange-800
                                                <?php endif; ?>">
                                                <?php echo e(__('quotes.' . $quote->type)); ?>

                                            </span>
                                        </div>
                                        <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                            <span><?php echo e(__('quotes.amount')); ?>: $<?php echo e(number_format($quote->final_amount, 2)); ?></span>
                                            <span><?php echo e(__('quotes.valid_until')); ?>: <?php echo e($quote->valid_until->format('M d, Y')); ?></span>
                                            <span><?php echo e(__('quotes.created')); ?>: <?php echo e($quote->created_at->format('M d, Y')); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <a href="<?php echo e(route('quotes.show', $quote)); ?>" 
                                           class="text-indigo-600 hover:text-indigo-900 text-sm">
                                            <?php echo e(__('clients.view')); ?>

                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-file-invoice text-gray-400 text-3xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo e(__('clients.no_quotes')); ?></h3>
                            <p class="text-gray-500 mb-4"><?php echo e(__('clients.no_quotes_description')); ?></p>
                            <a href="<?php echo e(route('quotes.create', ['client_id' => $client->id])); ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-plus mr-2"></i>
                                <?php echo e(__('clients.create_first_quote')); ?>

                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Projects -->
            <?php if($client->projects->count() > 0): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.projects')); ?></h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <?php $__currentLoopData = $client->projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900">
                                        <a href="<?php echo e(route('projects.show', $project)); ?>" class="hover:text-indigo-600">
                                            <?php echo e($project->name); ?>

                                        </a>
                                    </h4>
                                    <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                        <span><?php echo e(__('projects.status')); ?>: <?php echo e(__('projects.' . $project->status)); ?></span>
                                        <span><?php echo e(__('projects.budget')); ?>: $<?php echo e(number_format($project->budget, 2)); ?></span>
                                        <span><?php echo e(__('projects.created')); ?>: <?php echo e($project->created_at->format('M d, Y')); ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <a href="<?php echo e(route('projects.show', $project)); ?>" 
                                       class="text-indigo-600 hover:text-indigo-900 text-sm">
                                        <?php echo e(__('clients.view')); ?>

                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="space-y-8">
            <!-- Quote Statistics -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.quote_statistics')); ?></h3>
                </div>
                <div class="p-6">
                    <dl class="space-y-4">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.total_quotes')); ?></dt>
                            <dd class="text-sm font-medium text-gray-900"><?php echo e($quoteStats['total']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.draft')); ?></dt>
                            <dd class="text-sm font-medium text-gray-900"><?php echo e($quoteStats['draft']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.sent')); ?></dt>
                            <dd class="text-sm font-medium text-gray-900"><?php echo e($quoteStats['sent']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.viewed')); ?></dt>
                            <dd class="text-sm font-medium text-gray-900"><?php echo e($quoteStats['viewed']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.accepted')); ?></dt>
                            <dd class="text-sm font-medium text-green-600"><?php echo e($quoteStats['accepted']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.rejected')); ?></dt>
                            <dd class="text-sm font-medium text-red-600"><?php echo e($quoteStats['rejected']); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-500"><?php echo e(__('clients.expired')); ?></dt>
                            <dd class="text-sm font-medium text-gray-600"><?php echo e($quoteStats['expired']); ?></dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.recent_activity')); ?></h3>
                </div>
                <div class="p-6">
                    <?php if($recentActivity->count() > 0): ?>
                        <div class="space-y-4">
                            <?php $__currentLoopData = $recentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <?php if($activity instanceof \App\Models\Quote): ?>
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-file-invoice text-blue-600 text-sm"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-project-diagram text-green-600 text-sm"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900">
                                        <?php if($activity instanceof \App\Models\Quote): ?>
                                            <?php echo e(__('clients.quote_created')); ?>: <?php echo e($activity->title); ?>

                                        <?php else: ?>
                                            <?php echo e(__('clients.project_created')); ?>: <?php echo e($activity->name); ?>

                                        <?php endif; ?>
                                    </p>
                                    <p class="text-xs text-gray-500"><?php echo e($activity->created_at->diffForHumans()); ?></p>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history text-gray-400 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-500"><?php echo e(__('clients.no_recent_activity')); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lifecycle Management -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.lifecycle_management')); ?></h3>
                </div>
                <div class="p-6">
                    <form action="<?php echo e(route('clients.updateLifecycleStage', $client)); ?>" method="POST" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        
                        <div>
                            <label for="lifecycle_stage" class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo e(__('clients.update_lifecycle_stage')); ?>

                            </label>
                            <select id="lifecycle_stage" 
                                    name="lifecycle_stage" 
                                    class="form-select w-full">
                                <option value="lead" <?php echo e($client->lifecycle_stage === 'lead' ? 'selected' : ''); ?>>
                                    <?php echo e(__('clients.lead')); ?>

                                </option>
                                <option value="prospect" <?php echo e($client->lifecycle_stage === 'prospect' ? 'selected' : ''); ?>>
                                    <?php echo e(__('clients.prospect')); ?>

                                </option>
                                <option value="customer" <?php echo e($client->lifecycle_stage === 'customer' ? 'selected' : ''); ?>>
                                    <?php echo e(__('clients.customer')); ?>

                                </option>
                                <option value="inactive" <?php echo e($client->lifecycle_stage === 'inactive' ? 'selected' : ''); ?>>
                                    <?php echo e(__('clients.inactive')); ?>

                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" 
                                class="btn btn-primary w-full">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo e(__('clients.update_stage')); ?>

                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/clients/show.blade.php ENDPATH**/ ?>