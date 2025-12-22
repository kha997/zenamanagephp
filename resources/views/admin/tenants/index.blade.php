{{-- Admin Tenants - Week 2 Implementation --}}
{{-- Using standardized components with admin-specific tenant management --}}

@php
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
@endphp

<x-shared.layout-wrapper 
    title="Tenant Management"
    subtitle="Manage system tenants and subscriptions"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions"
    variant="admin">
    
    {{-- Filter Bar --}}
    <x-shared.filter-bar 
        :search="true"
        search-placeholder="Search tenants..."
        :filters="$filters"
        :sort-options="$sortOptions"
        :view-modes="['table', 'grid', 'list']"
        current-view-mode="table"
        :bulk-actions="$bulkActions">
        
        {{-- Custom Actions Slot --}}
        <x-slot name="actions">
            <button onclick="refreshTenants()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </x-slot>
    </x-shared.filter-bar>
    
    {{-- Tenants Table --}}
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
                :empty-message="'No tenants found'"
                :empty-description="'Create your first tenant to get started'"
                :empty-action-text="'Add Tenant'"
                :empty-action-handler="'openModal(\'create-tenant-modal\')'">
                
                {{-- Custom cell content for status --}}
                <x-slot name="cell-status">
                    @php
                        $status = $row['status'] ?? 'trial';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'inactive' => 'bg-gray-100 text-gray-800',
                            'trial' => 'bg-blue-100 text-blue-800',
                            'suspended' => 'bg-red-100 text-red-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['trial'];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        {{ ucfirst($status) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for plan --}}
                <x-slot name="cell-plan">
                    @php
                        $plan = $row['plan'] ?? 'free';
                        $planClasses = [
                            'free' => 'bg-gray-100 text-gray-800',
                            'basic' => 'bg-blue-100 text-blue-800',
                            'premium' => 'bg-purple-100 text-purple-800',
                            'enterprise' => 'bg-orange-100 text-orange-800'
                        ];
                        $planClass = $planClasses[$plan] ?? $planClasses['free'];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $planClass }}">
                        {{ ucfirst($plan) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for user count --}}
                <x-slot name="cell-user_count">
                    <div class="flex items-center">
                        <i class="fas fa-users text-gray-400 mr-1"></i>
                        <span class="text-sm font-medium text-gray-900">{{ $row['user_count'] }}</span>
                    </div>
                </x-slot>
                
                {{-- Custom cell content for project count --}}
                <x-slot name="cell-project_count">
                    <div class="flex items-center">
                        <i class="fas fa-project-diagram text-gray-400 mr-1"></i>
                        <span class="text-sm font-medium text-gray-900">{{ $row['project_count'] }}</span>
                    </div>
                </x-slot>
                
                {{-- Custom cell content for trial ends --}}
                <x-slot name="cell-trial_ends_at">
                    @if($row['trial_ends_at'] === 'N/A')
                        <span class="text-sm text-gray-500">N/A</span>
                    @else
                        <span class="text-sm font-medium text-gray-900">{{ $row['trial_ends_at'] }}</span>
                    @endif
                </x-slot>
                
                {{-- Row actions --}}
                <x-slot name="row-actions">
                    <div class="flex items-center space-x-2">
                        <button onclick="viewTenant('{{ $row['id'] }}')" 
                                class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button onclick="editTenant('{{ $row['id'] }}')" 
                                class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button onclick="manageUsers('{{ $row['id'] }}')" 
                                class="text-green-600 hover:text-green-800 text-sm font-medium">
                            <i class="fas fa-users mr-1"></i>Users
                        </button>
                        <button onclick="suspendTenant('{{ $row['id'] }}')" 
                                class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                            <i class="fas fa-pause mr-1"></i>Suspend
                        </button>
                        <button onclick="deleteTenant('{{ $row['id'] }}')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                </x-slot>
            </x-shared.table-standardized>
        @else
            {{-- Empty State --}}
            <x-shared.empty-state 
                icon="fas fa-building"
                title="No tenants found"
                description="Create your first tenant to start managing the system."
                action-text="Add Tenant"
                action-icon="fas fa-building"
                action-handler="openModal('create-tenant-modal')" />
        @endif
    </div>
    
    {{-- Create Tenant Modal --}}
    <x-shared.modal 
        id="create-tenant-modal"
        title="Create New Tenant"
        size="lg">
        
        <form id="create-tenant-form" @submit.prevent="createTenant()">
            <div class="space-y-6">
                {{-- Basic Information --}}
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
                
                {{-- Domain & Settings --}}
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
                
                {{-- Plan & Trial --}}
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
                
                {{-- Settings --}}
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
            
            {{-- Form Actions --}}
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
    </x-shared.modal>
</x-shared.layout-wrapper>

@push('scripts')
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
@endpush
