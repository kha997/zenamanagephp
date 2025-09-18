@extends('layouts.dashboard')

@section('title', 'Edit Task')
@section('page-title', 'Edit Task')
@section('page-description', 'Update task details and information')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')
@section('current-route', 'tasks')

@php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Tasks Management',
        'url' => '/tasks'
    ],
    [
        'label' => 'Edit Task',
        'url' => '/tasks/' . ($task->id ?? '1') . '/edit'
    ]
];
$currentRoute = 'tasks';
@endphp

@section('content')
<div x-data="editTask()">
    <!-- Task Information Card -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Task Information
            </h3>
            <div class="flex space-x-2">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    ID: {{ $task->id ?? 'TASK-001' }}
                </span>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                    {{ $task->status ?? 'In Progress' }}
                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center">
                <i class="fas fa-calendar-plus text-gray-400 mr-2"></i>
                <span class="text-gray-600">Created:</span>
                <span class="ml-2 font-medium">{{ $task->created_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-400 mr-2"></i>
                <span class="text-gray-600">Last Updated:</span>
                <span class="ml-2 font-medium">{{ $task->updated_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-user text-gray-400 mr-2"></i>
                <span class="text-gray-600">Assignee:</span>
                <span class="ml-2 font-medium">{{ $task->assignee ?? 'Mike Wilson' }}</span>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="dashboard-card p-6">
        <form @submit.prevent="updateTask()">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Task Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-heading text-gray-400 mr-1"></i>
                            Task Title
                        </label>
                        <input 
                            type="text" 
                            x-model="formData.title"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter task title"
                            required
                        >
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-align-left text-gray-400 mr-1"></i>
                            Description
                        </label>
                        <textarea 
                            x-model="formData.description"
                            rows="4"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-vertical"
                            placeholder="Enter task description"
                        ></textarea>
                    </div>

                    <!-- Project Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-project-diagram text-gray-400 mr-1"></i>
                            Project
                        </label>
                        <select 
                            x-model="formData.project_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            required
                        >
                            <option value="">Select Project</option>
                            @php
                                $projects = \Src\CoreProject\Models\Project::all();
                            @endphp
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Assignee -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i>
                            Assignee
                        </label>
                        <select 
                            x-model="formData.assignee_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="">Select Assignee</option>
                            @php
                                $users = \App\Models\User::all();
                            @endphp
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag text-gray-400 mr-1"></i>
                            Status
                        </label>
                        <select 
                            x-model="formData.status"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            required
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-exclamation-triangle text-gray-400 mr-1"></i>
                            Priority
                        </label>
                        <select 
                            x-model="formData.priority"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            required
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            Start Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.start_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            Due Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.due_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                    </div>

                    <!-- Progress -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-percentage text-gray-400 mr-1"></i>
                            Progress (%)
                        </label>
                        <div class="space-y-2">
                            <input 
                                type="range" 
                                x-model="formData.progress"
                                min="0" 
                                max="100" 
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                            >
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>0%</span>
                                <span class="font-medium" x-text="formData.progress + '%'"></span>
                                <span>100%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Estimated Hours -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock text-gray-400 mr-1"></i>
                            Estimated Hours
                        </label>
                        <input 
                            type="number" 
                            x-model="formData.estimated_hours"
                            min="0"
                            step="0.5"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter estimated hours"
                        >
                    </div>
                </div>
            </div>

            <!-- Tags Section -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-tags text-gray-400 mr-1"></i>
                    Tags
                </label>
                <div class="flex flex-wrap gap-2 mb-3">
                    <template x-for="tag in formData.tags" :key="tag">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full flex items-center">
                            <span x-text="tag"></span>
                            <button type="button" @click="removeTag(tag)" class="ml-2 text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </span>
                    </template>
                </div>
                <div class="flex space-x-2">
                    <input 
                        type="text" 
                        x-model="newTag"
                        @keydown.enter.prevent="addTag()"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Add a tag and press Enter"
                    >
                    <button type="button" @click="addTag()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                <div class="flex space-x-3">
                    <button 
                        type="button" 
                        @click="saveDraft()"
                        class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-save mr-2"></i>
                        Save Draft
                    </button>
                    <button 
                        type="button" 
                        @click="previewTask()"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        Preview
                    </button>
                </div>
                
                <div class="flex space-x-3">
                    <a 
                        href="/tasks" 
                        class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center"
                    >
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <a 
                        :href="`/tasks/${formData.id}`"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        View Task
                    </a>
                    <button 
                        type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        :disabled="isSubmitting"
                    >
                        <i class="fas fa-check mr-2" x-show="!isSubmitting"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="isSubmitting"></i>
                        <span x-text="isSubmitting ? 'Updating...' : 'Update Task'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editTask() {
    return {
        isSubmitting: false,
        newTag: '',
        formData: {
            id: '{{ $task->id ?? "" }}',
            title: '{{ $task->name ?? "" }}',
            description: '{{ $task->description ?? "" }}',
            project_id: '{{ $task->project_id ?? "" }}',
            assignee_id: '{{ $task->assignee_id ?? "" }}',
            status: '{{ $task->status ?? "pending" }}',
            priority: '{{ $task->priority ?? "medium" }}',
            start_date: '{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d') : "" }}',
            due_date: '{{ $task->end_date ? \Carbon\Carbon::parse($task->end_date)->format('Y-m-d') : "" }}',
            progress: {{ $task->progress_percent ?? '0' }},
            estimated_hours: {{ $task->estimated_hours ?? '0' }},
            tags: {{ json_encode(array_filter(explode(',', $task->tags ?? ''))) }}
        },

        addTag() {
            if (this.newTag.trim() && !this.formData.tags.includes(this.newTag.trim())) {
                this.formData.tags.push(this.newTag.trim());
                this.newTag = '';
            }
        },

        removeTag(tag) {
            this.formData.tags = this.formData.tags.filter(t => t !== tag);
        },

        async updateTask() {
            this.isSubmitting = true;
            
            try {
                // Prepare form data
                const formData = new FormData();
                formData.append('_method', 'PUT');
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('title', this.formData.title);
                formData.append('description', this.formData.description);
                formData.append('project_id', this.formData.project_id);
                formData.append('assignee_id', this.formData.assignee_id);
                formData.append('status', this.formData.status);
                formData.append('priority', this.formData.priority);
                formData.append('start_date', this.formData.start_date);
                formData.append('due_date', this.formData.due_date);
                formData.append('progress_percent', this.formData.progress);
                formData.append('estimated_hours', this.formData.estimated_hours);
                formData.append('tags', this.formData.tags.join(','));
                
                // Submit to server
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (response.ok) {
                    this.showNotification('Task updated successfully!', 'success');
                    
                    // Redirect to tasks list
                    setTimeout(() => {
                        window.location.href = '/tasks';
                    }, 1500);
                } else {
                    throw new Error('Failed to update task');
                }
                
            } catch (error) {
                console.error('Update error:', error);
                this.showNotification('Failed to update task. Please try again.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        saveDraft() {
            // Save form data to localStorage as draft
            localStorage.setItem('taskDraft', JSON.stringify(this.formData));
            this.showNotification('Draft saved successfully!', 'info');
        },

        previewTask() {
            // Open task preview in new window
            window.open(`/tasks/${this.formData.id}`, '_blank');
        },

        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        },

        init() {
            // Load draft if exists
            const draft = localStorage.getItem('taskDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                if (confirm('A draft was found. Would you like to load it?')) {
                    this.formData = { ...this.formData, ...draftData };
                }
            }
        }
    }
}
</script>

<style>
.slider::-webkit-slider-thumb {
    appearance: none;
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider::-moz-range-thumb {
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>
@endsection
