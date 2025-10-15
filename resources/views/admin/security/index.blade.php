@extends('layouts.admin')

@section('title', 'Security')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Security</h1>
        <p class="mt-2 text-gray-600">System security settings and monitoring</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fas fa-shield-alt text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Security Dashboard</h3>
            <p class="text-gray-500 mb-4">Security monitoring and settings will be available here.</p>
            <a href="{{ route('admin.security.scan') }}" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                <i class="fas fa-search mr-2"></i>
                Run Security Scan
            </a>
        </div>
    </div>
</div>
@endsection