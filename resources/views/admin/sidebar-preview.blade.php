<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Sidebar - {{ ucwords(str_replace('_', ' ', $role)) }} - ZENA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar-item {
            transition: all 0.2s ease;
        }
        .sidebar-item:hover {
            background-color: #f3f4f6;
        }
        .sidebar-group {
            transition: all 0.2s ease;
        }
        .sidebar-group:hover {
            background-color: #f9fafb;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar Preview -->
        <div class="w-64 bg-white shadow-lg">
            <!-- Sidebar Header -->
            <div class="p-6 border-b">
                <div class="flex items-center">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-building text-white text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">ZENA</h1>
                        <p class="text-xs text-gray-500">Project Management</p>
                    </div>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="p-4 space-y-2">
                @if(isset($configData['items']))
                    @foreach($configData['items'] as $item)
                        @if(($item['enabled'] ?? true))
                            @if($item['type'] === 'group')
                                <div class="sidebar-group">
                                    <div class="flex items-center justify-between py-2 px-3 text-sm font-medium text-gray-700">
                                        <span>{{ $item['label'] }}</span>
                                        <i class="fas fa-chevron-down text-xs"></i>
                                    </div>
                                    
                                    @if(isset($item['children']))
                                        <div class="ml-4 space-y-1">
                                            @foreach($item['children'] as $child)
                                                @if(($child['enabled'] ?? true))
                                                    <a href="#" class="sidebar-item flex items-center py-2 px-3 text-sm text-gray-600 rounded-md hover:bg-gray-100">
                                                        @if(isset($child['icon']))
                                                            <i class="fas fa-{{ $child['icon'] }} mr-3 text-gray-400"></i>
                                                        @endif
                                                        <span>{{ $child['label'] }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @elseif($item['type'] === 'link')
                                <a href="#" class="sidebar-item flex items-center py-2 px-3 text-sm text-gray-600 rounded-md hover:bg-gray-100">
                                    @if(isset($item['icon']))
                                        <i class="fas fa-{{ $item['icon'] }} mr-3 text-gray-400"></i>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @elseif($item['type'] === 'external')
                                <a href="#" class="sidebar-item flex items-center py-2 px-3 text-sm text-gray-600 rounded-md hover:bg-gray-100">
                                    @if(isset($item['icon']))
                                        <i class="fas fa-{{ $item['icon'] }} mr-3 text-gray-400"></i>
                                    @endif
                                    <span>{{ $item['label'] }}</span>
                                    <i class="fas fa-external-link-alt ml-auto text-xs text-gray-400"></i>
                                </a>
                            @elseif($item['type'] === 'divider')
                                <hr class="my-2 border-gray-200">
                            @endif
                        @endif
                    @endforeach
                @endif
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b">
                <div class="px-6 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <a href="{{ route('admin.sidebar-builder.edit', $role) }}" class="text-gray-500 hover:text-gray-700 mr-4">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <h1 class="text-2xl font-bold text-gray-900">
                                Sidebar Preview - {{ ucwords(str_replace('_', ' ', $role)) }}
                            </h1>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-500">Preview Mode</span>
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-eye text-white text-sm"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Preview Content -->
            <main class="p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- Preview Info -->
                    <div class="bg-blue-50 rounded-lg p-6 mb-8">
                        <h2 class="text-lg font-semibold text-blue-900 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            Sidebar Preview Information
                        </h2>
                        <div class="text-blue-800 space-y-2">
                            <p><strong>Role:</strong> {{ ucwords(str_replace('_', ' ', $role)) }}</p>
                            <p><strong>Status:</strong> This is how the sidebar will appear for users with this role</p>
                            <p><strong>Note:</strong> Only enabled items are shown in the preview</p>
                        </div>
                    </div>

                    <!-- Sidebar Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-list text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Items</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ isset($configData['items']) ? count($configData['items']) : 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-check-circle text-green-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Enabled Items</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ isset($configData['items']) ? count(array_filter($configData['items'], fn($item) => $item['enabled'] ?? true)) : 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-sm border p-6">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-layer-group text-purple-600"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Groups</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{ isset($configData['items']) ? count(array_filter($configData['items'], fn($item) => $item['type'] === 'group')) : 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Item Details -->
                    <div class="bg-white rounded-lg shadow-sm border">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold text-gray-900">Sidebar Item Details</h2>
                        </div>
                        <div class="p-6">
                            @if(isset($configData['items']))
                                <div class="space-y-4">
                                    @foreach($configData['items'] as $index => $item)
                                        <div class="border rounded-lg p-4 {{ ($item['enabled'] ?? true) ? 'bg-gray-50' : 'bg-red-50' }}">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <span class="text-sm font-medium text-gray-500">#{{ $index + 1 }}</span>
                                                    @if(isset($item['icon']))
                                                        <i class="fas fa-{{ $item['icon'] }} text-gray-400"></i>
                                                    @endif
                                                    <span class="font-medium">{{ $item['label'] }}</span>
                                                    <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">
                                                        {{ $item['type'] }}
                                                    </span>
                                                    @if(!($item['enabled'] ?? true))
                                                        <span class="text-xs bg-red-200 text-red-600 px-2 py-1 rounded">
                                                            Disabled
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Order: {{ $item['order'] ?? ($index + 1) * 10 }}
                                                </div>
                                            </div>
                                            
                                            @if($item['type'] === 'group' && isset($item['children']))
                                                <div class="mt-3 ml-8 space-y-2">
                                                    @foreach($item['children'] as $childIndex => $child)
                                                        <div class="flex items-center justify-between bg-white rounded p-2 border">
                                                            <div class="flex items-center space-x-2">
                                                                @if(isset($child['icon']))
                                                                    <i class="fas fa-{{ $child['icon'] }} text-gray-400"></i>
                                                                @endif
                                                                <span class="text-sm">{{ $child['label'] }}</span>
                                                                @if(!($child['enabled'] ?? true))
                                                                    <span class="text-xs bg-red-200 text-red-600 px-1 py-0.5 rounded">
                                                                        Disabled
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <span class="text-xs text-gray-500">
                                                                Order: {{ $child['order'] ?? ($childIndex + 1) * 10 }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500 text-center py-8">No sidebar items configured</p>
                            @endif
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
