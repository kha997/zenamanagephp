@extends('layouts.admin')

@section('title', 'Import Template Set')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Import Template Set</h1>
        <p class="text-gray-600 mt-1">Upload a JSON, CSV, or XLSX file to import a template set</p>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('admin.templates.import.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="mb-4">
                <label for="file" class="block text-sm font-medium text-gray-700 mb-2">
                    Template File
                </label>
                <input type="file" 
                       name="file" 
                       id="file" 
                       accept=".json,.csv,.xlsx"
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       required>
                <p class="mt-2 text-sm text-gray-500">
                    Supported formats: JSON, CSV, XLSX (max 10MB)
                </p>
            </div>

            <div class="mb-4">
                <label for="tenant_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Tenant (Optional)
                </label>
                <select name="tenant_id" 
                        id="tenant_id" 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">Global Template</option>
                    @foreach(\App\Models\Tenant::all() as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name ?? $tenant->id }}</option>
                    @endforeach
                </select>
                <p class="mt-2 text-sm text-gray-500">
                    Leave empty for global template, or select a tenant for tenant-specific template
                </p>
            </div>

            <div class="flex gap-3">
                <button type="submit" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-upload mr-2"></i>
                    Import Template
                </button>
                <a href="{{ route('admin.templates.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

