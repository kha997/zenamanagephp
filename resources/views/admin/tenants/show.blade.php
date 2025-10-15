@extends('layouts.admin')

@section('title', 'Tenant Details')

@section('content')
<div class="tenant-detail-container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    {{-- Breadcrumb --}}
    <nav class="flex mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <li class="inline-flex items-center">
                <a href="/admin/tenants" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                    <i class="fas fa-building mr-2"></i>
                    Tenants
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fas fa-chevron-right text-gray-400 mx-1"></i>
                    <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Tenant Details</span>
                </div>
            </li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="page-header flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900" id="tenant-name">Loading...</h1>
            <p class="text-gray-600" id="tenant-subtitle">Loading tenant details...</p>
        </div>
        <div class="flex items-center space-x-3">
            <button id="edit-tenant-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-edit mr-2"></i>Edit Tenant
            </button>
            <button id="back-to-list-btn" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </button>
        </div>
    </div>

    {{-- Loading State --}}
    <div id="loading-state" class="flex items-center justify-center py-12">
        <div class="flex items-center space-x-2 text-gray-600">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading tenant details...</span>
        </div>
    </div>

    {{-- Error State --}}
    <div id="error-state" class="hidden bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
            <div>
                <h3 class="text-sm font-medium text-red-800">Error loading tenant</h3>
                <p class="text-sm text-red-700 mt-1" id="error-message"></p>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div id="main-content" class="hidden">
        {{-- Tenant Overview --}}
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Overview</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {{-- Basic Info --}}
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Tenant Name</label>
                            <p class="text-sm text-gray-900" id="tenant-name-value"></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Domain</label>
                            <p class="text-sm text-gray-900" id="tenant-domain-value"></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Code/Slug</label>
                            <p class="text-sm text-gray-900" id="tenant-code-value"></p>
                        </div>
                    </div>

                    {{-- Status & Plan --}}
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status</label>
                            <p class="text-sm" id="tenant-status-value"></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Plan</label>
                            <p class="text-sm text-gray-900" id="tenant-plan-value"></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Region</label>
                            <p class="text-sm text-gray-900" id="tenant-region-value"></p>
                        </div>
                    </div>

                    {{-- Dates --}}
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Created At</label>
                            <p class="text-sm text-gray-900" id="tenant-created-value"></p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Updated At</label>
                            <p class="text-sm text-gray-900" id="tenant-updated-value"></p>
                        </div>
                        <div id="trial-ends-container" class="hidden">
                            <label class="text-sm font-medium text-gray-500">Trial Ends</label>
                            <p class="text-sm text-gray-900" id="tenant-trial-ends-value"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statistics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-users text-blue-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Users</p>
                        <p class="text-2xl font-semibold text-gray-900" id="users-count">0</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-project-diagram text-green-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Projects</p>
                        <p class="text-2xl font-semibold text-gray-900" id="projects-count">0</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tasks text-yellow-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Tasks</p>
                        <p class="text-2xl font-semibold text-gray-900" id="tasks-count">0</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-database text-purple-500 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Storage Used</p>
                        <p class="text-2xl font-semibold text-gray-900" id="storage-used">0 MB</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Actions</h2>
            </div>
            <div class="p-6">
                <div class="flex flex-wrap gap-3">
                    <button id="suspend-tenant-btn" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                        <i class="fas fa-pause mr-2"></i>Suspend
                    </button>
                    <button id="resume-tenant-btn" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-play mr-2"></i>Resume
                    </button>
                    <button id="impersonate-tenant-btn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-user-secret mr-2"></i>Impersonate
                    </button>
                    <button id="delete-tenant-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash mr-2"></i>Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Toast Container --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

<script src="/js/tenants/detail.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tenantId = '{{ $id }}';
    const tenantDetail = new TenantDetailPage(tenantId);
    tenantDetail.init();
});
</script>
@endsection