{{-- Admin Template Sets Index --}}
{{-- Lists all WBS template sets with filters and actions --}}

@php
    $user = Auth::user();
    
    // Filter options (associative array: value => label)
    $statusOptions = [
        '1' => 'Active',
        '0' => 'Inactive',
    ];
    
    $presetOptions = [
        'true' => 'Has Presets',
        'false' => 'No Presets',
    ];
    
    $filters = [
        [
            'key' => 'version',
            'name' => 'version',
            'label' => 'Version',
            'type' => 'text',
            'placeholder' => 'Filter by version'
        ],
        [
            'key' => 'is_active',
            'name' => 'is_active',
            'label' => 'Status',
            'type' => 'select',
            'options' => $statusOptions,
            'placeholder' => 'All Statuses'
        ],
        [
            'key' => 'has_presets',
            'name' => 'has_presets',
            'label' => 'Presets',
            'type' => 'select',
            'options' => $presetOptions,
            'placeholder' => 'All Templates'
        ],
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'name', 'label' => 'Name'],
        ['value' => 'code', 'label' => 'Code'],
        ['value' => 'version', 'label' => 'Version'],
        ['value' => 'created_at', 'label' => 'Created Date'],
    ];
@endphp

@extends('layouts.admin')

@section('title', 'Template Sets')
@section('page-title', 'WBS Template Sets')
@section('page-description', 'Manage WBS-style task template sets')

@section('content')
<div class="space-y-6">
    {{-- Header with Actions --}}
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Template Sets</h1>
            <p class="text-gray-600 mt-1">Manage WBS-style task template sets for project creation</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.templates.import') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-upload mr-2"></i>
                Import Template
            </a>
        </div>
    </div>

    {{-- Filters --}}
    @include('components.shared.filters', ['filters' => $filters, 'sortOptions' => $sortOptions])

    {{-- Template Sets Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presets</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($templateSets as $set)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $set->name }}</div>
                                @if($set->description)
                                    <div class="text-sm text-gray-500">{{ Str::limit($set->description, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $set->code }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">
                                    {{ $set->version }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($set->is_active)
                                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-900">{{ $set->presets->count() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($set->is_global)
                                    <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded">Global</span>
                                @else
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">Tenant</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex gap-2">
                                    <a href="{{ route('admin.templates.show', $set) }}" 
                                       class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    @can('update', $set)
                                        <a href="{{ route('admin.templates.edit', $set) }}" 
                                           class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    @endcan
                                    @can('delete', $set)
                                        <form action="{{ route('admin.templates.destroy', $set) }}" 
                                              method="POST" 
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this template set?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                No template sets found. 
                                <a href="{{ route('admin.templates.import') }}" class="text-blue-600 hover:underline">Import one</a> or 
                                <a href="{{ route('admin.templates.import') }}" class="text-blue-600 hover:underline">import from file</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($templateSets->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $templateSets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

