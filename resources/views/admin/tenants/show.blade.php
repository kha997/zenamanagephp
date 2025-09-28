{{-- Admin Tenant Detail --}}
@extends('layouts.admin')

@section('title', 'Tenant Details')

@section('breadcrumb')
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <a href="/admin/tenants" class="text-gray-500 hover:text-gray-700">Tenants</a>
</li>
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Tenant Details</span>
</li>
@endsection

@section('content')
<div class="space-y-6" x-data="tenantDetail()">
    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tenant Details</h1>
            <p class="text-gray-600">View and manage tenant information</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="/admin/tenants" 
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to Tenants
            </a>
        </div>
    </div>
    
    <!-- Loading State -->
    <div x-show="isLoading" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="animate-pulse space-y-4">
            <div class="h-8 bg-gray-200 rounded w-1/4"></div>
            <div class="h-4 bg-gray-200 rounded w-1/2"></div>
            <div class="h-4 bg-gray-200 rounded w-1/3"></div>
        </div>
    </div>
    
    <!-- Error State -->
    <div x-show="error && !isLoading" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <i class="fas fa-exclamation-triangle text-red-500 text-2xl mb-2"></i>
            <h3 class="text-lg font-medium text-red-900 mb-2">Error loading tenant</h3>
            <p class="text-red-700 mb-4" x-text="error"></p>
            <button @click="loadTenant" 
                    class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-retry mr-2"></i>Retry
            </button>
        </div>
    </div>
    
    <!-- Tenant Content -->
    <div x-show="!isLoading && !error && tenant" class="space-y-6">
        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="tenant.name"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Domain</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="tenant.domain"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Owner</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="tenant.ownerName"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Owner Email</label>
                    <p class="mt-1 text-sm text-gray-900" x-text="tenant.ownerEmail"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Plan</label>
                    <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="tenant.plan === 'Enterprise' ? 'bg-purple-100 text-purple-800' : tenant.plan === 'Professional' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'"
                          x-text="tenant.plan"></span>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="tenant.status === 'active' ? 'bg-green-100 text-green-800' : tenant.status === 'suspended' ? 'bg-red-100 text-red-800' : tenant.status === 'trial' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'"
                          x-text="tenant.status"></span>
                </div>
            </div>
        </div>
        
        <!-- Usage Statistics -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Usage Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600" x-text="tenant.usersCount || 0">0</div>
                    <div class="text-sm text-gray-600">Users</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600" x-text="tenant.projectsCount || 0">0</div>
                    <div class="text-sm text-gray-600">Projects</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600" x-text="formatBytes(tenant.storageUsed || 0)">0</div>
                    <div class="text-sm text-gray-600">Storage Used</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
            <div class="flex flex-wrap gap-3">
                <button @click="toggleStatus" 
                        :class="tenant.status === 'active' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                        class="px-4 py-2 text-white rounded-lg transition-colors">
                    <i :class="tenant.status === 'active' ? 'fas fa-pause' : 'fas fa-play'" class="mr-2"></i>
                    <span x-text="tenant.status === 'active' ? 'Suspend' : 'Activate'"></span>
                </button>
                <button @click="changePlan" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-edit mr-2"></i>Change Plan
                </button>
                <button @click="resetSecret" 
                        class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                    <i class="fas fa-key mr-2"></i>Reset Secret
                </button>
                <button @click="deleteTenant" 
                        class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-trash mr-2"></i>Delete
                </button>
            </div>
        </div>
        
        <!-- Raw JSON (for debugging) -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Raw Data (Debug)</h2>
            <pre class="bg-gray-100 p-4 rounded text-xs overflow-auto" x-text="JSON.stringify(tenant, null, 2)"></pre>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function tenantDetail() {
        return {
            tenant: null,
            isLoading: true,
            error: null,
            tenantId: null,
            
            init() {
                this.tenantId = window.location.pathname.split('/').pop();
                this.loadTenant();
            },
            
            async loadTenant() {
                this.isLoading = true;
                this.error = null;
                
                try {
                    // Mock data for now
                    await new Promise(resolve => setTimeout(resolve, 500));
                    
                    this.tenant = {
                        id: this.tenantId,
                        name: 'TechCorp',
                        domain: 'techcorp.com',
                        ownerName: 'John Doe',
                        ownerEmail: 'john@techcorp.com',
                        plan: 'Professional',
                        status: 'active',
                        usersCount: 25,
                        projectsCount: 8,
                        storageUsed: 2200000000000,
                        createdAt: '2024-01-15T00:00:00Z',
                        lastActiveAt: '2024-09-27T10:30:00Z'
                    };
                } catch (error) {
                    this.error = error.message;
                } finally {
                    this.isLoading = false;
                }
            },
            
            formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },
            
            async toggleStatus() {
                // TODO: Implement API call
                alert('Toggle status functionality coming soon');
            },
            
            async changePlan() {
                // TODO: Implement API call
                alert('Change plan functionality coming soon');
            },
            
            async resetSecret() {
                // TODO: Implement API call
                alert('Reset secret functionality coming soon');
            },
            
            async deleteTenant() {
                if (confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) {
                    // TODO: Implement API call
                    alert('Delete tenant functionality coming soon');
                }
            }
        }
    }
</script>
@endpush
