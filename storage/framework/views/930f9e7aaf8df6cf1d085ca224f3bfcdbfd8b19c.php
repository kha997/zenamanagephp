<?php $__env->startSection('title', 'Billing'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Billing</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Billing</h1>
            <p class="text-gray-600">Billing overview and plan management</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Revenue</h3>
            <div class="text-3xl font-bold text-green-600">$12,450</div>
            <p class="text-sm text-gray-600">+15% from last month</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Subscriptions</h3>
            <div class="text-3xl font-bold text-blue-600">89</div>
            <p class="text-sm text-gray-600">tenants with active plans</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Churn Rate</h3>
            <div class="text-3xl font-bold text-red-600">2.1%</div>
            <p class="text-sm text-gray-600">monthly churn rate</p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Plan Distribution</h3>
        <div class="grid grid-cols-3 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900">45</div>
                <div class="text-sm text-gray-600">Basic Plans</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900">32</div>
                <div class="text-sm text-gray-600">Professional Plans</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-gray-900">12</div>
                <div class="text-sm text-gray-600">Enterprise Plans</div>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/billing/index.blade.php ENDPATH**/ ?>