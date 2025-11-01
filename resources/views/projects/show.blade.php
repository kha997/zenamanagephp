<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - {{ $project->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="{{ route('projects.index') }}" class="text-gray-500 hover:text-gray-700">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $project->name }}</h1>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            @if($project->status === 'active') bg-green-100 text-green-800
                            @elseif($project->status === 'draft') bg-yellow-100 text-yellow-800
                            @elseif($project->status === 'completed') bg-blue-100 text-blue-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst($project->status) }}
                        </span>
                    </div>
                    <div class="flex space-x-2">
                        <a href="{{ route('projects.edit', $project) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-edit mr-2"></i>Edit Project
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Project Info -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Info -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Project Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Project Code</label>
                                <p class="text-gray-900">{{ $project->code }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Status</label>
                                <p class="text-gray-900">{{ ucfirst($project->status) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Start Date</label>
                                <p class="text-gray-900">{{ $project->start_date ? $project->start_date->format('M d, Y') : 'Not set' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">End Date</label>
                                <p class="text-gray-900">{{ $project->end_date ? $project->end_date->format('M d, Y') : 'Not set' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Budget</label>
                                <p class="text-gray-900">${{ number_format($project->budget_total, 2) }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Progress</label>
                                <p class="text-gray-900">{{ $project->progress }}%</p>
                            </div>
                        </div>
                        
                        @if($project->description)
                        <div class="mt-4">
                            <label class="text-sm font-medium text-gray-500">Description</label>
                            <p class="text-gray-900 mt-1">{{ $project->description }}</p>
                        </div>
                        @endif
                    </div>

                    <!-- Team Members -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Team Members</h2>
                        <div class="space-y-3">
                            @if($project->pm)
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    {{ substr($project->pm->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $project->pm->name }}</p>
                                    <p class="text-sm text-gray-500">Project Manager</p>
                                </div>
                            </div>
                            @endif
                            
                            @if($project->client)
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    {{ substr($project->client->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $project->client->name }}</p>
                                    <p class="text-sm text-gray-500">Client</p>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Tasks -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-lg font-semibold text-gray-900">Tasks</h2>
                            <a href="{{ route('tasks.create') }}?project_id={{ $project->id }}" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                <i class="fas fa-plus mr-1"></i>Add Task
                            </a>
                        </div>
                        
                        @if($project->tasks && $project->tasks->count() > 0)
                        <div class="space-y-3">
                            @foreach($project->tasks as $task)
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="font-medium text-gray-900">{{ $task->name }}</h3>
                                        <p class="text-sm text-gray-500">{{ $task->description }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        @if($task->status === 'done') bg-green-100 text-green-800
                                        @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($task->status) }}
                                    </span>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="text-gray-500 text-center py-8">No tasks yet. <a href="{{ route('tasks.create') }}?project_id={{ $project->id }}" class="text-blue-600 hover:text-blue-800">Create the first task</a></p>
                        @endif
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Progress -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Progress</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Overall Progress</span>
                                    <span class="text-gray-900">{{ $project->progress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="{{ route('tasks.create') }}?project_id={{ $project->id }}" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center justify-center">
                                <i class="fas fa-plus mr-2"></i>Add Task
                            </a>
                            <a href="{{ route('documents.create') }}?project_id={{ $project->id }}" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center justify-center">
                                <i class="fas fa-upload mr-2"></i>Upload Document
                            </a>
                            <a href="{{ route('projects.edit', $project) }}" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center justify-center">
                                <i class="fas fa-edit mr-2"></i>Edit Project
                            </a>
                        </div>
                    </div>

                    <!-- Project Stats -->
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Tasks</span>
                                <span class="font-semibold text-gray-900">{{ $project->tasks ? $project->tasks->count() : 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Completed</span>
                                <span class="font-semibold text-green-600">{{ $project->tasks ? $project->tasks->where('status', 'done')->count() : 0 }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">In Progress</span>
                                <span class="font-semibold text-blue-600">{{ $project->tasks ? $project->tasks->where('status', 'in_progress')->count() : 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>