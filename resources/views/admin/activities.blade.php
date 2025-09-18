<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recent Activities - Super Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">Recent Activities</h1>
                            <p class="text-gray-600 mt-1">System activity log and audit trail</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Activity Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Today's Activities</p>
                            <p class="text-3xl font-bold text-blue-600">1,245</p>
                        </div>
                        <i class="fas fa-chart-line text-blue-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">User Logins</p>
                            <p class="text-3xl font-bold text-green-600">89</p>
                        </div>
                        <i class="fas fa-sign-in-alt text-green-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Data Changes</p>
                            <p class="text-3xl font-bold text-purple-600">156</p>
                        </div>
                        <i class="fas fa-edit text-purple-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">System Events</p>
                            <p class="text-3xl font-bold text-orange-600">23</p>
                        </div>
                        <i class="fas fa-cogs text-orange-600 text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- Activity Filters -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Filter Activities</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                            <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option>All Types</option>
                                <option>User Login</option>
                                <option>Data Change</option>
                                <option>System Event</option>
                                <option>Security Event</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">User</label>
                            <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option>All Users</option>
                                <option>John Doe</option>
                                <option>Jane Smith</option>
                                <option>System Admin</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                            <select class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option>Last 24 hours</option>
                                <option>Last 7 days</option>
                                <option>Last 30 days</option>
                                <option>Custom range</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Activities Table -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Activity Log</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-sign-in-alt text-green-600 mr-3"></i>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">User login successful</div>
                                            <div class="text-sm text-gray-500">Dashboard access granted</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center">
                                                <span class="text-white text-xs font-medium">JD</span>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">John Doe</div>
                                            <div class="text-sm text-gray-500">Project Manager</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Authentication
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.100</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2 minutes ago</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-blue-600 hover:text-blue-900">View Details</button>
                                </td>
                            </tr>
                            
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <i class="fas fa-edit text-purple-600 mr-3"></i>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">Project updated</div>
                                            <div class="text-sm text-gray-500">Office Building Construction - Status changed</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-purple-500 flex items-center justify-center">
                                                <span class="text-white text-xs font-medium">JS</span>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">Jane Smith</div>
                                            <div class="text-sm text-gray-500">Designer</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                        Data Change
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">192.168.1.101</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">15 minutes ago</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="text-blue-600 hover:text-blue-900">View Details</button>
                                </td>
                            </tr>
                            <!-- More rows would go here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
