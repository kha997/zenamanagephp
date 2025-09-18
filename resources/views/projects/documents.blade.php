@extends('layouts.dashboard')

@section('title', 'Project Documents')
@section('page-title', 'Project Documents')
@section('page-description', 'Manage project documents and files')
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
        'label' => 'Project Documents',
        'url' => '/projects/' . ($projectData->id ?? '1') . '/documents'
    ]
];
$currentRoute = 'projects';
@endphp

@section('content')
<div x-data="projectDocuments()">
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
                <i class="fas fa-project-diagram text-gray-400 mr-2"></i>
                <span class="text-gray-600">Project:</span>
                <span class="ml-2 font-medium">{{ $projectData->name ?? 'Sample Project' }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-calendar text-gray-400 mr-2"></i>
                <span class="text-gray-600">Created:</span>
                <span class="ml-2 font-medium">{{ $projectData->created_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-400 mr-2"></i>
                <span class="text-gray-600">Last Updated:</span>
                <span class="ml-2 font-medium">{{ $projectData->updated_at ?? date('Y-m-d H:i:s') }}</span>
            </div>
        </div>
    </div>

    <!-- Documents Management -->
    <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-file-alt text-green-600 mr-2"></i>
                Documents & Files
            </h3>
            <button 
                @click="uploadDocument()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
            >
                <i class="fas fa-upload mr-2"></i>
                Upload Document
            </button>
        </div>

        <!-- Document Categories -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Design Documents -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-semibold text-blue-900 mb-3 flex items-center">
                    <i class="fas fa-drafting-compass mr-2"></i>
                    Design Documents
                </h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <span class="text-sm">Architectural Plans.pdf</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-image text-blue-500 mr-2"></i>
                            <span class="text-sm">3D Renderings.jpg</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Construction Documents -->
            <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-semibold text-green-900 mb-3 flex items-center">
                    <i class="fas fa-hard-hat mr-2"></i>
                    Construction Documents
                </h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <span class="text-sm">Construction Plans.pdf</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-excel text-green-500 mr-2"></i>
                            <span class="text-sm">Material List.xlsx</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legal Documents -->
            <div class="bg-purple-50 p-4 rounded-lg">
                <h4 class="font-semibold text-purple-900 mb-3 flex items-center">
                    <i class="fas fa-gavel mr-2"></i>
                    Legal Documents
                </h4>
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <span class="text-sm">Contract Agreement.pdf</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-2 bg-white rounded">
                        <div class="flex items-center">
                            <i class="fas fa-file-pdf text-red-500 mr-2"></i>
                            <span class="text-sm">Permits.pdf</span>
                        </div>
                        <div class="flex space-x-1">
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="px-2 py-1 bg-gray-200 text-gray-700 rounded text-xs hover:bg-gray-300">
                                <i class="fas fa-download"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Documents -->
        <div class="border-t pt-6">
            <h4 class="font-semibold text-gray-900 mb-4 flex items-center">
                <i class="fas fa-clock mr-2"></i>
                Recent Documents
            </h4>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-pdf text-red-500 mr-3"></i>
                        <div>
                            <div class="font-medium">Updated Construction Plans</div>
                            <div class="text-sm text-gray-600">Updated 2 hours ago by John Smith</div>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                            <i class="fas fa-download mr-1"></i>Download
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-file-image text-blue-500 mr-3"></i>
                        <div>
                            <div class="font-medium">Site Photos</div>
                            <div class="text-sm text-gray-600">Uploaded 1 day ago by Sarah Wilson</div>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                            <i class="fas fa-eye mr-1"></i>View
                        </button>
                        <button class="px-3 py-1 bg-gray-200 text-gray-700 rounded text-sm hover:bg-gray-300">
                            <i class="fas fa-download mr-1"></i>Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function projectDocuments() {
    return {
        uploadDocument() {
            this.showNotification('Opening document upload dialog...', 'info');
            // Simulate file upload dialog
            setTimeout(() => {
                this.showNotification('Document uploaded successfully!', 'success');
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
@endsection
