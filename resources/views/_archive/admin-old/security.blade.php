<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Audit - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center gap-4">
                        <a href="/admin/super-admin-dashboard" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Security Audit</h1>
                            <p class="text-gray-600 mt-1">System security monitoring and audit</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Security Status -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Security Score</p>
                            <p class="text-3xl font-bold text-green-600">95%</p>
                        </div>
                        <i class="fas fa-shield-alt text-green-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Active Threats</p>
                            <p class="text-3xl font-bold text-red-600">2</p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-red-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Failed Logins</p>
                            <p class="text-3xl font-bold text-orange-600">12</p>
                        </div>
                        <i class="fas fa-lock text-orange-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Last Scan</p>
                            <p class="text-3xl font-bold text-blue-600">2h</p>
                        </div>
                        <i class="fas fa-search text-blue-600 text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- Security Alerts -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Security Alerts</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                                <div>
                                    <p class="font-medium text-red-800">Multiple failed login attempts detected</p>
                                    <p class="text-sm text-red-600">IP: 192.168.1.100 - 15 attempts in last hour</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">High</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-yellow-600 mr-3"></i>
                                <div>
                                    <p class="font-medium text-yellow-800">SSL Certificate expires in 30 days</p>
                                    <p class="text-sm text-yellow-600">Domain: zenamanage.com</p>
                                </div>
                            </div>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">Medium</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <button class="w-full text-left p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-search text-blue-600 mr-3"></i>
                                Run Security Scan
                            </button>
                            <button class="w-full text-left p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-key text-green-600 mr-3"></i>
                                Reset User Passwords
                            </button>
                            <button class="w-full text-left p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-ban text-red-600 mr-3"></i>
                                Block Suspicious IPs
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <i class="fas fa-user-shield text-blue-600 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Admin login from new device</p>
                                    <p class="text-xs text-gray-500">2 hours ago</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-lock text-green-600 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Password policy updated</p>
                                    <p class="text-xs text-gray-500">1 day ago</p>
                                </div>
                            </div>
                            <div class="flex items-center">
                                <i class="fas fa-shield-alt text-purple-600 mr-3"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Security scan completed</p>
                                    <p class="text-xs text-gray-500">2 days ago</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
