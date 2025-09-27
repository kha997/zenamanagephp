<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Simple Admin Dashboard
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Simple Admin Dashboard Route (no middleware)
Route::get('/admin', function () {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <i class="fas fa-crown text-yellow-500 text-2xl mr-3"></i>
                        <h1 class="text-2xl font-bold text-gray-900">Super Admin Dashboard</h1>
                    </div>
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                            <i class="fas fa-circle text-green-500 mr-1"></i>
                            System Online
                        </span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">3</span>
                        </button>
                    </div>
                    <div class="flex items-center space-x-2 text-gray-700">
                        <img src="https://ui-avatars.com/api/?name=Admin+User&background=3b82f6&color=ffffff" 
                             alt="Admin User" class="h-8 w-8 rounded-full">
                        <span class="hidden md:block text-sm font-medium">Super Admin</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center space-x-8">
                    <a href="/admin" class="text-blue-600 font-medium border-b-2 border-blue-600 pb-2">
                        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                    </a>
                    <a href="/admin/users" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-users mr-2"></i>Users
                    </a>
                    <a href="/admin/tenants" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-building mr-2"></i>Tenants
                    </a>
                    <a href="/admin/projects" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-project-diagram mr-2"></i>Projects
                    </a>
                    <a href="/admin/security" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-shield-alt mr-2"></i>Security
                    </a>
                    <a href="/admin/settings" class="text-gray-600 hover:text-gray-900 font-medium">
                        <i class="fas fa-cog mr-2"></i>Settings
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- KPI Strip -->
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-sm font-medium">Total Users</p>
                            <p class="text-3xl font-bold">1,247</p>
                            <p class="text-blue-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                +12% from last month
                            </p>
                        </div>
                        <div class="bg-blue-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-sm font-medium">Active Tenants</p>
                            <p class="text-3xl font-bold">89</p>
                            <p class="text-green-100 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>
                                +5% from last month
                            </p>
                        </div>
                        <div class="bg-green-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-building text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-sm font-medium">System Health</p>
                            <p class="text-3xl font-bold">99.8%</p>
                            <p class="text-purple-100 text-sm">
                                <i class="fas fa-heartbeat mr-1"></i>
                                All systems operational
                            </p>
                        </div>
                        <div class="bg-purple-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-heartbeat text-2xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-orange-100 text-sm font-medium">Storage Usage</p>
                            <p class="text-3xl font-bold">67%</p>
                            <p class="text-orange-100 text-sm">
                                <i class="fas fa-database mr-1"></i>
                                2.1TB of 3.2TB used
                            </p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-30 rounded-full p-3">
                            <i class="fas fa-database text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">
                <!-- System Overview -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">System Overview</h2>
                        <div class="flex items-center space-x-2">
                            <select class="text-sm border border-gray-300 rounded-md px-3 py-1">
                                <option value="7d">Last 7 days</option>
                                <option value="30d">Last 30 days</option>
                                <option value="90d">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="h-64 bg-gray-50 rounded-lg flex items-center justify-center">
                        <div class="text-center">
                            <i class="fas fa-chart-line text-4xl text-gray-400 mb-4"></i>
                            <p class="text-gray-500">System metrics chart will be displayed here</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
                        <a href="/admin/activities" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View All
                        </a>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-blue-100">
                                    <i class="fas fa-user-plus text-blue-600"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">User Created</p>
                                <p class="text-sm text-gray-500">New user "Jane Smith" added to tenant "TechCorp"</p>
                                <p class="text-xs text-gray-400 mt-1">5 minutes ago</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-green-100">
                                    <i class="fas fa-building text-green-600"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">Tenant Updated</p>
                                <p class="text-sm text-gray-500">Tenant "ABC Corp" settings updated</p>
                                <p class="text-xs text-gray-400 mt-1">15 minutes ago</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-purple-100">
                                    <i class="fas fa-download text-purple-600"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">System Backup</p>
                                <p class="text-sm text-gray-500">Daily system backup completed</p>
                                <p class="text-xs text-gray-400 mt-1">1 hour ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-8">
                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
                    <div class="space-y-3">
                        <button class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>
                            Add User
                        </button>
                        <button class="w-full flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-building mr-2"></i>
                            Create Tenant
                        </button>
                        <button class="w-full flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>
                            Backup System
                        </button>
                        <button class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-cog mr-2"></i>
                            System Settings
                        </button>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">System Status</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm font-medium text-gray-900">Database</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Online</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm font-medium text-gray-900">Cache</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Online</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm font-medium text-gray-900">Queue</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Online</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm font-medium text-gray-900">Storage</span>
                            </div>
                            <span class="text-sm font-medium text-green-600">Online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="text-center text-gray-500 text-sm">
                <p>&copy; 2025 ZenaManage. Super Admin Dashboard - System Management Interface</p>
            </div>
        </div>
    </footer>
</body>
</html>';
})->name('admin.dashboard');
