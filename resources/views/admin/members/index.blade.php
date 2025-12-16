{{-- Admin Members - Tenant-scoped member management --}}
{{-- Using standardized components with tenant-scoped RBAC management --}}

@php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Tenant-scoped filters for members
    $statusOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'inactive', 'label' => 'Inactive'],
        ['value' => 'suspended', 'label' => 'Suspended'],
        ['value' => 'pending', 'label' => 'Pending']
    ];
    
    // Tenant roles only (exclude super_admin)
    $roleOptions = [
        ['value' => 'admin', 'label' => 'Admin'],
        ['value' => 'project_manager', 'label' => 'Project Manager'],
        ['value' => 'member', 'label' => 'Member'],
        ['value' => 'client', 'label' => 'Client'],
        ['value' => 'client_rep', 'label' => 'Client Representative']
    ];
    
    // Filter configuration (no tenant filter - all members are from same tenant)
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
            'label' => 'Activate Members',
            'icon' => 'fas fa-check',
            'handler' => 'bulkActivate()'
        ],
        [
            'label' => 'Suspend Members',
            'icon' => 'fas fa-pause',
            'handler' => 'bulkSuspend()'
        ],
        [
            'label' => 'Change Role',
            'icon' => 'fas fa-user-tag',
            'handler' => 'bulkChangeRole()'
        ],
        [
            'label' => 'Export Members',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ],
        [
            'label' => 'Remove Members',
            'icon' => 'fas fa-trash',
            'handler' => 'bulkRemove()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => route('admin.dashboard')],
        ['label' => 'Members', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportMembers()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'invite-member-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-user-plus mr-2"></i>Invite Member
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($users->items() ?? [])->map(function($user) {
        return [
            'id' => $user->id,
            'name' => $user->name ?? 'Unknown',
            'email' => $user->email ?? '',
            'role' => $user->role ?? 'member',
            'status' => $user->is_active ? 'active' : 'inactive',
            'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
            'created_at' => $user->created_at->format('M d, Y'),
            'updated_at' => $user->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration (no tenant column - all same tenant)
    $columns = [
        ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'primary' => true],
        ['key' => 'email', 'label' => 'Email', 'sortable' => true],
        ['key' => 'role', 'label' => 'Role', 'sortable' => true, 'format' => 'badge'],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'format' => 'badge'],
        ['key' => 'last_login', 'label' => 'Last Login', 'sortable' => true, 'format' => 'text'],
        ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'format' => 'date']
    ];
@endphp

<x-shared.layout-wrapper 
    title="Members Management"
    subtitle="Manage tenant members and permissions"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions"
    variant="admin">
    
    {{-- Tenant-scoped Badge --}}
    <div class="mb-4">
        <span class="badge bg-green-100 text-green-800">
            Tenant: {{ $tenant->name ?? 'Unknown' }}
        </span>
    </div>
    
    {{-- Filter Bar --}}
    <x-shared.filter-bar 
        :search="true"
        search-placeholder="Search members..."
        :filters="$filters"
        :sort-options="$sortOptions"
        :view-modes="['table', 'grid', 'list']"
        current-view-mode="table"
        :bulk-actions="$bulkActions">
        
        {{-- Custom Actions Slot --}}
        <x-slot name="actions">
            <button onclick="refreshMembers()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </x-slot>
    </x-shared.filter-bar>
    
    {{-- Members Table --}}
    <div class="mt-6">
        @if($tableData->count() > 0)
        <x-shared.table-standardized 
            :items="$tableData"
            :columns="$columns"
            :sortable="true"
            :show-bulk-actions="true"
            :pagination="$users->links()"
            :show-search="true"
            :show-filters="true"
            :loading="false"
            :empty-state="[
                'icon' => 'fas fa-users',
                'title' => 'No members found',
                'description' => 'Try adjusting your filters or invite a new member.',
                'action' => [
                    'label' => 'Invite Member',
                    'icon' => 'fas fa-user-plus',
                    'handler' => 'openModal(\'invite-member-modal\')'
                ]
            ]">
            </x-shared.table-standardized>
        @else
            {{-- Empty State --}}
            <x-shared.empty-state 
                icon="fas fa-users"
                title="No members found"
                description="Invite your first member to start managing your tenant."
                action-text="Invite Member"
                action-icon="fas fa-user-plus"
                action-handler="openModal('invite-member-modal')" />
        @endif
        
        {{-- Pagination Links --}}
        @if($users->hasPages())
            <div class="mt-6">
                {{ $users->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
    
    {{-- Invite Member Modal --}}
    <x-shared.modal 
        id="invite-member-modal"
        title="Invite New Member"
        size="lg">
        
        <form id="invite-member-form" @submit.prevent="inviteMember()">
            <div class="space-y-6">
                {{-- Basic Information --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="member-first-name" class="form-label">First Name *</label>
                        <input type="text" 
                               id="member-first-name" 
                               name="first_name" 
                               required
                               class="form-input"
                               placeholder="Enter first name">
                    </div>
                    
                    <div>
                        <label for="member-last-name" class="form-label">Last Name *</label>
                        <input type="text" 
                               id="member-last-name" 
                               name="last_name" 
                               required
                               class="form-input"
                               placeholder="Enter last name">
                    </div>
                </div>
                
                {{-- Email --}}
                <div>
                    <label for="member-email" class="form-label">Email Address *</label>
                    <input type="email" 
                           id="member-email" 
                           name="email" 
                           required
                           class="form-input"
                           placeholder="Enter email address">
                </div>
                
                {{-- Role & Tenant --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="member-role" class="form-label">Role *</label>
                        <select id="member-role" name="role" required class="form-select">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="project_manager">Project Manager</option>
                            <option value="member">Member</option>
                            <option value="client">Client</option>
                            <option value="client_rep">Client Representative</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="member-tenant" class="form-label">Tenant</label>
                        <input type="text" 
                               id="member-tenant" 
                               name="tenant_name" 
                               value="{{ $tenant->name ?? 'Unknown' }}"
                               readonly
                               class="form-input bg-gray-100"
                               placeholder="Current Tenant">
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id ?? '' }}">
                    </div>
                </div>
                
                {{-- Status --}}
                <div>
                    <label for="member-status" class="form-label">Status</label>
                    <select id="member-status" name="is_active" class="form-select">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            
            {{-- Form Actions --}}
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('invite-member-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-user-plus mr-2"></i>Invite Member
                </button>
            </div>
        </form>
    </x-shared.modal>
</x-shared.layout-wrapper>

@push('scripts')
<script>
function refreshMembers() {
    window.location.reload();
}

function exportMembers() {
    alert('Export members functionality would be implemented here');
}

function inviteMember() {
    const form = document.getElementById('invite-member-form');
    const formData = new FormData(form);
    
    fetch('/api/v1/admin/members/invite', {
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
            closeModal('invite-member-modal');
            window.location.reload();
        } else {
            alert('Error inviting member: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error inviting member');
    });
}

function viewMember(memberId) {
    window.location.href = '/admin/members/' + memberId;
}

function editMember(memberId) {
    window.location.href = '/admin/members/' + memberId + '/edit';
}

function changeRole(memberId) {
    if (confirm('Are you sure you want to change this member\'s role?')) {
        alert('Change role functionality would be implemented here');
    }
}

function removeMember(memberId) {
    if (confirm('Are you sure you want to remove this member from the tenant? This action cannot be undone.')) {
        fetch('/api/v1/admin/members/' + memberId, {
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
                alert('Error removing member: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error removing member');
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

function bulkRemove() {
    alert('Bulk remove functionality would be implemented here');
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

