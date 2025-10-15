@extends('layouts.app-layout')

@section('title', $template->name)

@section('content')
<div class="min-h-screen bg-gray-50" x-data="templateView()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-4">
                                <li>
                                    <a href="{{ route('templates') }}" class="text-gray-400 hover:text-gray-500">
                                        <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                                        </svg>
                                        <span class="sr-only">Templates</span>
                                    </a>
                                </li>
                                <li>
                                    <div class="flex items-center">
                                        <svg class="flex-shrink-0 h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="ml-4 text-sm font-medium text-gray-500">{{ $template->name }}</span>
                                    </div>
                                </li>
                            </ol>
                        </nav>
                        <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $template->name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ $template->description }}</p>
                    </div>
                    <div class="flex space-x-3">
                        <button @click="duplicateTemplate()" 
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Duplicate
                        </button>
                        <a href="{{ route('templates.edit', $template->id) }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit
                        </a>
                        <button @click="applyToProject()" 
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Apply to Project
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Template Details -->
            <div class="lg:col-span-2">
                <div class="bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Template Structure</h3>
                    </div>
                    <div class="p-6">
                        <!-- Template Info -->
                        <div class="mb-6">
                            <div class="flex items-center space-x-4 text-sm text-gray-500 mb-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ ucfirst($template->category) }}
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    {{ $template->status === 'published' ? 'bg-green-100 text-green-800' : 
                                       ($template->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ ucfirst($template->status) }}
                                </span>
                                @if($template->is_public)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                        Public
                                    </span>
                                @endif
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    Version {{ $template->version }}
                                </span>
                            </div>
                            
                            @if($template->tags && count($template->tags) > 0)
                                <div class="flex flex-wrap gap-2 mb-4">
                                    @foreach($template->tags as $tag)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Phases -->
                        <div class="space-y-4">
                            @if(isset($template->template_data['phases']) && count($template->template_data['phases']) > 0)
                                @foreach($template->template_data['phases'] as $phase)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <h4 class="text-lg font-medium text-gray-900">{{ $phase['name'] ?? 'Unnamed Phase' }}</h4>
                                            <span class="text-sm text-gray-500">{{ $phase['duration_days'] ?? 0 }} days</span>
                                        </div>
                                        
                                        @if(isset($phase['tasks']) && count($phase['tasks']) > 0)
                                            <div class="space-y-2">
                                                @foreach($phase['tasks'] as $task)
                                                    <div class="bg-gray-50 rounded p-3">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <h5 class="font-medium text-gray-900">{{ $task['name'] ?? 'Unnamed Task' }}</h5>
                                                            <div class="flex items-center space-x-2 text-sm text-gray-500">
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium 
                                                                    {{ $task['priority'] === 'high' ? 'bg-red-100 text-red-800' : 
                                                                       ($task['priority'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                                                    {{ ucfirst($task['priority'] ?? 'medium') }}
                                                                </span>
                                                                <span>{{ $task['duration_days'] ?? 0 }}d</span>
                                                                <span>{{ $task['estimated_hours'] ?? 0 }}h</span>
                                                            </div>
                                                        </div>
                                                        @if(isset($task['description']) && $task['description'])
                                                            <p class="text-sm text-gray-600">{{ $task['description'] }}</p>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center py-8 text-gray-500">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    <p class="mt-2">No phases defined in this template</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Template Stats -->
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Template Stats</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Usage Count</span>
                                <span class="text-sm font-medium text-gray-900">{{ $template->usage_count }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Created</span>
                                <span class="text-sm font-medium text-gray-900">{{ $template->created_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Last Updated</span>
                                <span class="text-sm font-medium text-gray-900">{{ $template->updated_at->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Created By</span>
                                <span class="text-sm font-medium text-gray-900">{{ $template->creator->name ?? 'Unknown' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Projects Using This Template -->
                @if($projects->count() > 0)
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">Projects Using This Template</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                @foreach($projects as $project)
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <a href="{{ route('projects.show', $project->id) }}" 
                                               class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $project->name }}
                                            </a>
                                            <p class="text-xs text-gray-500">{{ $project->owner->name ?? 'Unknown' }}</p>
                                        </div>
                                        <span class="text-xs text-gray-500">{{ $project->created_at->format('M d') }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Apply to Project Modal -->
    <div x-show="showApplyModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showApplyModal = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="w-full">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Apply Template to Project</h3>
                            <div class="mb-4">
                                <label for="project_select" class="block text-sm font-medium text-gray-700">Select Project</label>
                                <select id="project_select" x-model="selectedProjectId" 
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    <option value="">Choose a project...</option>
                                    <!-- Projects will be loaded here -->
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button @click="confirmApply()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Apply Template
                    </button>
                    <button @click="showApplyModal = false" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function templateView() {
    return {
        showApplyModal: false,
        selectedProjectId: '',

        duplicateTemplate() {
            const newName = prompt('Enter name for the duplicated template:');
            if (!newName) return;

            this.performDuplicate('{{ $template->id }}', newName);
        },

        applyToProject() {
            this.showApplyModal = true;
            this.loadProjects();
        },

        async loadProjects() {
            try {
                const response = await fetch('/api/v1/app/projects', {
                    headers: {
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const select = document.getElementById('project_select');
                    select.innerHTML = '<option value="">Choose a project...</option>';
                    
                    data.data.projects.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.name;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading projects:', error);
            }
        },

        async confirmApply() {
            if (!this.selectedProjectId) {
                alert('Please select a project');
                return;
            }

            try {
                const response = await fetch(`/api/v1/app/templates/{{ $template->id }}/apply-to-project`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    },
                    body: JSON.stringify({
                        project_id: this.selectedProjectId
                    })
                });

                if (response.ok) {
                    this.showApplyModal = false;
                    alert('Template applied successfully!');
                    window.location.reload();
                } else {
                    const error = await response.json();
                    alert('Error applying template: ' + error.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error applying template');
            }
        },

        async performDuplicate(templateId, newName) {
            try {
                const response = await fetch(`/api/v1/app/templates/${templateId}/duplicate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Authorization': 'Bearer ' + this.getAuthToken()
                    },
                    body: JSON.stringify({ name: newName })
                });

                if (response.ok) {
                    window.location.href = '/app/templates';
                } else {
                    const error = await response.json();
                    alert('Error duplicating template: ' + error.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error duplicating template');
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
