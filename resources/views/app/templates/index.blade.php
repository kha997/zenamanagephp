@extends('layouts.app')

@section('title', __('templates.title'))

@section('kpi-strip')
{{-- <x-kpi.strip :kpis="$kpis" /> --}}
@endsection

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">{{ __('templates.title') }}</h1>
                <p class="mt-2 text-gray-600">{{ __('templates.subtitle') }}</p>
            </div>
            <div class="flex space-x-3">
                {{-- TODO: Implement templates.library route --}}
                {{-- <a href="{{ route('app.templates.library') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-book mr-2"></i>{{ __('templates.template_library') }}
                </a> --}}
                {{-- TODO: Implement templates.builder route --}}
                {{-- <a href="{{ route('app.templates.builder') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="fas fa-plus mr-2"></i>{{ __('templates.create_template') }}
                </a> --}}
            </div>
        </div>
    </div>

    <!-- Templates Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Templates List -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">{{ __('templates.recent_templates') }}</h2>
                        <div class="flex items-center space-x-2">
                            <button id="list-view" class="p-2 text-blue-600 bg-blue-50 rounded-lg">
                                <i class="fas fa-list"></i>
                            </button>
                            <button id="grid-view" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg">
                                <i class="fas fa-th"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="text-center py-8">
                        <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-file-alt text-2xl text-gray-400"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('templates.no_templates') }}</h3>
                        <p class="text-gray-500 mb-4">{{ __('templates.no_templates_description') }}</p>
                        <a href="/app/templates/builder" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            <i class="fas fa-plus mr-2"></i>
                            {{ __('templates.create_first_template') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Template Statistics & Actions -->
        <div class="space-y-6">
            <!-- Template Statistics -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('templates.statistics') }}</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('templates.total_templates') }}</span>
                        <span class="font-semibold text-gray-900">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('templates.active_templates') }}</span>
                        <span class="font-semibold text-gray-900">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('templates.templates_used') }}</span>
                        <span class="font-semibold text-gray-900">0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ __('templates.last_used') }}</span>
                        <span class="font-semibold text-gray-900">{{ __('templates.never') }}</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('templates.quick_actions') }}</h2>
                </div>
                <div class="p-6 space-y-3">
                    <a href="/app/templates/builder" class="w-full flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                        <i class="fas fa-plus text-blue-600 mr-3"></i>
                        <span class="font-medium text-blue-900">{{ __('templates.create_template') }}</span>
                    </a>
                    <a href="/app/templates/library" class="w-full flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                        <i class="fas fa-book text-green-600 mr-3"></i>
                        <span class="font-medium text-green-900">{{ __('templates.browse_library') }}</span>
                    </a>
                    <button onclick="importTemplate()" class="w-full flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                        <i class="fas fa-upload text-purple-600 mr-3"></i>
                        <span class="font-medium text-purple-900">{{ __('templates.import_template') }}</span>
                    </button>
                </div>
            </div>

            <!-- Template Categories -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('templates.categories') }}</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-2">
                        <button onclick="filterByCategory('all')" class="w-full text-left px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            {{ __('templates.all_templates') }} (0)
                        </button>
                        <button onclick="filterByCategory('project')" class="w-full text-left px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            {{ __('templates.project_templates') }} (0)
                        </button>
                        <button onclick="filterByCategory('task')" class="w-full text-left px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            {{ __('templates.task_templates') }} (0)
                        </button>
                        <button onclick="filterByCategory('document')" class="w-full text-left px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 rounded-lg">
                            {{ __('templates.document_templates') }} (0)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// View toggle functionality
document.getElementById('list-view').addEventListener('click', function() {
    document.getElementById('templates-list').classList.remove('hidden');
    document.getElementById('templates-grid').classList.add('hidden');
    this.classList.add('text-blue-600', 'bg-blue-50');
    this.classList.remove('text-gray-400');
    document.getElementById('grid-view').classList.remove('text-blue-600', 'bg-blue-50');
    document.getElementById('grid-view').classList.add('text-gray-400');
});

document.getElementById('grid-view').addEventListener('click', function() {
    document.getElementById('templates-list').classList.add('hidden');
    document.getElementById('templates-grid').classList.remove('hidden');
    this.classList.add('text-blue-600', 'bg-blue-50');
    this.classList.remove('text-gray-400');
    document.getElementById('list-view').classList.remove('text-blue-600', 'bg-blue-50');
    document.getElementById('list-view').classList.add('text-gray-400');
});

function useTemplate(templateId) {
    if (confirm('Are you sure you want to use this template?')) {
        fetch(`/api/v1/app/templates/${templateId}/use`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Template applied successfully! You can now customize your project.');
                // Redirect to projects page or show success message
                window.location.href = '{{ route("app.projects.index") }}';
            } else {
                alert('Failed to use template: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error using template:', error);
            alert('Failed to use template');
        });
    }
}

function editTemplate(templateId) {
    // TODO: Implement app.templates.edit route
    // Redirect to template editor
    // window.location.href = `/app/templates/${templateId}/edit`;
    alert('Template editing coming soon!');
}

function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        fetch(`/api/v1/app/templates/${templateId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Template deleted successfully');
                loadTemplates(); // Reload template list
            } else {
                alert('Failed to delete template: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting template:', error);
            alert('Failed to delete template');
        });
    }
}

function importTemplate() {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = '.json,.zip';
    
    fileInput.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            const formData = new FormData();
            formData.append('template_file', file);
            
            fetch('/api/v1/app/templates/import', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Template imported successfully');
                    loadTemplates(); // Reload template list
                } else {
                    alert('Failed to import template: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error importing template:', error);
                alert('Failed to import template');
            });
        }
    };
    
    fileInput.click();
}

function filterByCategory(category) {
    // Update active filter
    document.querySelectorAll('.category-filter').forEach(btn => {
        btn.classList.remove('bg-blue-600', 'text-white');
        btn.classList.add('bg-gray-200', 'text-gray-700');
    });
    
    event.target.classList.add('bg-blue-600', 'text-white');
    event.target.classList.remove('bg-gray-200', 'text-gray-700');
    
    // Filter templates
    const templates = document.querySelectorAll('.template-item');
    templates.forEach(template => {
        const templateCategory = template.dataset.category;
        if (category === 'all' || templateCategory === category) {
            template.style.display = 'block';
        } else {
            template.style.display = 'none';
        }
    });
}

function loadTemplates() {
    fetch('/api/v1/app/templates')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTemplateList(data.data);
            } else {
                console.error('Failed to load templates:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading templates:', error);
        });
}

function updateTemplateList(templates) {
    const listContainer = document.getElementById('templates-list');
    const gridContainer = document.getElementById('templates-grid');
    
    // Clear existing content
    listContainer.innerHTML = '';
    gridContainer.innerHTML = '';
    
    templates.forEach(template => {
        // Add to list view
        const listItem = createTemplateListItem(template);
        listContainer.appendChild(listItem);
        
        // Add to grid view
        const gridItem = createTemplateGridItem(template);
        gridContainer.appendChild(gridItem);
    });
}

function createTemplateListItem(template) {
    const item = document.createElement('div');
    item.className = 'template-item flex items-center justify-between p-4 border-b border-gray-200 hover:bg-gray-50';
    item.dataset.category = template.category;
    item.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-file-alt text-blue-600"></i>
            </div>
            <div>
                <h3 class="text-sm font-medium text-gray-900">${template.name}</h3>
                <p class="text-sm text-gray-500">${template.description || 'No description'}</p>
                <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full mt-1">
                    ${template.category}
                </span>
            </div>
        </div>
        <div class="flex items-center space-x-2">
            <button onclick="useTemplate(${template.id})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                Use
            </button>
            <button onclick="editTemplate(${template.id})" class="text-green-600 hover:text-green-800">
                <i class="fas fa-edit"></i>
            </button>
            <button onclick="deleteTemplate(${template.id})" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    return item;
}

function createTemplateGridItem(template) {
    const item = document.createElement('div');
    item.className = 'template-item bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow';
    item.dataset.category = template.category;
    item.innerHTML = `
        <div class="flex items-center justify-center w-16 h-16 bg-blue-100 rounded-lg mb-3">
            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
        </div>
        <h3 class="text-sm font-medium text-gray-900 mb-1">${template.name}</h3>
        <p class="text-xs text-gray-500 mb-3">${template.description || 'No description'}</p>
        <span class="inline-block px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full mb-3">
            ${template.category}
        </span>
        <div class="flex justify-between">
            <button onclick="useTemplate(${template.id})" class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                Use
            </button>
            <div class="flex space-x-1">
                <button onclick="editTemplate(${template.id})" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteTemplate(${template.id})" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    return item;
}

// Load templates on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTemplates();
});
</script>
@endpush
@endsection