<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Super Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
                            <p class="text-gray-600 mt-1">Configure system-wide settings and preferences</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Settings Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- General Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-cog text-blue-600 text-2xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">General Settings</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Site Name</span>
                            <input type="text" value="ZenaManage" class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Default Language</span>
                            <select class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option>English</option>
                                <option>Vietnamese</option>
                                <option>Chinese</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Timezone</span>
                            <select class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option>UTC+7 (Ho Chi Minh)</option>
                                <option>UTC+0 (London)</option>
                                <option>UTC-5 (New York)</option>
                            </select>
                        </div>
                        <button class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            Save Changes
                        </button>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-shield-alt text-red-600 text-2xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Security Settings</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Password Policy</span>
                            <select class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option>Strong (8+ chars)</option>
                                <option>Medium (6+ chars)</option>
                                <option>Weak (4+ chars)</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Session Timeout</span>
                            <select class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option>30 minutes</option>
                                <option>1 hour</option>
                                <option>2 hours</option>
                                <option>Never</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Two-Factor Auth</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <button class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                            Update Security
                        </button>
                    </div>
                </div>

                <!-- Notification Settings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-bell text-yellow-600 text-2xl mr-3"></i>
                        <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Email Notifications</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">SMS Notifications</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Push Notifications</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <button class="w-full bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700">
                            Save Preferences
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Advanced Settings</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Database Settings -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3">Database Configuration</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Connection Pool Size</span>
                                    <input type="number" value="10" class="px-3 py-1 border border-gray-300 rounded-md text-sm w-20">
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Query Timeout</span>
                                    <input type="number" value="30" class="px-3 py-1 border border-gray-300 rounded-md text-sm w-20">
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Auto Backup</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" checked class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Cache Settings -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-3">Cache Configuration</h4>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Cache Driver</span>
                                    <select class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                        <option>Redis</option>
                                        <option>Memcached</option>
                                        <option>File</option>
                                    </select>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Cache TTL</span>
                                    <input type="number" value="3600" class="px-3 py-1 border border-gray-300 rounded-md text-sm w-20">
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Enable Cache</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" checked class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Actions -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">System Actions</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <button class="flex items-center justify-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-sync-alt text-blue-600"></i>
                            <span>Clear Cache</span>
                        </button>
                        <button class="flex items-center justify-center space-x-2 p-4 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            <i class="fas fa-database text-green-600"></i>
                            <span>Optimize Database</span>
                        </button>
                        <button class="flex items-center justify-center space-x-2 p-4 border border-red-300 rounded-lg hover:bg-red-50 transition-colors text-red-600">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Reset System</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
