@extends('layouts.admin')

@section('title', 'Admin Settings')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Admin Settings</h1>
        <p class="mt-2 text-gray-600">System configuration and settings</p>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="text-center py-8">
            <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="fas fa-cog text-2xl text-gray-400"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Admin Settings</h3>
            <p class="text-gray-500">System configuration and admin settings will be available here.</p>
        </div>
    </div>
</div>
@endsection