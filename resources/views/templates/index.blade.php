<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Templates - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-bold text-sm">Z</span>
                        </div>
                        <h1 class="text-xl font-bold text-blue-600">ZenaManage</h1>
                    </div>
                </div>
                
                <nav class="flex space-x-8">
                    <a href="/app/dashboard" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                    <a href="/app/tasks" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Tasks</a>
                    <a href="/app/projects" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Projects</a>
                    <a href="/app/documents" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Documents</a>
                    <a href="/app/team" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Team</a>
                    <a href="/app/templates" class="text-blue-600 bg-blue-50 px-3 py-2 rounded-md text-sm font-medium">Templates</a>
                    <a href="/admin" class="text-gray-600 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">Admin</a>
                </nav>
                
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-sm font-bold">PT</div>
                        <span class="text-sm font-medium text-gray-700">Template Manager</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Project Templates</h1>
            <p class="text-gray-600">Manage and apply project templates for efficient project creation</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex space-x-4">
                <button class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Create Template
                </button>
                <button class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                    <i class="fas fa-sync mr-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Templates Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Project Templates</h2>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm font-medium">All Templates</a>
                        <button class="px-3 py-1 text-gray-600 hover:bg-gray-100 rounded-md text-sm font-medium">Recent</button>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Template Form -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Template</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Template Name</label>
                                <input type="text" placeholder="Enter template name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option>Select category</option>
                                    <option>Construction</option>
                                    <option>Design</option>
                                    <option>Development</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea placeholder="Enter template description" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Design Phases</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Phase 1: Planning</span>
                                <button class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Phase 2: Design</span>
                                <button class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <span class="text-sm font-medium text-gray-700">Phase 3: Development</span>
                                <button class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <button class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-gray-600 hover:border-gray-400 hover:text-gray-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Phase
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Templates List -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Available Templates</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="openApplyModal()">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Construction Template</h4>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">Complete construction project template with all phases</p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">5 phases</span>
                                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Apply Template</button>
                            </div>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="openApplyModal()">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Design Template</h4>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Draft</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">Design-focused project template for creative projects</p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">3 phases</span>
                                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Apply Template</button>
                            </div>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="openApplyModal()">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Development Template</h4>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                            </div>
                            <p class="text-sm text-gray-600 mb-3">Software development project template</p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">4 phases</span>
                                <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">Apply Template</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Template Modal -->
    <div id="applyModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" onclick="closeApplyModal(event)">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Apply Template</h3>
                </div>
                
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Phases to Apply:</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Phase 1: Planning</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Phase 2: Design</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Phase 3: Development</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Name</label>
                        <input type="text" placeholder="Enter project name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button onclick="closeApplyModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Apply Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openApplyModal() {
            document.getElementById('applyModal').classList.remove('hidden');
        }
        
        function closeApplyModal(event) {
            if (event && event.target !== event.currentTarget) return;
            document.getElementById('applyModal').classList.add('hidden');
        }
    </script>
</body>
</html>