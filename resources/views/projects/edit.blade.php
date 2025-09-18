@extends('layouts.dashboard')

@section('title', 'Edit Project')
@section('page-title', 'Edit Project')
@section('page-description', 'Update project details and information')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')
@section('current-route', 'projects')

@php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Projects Management',
        'url' => '/projects'
    ],
    [
        'label' => 'Edit Project',
        'url' => '/projects/' . ($projectData->id ?? '1') . '/edit'
    ]
];
$currentRoute = 'projects';
@endphp

@section('content')
<div x-data="editProject()">
    <!-- Project Information Card -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Project Information
            </h3>
            <div class="flex space-x-2">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    ID: {{ $projectData->id ?? 'PROJ-001' }}
                </span>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                    {{ ucfirst($projectData->status ?? 'Active') }}
                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center">
                <i class="fas fa-calendar-plus text-gray-400 mr-2"></i>
                <span class="text-gray-600">Created:</span>
                <span class="ml-2 font-medium">{{ $projectData->created_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-400 mr-2"></i>
                <span class="text-gray-600">Last Updated:</span>
                <span class="ml-2 font-medium">{{ $projectData->updated_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-user text-gray-400 mr-2"></i>
                <span class="text-gray-600">Project Manager:</span>
                <span class="ml-2 font-medium">{{ $users->where('id', $projectData->pm_id)->first()->name ?? 'John Smith' }}</span>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="dashboard-card p-6">
        <form @submit.prevent="updateProject()">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Project Code -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-code text-gray-400 mr-1"></i>
                            Project Code
                        </label>
                        <input 
                            type="text" 
                            x-model="formData.code"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter project code"
                            required
                        >
                    </div>

                    <!-- Project Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-heading text-gray-400 mr-1"></i>
                            Project Name
                        </label>
                        <input 
                            type="text" 
                            x-model="formData.name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter project name"
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
                            placeholder="Enter project description"
                        ></textarea>
                    </div>

                    <!-- Client Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tie text-gray-400 mr-1"></i>
                            Client
                        </label>
                        <select 
                            x-model="formData.client_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="">Select Client</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Project Manager -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-cog text-gray-400 mr-1"></i>
                            Project Manager
                        </label>
                        <select 
                            x-model="formData.pm_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            required
                        >
                            <option value="">Select Project Manager</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

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
                            <option value="draft">Draft</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="archived">Archived</option>
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

                    <!-- End Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-check text-gray-400 mr-1"></i>
                            End Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.end_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                    </div>

                    <!-- Budget -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign text-gray-400 mr-1"></i>
                            Total Budget
                        </label>
                        <input 
                            type="number" 
                            x-model="formData.budget_total"
                            min="0"
                            step="0.01"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Enter total budget"
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
                        @click="previewProject()"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        Preview
                    </button>
                </div>
                
                <div class="flex space-x-3">
                    <a 
                        href="/projects" 
                        class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center"
                    >
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <a 
                        :href="`/projects/${formData.id}`"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        View Project
                    </a>
                    <button 
                        type="submit" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        :disabled="isSubmitting"
                    >
                        <i class="fas fa-check mr-2" x-show="!isSubmitting"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="isSubmitting"></i>
                        <span x-text="isSubmitting ? 'Updating...' : 'Update Project'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editProject() {
    return {
        isSubmitting: false,
        newTag: '',
        formData: {
            id: {{ $projectData->id ?? 1 }},
            code: '{{ $projectData->code ?? "PROJ-001" }}',
            name: '{{ $projectData->name ?? "Sample Project" }}',
            description: '{{ $projectData->description ?? "" }}',
            client_id: {{ $projectData->client_id ?? 1 }},
            pm_id: {{ $projectData->pm_id ?? 2 }},
            status: '{{ $projectData->status ?? "active" }}',
            start_date: '{{ $projectData->start_date ?? "" }}',
            end_date: '{{ $projectData->end_date ?? "" }}',
            budget_total: {{ $projectData->budget_total ?? 0 }},
            progress: {{ $projectData->progress ?? 0 }},
            tags: @json($projectData->tags ?? [])
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
        
        saveDraft() {
            // Save to localStorage
            localStorage.setItem('project_draft_' + this.formData.id, JSON.stringify(this.formData));
            this.showNotification('Draft saved successfully!', 'success');
        },
        
        previewProject() {
            this.showNotification('Opening project preview...', 'info');
            setTimeout(() => {
                window.open(`/projects/${this.formData.id}`, '_blank');
            }, 1000);
        },
        
        updateProject() {
            this.isSubmitting = true;
            
            // Simulate API call
            setTimeout(() => {
                this.isSubmitting = false;
                this.showNotification('Project updated successfully!', 'success');
                
                // Clear draft
                localStorage.removeItem('project_draft_' + this.formData.id);
                
                // Redirect to project view
                setTimeout(() => {
                    window.location.href = `/projects/${this.formData.id}`;
                }, 1500);
            }, 2000);
        },
        
        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
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
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider::-moz-range-thumb {
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>
@endsection
