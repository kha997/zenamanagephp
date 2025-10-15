@extends('layouts.app-layout')

@section('title', 'Template Builder')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="templateBuilder()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Template Builder</h1>
                        <p class="mt-1 text-sm text-gray-600">Create and customize project templates</p>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="previewTemplate()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Preview
                        </button>
                        <button @click="saveTemplate()" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Save Template
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Template Configuration -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Template Configuration</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <label for="template_name" class="block text-sm font-medium text-gray-700">Template Name</label>
                            <input type="text" id="template_name" x-model="template.name" 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Enter template name">
                        </div>

                        <div>
                            <label for="template_description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="template_description" x-model="template.description" rows="3"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                      placeholder="Describe your template"></textarea>
                        </div>

                        <div>
                            <label for="template_category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="template_category" x-model="template.category" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="project">Project</option>
                                <option value="task">Task</option>
                                <option value="workflow">Workflow</option>
                                <option value="document">Document</option>
                                <option value="report">Report</option>
                            </select>
                        </div>

                        <div>
                            <label for="template_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="template_status" x-model="template.status" 
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" id="template_is_public" x-model="template.is_public" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="template_is_public" class="ml-2 block text-sm text-gray-900">
                                Make this template public
                            </label>
                        </div>

                        <!-- Tags -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Tags</label>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <template x-for="(tag, index) in template.tags" :key="index">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <span x-text="tag"></span>
                                        <button @click="removeTag(index)" class="ml-1 text-blue-600 hover:text-blue-800">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </span>
                                </template>
                            </div>
                            <div class="mt-2 flex">
                                <input type="text" x-model="newTag" @keydown.enter.prevent="addTag()"
                                       class="flex-1 border-gray-300 rounded-l-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       placeholder="Add tag">
                                <button @click="addTag()" 
                                        class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-500 hover:bg-gray-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Builder -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Template Structure</h3>
                            <div class="flex space-x-2">
                                <button @click="addPhase()" 
                                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    Add Phase
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Phases -->
                        <div class="space-y-4">
                            <template x-for="(phase, phaseIndex) in template.template_data.phases" :key="phaseIndex">
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex-1">
                                            <input type="text" x-model="phase.name" 
                                                   class="block w-full text-lg font-medium border-none focus:ring-0 p-0"
                                                   placeholder="Phase name">
                                            <input type="number" x-model="phase.duration_days" 
                                                   class="block w-32 mt-1 text-sm text-gray-600 border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="Days">
                                        </div>
                                        <button @click="removePhase(phaseIndex)" 
                                                class="text-red-600 hover:text-red-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    
                                    <!-- Tasks in Phase -->
                                    <div class="space-y-2">
                                        <template x-for="(task, taskIndex) in phase.tasks" :key="taskIndex">
                                            <div class="bg-gray-50 rounded p-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <input type="text" x-model="task.name" 
                                                           class="block w-full font-medium border-none bg-transparent focus:ring-0 p-0"
                                                           placeholder="Task name">
                                                    <button @click="removeTask(phaseIndex, taskIndex)" 
                                                            class="text-red-600 hover:text-red-800">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2 text-sm">
                                                    <input type="text" x-model="task.description" 
                                                           class="border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                                                           placeholder="Description">
                                                    <select x-model="task.priority" 
                                                            class="border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500">
                                                        <option value="low">Low Priority</option>
                                                        <option value="medium">Medium Priority</option>
                                                        <option value="high">High Priority</option>
                                                    </select>
                                                    <input type="number" x-model="task.duration_days" 
                                                           class="border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                                                           placeholder="Duration (days)">
                                                    <input type="number" x-model="task.estimated_hours" 
                                                           class="border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500"
                                                           placeholder="Hours">
                                                </div>
                                            </div>
                                        </template>
                                        
                                        <button @click="addTask(phaseIndex)" 
                                                class="w-full text-sm text-blue-600 hover:text-blue-800 py-2 border border-dashed border-gray-300 rounded">
                                            + Add Task
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div x-show="showPreview" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showPreview = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Template Preview</h3>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="text-lg font-semibold mb-2" x-text="template.name"></h4>
                                <p class="text-gray-600 mb-4" x-text="template.description"></p>
                                
                                <div class="space-y-4">
                                    <template x-for="(phase, index) in template.template_data.phases" :key="index">
                                        <div class="border border-gray-200 rounded p-3">
                                            <h5 class="font-medium" x-text="phase.name"></h5>
                                            <p class="text-sm text-gray-600" x-text="`${phase.duration_days} days`"></p>
                                            <div class="mt-2 space-y-1">
                                                <template x-for="(task, taskIndex) in phase.tasks" :key="taskIndex">
                                                    <div class="text-sm text-gray-700">
                                                        • <span x-text="task.name"></span> 
                                                        <span class="text-gray-500" x-text="`(${task.duration_days}d, ${task.estimated_hours}h)`"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="showPreview = false" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function templateBuilder() {
    return {
        template: {
            name: '',
            description: '',
            category: 'project',
            status: 'draft',
            is_public: false,
            tags: [],
            template_data: {
                phases: []
            }
        },
        newTag: '',
        showPreview: false,

        addPhase() {
            this.template.template_data.phases.push({
                name: '',
                duration_days: 1,
                tasks: []
            });
        },

        removePhase(index) {
            this.template.template_data.phases.splice(index, 1);
        },

        addTask(phaseIndex) {
            this.template.template_data.phases[phaseIndex].tasks.push({
                name: '',
                description: '',
                duration_days: 1,
                priority: 'medium',
                estimated_hours: 8
            });
        },

        removeTask(phaseIndex, taskIndex) {
            this.template.template_data.phases[phaseIndex].tasks.splice(taskIndex, 1);
        },

        addTag() {
            if (this.newTag.trim() && !this.template.tags.includes(this.newTag.trim())) {
                this.template.tags.push(this.newTag.trim());
                this.newTag = '';
            }
        },

        removeTag(index) {
            this.template.tags.splice(index, 1);
        },

        previewTemplate() {
            this.showPreview = true;
        },

        async saveTemplate() {
            try {
                const response = await fetch('/api/v1/app/templates', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    },
                    body: JSON.stringify(this.template)
                });

                if (response.ok) {
                    const result = await response.json();
                    window.location.href = '/app/templates';
                } else {
                    const error = await response.json();
                    alert('Error saving template: ' + error.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving template');
            }
        },

        getAuthToken() {
            // Mock implementation - replace with actual token retrieval
            return 'mock-token';
        }
    }
}
</script>
@endsection
