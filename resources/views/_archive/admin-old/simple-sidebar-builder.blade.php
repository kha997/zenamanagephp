<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sidebar Builder - ZENA Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Sidebar Builder</h1>
                        <p class="mt-2 text-sm text-gray-600">Customize sidebar configurations for different roles</p>
                    </div>
                    <div class="flex space-x-4">
                        <a href="/dashboard" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Available Roles</h2>
                    <p class="mt-1 text-sm text-gray-600">Select a role to customize its sidebar configuration</p>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($roles as $role)
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 capitalize">
                                            {{ str_replace('_', ' ', $role) }}
                                        </h3>
                                        <p class="text-sm text-gray-600 mt-1">
                                            @if(isset($groupedConfigs[$role]))
                                                {{ count($groupedConfigs[$role]) }} configuration(s)
                                            @else
                                                No configuration
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex space-x-2">
                                        <a href="/admin/sidebar-builder/{{ $role }}" 
                                           class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        <a href="/admin/sidebar-builder/{{ $role }}/preview" 
                                           class="bg-gray-600 text-white px-3 py-1 rounded text-sm hover:bg-gray-700">
                                            <i class="fas fa-eye mr-1"></i>Preview
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
