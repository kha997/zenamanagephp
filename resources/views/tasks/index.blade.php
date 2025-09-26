@extends('layouts.app')

@section('title', 'Tasks - ZenaManage')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Tasks</h1>
        <p class="text-gray-600">Manage and track your project tasks</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Total Tasks</h3>
            <p class="text-3xl font-bold text-gray-900">24</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500 mb-2">In Progress</h3>
            <p class="text-3xl font-bold text-blue-600">8</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Completed</h3>
            <p class="text-3xl font-bold text-green-600">12</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="text-sm font-medium text-gray-500 mb-2">Pending</h3>
            <p class="text-3xl font-bold text-yellow-600">4</p>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-900">Recent Tasks</h2>
            <a href="/app/tasks/create" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                + Create Task
            </a>
        </div>
        
        <div class="p-6">
            <div class="space-y-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">Design System Implementation</h3>
                    <div class="flex gap-4 text-sm text-gray-600">
                        <span>Project: Website Redesign</span>
                        <span>Due: Dec 15, 2024</span>
                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full">In Progress</span>
                    </div>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">Database Migration</h3>
                    <div class="flex gap-4 text-sm text-gray-600">
                        <span>Project: Backend Optimization</span>
                        <span>Due: Dec 20, 2024</span>
                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full">Pending</span>
                    </div>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">User Authentication</h3>
                    <div class="flex gap-4 text-sm text-gray-600">
                        <span>Project: Security Enhancement</span>
                        <span>Due: Dec 10, 2024</span>
                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full">Completed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection