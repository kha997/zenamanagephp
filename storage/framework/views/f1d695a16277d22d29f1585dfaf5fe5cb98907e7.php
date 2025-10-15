<?php $__env->startSection('title', __('clients.title')); ?>

<?php $__env->startSection('kpi-strip'); ?>
<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi.strip','data' => ['kpis' => $kpis]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi.strip'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['kpis' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpis)]); ?>
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
                <h1 class="text-3xl font-bold text-gray-900"><?php echo e(__('clients.title')); ?></h1>
                <p class="mt-2 text-gray-600"><?php echo e(__('clients.subtitle')); ?></p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="<?php echo e(route('clients.create')); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    <?php echo e(__('clients.create_client')); ?>

                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('clients.total_clients')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['total']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-plus text-green-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('clients.leads')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['leads']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-eye text-yellow-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('clients.prospects')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['prospects']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-star text-purple-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('clients.customers')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['customers']); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-slash text-gray-600"></i>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500"><?php echo e(__('clients.inactive')); ?></p>
                    <p class="text-2xl font-semibold text-gray-900"><?php echo e($stats['inactive']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('clients.search')); ?>

                    </label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo e(request('search')); ?>"
                           placeholder="<?php echo e(__('clients.search_placeholder')); ?>"
                           class="form-input w-full">
                </div>

                <div>
                    <label for="lifecycle_stage" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('clients.lifecycle_stage')); ?>

                    </label>
                    <select id="lifecycle_stage" 
                            name="lifecycle_stage" 
                            class="form-select w-full">
                        <option value=""><?php echo e(__('clients.all_stages')); ?></option>
                        <option value="lead" <?php echo e(request('lifecycle_stage') === 'lead' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.lead')); ?>

                        </option>
                        <option value="prospect" <?php echo e(request('lifecycle_stage') === 'prospect' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.prospect')); ?>

                        </option>
                        <option value="customer" <?php echo e(request('lifecycle_stage') === 'customer' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.customer')); ?>

                        </option>
                        <option value="inactive" <?php echo e(request('lifecycle_stage') === 'inactive' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.inactive')); ?>

                        </option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                        <?php echo e(__('clients.status')); ?>

                    </label>
                    <select id="status" 
                            name="status" 
                            class="form-select w-full">
                        <option value=""><?php echo e(__('clients.all_statuses')); ?></option>
                        <option value="active" <?php echo e(request('status') === 'active' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.active')); ?>

                        </option>
                        <option value="customers" <?php echo e(request('status') === 'customers' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.customers')); ?>

                        </option>
                        <option value="prospects" <?php echo e(request('status') === 'prospects' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.prospects')); ?>

                        </option>
                        <option value="leads" <?php echo e(request('status') === 'leads' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.leads')); ?>

                        </option>
                        <option value="inactive" <?php echo e(request('status') === 'inactive' ? 'selected' : ''); ?>>
                            <?php echo e(__('clients.inactive')); ?>

                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" 
                            class="btn btn-primary w-full">
                        <i class="fas fa-search mr-2"></i>
                        <?php echo e(__('clients.filter')); ?>

                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Clients Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900"><?php echo e(__('clients.client_list')); ?></h3>
        </div>

        <?php if($clients->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.client')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.contact')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.lifecycle_stage')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.quotes')); ?>

                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.created')); ?>

                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <?php echo e(__('clients.actions')); ?>

                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo e($client->name); ?>

                                        </div>
                                        <?php if($client->company): ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo e($client->company); ?>

                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo e($client->email); ?></div>
                                <?php if($client->phone): ?>
                                <div class="text-sm text-gray-500"><?php echo e($client->phone); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    <?php if($client->lifecycle_stage === 'lead'): ?> bg-green-100 text-green-800
                                    <?php elseif($client->lifecycle_stage === 'prospect'): ?> bg-yellow-100 text-yellow-800
                                    <?php elseif($client->lifecycle_stage === 'customer'): ?> bg-purple-100 text-purple-800
                                    <?php else: ?> bg-gray-100 text-gray-800
                                    <?php endif; ?>">
                                    <?php echo e(__('clients.' . $client->lifecycle_stage)); ?>

                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo e($client->quotes->count()); ?> <?php echo e(__('clients.quotes_count')); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo e($client->created_at->format('M d, Y')); ?>

                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <a href="<?php echo e(route('clients.show', $client)); ?>" 
                                       class="text-indigo-600 hover:text-indigo-900">
                                        <?php echo e(__('clients.view')); ?>

                                    </a>
                                    <a href="<?php echo e(route('quotes.create', ['client_id' => $client->id])); ?>" 
                                       class="text-green-600 hover:text-green-900">
                                        <?php echo e(__('clients.create_quote')); ?>

                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                <?php echo e($clients->links()); ?>

            </div>
        <?php else: ?>
            <div class="px-6 py-12 text-center">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo e(__('clients.no_clients')); ?></h3>
                <p class="text-gray-500 mb-6"><?php echo e(__('clients.no_clients_description')); ?></p>
                <a href="<?php echo e(route('clients.create')); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-plus mr-2"></i>
                    <?php echo e(__('clients.create_first_client')); ?>

                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/clients/index.blade.php ENDPATH**/ ?>