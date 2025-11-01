{{-- Simple test for table-standardized component --}}
@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Table Component Test</h1>
    
    <div class="mb-4">
        <p>Table data count: {{ $tableData->count() }}</p>
    </div>
    
    @if($tableData->count() > 0)
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Raw Table Data:</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm">{{ json_encode($tableData->toArray(), JSON_PRETTY_PRINT) }}</pre>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Table Component:</h3>
            <x-shared.table-standardized 
                :items="$tableData"
                :columns="[
                    ['key' => 'name', 'label' => 'Name'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'role', 'label' => 'Role'],
                    ['key' => 'status', 'label' => 'Status']
                ]"
                :sortable="true"
                :show-bulk-actions="true"
                :loading="false">
            </x-shared.table-standardized>
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-500">No data to display</p>
        </div>
    @endif
</div>
@endsection
