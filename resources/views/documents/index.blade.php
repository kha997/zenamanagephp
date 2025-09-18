@extends('layouts.dashboard')

@section('title', 'Documents Management')
@section('page-title', 'Documents Management')
@section('page-description', 'Upload, view, and manage project documents')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="documentsManagement()">
    <!-- Header Actions -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">📄 Documents Management</h2>
            <p class="text-gray-600 mt-1">Manage all project documents and files</p>
        </div>
        <div class="flex space-x-3">
            <button 
                @click="quickUpload()"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
            >
                📁 Quick Upload
            </button>
            <button 
                @click="uploadDocument()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
            >
                📤 Upload Document
            </button>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="dashboard-card p-4 mb-6">
        <div class="flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-64">
                <input 
                    type="text" 
                    x-model="searchQuery"
                    @input="filterDocuments()"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Search documents..."
                >
            </div>
            <select 
                x-model="selectedProject"
                @change="filterDocuments()"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="">All Projects</option>
                <option value="1">Office Building Complex</option>
                <option value="2">Shopping Mall Development</option>
                <option value="3">Residential Complex</option>
            </select>
            <select 
                x-model="selectedType"
                @change="filterDocuments()"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
                <option value="">All Types</option>
                <option value="drawing">Drawing</option>
                <option value="specification">Specification</option>
                <option value="contract">Contract</option>
                <option value="report">Report</option>
                <option value="photo">Photo</option>
            </select>
            <button 
                @click="clearFilters()"
                class="px-3 py-2 text-gray-600 hover:text-gray-800"
            >
                Clear Filters
            </button>
        </div>
    </div>

    <!-- Documents Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <template x-for="document in filteredDocuments" :key="document.id">
            <div class="dashboard-card p-4 hover:shadow-lg transition-shadow cursor-pointer" @click="viewDocument(document)">
                <div class="flex items-start justify-between mb-3">
                    <div class="text-3xl" x-text="getDocumentIcon(document.type)"></div>
                    <div class="flex space-x-1">
                        <button 
                            @click.stop="editDocument(document)"
                            class="p-1 text-gray-400 hover:text-blue-600"
                        >
                            ✏️
                        </button>
                        <button 
                            @click.stop="deleteDocument(document)"
                            class="p-1 text-gray-400 hover:text-red-600"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
                
                <h3 class="font-semibold text-gray-900 mb-2 truncate" x-text="document.title"></h3>
                <p class="text-sm text-gray-600 mb-2" x-text="document.description"></p>
                
                <div class="space-y-1 text-xs text-gray-500">
                    <div class="flex justify-between">
                        <span>Project:</span>
                        <span x-text="document.project"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Size:</span>
                        <span x-text="document.size"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Uploaded:</span>
                        <span x-text="document.uploaded_at"></span>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <span 
                        class="px-2 py-1 text-xs rounded-full"
                        :class="getStatusClass(document.status)"
                        x-text="document.status"
                    ></span>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="filteredDocuments.length === 0" class="text-center py-12">
        <div class="text-6xl mb-4">📄</div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No documents found</h3>
        <p class="text-gray-600 mb-4">Upload your first document to get started</p>
        <button 
            @click="uploadDocument()"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
            Upload Document
        </button>
    </div>
</div>

<script>
function documentsManagement() {
    return {
        searchQuery: '',
        selectedProject: '',
        selectedType: '',
        documents: [
            {
                id: 1,
                title: 'Building Plans - Floor 1',
                description: 'Detailed architectural plans for the first floor',
                type: 'drawing',
                project: 'Office Building Complex',
                size: '2.4 MB',
                status: 'approved',
                uploaded_at: '2 days ago'
            },
            {
                id: 2,
                title: 'Project Specification',
                description: 'Complete project requirements and specifications',
                type: 'specification',
                project: 'Shopping Mall Development',
                size: '1.8 MB',
                status: 'pending',
                uploaded_at: '1 week ago'
            },
            {
                id: 3,
                title: 'Contract Agreement',
                description: 'Legal contract for construction services',
                type: 'contract',
                project: 'Residential Complex',
                size: '3.2 MB',
                status: 'approved',
                uploaded_at: '3 days ago'
            },
            {
                id: 4,
                title: 'Progress Report Q1',
                description: 'Quarterly progress report for Q1 2024',
                type: 'report',
                project: 'Office Building Complex',
                size: '1.5 MB',
                status: 'review',
                uploaded_at: '5 days ago'
            },
            {
                id: 5,
                title: 'Site Photos - Week 12',
                description: 'Construction progress photos from week 12',
                type: 'photo',
                project: 'Shopping Mall Development',
                size: '8.7 MB',
                status: 'approved',
                uploaded_at: '1 day ago'
            }
        ],
        
        get filteredDocuments() {
            let filtered = this.documents;
            
            if (this.searchQuery) {
                filtered = filtered.filter(doc => 
                    doc.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    doc.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            
            if (this.selectedProject) {
                filtered = filtered.filter(doc => doc.project === this.getProjectName(this.selectedProject));
            }
            
            if (this.selectedType) {
                filtered = filtered.filter(doc => doc.type === this.selectedType);
            }
            
            return filtered;
        },
        
        getDocumentIcon(type) {
            const iconMap = {
                'drawing': '📐',
                'specification': '📋',
                'contract': '📜',
                'report': '📊',
                'photo': '📷',
                'other': '📁'
            };
            return iconMap[type] || '📄';
        },
        
        getStatusClass(status) {
            const classMap = {
                'approved': 'bg-green-100 text-green-800',
                'pending': 'bg-yellow-100 text-yellow-800',
                'review': 'bg-blue-100 text-blue-800',
                'rejected': 'bg-red-100 text-red-800'
            };
            return classMap[status] || 'bg-gray-100 text-gray-800';
        },
        
        getProjectName(projectId) {
            const projects = {
                '1': 'Office Building Complex',
                '2': 'Shopping Mall Development',
                '3': 'Residential Complex'
            };
            return projects[projectId] || '';
        },
        
        filterDocuments() {
            // Reactive filtering is handled by the computed property
        },
        
        clearFilters() {
            this.searchQuery = '';
            this.selectedProject = '';
            this.selectedType = '';
        },
        
        uploadDocument() {
            window.location.href = '/documents/create';
        },
        
        quickUpload() {
            // Quick upload functionality
            alert('Quick upload feature coming soon!');
        },
        
        viewDocument(document) {
            window.location.href = `/documents/${document.id}`;
        },
        
        editDocument(document) {
            window.location.href = `/documents/${document.id}/edit`;
        },
        
        deleteDocument(document) {
            if (confirm(`Are you sure you want to delete "${document.title}"?`)) {
                // Delete functionality
                alert('Document deleted successfully!');
            }
        }
    }
}
</script>
@endsection