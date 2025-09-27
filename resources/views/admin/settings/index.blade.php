{{-- Admin Settings Index --}}
@extends('layouts.admin')

@section('title', 'Settings')

@section('breadcrumb')
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Settings</span>
</li>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="text-gray-600">System configuration and preferences</p>
        </div>
        <button class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Save Changes
        </button>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">General Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">App Name</label>
                    <input type="text" value="ZenaManage" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Sender</label>
                    <input type="email" value="noreply@zenamanage.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Feature Flags</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Enable MFA</span>
                    <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Enable Analytics</span>
                    <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
