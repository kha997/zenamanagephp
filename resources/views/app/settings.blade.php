@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
        <p class="mt-2 text-gray-600">Configure your account and application preferences.</p>
    </div>

    <!-- Settings Content -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Account Settings</h2>
        </div>
        <div class="p-6">
            <div class="text-center py-8">
                <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-cog text-2xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Settings Panel</h3>
                <p class="text-gray-500 mb-4">Configure your account preferences and application settings.</p>
                <button class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i>
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
