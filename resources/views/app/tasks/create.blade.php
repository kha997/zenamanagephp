@extends('layouts.app')

@section('title', 'Create Task - ZenaManage')

@section('kpi-strip')
<!-- KPI Strip -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Tasks -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Total Tasks</p>
                        <p class="text-3xl font-bold">{{ $projects->count() }}</p>
                        <p class="text-sm opacity-90">Available projects</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tasks text-2xl"></i>
                    </div>
                </div>
                <button class="mt-4 w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    View All Tasks
                </button>
            </div>

            <!-- Active Tasks -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Active Tasks</p>
                        <p class="text-3xl font-bold">0</p>
                        <p class="text-sm opacity-90">In progress</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-play-circle text-2xl"></i>
                    </div>
                </div>
                <button class="mt-4 w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    Manage Active
                </button>
            </div>

            <!-- Completed -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Completed</p>
                        <p class="text-3xl font-bold">0</p>
                        <p class="text-sm opacity-90">Success!</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                </div>
                <button class="mt-4 w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    View Completed
                </button>
            </div>

            <!-- Overdue -->
            <div class="bg-gradient-to-r from-red-500 to-red-600 rounded-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium opacity-90">Overdue</p>
                        <p class="text-3xl font-bold">0</p>
                        <p class="text-sm opacity-90">All on time</p>
                    </div>
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-2xl"></i>
                    </div>
                </div>
                <button class="mt-4 w-full bg-white bg-opacity-20 hover:bg-opacity-30 text-white font-medium py-2 px-4 rounded-md transition duration-200">
                    View Overdue
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="bg-gray-50">
    <!-- Page Header -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Create New Task</h1>
                <p class="mt-2 text-gray-600">Add a new task to your project</p>
            </div>
            <div class="flex space-x-3">
                <a href="/app/tasks" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tasks
                </a>
            </div>
        </div>
    </div>

    <!-- Create Task Form -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <form method="POST" action="/app/tasks" class="space-y-6">
                @csrf
                
                <!-- Task Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Task Title</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="Enter task title">
                </div>

                <!-- Task Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" 
                              name="description" 
                              rows="4"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                              placeholder="Enter task description"></textarea>
                </div>

                <!-- Project Selection -->
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                    <!-- Debug: Projects count: {{ $projects->count() }} -->
                    <!-- Debug: Users count: {{ $users->count() }} -->
                    <!-- Debug: API Response: {{ json_encode($debug_info ?? []) }} -->
                    <select id="project_id" 
                            name="project_id" 
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select a project</option>
                        @if($projects->count() > 0)
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        @else
                            <option value="" disabled>No projects available</option>
                        @endif
                    </select>
                    <!-- Debug: Projects data: {{ $projects->toJson() }} -->
                </div>

                <!-- Priority -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select id="priority" 
                            name="priority" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" 
                            name="status" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="pending" selected>Pending</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Due Date -->
                <div>
                    <label for="due_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                    <input type="date" 
                           id="due_date" 
                           name="due_date"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Assignee -->
                <div>
                    <label for="assignee_id" class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                    <select id="assignee_id" 
                            name="assignee_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Unassigned</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="/app/tasks" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg transition duration-200">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-plus mr-2"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('activity')
<!-- Activity/History Section -->
<div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Task Activity</h3>
    <div class="space-y-4">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-tasks text-blue-600"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">New task creation form opened.</p>
                <p class="text-xs text-gray-500">Just now</p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-green-600"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">User accessed task creation.</p>
                <p class="text-xs text-gray-500">1 minute ago</p>
            </div>
        </div>
    </div>
</div>
@endsection