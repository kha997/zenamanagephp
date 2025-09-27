{{-- Admin Security Index --}}
@extends('layouts.admin')

@section('title', 'Security')

@section('breadcrumb')
<li class="flex items-center">
    <i class="fas fa-chevron-right text-gray-400 mr-2"></i>
    <span class="text-gray-900">Security</span>
</li>
@endsection

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Security</h1>
            <p class="text-gray-600">Security overview and management</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">MFA Adoption</h3>
            <div class="text-3xl font-bold text-blue-600">78%</div>
            <p class="text-sm text-gray-600">of users have MFA enabled</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Violations</h3>
            <div class="text-3xl font-bold text-red-600">3</div>
            <p class="text-sm text-gray-600">violations in last 24h</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Active Sessions</h3>
            <div class="text-3xl font-bold text-green-600">1,247</div>
            <p class="text-sm text-gray-600">active user sessions</p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Security Events</h3>
        <div class="space-y-3">
            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                    <div>
                        <p class="font-medium text-gray-900">Failed login attempt</p>
                        <p class="text-sm text-gray-600">john@example.com - 2 minutes ago</p>
                    </div>
                </div>
                <span class="text-sm text-red-600 font-medium">High Risk</span>
            </div>
        </div>
    </div>
</div>
@endsection
