


<?php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Admin-specific filters for users
    $statusOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
        ['value' => 'suspended', 'label' => 'Suspended'],
        ['value' => 'pending', 'label' => 'Pending']
    ];
    
    $roleOptions = [
        ['value' => 'super_admin', 'label' => 'Super Admin'],
        ['value' => 'admin', 'label' => 'Admin'],
        ['value' => 'project_manager', 'label' => 'Project Manager'],
        ['value' => 'member', 'label' => 'Member'],
        ['value' => 'client', 'label' => 'Client'],
        ['value' => 'client_rep', 'label' => 'Client Representative']
    ];
    
    $tenantOptions = collect($tenants ?? [])->map(function($tenant) {
        return ['value' => $tenant->id ?? '', 'label' => $tenant->name ?? 'Unknown'];
    })->toArray();
    
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
            'key' => 'role',
            'label' => 'Role',
            'type' => 'select',
            'options' => $roleOptions,
            'placeholder' => 'All Roles'
        ],
        [
            'key' => 'tenant_id',
            'label' => 'Tenant',
            'type' => 'select',
            'options' => $tenantOptions,
            'placeholder' => 'All Tenants'
        ],
        [
            'key' => 'created_date',
            'label' => 'Created Date',
            'type' => 'date-range'
        ],
        [
            'key' => 'last_login',
            'label' => 'Last Login',
            'type' => 'date-range'
        ]
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'name', 'label' => 'Name'],
        ['value' => 'email', 'label' => 'Email'],
        ['value' => 'role', 'label' => 'Role'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'created_at', 'label' => 'Created Date'],
        ['value' => 'last_login_at', 'label' => 'Last Login']
    ];
    
    // Bulk actions
    $bulkActions = [
        [
            'label' => 'Activate Users',
            'icon' => 'fas fa-check',
            'handler' => 'bulkActivate()'
        ],
        [
            'label' => 'Suspend Users',
            'icon' => 'fas fa-pause',
            'handler' => 'bulkSuspend()'
        ],
        [
            'label' => 'Change Role',
            'icon' => 'fas fa-user-tag',
            'handler' => 'bulkChangeRole()'
        ],
        [
            'label' => 'Export Users',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ],
        [
            'label' => 'Delete Users',
            'icon' => 'fas fa-trash',
            'handler' => 'bulkDelete()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Users', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportUsers()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'create-user-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-user-plus mr-2"></i>Add User
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($users ?? [])->map(function($user) {
        return [
            'id' => $user->id,
            'name' => $user->name ?? 'Unknown',
            'email' => $user->email ?? '',
            'role' => $user->role ?? 'member',
            'status' => $user->is_active ? 'active' : 'inactive',
            'tenant' => $user->tenant->name ?? 'No Tenant',
            'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
            'created_at' => $user->created_at->format('M d, Y'),
            'updated_at' => $user->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration
    $columns = [
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'primary' => true],
        ['key' => 'email', 'label' => 'Email', 'sortable' => true],
        ['key' => 'role', 'label' => 'Role', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'tenant', 'label' => 'Tenant', 'sortable' => true],
        ['key' => 'last_login', 'label' => 'Last Login', 'sortable' => true, 'type' => 'date'],
        ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'type' => 'date']
    ];
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.layout-wrapper','data' => ['title' => 'User Management','subtitle' => 'Manage system users and permissions','breadcrumbs' => $breadcrumbs,'actions' => $actions,'variant' => 'admin']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.layout-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'User Management','subtitle' => 'Manage system users and permissions','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions),'variant' => 'admin']); ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.filter-bar','data' => ['search' => true,'searchPlaceholder' => 'Search users...','filters' => $filters,'sortOptions' => $sortOptions,'viewModes' => ['table', 'grid', 'list'],'currentViewMode' => 'table','bulkActions' => $bulkActions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['search' => true,'search-placeholder' => 'Search users...','filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'sort-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sortOptions),'view-modes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['table', 'grid', 'list']),'current-view-mode' => 'table','bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions)]); ?>
        
        
         <?php $__env->slot('actions', null, []); ?> 
            <button onclick="refreshUsers()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-standardized','data' => ['data' => $tableData,'columns' => $columns,'sortable' => true,'selectable' => true,'pagination' => true,'perPage' => 15,'search' => true,'export' => true,'bulkActions' => $bulkActions,'responsive' => true,'loading' => false,'emptyMessage' => 'No users found','emptyDescription' => 'Create your first user to get started','emptyActionText' => 'Add User','emptyActionHandler' => 'openModal(\'create-user-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableData),'columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($columns),'sortable' => true,'selectable' => true,'pagination' => true,'per-page' => 15,'search' => true,'export' => true,'bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions),'responsive' => true,'loading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'empty-message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('No users found'),'empty-description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create your first user to get started'),'empty-action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Add User'),'empty-action-handler' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('openModal(\'create-user-modal\')')]); ?>
                
                
                 <?php $__env->slot('cell-role', null, []); ?> 
                    <?php
                        $role = $row['role'] ?? 'member';
                        $roleClasses = [
                            'super_admin' => 'bg-red-100 text-red-800',
                            'admin' => 'bg-orange-100 text-orange-800',
                            'project_manager' => 'bg-blue-100 text-blue-800',
                            'member' => 'bg-green-100 text-green-800',
                            'client' => 'bg-purple-100 text-purple-800',
                            'client_rep' => 'bg-indigo-100 text-indigo-800'
                        ];
                        $roleClass = $roleClasses[$role] ?? $roleClasses['member'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($roleClass); ?>">
                        <?php echo e(ucfirst(str_replace('_', ' ', $role))); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-status', null, []); ?> 
                    <?php
                        $status = $row['status'] ?? 'inactive';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'suspended' => 'bg-red-100 text-red-800',
                            'pending' => 'bg-yellow-100 text-yellow-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['inactive'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                        <?php echo e(ucfirst($status)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-last_login', null, []); ?> 
                    <?php if($row['last_login'] === 'Never'): ?>
                        <span class="text-sm text-gray-500">Never</span>
                    <?php else: ?>
                        <span class="text-sm font-medium text-gray-900"><?php echo e($row['last_login']); ?></span>
                    <?php endif; ?>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('row-actions', null, []); ?> 
                    <div class="flex items-center space-x-2">
                        <button onclick="viewUser('<?php echo e($row['id']); ?>')" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="editUser('<?php echo e($row['id']); ?>')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="resetPassword('<?php echo e($row['id']); ?>')" 
                                class="text-orange-600 hover:text-orange-800 text-sm font-medium">
                            <i class="fas fa-key mr-1"></i>Reset Password
                        </button>
                        <button onclick="suspendUser('<?php echo e($row['id']); ?>')" 
                                class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                            <i class="fas fa-pause mr-1"></i>Suspend
                        </button>
                        <button onclick="deleteUser('<?php echo e($row['id']); ?>')" 
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-users','title' => 'No users found','description' => 'Create your first user to start managing the system.','actionText' => 'Add User','actionIcon' => 'fas fa-user-plus','actionHandler' => 'openModal(\'create-user-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-users','title' => 'No users found','description' => 'Create your first user to start managing the system.','action-text' => 'Add User','action-icon' => 'fas fa-user-plus','action-handler' => 'openModal(\'create-user-modal\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'create-user-modal','title' => 'Create New User','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'create-user-modal','title' => 'Create New User','size' => 'lg']); ?>
        
        <form id="create-user-form" @submit.prevent="createUser()">
            <div class="space-y-6">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="user-first-name" class="form-label">First Name *</label>
                        <input type="text" 
                               id="user-first-name" 
                               name="first_name" 
                               required
                               class="form-input"
                               placeholder="Enter first name">
                    </div>
                    
                    <div>
                        <label for="user-last-name" class="form-label">Last Name *</label>
                        <input type="text" 
                               id="user-last-name" 
                               name="last_name" 
                               required
                               class="form-input"
                               placeholder="Enter last name">
                    </div>
                </div>
                
                
                <div>
                    <label for="user-email" class="form-label">Email Address *</label>
                    <input type="email" 
                           id="user-email" 
                           name="email" 
                           required
                           class="form-input"
                           placeholder="Enter email address">
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="user-role" class="form-label">Role *</label>
                        <select id="user-role" name="role" required class="form-select">
                            <option value="">Select Role</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Admin</option>
                            <option value="project_manager">Project Manager</option>
                            <option value="member">Member</option>
                            <option value="client">Client</option>
                            <option value="client_rep">Client Representative</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="user-tenant" class="form-label">Tenant *</label>
                        <select id="user-tenant" name="tenant_id" required class="form-select">
                            <option value="">Select Tenant</option>
                            <?php $__currentLoopData = $tenants ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($tenant->id); ?>"><?php echo e($tenant->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                
                
                <div>
                    <label for="user-password" class="form-label">Password *</label>
                    <input type="password" 
                           id="user-password" 
                           name="password" 
                           required
                           class="form-input"
                           placeholder="Enter password">
                    <p class="text-sm text-gray-500 mt-1">Minimum 8 characters</p>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="user-status" class="form-label">Status</label>
                        <select id="user-status" name="is_active" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="user-permissions" class="form-label">Additional Permissions</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="permissions[]" value="can_manage_users" class="form-checkbox">
                                <span class="ml-2 text-sm text-gray-700">Manage Users</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="permissions[]" value="can_manage_projects" class="form-checkbox">
                                <span class="ml-2 text-sm text-gray-700">Manage Projects</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="permissions[]" value="can_view_analytics" class="form-checkbox">
                                <span class="ml-2 text-sm text-gray-700">View Analytics</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('create-user-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-user-plus mr-2"></i>Create User
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
function refreshUsers() {
    window.location.reload();
}

function exportUsers() {
    alert('Export users functionality would be implemented here');
}

function createUser() {
    const form = document.getElementById('create-user-form');
    const formData = new FormData(form);
    
    fetch('/api/v1/admin/users', {
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
            closeModal('create-user-modal');
            window.location.reload();
        } else {
            alert('Error creating user: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating user');
    });
}

function viewUser(userId) {
    window.location.href = '/admin/users/' + userId;
}

function editUser(userId) {
    window.location.href = '/admin/users/' + userId + '/edit';
}

function resetPassword(userId) {
    if (confirm('Are you sure you want to reset this user\'s password?')) {
        alert('Reset password functionality would be implemented here');
    }
}

function suspendUser(userId) {
    if (confirm('Are you sure you want to suspend this user?')) {
        alert('Suspend user functionality would be implemented here');
    }
}

function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch('/api/v1/admin/users/' + userId, {
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
                alert('Error deleting user: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting user');
        });
    }
}

function bulkActivate() {
    alert('Bulk activate functionality would be implemented here');
}

function bulkSuspend() {
    alert('Bulk suspend functionality would be implemented here');
}

function bulkChangeRole() {
    alert('Bulk change role functionality would be implemented here');
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/index.blade.php ENDPATH**/ ?>