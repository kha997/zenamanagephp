{{-- Tenant Scope Badge --}}
@php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
@endphp

@if($user->can('admin.access.tenant') && !$user->isSuperAdmin() && $tenant)
<div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
    <div class="flex items-center">
        <i class="fas fa-building text-blue-600 mr-2"></i>
        <span class="text-sm font-medium text-blue-900">
            Viewing data for tenant: <strong>{{ $tenant->name }}</strong>
        </span>
    </div>
</div>
@endif

