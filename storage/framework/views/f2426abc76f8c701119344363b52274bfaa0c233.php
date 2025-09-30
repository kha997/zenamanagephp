<?php $__env->startSection('title', 'Tenants'); ?>

<?php $__env->startSection('breadcrumb'); ?>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Tenants</span>
</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6 tenants-container" style="min-height: 520px;">
    
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tenants</h1>
            <p class="text-gray-600">Manage all tenant organizations</p>
        </div>
        <div class="flex items-center space-x-3">
            <button class="export-btn px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Tenant
            </button>
        </div>
    </div>
    
    
    <div class="kpi-cards grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="kpi-card" data-kpi="total">
            <button data-kpi-action="view-all" class="w-full text-left">
                <div class="kpi-value text-2xl font-bold text-gray-900">0</div>
                <div class="kpi-delta text-sm text-gray-600">Total Tenants</div>
                <div class="sparkline-container mt-2"></div>
            </button>
        </div>
        <div class="kpi-card" data-kpi="active">
            <button data-kpi-action="filter-active" class="w-full text-left">
                <div class="kpi-value text-2xl font-bold text-green-600">0</div>
                <div class="kpi-delta text-sm text-gray-600">Active</div>
                <div class="sparkline-container mt-2"></div>
            </button>
        </div>
        <div class="kpi-card" data-kpi="disabled">
            <button data-kpi-action="filter-disabled" class="w-full text-left">
                <div class="kpi-value text-2xl font-bold text-red-600">0</div>
                <div class="kpi-delta text-sm text-gray-600">Disabled</div>
                <div class="sparkline-container mt-2"></div>
            </button>
        </div>
        <div class="kpi-card" data-kpi="new30d">
            <button data-kpi-action="view-recent" class="w-full text-left">
                <div class="kpi-value text-2xl font-bold text-purple-600">0</div>
                <div class="kpi-delta text-sm text-gray-600">New 30d</div>
                <div class="sparkline-container mt-2"></div>
            </button>
        </div>
        <div class="kpi-card" data-kpi="trialExpiring">
            <button data-kpi-action="view-expiring" class="w-full text-left">
                <div class="kpi-value text-2xl font-bold text-yellow-600">0</div>
                <div class="kpi-delta text-sm text-gray-600">Trial Expiring</div>
                <div class="sparkline-container mt-2"></div>
            </button>
        </div>
    </div>
    
    
    <div class="bg-white p-4 rounded-lg shadow">
        <div class="flex flex-wrap gap-4 items-center">
            <!-- Search -->
            <div class="flex-1 min-w-64">
                <input type="text" id="search-input" placeholder="Search tenants..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Status Filter -->
            <select data-filter="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="trial">Trial</option>
                <option value="inactive">Inactive</option>
            </select>
            
            <!-- Plan Filter -->
            <select data-filter="plan" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Plans</option>
                <option value="Basic">Basic</option>
                <option value="Professional">Professional</option>
                <option value="Enterprise">Enterprise</option>
            </select>
            
            <!-- Date From -->
            <input type="date" data-filter="from" placeholder="From" 
                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            
            <!-- Date To -->
            <input type="date" data-filter="to" placeholder="To" 
                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
            
            <!-- Per Page -->
            <select data-filter="per_page" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="10">10 per page</option>
                <option value="20" selected>20 per page</option>
                <option value="50">50 per page</option>
            </select>
        </div>
        
        <!-- Filter Chips -->
        <div class="mt-4 flex flex-wrap gap-2">
            <button class="filter-chip" data-filter-chip="status" data-filter-value="active" aria-pressed="false">
                Active
            </button>
            <button class="filter-chip" data-filter-chip="status" data-filter-value="suspended" aria-pressed="false">
                Suspended
            </button>
            <button class="filter-chip" data-filter-chip="status" data-filter-value="trial" aria-pressed="false">
                Trial
            </button>
            <button class="filter-chip" data-filter-chip="plan" data-filter-value="Enterprise" aria-pressed="false">
                Enterprise
            </button>
        </div>
    </div>
    
    
    <div class="tenants-table bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <input type="checkbox" class="select-all-checkbox">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Domain</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Projects</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="tenants-table" class="bg-white divide-y divide-gray-200">
                <!-- Table content will be populated by JavaScript -->
            </tbody>
        </table>
    </div>
    
    
    <div class="pagination flex justify-between items-center">
        <!-- Pagination content will be populated by JavaScript -->
    </div>
    
    <!-- Aria live region for screen readers -->
    <div aria-live="polite" aria-atomic="true"></div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<!-- Tenants Page Module -->
<script src="<?php echo e(asset('js/tenants/page.js')); ?>" defer></script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/index.blade.php ENDPATH**/ ?>