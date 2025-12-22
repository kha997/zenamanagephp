{{-- Simple debug view for table component --}}
@extends('layouts.admin')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Table Component Debug</h1>
    
    <div class="mb-4">
        <p>Table data count: {{ $tableData->count() }}</p>
        <p>Table data type: {{ get_class($tableData) }}</p>
        <p>Table data isEmpty: {{ $tableData->isEmpty() ? 'true' : 'false' }}</p>
    </div>
    
    @if($tableData->count() > 0)
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Raw Table Data (first item):</h3>
            <pre class="bg-gray-100 p-4 rounded text-sm">{{ json_encode($tableData->first(), JSON_PRETTY_PRINT) }}</pre>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Simple HTML Table:</h3>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($tableData as $user)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user['name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user['email'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user['role'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">Table Component Test:</h3>
            <x-shared.table-standardized 
                :items="$tableData"
                :columns="[
                    ['key' => 'name', 'label' => 'Name'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'role', 'label' => 'Role'],
                    ['key' => 'status', 'label' => 'Status']
                ]"
                :sortable="false"
                :show-bulk-actions="false"
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
