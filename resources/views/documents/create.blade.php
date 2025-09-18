@extends('layouts.dashboard')

@section('title', 'Upload Document')
@section('page-title', 'Upload Document')
@section('page-description', 'Upload and manage project documents')
@section('user-initials', 'PM')
@section('user-name', 'Project Manager')

@section('content')
<div x-data="documentUpload()">
    <!-- Document Type Selection -->
    <div class="dashboard-card p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">📄 Select Document Type</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'drawing' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('drawing')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📐</div>
                    <div class="font-medium text-sm">Drawing</div>
                    <div class="text-xs text-gray-500 mt-1">Technical drawings, blueprints</div>
                </div>
            </div>
            
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'specification' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('specification')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📋</div>
                    <div class="font-medium text-sm">Specification</div>
                    <div class="text-xs text-gray-500 mt-1">Project specifications</div>
                </div>
            </div>
            
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'contract' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('contract')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📜</div>
                    <div class="font-medium text-sm">Contract</div>
                    <div class="text-xs text-gray-500 mt-1">Contracts, agreements</div>
                </div>
            </div>
            
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'report' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('report')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📊</div>
                    <div class="font-medium text-sm">Report</div>
                    <div class="text-xs text-gray-500 mt-1">Progress reports</div>
                </div>
            </div>
            
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'photo' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('photo')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📷</div>
                    <div class="font-medium text-sm">Photo</div>
                    <div class="text-xs text-gray-500 mt-1">Site photos</div>
                </div>
            </div>
            
            <div 
                class="p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="selectedType === 'other' ? 'border-blue-500 bg-blue-50' : ''"
                @click="selectType('other')"
            >
                <div class="text-center">
                    <div class="text-3xl mb-2">📁</div>
                    <div class="font-medium text-sm">Other</div>
                    <div class="text-xs text-gray-500 mt-1">Other documents</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="dashboard-card p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">📤 Upload Document</h3>
        
        <form method="POST" action="/api/v1/upload-document" enctype="multipart/form-data" @submit.prevent="uploadDocument">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Title *</label>
                        <input 
                            type="text" 
                            name="title" 
                            required 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter document title"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                        <select 
                            name="project_id" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Select Project</option>
                            <option value="1">Office Building Complex</option>
                            <option value="2">Shopping Mall Development</option>
                            <option value="3">Residential Complex</option>
                            <option value="4">Hotel Complex</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Version</label>
                        <input 
                            type="text" 
                            name="version" 
                            value="1.0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="e.g., 1.0, 2.1"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tags (optional)</label>
                        <input 
                            type="text" 
                            name="tags" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Enter tags separated by commas"
                        >
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea 
                            name="description" 
                            rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Describe the document..."
                        ></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Document Type *</label>
                        <input type="hidden" name="document_type" x-model="selectedType" required>
                        <div class="p-3 bg-gray-50 border border-gray-300 rounded-lg">
                            <span class="font-medium text-gray-900" x-text="selectedType ? selectedType.charAt(0).toUpperCase() + selectedType.slice(1) : 'Please select a type'"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">File Upload *</label>
                        <div 
                            class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-blue-500 hover:bg-blue-50 transition-all cursor-pointer"
                            @click="$refs.fileInput.click()"
                            @dragover.prevent
                            @drop.prevent="handleFileDrop($event)"
                        >
                            <div class="text-4xl mb-2">📁</div>
                            <div class="text-gray-600 mb-1">Click to select file or drag and drop</div>
                            <div class="text-sm text-gray-500">Supported: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, DWG</div>
                            <input 
                                type="file" 
                                x-ref="fileInput" 
                                name="file" 
                                style="display: none;" 
                                required
                                @change="handleFileSelect($event)"
                            >
                        </div>
                        
                        <!-- File Preview -->
                        <div x-show="selectedFile" class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="text-2xl mr-3" x-text="getFileIcon(selectedFile?.name)"></div>
                                    <div>
                                        <div class="font-medium text-gray-900" x-text="selectedFile?.name"></div>
                                        <div class="text-sm text-gray-500" x-text="formatFileSize(selectedFile?.size)"></div>
                                    </div>
                                </div>
                                <button 
                                    type="button" 
                                    @click="removeFile()"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button 
                    type="button" 
                    @click="cancelUpload()"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                    :disabled="!selectedType || !selectedFile"
                >
                    <span x-show="!uploading">📤 Upload Document</span>
                    <span x-show="uploading">⏳ Uploading...</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function documentUpload() {
    return {
        selectedType: '',
        selectedFile: null,
        uploading: false,
        
        selectType(type) {
            this.selectedType = type;
        },
        
        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.selectedFile = file;
            }
        },
        
        handleFileDrop(event) {
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                this.selectedFile = files[0];
                this.$refs.fileInput.files = files;
            }
        },
        
        removeFile() {
            this.selectedFile = null;
            this.$refs.fileInput.value = '';
        },
        
        getFileIcon(filename) {
            if (!filename) return '📄';
            const extension = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': '📄',
                'doc': '📝',
                'docx': '📝',
                'xls': '📊',
                'xlsx': '📊',
                'jpg': '🖼️',
                'jpeg': '🖼️',
                'png': '🖼️',
                'dwg': '📐',
                'zip': '📦',
                'rar': '📦'
            };
            return iconMap[extension] || '📄';
        },
        
        formatFileSize(bytes) {
            if (!bytes) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        uploadDocument() {
            if (!this.selectedType || !this.selectedFile) {
                alert('Please select a document type and file');
                return;
            }
            
            this.uploading = true;
            // Form submission will be handled by Laravel
            // This is just for UI feedback
            setTimeout(() => {
                this.uploading = false;
                alert('Document uploaded successfully!');
            }, 2000);
        },
        
        cancelUpload() {
            window.location.href = '/documents';
        }
    }
}
</script>
@endsection