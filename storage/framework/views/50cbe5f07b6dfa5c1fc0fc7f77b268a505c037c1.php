<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard Index - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-8">
        <!-- Header -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Admin Dashboard Index</h1>
                <p class="text-xl text-gray-600">Choose your dashboard view</p>
            </div>
        </div>

        <!-- Dashboard Options -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                
                <!-- Super Admin Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-crown text-yellow-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Super Admin Dashboard</h3>
                        <p class="text-gray-600">Full system overview and management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            System Health Monitoring
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            User & Tenant Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Storage & Performance Metrics
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            System Alerts & Activities
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/admin" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>Live View
                        </a>
                    </div>
                </div>

                <!-- Project Manager Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-user-tie text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Project Manager Dashboard</h3>
                        <p class="text-gray-600">Project oversight and team management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Project Progress Tracking
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Team Performance Metrics
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Budget & Timeline Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Resource Allocation
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/pm" class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Admin Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Admin Dashboard</h3>
                        <p class="text-gray-600">Tenant-level organization management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            User & Team Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Project & Task Overview
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Financial Metrics
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Organization Analytics
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Finance Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-chart-line text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Finance Dashboard</h3>
                        <p class="text-gray-600">Financial tracking and budget management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Budget Tracking
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Expense Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Revenue Analytics
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Financial Reports
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/finance" class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Designer Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-pink-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-palette text-pink-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Designer Dashboard</h3>
                        <p class="text-gray-600">Creative workflow and design management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Design Projects
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Asset Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Client Feedback
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Creative Tools
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/designer" class="flex-1 bg-pink-600 text-white px-4 py-2 rounded-lg hover:bg-pink-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Site Engineer Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-hard-hat text-orange-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Site Engineer Dashboard</h3>
                        <p class="text-gray-600">Construction site management and monitoring</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Site Monitoring
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Safety Compliance
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Progress Tracking
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Quality Control
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/site" class="flex-1 bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Sales Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-handshake text-red-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Sales Dashboard</h3>
                        <p class="text-gray-600">Sales pipeline and client relationship management</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Sales Pipeline
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Lead Management
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Client Relations
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Revenue Tracking
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/sales" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>

                <!-- Client Dashboard -->
                <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition-shadow">
                    <div class="text-center mb-6">
                        <div class="mx-auto w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-user-tie text-indigo-600 text-2xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-2">Client Dashboard</h3>
                        <p class="text-gray-600">Client portal and project visibility</p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Project Status
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Document Access
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Communication Hub
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            Progress Reports
                        </div>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="/dashboard/client" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors text-center">
                            <i class="fas fa-eye mr-2"></i>View Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back to Admin -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 text-center">
            <a href="/admin" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Super Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard-index.blade.php ENDPATH**/ ?>