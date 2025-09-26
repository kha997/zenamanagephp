@extends('layouts.app-layout')

@section('title', 'Dashboard - ZenaManage')

@section('content')
<div x-data="dashboardComponent()" x-init="init()" class="min-h-screen bg-gray-50">
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-gray-600 mt-1">Welcome back, John Doe! Here's what's happening with your projects.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>New Project
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Projects</p>
                        <p class="text-2xl font-bold text-gray-900">12</p>
                        <p class="text-xs text-green-600">+2 from last month</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Completed Tasks</p>
                        <p class="text-2xl font-bold text-gray-900">48</p>
                        <p class="text-xs text-green-600">+15 this week</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Team Members</p>
                        <p class="text-2xl font-bold text-gray-900">24</p>
                        <p class="text-xs text-gray-500">No change</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Hours Logged</p>
                        <p class="text-2xl font-bold text-gray-900">156</p>
                        <p class="text-xs text-green-600">+8 this week</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Projects -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900">Recent Projects</h2>
                <a href="/app/projects" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All</a>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-project-diagram text-blue-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Website Redesign</h3>
                            <p class="text-xs text-gray-500">Due in 3 days</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                        <span class="text-xs text-gray-500">75%</span>
                    </div>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-mobile-alt text-green-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Mobile App Development</h3>
                            <p class="text-xs text-gray-500">Due in 1 week</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-200 rounded-full h-2">
                            <div class="bg-green-500 h-2 rounded-full" style="width: 45%"></div>
                        </div>
                        <span class="text-xs text-gray-500">45%</span>
                    </div>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-4">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-database text-purple-600"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Database Migration</h3>
                            <p class="text-xs text-gray-500">Due in 2 weeks</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-20 bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-500 h-2 rounded-full" style="width: 20%"></div>
                        </div>
                        <span class="text-xs text-gray-500">20%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function dashboardComponent() {
    return {
        init() {
            console.log('Dashboard component initialized');
        }
    }
}
</script>
@endsection