@extends('layouts.admin')

@section('title', 'Maintenance')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Maintenance</h1>
        <p class="mt-2 text-gray-600">System maintenance and management</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fas fa-tools text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Maintenance Dashboard</h3>
            <p class="text-gray-500 mb-4">System maintenance tools and utilities.</p>
            <a href="{{ route('admin.maintenance.backup') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                <i class="fas fa-download mr-2"></i>
                Backup System
            </a>
        </div>
    </div>
</div>
@endsection