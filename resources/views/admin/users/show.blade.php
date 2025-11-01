@extends('layouts.admin')

@section('title', 'User Details')

@section('content')
<div class="container mx-auto p-6" x-data="userDetailsPage()">
    <!-- Breadcrumb -->
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="/admin/users" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    <i class="fas fa-users mr-2"></i>
                    Users
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">User Details</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">User Details</h1>
            <p class="text-gray-600 mt-1" x-show="user.name" x-text="user.name"></p>
        </div>
        <div class="flex space-x-3">
            <button @click="goBack()" class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Users
            </button>
            <button @click="editUser()" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-edit mr-2"></i>
                Edit User
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="bg-white shadow-md rounded-lg p-6">
        <div class="animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
            <div class="space-y-3">
                <div class="h-4 bg-gray-200 rounded"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                <div class="h-4 bg-gray-200 rounded w-4/6"></div>
            </div>
        </div>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-md p-4 mb-6">
        <div class="flex">
            <i class="fas fa-exclamation-circle text-red-400 mt-0.5"></i>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error loading user details</h3>
                <p class="mt-1 text-sm text-red-700" x-text="error"></p>
                <button @click="loadUser()" class="mt-2 text-sm text-red-600 hover:text-red-500 underline">
                    Try again
                </button>
            </div>
        </div>
    </div>

    <!-- User Details -->
    <div x-show="!loading && !error && user.id" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="user.name || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Email Address</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="user.email || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Role</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="getRoleBadgeClass(user.role)" x-text="user.role || 'N/A'"></span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="getStatusBadgeClass(user.status)" x-text="getStatusText(user.status)"></span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">MFA Enabled</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" 
                                  :class="user.mfaEnabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                <i :class="user.mfaEnabled ? 'fas fa-check' : 'fas fa-times'" class="mr-1"></i>
                                <span x-text="user.mfaEnabled ? 'Enabled' : 'Disabled'"></span>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Last Login</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="formatDate(user.lastLoginAt)"></dd>
                    </div>
                </dl>
            </div>

            <!-- Tenant Information -->
            <div class="bg-white shadow-md rounded-lg p-6 mt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Tenant Information</h2>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tenant Name</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="user.tenantName || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Tenant ID</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="user.tenantId || 'N/A'"></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-3">
                    <button @click="editUser()" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-edit mr-2"></i>
                        Edit User
                    </button>
                    
                    <button @click="changeRole()" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-md hover:bg-purple-100 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <i class="fas fa-user-tag mr-2"></i>
                        Change Role
                    </button>
                    
                    <button @click="toggleStatus()" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium" 
                            :class="user.status === 'active' ? 'text-red-700 bg-red-50 border-red-200 hover:bg-red-100' : 'text-green-700 bg-green-50 border-green-200 hover:bg-green-100'"
                            :disabled="user.status === 'invited'">
                        <i :class="user.status === 'active' ? 'fas fa-ban' : 'fas fa-check'" class="mr-2"></i>
                        <span x-text="user.status === 'active' ? 'Disable User' : 'Enable User'"></span>
                    </button>
                    
                    <button @click="sendResetLink()" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded-md hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <i class="fas fa-key mr-2"></i>
                        Send Reset Link
                    </button>
                    
                    <button @click="forceMfa()" x-show="!user.mfaEnabled" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-md hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <i class="fas fa-shield-alt mr-2"></i>
                        Force MFA
                    </button>
                    
                    <button @click="deleteUser()" class="w-full flex items-center justify-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <i class="fas fa-trash mr-2"></i>
                        Delete User
                    </button>
                </div>
            </div>

            <!-- Account Information -->
            <div class="bg-white shadow-md rounded-lg p-6 mt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Account Information</h2>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">User ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono" x-text="user.id || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Created At</dt>
                        <dd class="mt-1 text-sm text-gray-900" x-text="formatDate(user.createdAt)"></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<script>
function userDetailsPage() {
    return {
        user: {},
        loading: true,
        error: null,
        userId: null,

        init() {
            this.userId = window.location.pathname.split('/').pop();
            this.loadUser();
        },

        async loadUser() {
            this.loading = true;
            this.error = null;
            
            try {
                const response = await fetch(`/api/admin/users/${this.userId}`);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();
                this.user = data.data || {};
            } catch (error) {
                this.error = error.message;
                console.error('Error loading user details:', error);
            } finally {
                this.loading = false;
            }
        },

        goBack() {
            window.location.href = '/admin/users';
        },

        editUser() {
            // TODO: Implement edit user modal
            alert('Edit user functionality will be implemented');
        },

        changeRole() {
            // TODO: Implement change role modal
            alert('Change role functionality will be implemented');
        },

        toggleStatus() {
            if (this.user.status === 'invited') return;
            
            const action = this.user.status === 'active' ? 'disable' : 'enable';
            const confirmed = confirm(`Are you sure you want to ${action} this user?`);
            
            if (confirmed) {
                // TODO: Implement status toggle
                alert(`${action} user functionality will be implemented`);
            }
        },

        sendResetLink() {
            // TODO: Implement send reset link
            alert('Send reset link functionality will be implemented');
        },

        forceMfa() {
            // TODO: Implement force MFA
            alert('Force MFA functionality will be implemented');
        },

        deleteUser() {
            const confirmed = confirm('Are you sure you want to delete this user? This action cannot be undone.');
            
            if (confirmed) {
                // TODO: Implement delete user
                alert('Delete user functionality will be implemented');
            }
        },

        getRoleBadgeClass(role) {
            const classes = {
                'Super Admin': 'bg-red-100 text-red-800',
                'Admin': 'bg-purple-100 text-purple-800',
                'Project Manager': 'bg-blue-100 text-blue-800',
                'Member': 'bg-green-100 text-green-800',
                'Viewer': 'bg-gray-100 text-gray-800'
            };
            return classes[role] || 'bg-gray-100 text-gray-800';
        },

        getStatusBadgeClass(status) {
            const classes = {
                'active': 'bg-green-100 text-green-800',
                'invited': 'bg-yellow-100 text-yellow-800',
                'locked': 'bg-red-100 text-red-800',
                'disabled': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getStatusText(status) {
            const texts = {
                'active': 'Active',
                'invited': 'Invited',
                'locked': 'Locked',
                'disabled': 'Disabled'
            };
            return texts[status] || status;
        },

        formatDate(dateString) {
            if (!dateString) return 'Never';
            try {
                return new Date(dateString).toLocaleString();
            } catch (error) {
                return 'Invalid date';
            }
        }
    }
}
</script>
@endsection
