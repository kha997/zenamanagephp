{{-- Admin Users - Week 2 Implementation --}}
{{-- Using standardized components with admin-specific RBAC management --}}

@php
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
@endphp

<x-shared.layout-wrapper 
    title="User Management"
    subtitle="Manage system users and permissions"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions"
    variant="admin">
    
    {{-- Filter Bar --}}
    <x-shared.filter-bar 
        :search="true"
        search-placeholder="Search users..."
        :filters="$filters"
        :sort-options="$sortOptions"
        :view-modes="['table', 'grid', 'list']"
        current-view-mode="table"
        :bulk-actions="$bulkActions">
        
        {{-- Custom Actions Slot --}}
        <x-slot name="actions">
            <button onclick="refreshUsers()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </x-slot>
    </x-shared.filter-bar>
    
    {{-- Users Table --}}
    <div class="mt-6">
        @if($tableData->count() > 0)
            <x-shared.table-standardized 
                :data="$tableData"
                :columns="$columns"
                :sortable="true"
                :selectable="true"
                :pagination="true"
                :per-page="15"
                :search="true"
                :export="true"
                :bulk-actions="$bulkActions"
                :responsive="true"
                :loading="false"
                :empty-message="'No users found'"
                :empty-description="'Create your first user to get started'"
                :empty-action-text="'Add User'"
                :empty-action-handler="'openModal(\'create-user-modal\')'">
                
                {{-- Custom cell content for role --}}
                <x-slot name="cell-role">
                    @php
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
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleClass }}">
                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for status --}}
                <x-slot name="cell-status">
                    @php
                        $status = $row['status'] ?? 'inactive';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'suspended' => 'bg-red-100 text-red-800',
                            'pending' => 'bg-yellow-100 text-yellow-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['inactive'];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        {{ ucfirst($status) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for last login --}}
                <x-slot name="cell-last_login">
                    @if($row['last_login'] === 'Never')
                        <span class="text-sm text-gray-500">Never</span>
                    @else
                        <span class="text-sm font-medium text-gray-900">{{ $row['last_login'] }}</span>
                    @endif
                </x-slot>
                
                {{-- Row actions --}}
                <x-slot name="row-actions">
                    <div class="flex items-center space-x-2">
                        <button onclick="viewUser('{{ $row['id'] }}')" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="editUser('{{ $row['id'] }}')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="resetPassword('{{ $row['id'] }}')" 
                                class="text-orange-600 hover:text-orange-800 text-sm font-medium">
                            <i class="fas fa-key mr-1"></i>Reset Password
                        </button>
                        <button onclick="suspendUser('{{ $row['id'] }}')" 
                                class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                            <i class="fas fa-pause mr-1"></i>Suspend
                        </button>
                        <button onclick="deleteUser('{{ $row['id'] }}')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                </x-slot>
            </x-shared.table-standardized>
        @else
            {{-- Empty State --}}
            <x-shared.empty-state 
                icon="fas fa-users"
                title="No users found"
                description="Create your first user to start managing the system."
                action-text="Add User"
                action-icon="fas fa-user-plus"
                action-handler="openModal('create-user-modal')" />
        @endif
    </div>
    
    {{-- Create User Modal --}}
    <x-shared.modal 
        id="create-user-modal"
        title="Create New User"
        size="lg">
        
        <form id="create-user-form" @submit.prevent="createUser()">
            <div class="space-y-6">
                {{-- Basic Information --}}
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
                
                {{-- Email --}}
                <div>
                    <label for="user-email" class="form-label">Email Address *</label>
                    <input type="email" 
                           id="user-email" 
                           name="email" 
                           required
                           class="form-input"
                           placeholder="Enter email address">
                </div>
                
                {{-- Role & Tenant --}}
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
                            @foreach($tenants ?? [] as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                {{-- Password --}}
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
                
                {{-- Status & Permissions --}}
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
            
            {{-- Form Actions --}}
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
    </x-shared.modal>
</x-shared.layout-wrapper>

@push('scripts')
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
@endpush
