<?php $__env->startSection('title', __('settings.title')); ?>

<?php $__env->startSection('kpi-strip'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900"><?php echo e(__('settings.title')); ?></h1>
                <p class="mt-2 text-gray-600"><?php echo e(__('settings.subtitle')); ?></p>
            </div>
        </div>
    </div>

    <!-- Settings Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Settings Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.categories')); ?></h2>
                </div>
                <div class="p-6">
                    <nav class="space-y-2">
                        <button onclick="showSection('general')" id="nav-general" class="w-full flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg">
                            <i class="fas fa-cog mr-3"></i>
                            <?php echo e(__('settings.general')); ?>

                        </button>
                        <button onclick="showSection('notifications')" id="nav-notifications" class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">
                            <i class="fas fa-bell mr-3"></i>
                            <?php echo e(__('settings.notifications')); ?>

                        </button>
                        <button onclick="showSection('security')" id="nav-security" class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">
                            <i class="fas fa-shield-alt mr-3"></i>
                            <?php echo e(__('settings.security')); ?>

                        </button>
                        <button onclick="showSection('privacy')" id="nav-privacy" class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">
                            <i class="fas fa-user-secret mr-3"></i>
                            <?php echo e(__('settings.privacy')); ?>

                        </button>
                        <button onclick="showSection('integrations')" id="nav-integrations" class="w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg">
                            <i class="fas fa-plug mr-3"></i>
                            <?php echo e(__('settings.integrations')); ?>

                        </button>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="lg:col-span-2">
            <!-- General Settings -->
            <div id="section-general" class="settings-section">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.general')); ?></h2>
                    </div>
                    <div class="p-6">
                        <form class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.company_name')); ?></label>
                                <input type="text" value="<?php echo e($settings['company_name'] ?? ''); ?>" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.timezone')); ?></label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="UTC">UTC</option>
                                    <option value="Asia/Ho_Chi_Minh" selected>Asia/Ho_Chi_Minh</option>
                                    <option value="America/New_York">America/New_York</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.language')); ?></label>
                                <select class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="en">English</option>
                                    <option value="vi" selected>Tiếng Việt</option>
                                </select>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo e(__('settings.save_changes')); ?>

                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Notifications Settings -->
            <div id="section-notifications" class="settings-section hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.notifications')); ?></h2>
                    </div>
                    <div class="p-6">
                        <form class="space-y-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.email_notifications')); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.email_notifications_description')); ?></p>
                                    </div>
                                    <input type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.push_notifications')); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.push_notifications_description')); ?></p>
                                    </div>
                                    <input type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.project_updates')); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.project_updates_description')); ?></p>
                                    </div>
                                    <input type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo e(__('settings.save_changes')); ?>

                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div id="section-security" class="settings-section hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.security')); ?></h2>
                    </div>
                    <div class="p-6">
                        <form class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.current_password')); ?></label>
                                <input type="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.new_password')); ?></label>
                                <input type="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700"><?php echo e(__('settings.confirm_password')); ?></label>
                                <input type="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.two_factor_auth')); ?></h3>
                                    <p class="text-sm text-gray-500"><?php echo e(__('settings.two_factor_auth_description')); ?></p>
                                </div>
                                <button type="button" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <?php echo e(__('settings.enable_2fa')); ?>

                                </button>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo e(__('settings.save_changes')); ?>

                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Privacy Settings -->
            <div id="section-privacy" class="settings-section hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.privacy')); ?></h2>
                    </div>
                    <div class="p-6">
                        <form class="space-y-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.data_collection')); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.data_collection_description')); ?></p>
                                    </div>
                                    <input type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900"><?php echo e(__('settings.analytics')); ?></h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.analytics_description')); ?></p>
                                    </div>
                                    <input type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo e(__('settings.save_changes')); ?>

                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Integrations Settings -->
            <div id="section-integrations" class="settings-section hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo e(__('settings.integrations')); ?></h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fab fa-google text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Google Drive</h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.google_drive_description')); ?></p>
                                    </div>
                                </div>
                                <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo e(__('settings.connect')); ?>

                                </button>
                            </div>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                        <i class="fab fa-slack text-green-600"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-medium text-gray-900">Slack</h3>
                                        <p class="text-sm text-gray-500"><?php echo e(__('settings.slack_description')); ?></p>
                                    </div>
                                </div>
                                <button class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <?php echo e(__('settings.connect')); ?>

                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
function showSection(sectionName) {
    // Hide all sections
    document.querySelectorAll('.settings-section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    document.getElementById(`section-${sectionName}`).classList.remove('hidden');
    
    // Update navigation
    document.querySelectorAll('[id^="nav-"]').forEach(nav => {
        nav.className = 'w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 rounded-lg';
    });
    
    document.getElementById(`nav-${sectionName}`).className = 'w-full flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg';
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app-layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/settings/index.blade.php ENDPATH**/ ?>