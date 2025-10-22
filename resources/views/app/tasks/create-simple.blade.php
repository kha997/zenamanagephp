@extends('layouts.app')

@section('title', 'Create Task')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create New Task</h1>
                    <p class="text-sm text-gray-600">Fill in the details to create a new task</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('app.tasks.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Tasks
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow">
            <form action="{{ route('app.tasks.store-simple') }}" method="POST" class="p-6">
                @csrf
                
                <!-- Task Name -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Task Name *</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Enter task name">
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" 
                              name="description" 
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Enter task description"></textarea>
                </div>

                <!-- Project Selection -->
                <div class="mb-6">
                    <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">Project *</label>
                    <select id="project_id" 
                            name="project_id" 
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a project</option>
                        @if($projects->count() > 0)
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        @else
                            <option value="" disabled>No projects available</option>
                        @endif
                    </select>
                    @if($projects->count() == 0)
                        <p class="mt-1 text-sm text-red-600">No projects available. <a href="{{ route('app.projects.create') }}" class="text-blue-600 hover:underline">Create a project first</a></p>
                    @endif
                </div>

                <!-- Assignee -->
                <div class="mb-6">
                    <label for="assignee_id" class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                    <select id="assignee_id" 
                            name="assignee_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select an assignee</option>
                        @if($users->count() > 0)
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <!-- Priority -->
                <div class="mb-6">
                    <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select id="priority" 
                            name="priority"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" 
                            name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="pending" selected>Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- End Date -->
                <div class="mb-6">
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                    <input type="date" 
                           id="end_date" 
                           name="end_date"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('app.tasks.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
