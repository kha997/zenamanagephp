<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Minimal Admin Dashboard
|--------------------------------------------------------------------------
|
| Simple admin dashboard route without any middleware or dependencies
|
*/

// Minimal Admin Dashboard Route
Route::get('/admin', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Super Admin Dashboard is working!',
        'data' => [
            'dashboard' => 'ZenaManage Super Admin Dashboard',
            'version' => '1.0.0',
            'timestamp' => now()->toISOString(),
            'features' => [
                'User Management',
                'Tenant Management', 
                'System Monitoring',
                'Security Center',
                'Activity Logs'
            ]
        ]
    ]);
})->name('admin.dashboard');

// HTML Admin Dashboard Route
Route::get('/admin-html', function () {
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
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-8">
                    <i class="fas fa-crown text-yellow-500 text-6xl mb-4"></i>
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">Super Admin Dashboard</h1>
                    <p class="text-xl text-gray-600">ZenaManage System Management Interface</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-blue-50 rounded-lg p-6 text-center">
                        <i class="fas fa-users text-blue-500 text-3xl mb-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">User Management</h3>
                        <p class="text-sm text-gray-600">Manage system users</p>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-6 text-center">
                        <i class="fas fa-building text-green-500 text-3xl mb-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Tenant Management</h3>
                        <p class="text-sm text-gray-600">Manage organizations</p>
                    </div>
                    
                    <div class="bg-purple-50 rounded-lg p-6 text-center">
                        <i class="fas fa-shield-alt text-purple-500 text-3xl mb-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Security Center</h3>
                        <p class="text-sm text-gray-600">Monitor security</p>
                    </div>
                    
                    <div class="bg-orange-50 rounded-lg p-6 text-center">
                        <i class="fas fa-chart-line text-orange-500 text-3xl mb-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Analytics</h3>
                        <p class="text-sm text-gray-600">System analytics</p>
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 rounded-full">
                        <i class="fas fa-check-circle mr-2"></i>
                        Dashboard is working correctly!
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
})->name('admin.html');
