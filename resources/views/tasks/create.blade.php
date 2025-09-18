@extends('layouts.dashboard')

@section('title', 'Create Task')
@section('page-title', 'Create Task')
@section('page-description', 'Create and assign tasks to team members')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="taskCreate()">
    <!-- Task Creation Form -->
    <div class="dashboard-card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-6">📝 Create New Task</h3>
        
        <!-- Display validation errors -->
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <h4 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                <ul class="text-sm text-red-700 list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <form method="POST" action="/tasks" >
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Task Basic Info -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Task Information</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task Title *</label>
                            <input 
                                type="text" 
                                name="name" 
                                required 
                                value="{{ old('name') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter task title"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Task Description</label>
                            <textarea 
                                name="description" 
                                rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Describe the task..."
                            >{{ old('description') }}</textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                            <select 
                                name="project_id" 
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select Project</option>
                                @php
                                    $projects = \Src\CoreProject\Models\Project::all();
                                @endphp
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id') == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <!-- Task Details -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Task Details</h4>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select 
                                    name="priority" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                    <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select 
                                    name="status" 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="pending" {{ old('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date *</label>
                                <input 
                                    type="date" 
                                    name="start_date" 
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                                <input 
                                    type="date" 
                                    name="end_date" 
                                    required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                >
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Estimated Hours</label>
                            <input 
                                type="number" 
                                name="estimated_hours" 
                                min="0" 
                                step="0.5"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter estimated hours"
                            >
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Assignment -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Assignment</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                            <select 
                                name="assignee_id" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">Select Assignee</option>
                                <option value="1">John Smith (Project Manager)</option>
                                <option value="2">Sarah Wilson (Designer)</option>
                                <option value="3">Mike Johnson (Developer)</option>
                                <option value="4">Alex Lee (Site Engineer)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Watchers</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" name="watchers[]" value="1" class="mr-2">
                                    <span class="text-sm">John Smith</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="watchers[]" value="2" class="mr-2">
                                    <span class="text-sm">Sarah Wilson</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="watchers[]" value="3" class="mr-2">
                                    <span class="text-sm">Mike Johnson</span>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="watchers[]" value="4" class="mr-2">
                                    <span class="text-sm">Alex Lee</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task Settings -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Task Settings</h4>
                        
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" name="notifications" value="1" class="mr-2" checked>
                                <span class="text-sm">Enable notifications</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="time_tracking" value="1" class="mr-2" checked>
                                <span class="text-sm">Enable time tracking</span>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="subtasks" value="1" class="mr-2">
                                <span class="text-sm">Allow subtasks</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Task Tags -->
                    <div class="space-y-4">
                        <h4 class="text-md font-medium text-gray-900 border-b border-gray-200 pb-2">Tags & Labels</h4>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                            <input 
                                type="text" 
                                name="tags" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter tags separated by commas"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Labels</label>
                            <div class="flex flex-wrap gap-2">
                                <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full cursor-pointer hover:bg-red-200">Bug</span>
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full cursor-pointer hover:bg-blue-200">Feature</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full cursor-pointer hover:bg-green-200">Enhancement</span>
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full cursor-pointer hover:bg-yellow-200">Documentation</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-8 pt-6 border-t border-gray-200">
                <button 
                    type="button" 
                    @click="cancelCreate()"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                    :disabled="creating"
                >
                    <span x-show="!creating">📝 Create Task</span>
                    <span x-show="creating">⏳ Creating...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function taskCreate() {
    return {
        creating: false,
        
        createTask() {
            this.creating = true;
            // Form will submit normally to server
            // This is just for UI feedback
        },
        
        cancelCreate() {
            window.location.href = '/tasks';
        }
    }
}
</script>
@endsection