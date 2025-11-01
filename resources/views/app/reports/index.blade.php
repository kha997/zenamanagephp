@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Reports</h1>
        <p class="mt-2 text-gray-600">View analytics and insights about your projects.</p>
    </div>

    <!-- Reports Content -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Analytics Dashboard</h2>
        </div>
        <div class="p-6">
            <div class="text-center py-8">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-chart-bar text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No data available</h3>
                <p class="text-gray-500 mb-4">Reports will appear here once you have project data.</p>
                <a href="{{ route('app.projects.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-project-diagram mr-2"></i>
                    Create Projects
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
