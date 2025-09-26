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
@if(isset($error))
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-red-800 mb-2">Error Loading Task</h3>
        <p class="text-red-700">{{ $error }}</p>
        <div class="mt-4">
            <a href="/tasks" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                Back to Tasks
            </a>
        </div>
    </div>
</div>
@elseif(!$task)
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-yellow-800 mb-2">Task Not Found</h3>
        <p class="text-yellow-700">The requested task could not be found.</p>
        <div class="mt-4">
            <a href="/tasks" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                Back to Tasks
            </a>
        </div>
    </div>
</div>
@else
<div x-data="taskEditData" x-init="init()">
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
                <form method="POST">
                    @csrf
                    @method('PUT')
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
                            x-model="formData.name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-vertical text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                            x-model="formData.end_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                                x-model="formData.progress_percent"
                                min="0" 
                                max="100" 
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                            >
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>0%</span>
                                <span class="font-medium" x-text="formData.progress_percent + '%'"></span>
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-700 bg-white"
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
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-700 bg-white"
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
                        type="button" 
                        @click="updateTask()"
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
document.addEventListener('alpine:init', () => {
    Alpine.data('editTask', () => ({
        isSubmitting: false,
        newTag: '',
        formData: {
            id: '{{ $task->id ?? "" }}',
            name: '{{ $task->name ?? "" }}',
            description: '{{ $task->description ?? "" }}',
            project_id: '{{ $task->project_id ?? "" }}',
            assignee_id: '{{ $task->assignee_id ?? "" }}',
            status: '{{ $task->status ?? "pending" }}',
            priority: '{{ $task->priority ?? "medium" }}',
            start_date: '{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d') : "" }}',
            end_date: '{{ $task->end_date ? \Carbon\Carbon::parse($task->end_date)->format('Y-m-d') : "" }}',
            progress_percent: {{ $task->progress_percent ?? '0' }},
            estimated_hours: {{ $task->estimated_hours ?? '0' }},
            tags: {{ json_encode(array_filter(explode(',', $task->tags ?? ''))) }}
        },

        init() {
            // Debug: Log task data
            console.log('=== ALPINE.JS INITIALIZATION ===');
            console.log('Alpine.js initialized!');
            console.log('Raw task data from server:');
            console.log('Task ID:', '{{ $task->id ?? "NO_ID" }}');
            console.log('Task Name:', '{{ $task->name ?? "NO_NAME" }}');
            console.log('Task Status:', '{{ $task->status ?? "NO_STATUS" }}');
            console.log('Task Priority:', '{{ $task->priority ?? "NO_PRIORITY" }}');
            console.log('Task Assignee ID:', '{{ $task->assignee_id ?? "NO_ASSIGNEE" }}');
            console.log('Task Description:', '{{ $task->description ?? "NO_DESCRIPTION" }}');
            console.log('Task Project ID:', '{{ $task->project_id ?? "NO_PROJECT" }}');
            console.log('Task Start Date:', '{{ $task->start_date ?? "NO_START_DATE" }}');
            console.log('Task End Date:', '{{ $task->end_date ?? "NO_END_DATE" }}');
            console.log('Task Progress:', '{{ $task->progress_percent ?? "NO_PROGRESS" }}');
            console.log('Task Estimated Hours:', '{{ $task->estimated_hours ?? "NO_HOURS" }}');
            console.log('Task Tags:', '{{ $task->tags ?? "NO_TAGS" }}');
            
            console.log('FormData after initialization:');
            console.log('formData.id:', this.formData.id);
            console.log('formData.name:', this.formData.name);
            console.log('formData.status:', this.formData.status);
            console.log('formData.priority:', this.formData.priority);
            console.log('formData.description:', this.formData.description);
            
            // Test Alpine.js functionality
            this.testAlpine = 'Alpine.js is working!';
            console.log('Alpine test:', this.testAlpine);
            
            // Load saved draft if exists
            const draft = localStorage.getItem('taskDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                if (confirm('A draft was found. Would you like to load it?')) {
                    this.formData = { ...this.formData, ...draftData };
                }
            }
            
            console.log('=== ALPINE.JS INITIALIZATION COMPLETED ===');
        },

        async testUpdate() {
            console.log('Testing update...');
            try {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('name', 'Test Task');
                formData.append('description', 'Test Description');
                
                const response = await fetch('/test-task-update', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const result = await response.json();
                console.log('Test response:', result);
                alert('Test successful! Check console for details.');
            } catch (error) {
                console.error('Test failed:', error);
                alert('Test failed: ' + error.message);
            }
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
                console.log('Starting task update...');
                
                // Get CSRF token
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                if (!csrfToken) {
                    throw new Error('CSRF token not found');
                }
                
                // Prepare form data
                const formData = new FormData();
                formData.append('_method', 'PUT');
                formData.append('_token', csrfToken);
                formData.append('project_id', this.formData.project_id);
                formData.append('name', this.formData.name);
                formData.append('description', this.formData.description);
                formData.append('status', this.formData.status);
                formData.append('priority', this.formData.priority);
                formData.append('start_date', this.formData.start_date);
                formData.append('end_date', this.formData.end_date);
                formData.append('progress_percent', this.formData.progress_percent);
                formData.append('estimated_hours', this.formData.estimated_hours);
                formData.append('assignee_id', this.formData.assignee_id || '');
                formData.append('tags', this.formData.tags.join(','));
                
                console.log('Submitting update...');
                
                // Submit to server with proper error handling
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('Response received:', response.status);
                
                // Handle different response types
                if (response.status === 302) {
                    // Redirect response - success
                    this.showNotification('Task updated successfully!', 'success');
                    window.location.href = '/tasks';
                } else if (response.ok) {
                    // Success response
                    this.showNotification('Task updated successfully!', 'success');
                    window.location.href = '/tasks';
                } else {
                    // Error response
                    const responseText = await response.text();
                    console.error('Update failed:', response.status, responseText);
                    
                    if (response.status === 419) {
                        this.showNotification('Session expired. Please refresh the page and try again.', 'error');
                    } else if (response.status === 422) {
                        this.showNotification('Validation error. Please check your input.', 'error');
                    } else {
                        this.showNotification('Failed to update task. Please try again.', 'error');
                    }
                }
                
            } catch (error) {
                console.error('Update error:', error);
                this.showNotification('Network error. Please check your connection and try again.', 'error');
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

    }));
        });
    </script>

<script>
window.taskEditData = {
    isSubmitting: false,
    newTag: '',
    formData: {
        id: '{{ $task->id ?? "" }}',
        name: '{{ $task->name ?? "" }}',
        description: '{{ $task->description ?? "" }}',
        project_id: '{{ $task->project_id ?? "" }}',
        assignee_id: '{{ $task->assignee_id ?? "" }}',
        status: '{{ $task->status ?? "pending" }}',
        priority: '{{ $task->priority ?? "medium" }}',
        start_date: '{{ $task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d') : "" }}',
        end_date: '{{ $task->end_date ? \Carbon\Carbon::parse($task->end_date)->format('Y-m-d') : "" }}',
        progress_percent: {{ $task->progress_percent ?? '0' }},
        estimated_hours: {{ $task->estimated_hours ?? '0' }},
        tags: {!! json_encode(array_filter(explode(',', $task->tags ?? '')), JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    },
    testAlpine: 'Alpine.js is working!',
    
    init() {
        console.log('=== ALPINE.JS INITIALIZATION ===');
        console.log('Alpine.js initialized!');
        console.log('Raw task data from server:');
        console.log('Task ID:', '{{ $task->id ?? 'NO_ID' }}');
        console.log('Task Name:', '{{ $task->name ?? 'NO_NAME' }}');
        console.log('Task Status:', '{{ $task->status ?? 'NO_STATUS' }}');
        console.log('Task Priority:', '{{ $task->priority ?? 'NO_PRIORITY' }}');
        console.log('Task Assignee ID:', '{{ $task->assignee_id ?? 'NO_ASSIGNEE' }}');
        console.log('Task Description:', '{{ $task->description ?? 'NO_DESCRIPTION' }}');
        console.log('Task Project ID:', '{{ $task->project_id ?? 'NO_PROJECT' }}');
        console.log('Task Start Date:', '{{ $task->start_date ?? 'NO_START_DATE' }}');
        console.log('Task End Date:', '{{ $task->end_date ?? 'NO_END_DATE' }}');
        console.log('Task Progress:', '{{ $task->progress_percent ?? 'NO_PROGRESS' }}');
        console.log('Task Estimated Hours:', '{{ $task->estimated_hours ?? 'NO_HOURS' }}');
        console.log('Task Tags:', '{{ $task->tags ?? 'NO_TAGS' }}');
        
        console.log('FormData after initialization:');
        console.log('formData.id:', this.formData.id);
        console.log('formData.name:', this.formData.name);
        console.log('formData.status:', this.formData.status);
        console.log('formData.priority:', this.formData.priority);
        console.log('formData.description:', this.formData.description);
        
        console.log('=== ALPINE.JS INITIALIZATION COMPLETED ===');
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
            console.log('Starting task update...');
            
            // Get CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            if (!csrfToken) {
                throw new Error('CSRF token not found');
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('_method', 'PUT');
            formData.append('_token', csrfToken);
            formData.append('project_id', this.formData.project_id);
            formData.append('name', this.formData.name);
            formData.append('description', this.formData.description);
            formData.append('status', this.formData.status);
            formData.append('priority', this.formData.priority);
            formData.append('start_date', this.formData.start_date);
            formData.append('end_date', this.formData.end_date);
            formData.append('progress_percent', this.formData.progress_percent);
            formData.append('estimated_hours', this.formData.estimated_hours);
            formData.append('assignee_id', this.formData.assignee_id || '');
            formData.append('tags', this.formData.tags.join(','));
            
            console.log('Submitting update...');
            
            // Submit to server with proper error handling
            const response = await fetch(`/tasks/${this.formData.id}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                },
                credentials: 'same-origin'
            });
            
            console.log('Response received:', response.status);
            
            // Handle different response types
            if (response.status === 302) {
                // Redirect response - success
                this.showNotification('Task updated successfully!', 'success');
                window.location.href = '/tasks';
            } else if (response.ok) {
                // Success response
                this.showNotification('Task updated successfully!', 'success');
                window.location.href = '/tasks';
            } else {
                // Error response
                const responseText = await response.text();
                console.error('Update failed:', response.status, responseText);
                
                if (response.status === 419) {
                    this.showNotification('Session expired. Please refresh the page and try again.', 'error');
                } else if (response.status === 422) {
                    this.showNotification('Validation error. Please check your input.', 'error');
                } else {
                    this.showNotification('Failed to update task. Please try again.', 'error');
                }
            }
            
        } catch (error) {
            console.error('Update error:', error);
            this.showNotification('Network error. Please check your connection and try again.', 'error');
        } finally {
            this.isSubmitting = false;
        }
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
    }
};
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
@endif
@endsection
