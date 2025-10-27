@extends('layouts.app')

@section('title', 'Template Library')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="templateLibrary()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Template Library</h1>
                        <p class="mt-1 text-sm text-gray-600">Browse and discover project templates</p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="{{ route('templates') }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            My Templates
                        </a>
                        <a href="{{ route('templates.builder') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create Template
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                               placeholder="Search templates...">
                    </div>
                    
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="category" id="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">All Categories</option>
                            <option value="project" {{ request('category') === 'project' ? 'selected' : '' }}>Project</option>
                            <option value="task" {{ request('category') === 'task' ? 'selected' : '' }}>Task</option>
                            <option value="workflow" {{ request('category') === 'workflow' ? 'selected' : '' }}>Workflow</option>
                            <option value="document" {{ request('category') === 'document' ? 'selected' : '' }}>Document</option>
                            <option value="report" {{ request('category') === 'report' ? 'selected' : '' }}>Report</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="sort" class="block text-sm font-medium text-gray-700">Sort By</label>
                        <select name="sort" id="sort" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="popular" {{ request('sort') === 'popular' ? 'selected' : '' }}>Most Popular</option>
                            <option value="recent" {{ request('sort') === 'recent' ? 'selected' : '' }}>Most Recent</option>
                            <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Name A-Z</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Public Templates -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Public Templates</h3>
                <p class="mt-1 text-sm text-gray-600">Templates shared by the community</p>
            </div>
            
            @if($publicTemplates->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    @foreach($publicTemplates as $template)
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                            <div class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-lg font-medium text-gray-900 mb-2">{{ $template->name }}</h4>
                                        <p class="text-sm text-gray-600 mb-4">{{ $template->description }}</p>
                                        
                                        <div class="flex items-center space-x-4 text-sm text-gray-500 mb-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ ucfirst($template->category) }}
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Published
                                            </span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                Public
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                            {{ $template->usage_count }} uses
                                        </div>
                                        
                                        <div class="text-sm text-gray-500">
                                            Created by {{ $template->creator->name ?? 'Unknown' }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <button @click="viewTemplate('{{ $template->id }}')" 
                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        View
                                    </button>
                                    <button @click="duplicateTemplate('{{ $template->id }}')" 
                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                        Use Template
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No public templates available</h3>
                    <p class="mt-1 text-sm text-gray-500">Be the first to share a template with the community.</p>
                </div>
            @endif
        </div>

        <!-- My Templates -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">My Templates</h3>
                <p class="mt-1 text-sm text-gray-600">Templates I've created</p>
            </div>
            
            @if($userTemplates->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                    @foreach($userTemplates as $template)
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                            <div class="p-6">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h4 class="text-lg font-medium text-gray-900 mb-2">{{ $template->name }}</h4>
                                        <p class="text-sm text-gray-600 mb-4">{{ $template->description }}</p>
                                        
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
                                        </div>
                                        
                                        <div class="flex items-center text-sm text-gray-500 mb-4">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                            {{ $template->usage_count }} uses
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <button @click="viewTemplate('{{ $template->id }}')" 
                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        View
                                    </button>
                                    <button @click="editTemplate('{{ $template->id }}')" 
                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Edit
                                    </button>
                                    <button @click="duplicateTemplate('{{ $template->id }}')" 
                                            class="flex-1 inline-flex justify-center items-center px-3 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                        Duplicate
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No templates created yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first template.</p>
                    <div class="mt-6">
                        <a href="{{ route('templates.builder') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Create Template
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function templateLibrary() {
    return {
        viewTemplate(templateId) {
            window.location.href = `/app/templates/${templateId}`;
        },

        editTemplate(templateId) {
            window.location.href = `/app/templates/${templateId}/edit`;
        },

        async duplicateTemplate(templateId) {
            try {
                const newName = prompt('Enter name for the duplicated template:');
                if (!newName) return;

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
                    const result = await response.json();
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
