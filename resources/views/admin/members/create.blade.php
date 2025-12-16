@extends('layouts.admin')

@section('title', 'Invite Member')

@section('content')
@php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Invite New Member</h1>
                <p class="mt-2 text-gray-600">Invite a new member to your tenant</p>
            </div>
            <a href="{{ route('admin.members.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Members
            </a>
        </div>
    </div>

    <!-- Invite Member Form -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <form class="space-y-6" id="invite-member-form" action="/api/v1/admin/members/invite" method="POST">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
                <input type="email" id="email" name="email" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role *</label>
                    <select id="role" name="role" required
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a role</option>
                        <option value="admin">Admin</option>
                        <option value="project_manager">Project Manager</option>
                        <option value="member">Member</option>
                        <option value="client">Client</option>
                        <option value="client_rep">Client Representative</option>
                    </select>
                </div>
                <div>
                    <label for="tenant" class="block text-sm font-medium text-gray-700">Tenant</label>
                    <input type="text" id="tenant" name="tenant_name" 
                           value="{{ $tenant->name ?? 'Unknown' }}"
                           readonly
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <input type="hidden" name="tenant_id" value="{{ $tenant->id ?? '' }}">
                </div>
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" id="email_verified" name="email_verified" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                <label for="email_verified" class="ml-2 block text-sm text-gray-900">
                    Email verified
                </label>
            </div>
            
            <div class="flex items-center justify-end space-x-4">
                <a href="{{ route('admin.members.index') }}" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Invite Member
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

