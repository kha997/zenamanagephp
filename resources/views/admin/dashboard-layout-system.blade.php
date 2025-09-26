{{-- Admin Dashboard sử dụng Layout System --}}
@extends('layouts.app')

@section('title', 'Admin Dashboard - ZenaManage')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', 'Welcome back, Administrator')
@section('header-icon', 'fas fa-crown')

@section('header-actions')
<div class="flex items-center gap-4">
    <div class="flex items-center gap-2 bg-green-100 px-4 py-2 rounded-full">
        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
        <span class="text-green-800 text-sm font-medium">System Online</span>
    </div>
</div>
@endsection

@section('navigation')
<a href="/admin" class="nav-link active">
    <i class="fas fa-tachometer-alt"></i>
    <span>Dashboard</span>
</a>
<a href="/admin/users" class="nav-link">
    <i class="fas fa-users"></i>
    <span>Users</span>
</a>
<a href="/admin/tenants" class="nav-link">
    <i class="fas fa-building"></i>
    <span>Tenants</span>
</a>
<a href="/admin/projects" class="nav-link">
    <i class="fas fa-project-diagram"></i>
    <span>Projects</span>
</a>
<a href="/admin/analytics" class="nav-link">
    <i class="fas fa-chart-bar"></i>
    <span>Analytics</span>
</a>
<a href="/admin/security" class="nav-link">
    <i class="fas fa-shield-alt"></i>
    <span>Security</span>
</a>
<a href="/admin/settings" class="nav-link">
    <i class="fas fa-cog"></i>
    <span>Settings</span>
</a>
@endsection

@section('nav-actions')
<div class="flex items-center gap-4">
    <div class="relative">
        <input type="text" placeholder="Search..." class="input w-64 pl-10">
        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
    </div>
</div>
@endsection

@section('kpi-strip')
<div class="kpi-card blue">
    <div class="kpi-header">
        <div>
            <div class="kpi-title">Total Users</div>
            <div class="kpi-value">1,247</div>
            <div class="kpi-change">
                <i class="fas fa-arrow-up"></i>
                <span>+12%</span>
                <span style="opacity: 0.7; margin-left: 8px;">from last month</span>
            </div>
        </div>
        <div class="kpi-icon">
            <i class="fas fa-users"></i>
        </div>
    </div>
</div>

<div class="kpi-card green">
    <div class="kpi-header">
        <div>
            <div class="kpi-title">Active Tenants</div>
            <div class="kpi-value">89</div>
            <div class="kpi-change">
                <i class="fas fa-arrow-up"></i>
                <span>+5%</span>
                <span style="opacity: 0.7; margin-left: 8px;">from last month</span>
            </div>
        </div>
        <div class="kpi-icon">
            <i class="fas fa-building"></i>
        </div>
    </div>
</div>

<div class="kpi-card purple">
    <div class="kpi-header">
        <div>
            <div class="kpi-title">System Health</div>
            <div class="kpi-value">99.8%</div>
            <div class="kpi-change">
                <i class="fas fa-heartbeat"></i>
                <span>All systems operational</span>
            </div>
        </div>
        <div class="kpi-icon">
            <i class="fas fa-heartbeat"></i>
        </div>
    </div>
</div>

<div class="kpi-card orange">
    <div class="kpi-header">
        <div>
            <div class="kpi-title">Storage Usage</div>
            <div class="kpi-value">67%</div>
            <div class="kpi-change">
                <i class="fas fa-database"></i>
                <span>2.1TB of 3.2TB used</span>
            </div>
        </div>
        <div class="kpi-icon">
            <i class="fas fa-database"></i>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column -->
    <div class="lg:col-span-2">
        <!-- System Overview Chart -->
        <div class="card mb-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">System Overview</h2>
            <div class="h-80 bg-gray-50 rounded-xl flex items-center justify-center">
                <div class="text-center">
                    <i class="fas fa-chart-line text-4xl text-gray-400 mb-4"></i>
                    <div class="text-gray-600">System Performance Chart</div>
                    <div class="text-sm text-gray-500 mt-2">Chart.js integration coming soon</div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Activity</h2>
            <div class="space-y-6">
                <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-white">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">User Created</h3>
                        <p class="text-gray-600 text-sm">New user "Jane Smith" added to tenant "TechCorp"</p>
                        <p class="text-gray-400 text-xs mt-1">5 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-white">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">Tenant Updated</h3>
                        <p class="text-gray-600 text-sm">Tenant "ABC Corp" settings updated</p>
                        <p class="text-gray-400 text-xs mt-1">15 minutes ago</p>
                    </div>
                </div>
                <div class="flex items-start gap-4 p-4 bg-gray-50 rounded-xl">
                    <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center text-white">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-800">System Backup</h3>
                        <p class="text-gray-600 text-sm">Daily system backup completed</p>
                        <p class="text-gray-400 text-xs mt-1">1 hour ago</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Quick Actions -->
        <div class="card mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
            <div class="space-y-4">
                <button class="btn btn-primary w-full">
                    <i class="fas fa-user-plus"></i>
                    <span>Add User</span>
                </button>
                <button class="btn btn-secondary w-full">
                    <i class="fas fa-building"></i>
                    <span>Create Tenant</span>
                </button>
                <button class="btn btn-accent w-full">
                    <i class="fas fa-download"></i>
                    <span>Backup System</span>
                </button>
                <button class="btn w-full bg-gray-600 hover:bg-gray-700 text-white">
                    <i class="fas fa-cog"></i>
                    <span>System Settings</span>
                </button>
            </div>
        </div>

        <!-- System Status -->
        <div class="card">
            <h2 class="text-xl font-bold text-gray-800 mb-6">System Status</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium text-gray-800">Database</span>
                    </div>
                    <span class="text-green-600 font-medium">online</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium text-gray-800">Cache</span>
                    </div>
                    <span class="text-green-600 font-medium">online</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium text-gray-800">Queue</span>
                    </div>
                    <span class="text-green-600 font-medium">online</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium text-gray-800">Storage</span>
                    </div>
                    <span class="text-green-600 font-medium">online</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium text-gray-800">Email</span>
                    </div>
                    <span class="text-green-600 font-medium">online</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
