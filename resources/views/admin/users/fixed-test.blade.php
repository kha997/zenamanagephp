{{-- Simple test view với cùng data và columns như view chính --}}
@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Admin Users - Fixed Test</h1>
    
    <div class="mb-4">
        <p>Table data count: {{ $tableData->count() }}</p>
        <p>Users total: {{ $users->total() }}</p>
    </div>
    
    @if($tableData->count() > 0)
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Fixed Table Component:</h3>
            <x-shared.table-standardized 
                :items="$tableData"
                :columns="[
                    ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'primary' => true],
                    ['key' => 'email', 'label' => 'Email', 'sortable' => true],
                    ['key' => 'role', 'label' => 'Role', 'sortable' => true, 'format' => 'badge'],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'format' => 'badge'],
                    ['key' => 'tenant', 'label' => 'Tenant', 'sortable' => true],
                    ['key' => 'last_login', 'label' => 'Last Login', 'sortable' => true, 'format' => 'date'],
                    ['key' => 'created_at', 'label' => 'Created', 'sortable' => true, 'format' => 'date']
                ]"
                :sortable="true"
                :show-bulk-actions="true"
                :loading="false">
            </x-shared.table-standardized>
        </div>
        
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">No users found</p>
        </div>
    @endif
</div>
@endsection
