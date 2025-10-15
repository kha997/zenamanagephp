


<?php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Admin-specific filters for tenants
    $statusOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
        ['value' => 'trial', 'label' => 'Trial'],
        ['value' => 'suspended', 'label' => 'Suspended']
    ];
    
    $planOptions = [
        ['value' => 'free', 'label' => 'Free'],
        ['value' => 'basic', 'label' => 'Basic'],
        ['value' => 'premium', 'label' => 'Premium'],
        ['value' => 'enterprise', 'label' => 'Enterprise']
    ];
    
    // Filter configuration
    $filters = [
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => $statusOptions,
            'placeholder' => 'All Statuses'
        ],
        [
            'key' => 'plan',
            'label' => 'Plan',
            'type' => 'select',
            'options' => $planOptions,
            'placeholder' => 'All Plans'
        ],
        [
            'key' => 'created_date',
            'label' => 'Created Date',
            'type' => 'date-range'
        ],
        [
            'key' => 'trial_ends',
            'label' => 'Trial Ends',
            'type' => 'date-range'
        ]
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'name', 'label' => 'Name'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'plan', 'label' => 'Plan'],
        ['value' => 'created_at', 'label' => 'Created Date'],
        ['value' => 'trial_ends_at', 'label' => 'Trial Ends'],
        ['value' => 'user_count', 'label' => 'User Count']
    ];
    
    // Bulk actions
    $bulkActions = [
        [
            'label' => 'Activate Tenants',
            'icon' => 'fas fa-check',
            'handler' => 'bulkActivate()'
        ],
        [
            'label' => 'Suspend Tenants',
            'icon' => 'fas fa-pause',
            'handler' => 'bulkSuspend()'
        ],
        [
            'label' => 'Upgrade Plan',
            'icon' => 'fas fa-arrow-up',
            'handler' => 'bulkUpgrade()'
        ],
        [
            'label' => 'Export Tenants',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ],
        [
            'label' => 'Delete Tenants',
            'icon' => 'fas fa-trash',
            'handler' => 'bulkDelete()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Tenants', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportTenants()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'create-tenant-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-building mr-2"></i>Add Tenant
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($tenants ?? [])->map(function($tenant) {
        $userCount = \App\Models\User::where('tenant_id', $tenant->id)->count();
        $projectCount = \App\Models\Project::where('tenant_id', $tenant->id)->count();
        
        return [
            'id' => $tenant->id,
            'name' => $tenant->name ?? 'Unknown',
            'slug' => $tenant->slug ?? '',
            'domain' => $tenant->domain ?? 'N/A',
            'status' => $tenant->status ?? 'trial',
            'plan' => $tenant->plan ?? 'free',
            'user_count' => $userCount,
            'project_count' => $projectCount,
            'trial_ends_at' => $tenant->trial_ends_at ? $tenant->trial_ends_at->format('M d, Y') : 'N/A',
            'created_at' => $tenant->created_at->format('M d, Y'),
            'updated_at' => $tenant->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration
    $columns = [
        ['key' => 'name', 'label' => 'Tenant Name', 'sortable' => true, 'primary' => true],
        ['key' => 'slug', 'label' => 'Slug', 'sortable' => true],
        ['key' => 'domain', 'label' => 'Domain', 'sortable' => true],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'plan', 'label' => 'Plan', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'user_count', 'label' => 'Users', 'sortable' => true, 'type' => 'number'],
        ['key' => 'project_count', 'label' => 'Projects', 'sortable' => true, 'type' => 'number'],
        ['key' => 'trial_ends_at', 'label' => 'Trial Ends', 'sortable' => true, 'type' => 'date'],
        ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'type' => 'date']
    ];
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.layout-wrapper','data' => ['title' => 'Tenant Management','subtitle' => 'Manage system tenants and subscriptions','breadcrumbs' => $breadcrumbs,'actions' => $actions,'variant' => 'admin']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.layout-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Tenant Management','subtitle' => 'Manage system tenants and subscriptions','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions),'variant' => 'admin']); ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.filter-bar','data' => ['search' => true,'searchPlaceholder' => 'Search tenants...','filters' => $filters,'sortOptions' => $sortOptions,'viewModes' => ['table', 'grid', 'list'],'currentViewMode' => 'table','bulkActions' => $bulkActions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['search' => true,'search-placeholder' => 'Search tenants...','filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'sort-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sortOptions),'view-modes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['table', 'grid', 'list']),'current-view-mode' => 'table','bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions)]); ?>
        
        
         <?php $__env->slot('actions', null, []); ?> 
            <button onclick="refreshTenants()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    
    
    <div class="mt-6">
        <?php if($tableData->count() > 0): ?>
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-standardized','data' => ['data' => $tableData,'columns' => $columns,'sortable' => true,'selectable' => true,'pagination' => true,'perPage' => 15,'search' => true,'export' => true,'bulkActions' => $bulkActions,'responsive' => true,'loading' => false,'emptyMessage' => 'No tenants found','emptyDescription' => 'Create your first tenant to get started','emptyActionText' => 'Add Tenant','emptyActionHandler' => 'openModal(\'create-tenant-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableData),'columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($columns),'sortable' => true,'selectable' => true,'pagination' => true,'per-page' => 15,'search' => true,'export' => true,'bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions),'responsive' => true,'loading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'empty-message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('No tenants found'),'empty-description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create your first tenant to get started'),'empty-action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Add Tenant'),'empty-action-handler' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('openModal(\'create-tenant-modal\')')]); ?>
                
                
                 <?php $__env->slot('cell-status', null, []); ?> 
                    <?php
                        $status = $row['status'] ?? 'trial';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'trial' => 'bg-blue-100 text-blue-800',
                            'suspended' => 'bg-red-100 text-red-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['trial'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                        <?php echo e(ucfirst($status)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-plan', null, []); ?> 
                    <?php
                        $plan = $row['plan'] ?? 'free';
                        $planClasses = [
                            'free' => 'bg-gray-100 text-gray-800',
                            'basic' => 'bg-blue-100 text-blue-800',
                            'premium' => 'bg-purple-100 text-purple-800',
                            'enterprise' => 'bg-orange-100 text-orange-800'
                        ];
                        $planClass = $planClasses[$plan] ?? $planClasses['free'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($planClass); ?>">
                        <?php echo e(ucfirst($plan)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-user_count', null, []); ?> 
                    <div class="flex items-center">
                        <i class="fas fa-users text-gray-400 mr-1"></i>
                        <span class="text-sm font-medium text-gray-900"><?php echo e($row['user_count']); ?></span>
                    </div>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-project_count', null, []); ?> 
                    <div class="flex items-center">
                        <i class="fas fa-project-diagram text-gray-400 mr-1"></i>
                        <span class="text-sm font-medium text-gray-900"><?php echo e($row['project_count']); ?></span>
                    </div>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-trial_ends_at', null, []); ?> 
                    <?php if($row['trial_ends_at'] === 'N/A'): ?>
                        <span class="text-sm text-gray-500">N/A</span>
                    <?php else: ?>
                        <span class="text-sm font-medium text-gray-900"><?php echo e($row['trial_ends_at']); ?></span>
                    <?php endif; ?>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('row-actions', null, []); ?> 
                    <div class="flex items-center space-x-2">
                        <button onclick="viewTenant('<?php echo e($row['id']); ?>')" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="editTenant('<?php echo e($row['id']); ?>')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="manageUsers('<?php echo e($row['id']); ?>')" 
                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                            <i class="fas fa-users mr-1"></i>Users
                        </button>
                        <button onclick="suspendTenant('<?php echo e($row['id']); ?>')" 
                                class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                            <i class="fas fa-pause mr-1"></i>Suspend
                        </button>
                        <button onclick="deleteTenant('<?php echo e($row['id']); ?>')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                 <?php $__env->endSlot(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php else: ?>
            
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-building','title' => 'No tenants found','description' => 'Create your first tenant to start managing the system.','actionText' => 'Add Tenant','actionIcon' => 'fas fa-building','actionHandler' => 'openModal(\'create-tenant-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-building','title' => 'No tenants found','description' => 'Create your first tenant to start managing the system.','action-text' => 'Add Tenant','action-icon' => 'fas fa-building','action-handler' => 'openModal(\'create-tenant-modal\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'create-tenant-modal','title' => 'Create New Tenant','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'create-tenant-modal','title' => 'Create New Tenant','size' => 'lg']); ?>
        
        <form id="create-tenant-form" @submit.prevent="createTenant()">
            <div class="space-y-6">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tenant-name" class="form-label">Tenant Name *</label>
                        <input type="text" 
                               id="tenant-name" 
                               name="name" 
                               required
                               class="form-input"
                               placeholder="Enter tenant name">
                    </div>
                    
                    <div>
                        <label for="tenant-slug" class="form-label">Slug *</label>
                        <input type="text" 
                               id="tenant-slug" 
                               name="slug" 
                               required
                               class="form-input"
                               placeholder="Enter slug (e.g., acme-corp)">
                    </div>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tenant-domain" class="form-label">Domain</label>
                        <input type="text" 
                               id="tenant-domain" 
                               name="domain" 
                               class="form-input"
                               placeholder="Enter domain (optional)">
                    </div>
                    
                    <div>
                        <label for="tenant-status" class="form-label">Status</label>
                        <select id="tenant-status" name="status" class="form-select">
                            <option value="trial">Trial</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="tenant-plan" class="form-label">Plan *</label>
                        <select id="tenant-plan" name="plan" required class="form-select">
                            <option value="free">Free</option>
                            <option value="basic">Basic</option>
                            <option value="premium">Premium</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="tenant-trial-ends" class="form-label">Trial Ends</label>
                        <input type="date" 
                               id="tenant-trial-ends" 
                               name="trial_ends_at" 
                               class="form-input">
                    </div>
                </div>
                
                
                <div>
                    <label for="tenant-settings" class="form-label">Settings (JSON)</label>
                    <textarea id="tenant-settings" 
                              name="settings" 
                              rows="4"
                              class="form-textarea"
                              placeholder='{"theme": "light", "features": ["projects", "tasks"]}'></textarea>
                    <p class="text-sm text-gray-500 mt-1">Enter JSON configuration for tenant settings</p>
                </div>
            </div>
            
            
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('create-tenant-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-building mr-2"></i>Create Tenant
                </button>
            </div>
        </form>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
<script>
function refreshTenants() {
    window.location.reload();
}

function exportTenants() {
    alert('Export tenants functionality would be implemented here');
}

function createTenant() {
    const form = document.getElementById('create-tenant-form');
    const formData = new FormData(form);
    
    // Convert settings to JSON if provided
    const settings = document.getElementById('tenant-settings').value;
    if (settings) {
        try {
            JSON.parse(settings);
            formData.set('settings', settings);
        } catch (e) {
            alert('Invalid JSON in settings field');
            return;
        }
    }
    
    fetch('/api/v1/admin/tenants', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        },
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal('create-tenant-modal');
            window.location.reload();
        } else {
            alert('Error creating tenant: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating tenant');
    });
}

function viewTenant(tenantId) {
    window.location.href = '/admin/tenants/' + tenantId;
}

function editTenant(tenantId) {
    window.location.href = '/admin/tenants/' + tenantId + '/edit';
}

function manageUsers(tenantId) {
    window.location.href = '/admin/users?tenant_id=' + tenantId;
}

function suspendTenant(tenantId) {
    if (confirm('Are you sure you want to suspend this tenant?')) {
        alert('Suspend tenant functionality would be implemented here');
    }
}

function deleteTenant(tenantId) {
    if (confirm('Are you sure you want to delete this tenant? This action cannot be undone and will delete all associated data.')) {
        fetch('/api/v1/admin/tenants/' + tenantId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Authorization': 'Bearer ' + getAuthToken()
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                window.location.reload();
            } else {
                alert('Error deleting tenant: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting tenant');
        });
    }
}

function bulkActivate() {
    alert('Bulk activate functionality would be implemented here');
}

function bulkSuspend() {
    alert('Bulk suspend functionality would be implemented here');
}

function bulkUpgrade() {
    alert('Bulk upgrade functionality would be implemented here');
}

function bulkExport() {
    alert('Bulk export functionality would be implemented here');
}

function bulkDelete() {
    alert('Bulk delete functionality would be implemented here');
}

function openModal(modalId) {
    alert('Open modal: ' + modalId);
}

function closeModal(modalId) {
    alert('Close modal: ' + modalId);
}

function getAuthToken() {
    return localStorage.getItem('auth_token') || '';
}

// Listen for filter events
document.addEventListener('filter-search', (e) => {
    console.log('Search:', e.detail.query);
});

document.addEventListener('filter-apply', (e) => {
    console.log('Filters:', e.detail.filters);
});

document.addEventListener('filter-sort', (e) => {
    console.log('Sort:', e.detail.sortBy, e.detail.sortDirection);
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/tenants/index.blade.php ENDPATH**/ ?>