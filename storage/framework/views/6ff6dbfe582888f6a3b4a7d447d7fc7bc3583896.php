<?php $__env->startSection('title', __('quotes.title')); ?>

<?php $__env->startSection('kpi-strip'); ?>
<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi.strip','data' => ['kpis' => $kpiStats]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi.strip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['kpis' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpiStats)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo e(__('quotes.title')); ?></h1>
                <p class="mt-2 text-gray-600"><?php echo e(__('quotes.subtitle')); ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?php echo e(route('app.quotes.create')); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    <?php echo e(__('quotes.create_quote')); ?>

                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('quotes.total_quotes')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['total']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('quotes.accepted')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['accepted']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('quotes.expiring_soon')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['expiring_soon']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-dollar-sign text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('quotes.total_value')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900">$<?php echo e(number_format($stats['total_value'] ?? 0, 0)); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('quotes.search')); ?>

                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo e(request('search')); ?>"
                           placeholder="<?php echo e(__('quotes.search_placeholder')); ?>"
                           class="form-input w-full">
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('quotes.status')); ?>

                    </label>
                    <select id="status" 
                            name="status" 
                            class="form-select w-full">
                        <option value=""><?php echo e(__('quotes.all_statuses')); ?></option>
                        <option value="draft" <?php echo e(request('status') === 'draft' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.draft')); ?>

                        </option>
                        <option value="sent" <?php echo e(request('status') === 'sent' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.sent')); ?>

                        </option>
                        <option value="viewed" <?php echo e(request('status') === 'viewed' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.viewed')); ?>

                        </option>
                        <option value="accepted" <?php echo e(request('status') === 'accepted' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.accepted')); ?>

                        </option>
                        <option value="rejected" <?php echo e(request('status') === 'rejected' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.rejected')); ?>

                        </option>
                        <option value="expired" <?php echo e(request('status') === 'expired' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.expired')); ?>

                        </option>
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('quotes.type')); ?>

                    </label>
                    <select id="type" 
                            name="type" 
                            class="form-select w-full">
                        <option value=""><?php echo e(__('quotes.all_types')); ?></option>
                        <option value="design" <?php echo e(request('type') === 'design' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.design')); ?>

                        </option>
                        <option value="construction" <?php echo e(request('type') === 'construction' ? 'selected' : ''); ?>>
                            <?php echo e(__('quotes.construction')); ?>

                        </option>
                    </select>
                </div>

                <div>
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('quotes.client')); ?>

                    </label>
                    <select id="client_id" 
                            name="client_id" 
                            class="form-select w-full">
                        <option value=""><?php echo e(__('quotes.all_clients')); ?></option>
                        <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($client->id); ?>" <?php echo e(request('client_id') == $client->id ? 'selected' : ''); ?>>
                            <?php echo e($client->name); ?>

                        </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="btn btn-primary w-full">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo e(__('quotes.filter')); ?>

                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Quotes Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('quotes.quote_list')); ?></h3>
        </div>

        <?php if($quotes->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.quote')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.client')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.status')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.type')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.amount')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.valid_until')); ?>

                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('quotes.actions')); ?>

                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__currentLoopData = $quotes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo e($quote->title); ?>

                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo e(__('quotes.created')); ?> <?php echo e($quote->created_at->format('M d, Y')); ?>

                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo e($quote->client->name); ?></div>
                                <?php if($quote->client->company): ?>
                                <div class="text-sm text-gray-500"><?php echo e($quote->client->company); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.quotes.status-badge','data' => ['status' => $quote->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('quotes.status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($quote->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php if($quote->type === 'design'): ?> bg-purple-100 text-purple-800
                                    <?php else: ?> bg-orange-100 text-orange-800
                                    <?php endif; ?>">
                                    <?php echo e(__('quotes.' . $quote->type)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                $<?php echo e(number_format($quote->final_amount, 2)); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($quote->valid_until->format('M d, Y')); ?>

                                <?php if($quote->isExpired()): ?>
                                    <span class="text-red-500 text-xs">(<?php echo e(__('quotes.expired')); ?>)</span>
                                <?php elseif($quote->expiringSoon()): ?>
                                    <span class="text-yellow-500 text-xs">(<?php echo e(__('quotes.expiring_soon')); ?>)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="<?php echo e(route('app.quotes.show', $quote)); ?>" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        <?php echo e(__('quotes.view')); ?>

                                    </a>
                                    <?php if($quote->canBeSent()): ?>
                                    <form action="<?php echo e(route('app.quotes.send', $quote)); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" 
                                                class="text-green-600 hover:text-green-900">
                                            <?php echo e(__('quotes.send')); ?>

                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <?php echo e($quotes->links()); ?>

            </div>
        <?php else: ?>
            <div class="px-6 py-12 text-center">
                <i class="fas fa-file-invoice text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo e(__('quotes.no_quotes')); ?></h3>
                <p class="text-gray-500 mb-6"><?php echo e(__('quotes.no_quotes_description')); ?></p>
                <a href="<?php echo e(route('app.quotes.create')); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    <?php echo e(__('quotes.create_first_quote')); ?>

                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/quotes/index.blade.php ENDPATH**/ ?>