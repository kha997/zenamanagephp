@extends('layouts.app')

@section('title', 'Permission Test')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">Permission Test Page</h1>
            
            @auth
                <div class="space-y-4">
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <h3 class="font-semibold text-green-800">Authentication Status</h3>
                        <p class="text-green-700">✅ User is authenticated</p>
                        <p class="text-sm text-green-600">User: {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>
                    </div>
                    
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h3 class="font-semibold text-blue-800">User Roles</h3>
                        <p class="text-blue-700">Roles: {{ implode(', ', auth()->user()->getRoleNames()) }}</p>
                        <p class="text-sm text-blue-600">Primary Role: {{ auth()->user()->getPrimaryRole() ?? 'None' }}</p>
                    </div>
                    
                    <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                        <h3 class="font-semibold text-purple-800">Permission Checks</h3>
                        <ul class="text-purple-700 space-y-1">
                            <li>Is Super Admin: {{ auth()->user()->isSuperAdmin() ? '✅ Yes' : '❌ No' }}</li>
                            <li>Is Admin: {{ auth()->user()->isAdmin() ? '✅ Yes' : '❌ No' }}</li>
                            <li>Has Tenant: {{ auth()->user()->hasTenant() ? '✅ Yes' : '❌ No' }}</li>
                            <li>Can Access Admin: {{ auth()->user()->canAccessAdmin() ? '✅ Yes' : '❌ No' }}</li>
                            <li>Can Access App: {{ auth()->user()->canAccessApp() ? '✅ Yes' : '❌ No' }}</li>
                        </ul>
                    </div>
                    
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <h3 class="font-semibold text-yellow-800">Tenant Information</h3>
                        @if(auth()->user()->hasTenant())
                            <p class="text-yellow-700">Tenant ID: {{ auth()->user()->tenant_id }}</p>
                            @if(auth()->user()->tenant)
                                <p class="text-yellow-700">Tenant Name: {{ auth()->user()->tenant->name }}</p>
                            @endif
                        @else
                            <p class="text-yellow-700">No tenant assigned (Super Admin)</p>
                        @endif
                    </div>
                    
                    <div class="flex space-x-4">
                        <a href="/admin" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            Test Admin Access
                        </a>
                        <a href="/app/dashboard" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Test App Access
                        </a>
                        <a href="/logout" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                            Logout
                        </a>
                    </div>
                </div>
            @else
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <h3 class="font-semibold text-red-800">Authentication Required</h3>
                    <p class="text-red-700">Please login to test permissions</p>
                    <a href="/login" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 mt-2 inline-block">
                        Login
                    </a>
                </div>
            @endauth
        </div>
    </div>
</div>
@endsection
