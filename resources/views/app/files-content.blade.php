<!-- Files Management Content -->
<div x-data="filesManager()" x-init="init()" class="space-y-6 mobile-content">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">File Management</h1>
            <p class="mt-1 text-sm text-gray-500">Upload, organize, and manage your files</p>
        </div>
        
        <!-- Actions -->
        <div class="mt-4 sm:mt-0 flex items-center space-x-3">
            <!-- Upload Button -->
            <button @click="showUploadModal = true" 
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-upload mr-2"></i>
                Upload Files
            </button>
            
            <!-- View Toggle -->
            <div class="flex rounded-md shadow-sm">
                <button @click="viewMode = 'grid'" 
                        :class="viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-3 py-2 text-sm font-medium border border-gray-300 rounded-l-md focus:z-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <i class="fas fa-th"></i>
                </button>
                <button @click="viewMode = 'list'" 
                        :class="viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-3 py-2 text-sm font-medium border border-gray-300 rounded-r-md focus:z-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white shadow rounded-lg p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="md:col-span-2">
                <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                <div class="mt-1 relative">
                    <input type="text" 
                           x-model="filters.search" 
                           @input.debounce.300ms="loadFiles()"
                           placeholder="Search files..."
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                </div>
            </div>
            
            <!-- Type Filter -->
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                <select x-model="filters.type" 
                        @change="loadFiles()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="">All Types</option>
                    <option value="document">Documents</option>
                    <option value="image">Images</option>
                    <option value="video">Videos</option>
                    <option value="audio">Audio</option>
                    <option value="archive">Archives</option>
                    <option value="code">Code</option>
                </select>
            </div>
            
            <!-- Category Filter -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                <select x-model="filters.category" 
                        @change="loadFiles()"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="">All Categories</option>
                    <option value="project">Project Files</option>
                    <option value="task">Task Files</option>
                    <option value="template">Templates</option>
                    <option value="general">General</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-3 text-gray-600">Loading files...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error loading files</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p x-text="error"></p>
                </div>
                <div class="mt-4">
                    <button @click="loadFiles()" 
                            class="bg-red-100 px-3 py-2 rounded-md text-sm font-medium text-red-800 hover:bg-red-200">
                        Try again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Files Content -->
    <div x-show="!loading && !error" class="space-y-6">
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file text-blue-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Files</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="stats.total_files || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-hdd text-green-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Size</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="formatBytes(stats.total_size || 0)"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-upload text-purple-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Recent Uploads</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="stats.recent_uploads || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-download text-orange-500 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Most Downloaded</dt>
                                <dd class="text-lg font-medium text-gray-900" x-text="stats.most_downloaded?.length || 0"></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Files Grid View -->
        <div x-show="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
            <template x-for="file in files" :key="file.id">
                <div class="bg-white rounded-lg shadow hover:shadow-md transition-shadow duration-200 cursor-pointer"
                     @click="selectFile(file)">
                    <div class="p-4">
                        <!-- File Icon -->
                        <div class="flex justify-center mb-3">
                            <div class="w-12 h-12 flex items-center justify-center rounded-lg bg-gray-100">
                                <i :class="getFileIcon(file.extension)" class="text-2xl"></i>
                            </div>
                        </div>
                        
                        <!-- File Name -->
                        <h3 class="text-sm font-medium text-gray-900 truncate" x-text="file.name"></h3>
                        
                        <!-- File Size -->
                        <p class="text-xs text-gray-500 mt-1" x-text="formatBytes(file.size)"></p>
                        
                        <!-- File Type -->
                        <p class="text-xs text-gray-400 mt-1 capitalize" x-text="file.type"></p>
                    </div>
                    
                    <!-- Actions -->
                    <div class="px-4 py-2 bg-gray-50 rounded-b-lg flex justify-between items-center">
                        <button @click.stop="downloadFile(file)" 
                                class="text-gray-400 hover:text-gray-600" 
                                title="Download">
                            <i class="fas fa-download"></i>
                        </button>
                        <button @click.stop="previewFile(file)" 
                                class="text-gray-400 hover:text-gray-600" 
                                title="Preview">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button @click.stop="showFileVersions(file)" 
                                class="text-gray-400 hover:text-gray-600" 
                                title="Versions">
                            <i class="fas fa-history"></i>
                        </button>
                        <button @click.stop="deleteFile(file)" 
                                class="text-gray-400 hover:text-red-600" 
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Files List View -->
        <div x-show="viewMode === 'list'" class="bg-white shadow overflow-hidden sm:rounded-md">
            <ul class="divide-y divide-gray-200">
                <template x-for="file in files" :key="file.id">
                    <li class="hover:bg-gray-50">
                        <div class="px-4 py-4 flex items-center justify-between">
                            <div class="flex items-center min-w-0 flex-1">
                                <!-- File Icon -->
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                                        <i :class="getFileIcon(file.extension)" class="text-lg"></i>
                                    </div>
                                </div>
                                
                                <!-- File Info -->
                                <div class="ml-4 min-w-0 flex-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="file.name"></p>
                                            <p class="text-sm text-gray-500">
                                                <span x-text="formatBytes(file.size)"></span>
                                                <span class="mx-1">•</span>
                                                <span class="capitalize" x-text="file.type"></span>
                                                <span x-show="file.category" class="mx-1">•</span>
                                                <span x-show="file.category" class="capitalize" x-text="file.category"></span>
                                            </p>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="flex items-center space-x-2">
                                            <button @click="downloadFile(file)" 
                                                    class="text-gray-400 hover:text-gray-600" 
                                                    title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button @click="previewFile(file)" 
                                                    class="text-gray-400 hover:text-gray-600" 
                                                    title="Preview">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button @click="showFileVersions(file)" 
                                                    class="text-gray-400 hover:text-gray-600" 
                                                    title="Versions">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button @click="deleteFile(file)" 
                                                    class="text-gray-400 hover:text-red-600" 
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <!-- Empty State -->
        <div x-show="files.length === 0" class="text-center py-12">
            <i class="fas fa-folder-open text-gray-400 text-6xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No files found</h3>
            <p class="text-gray-500 mb-4">Get started by uploading your first file.</p>
            <button @click="showUploadModal = true" 
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-upload mr-2"></i>
                Upload Files
            </button>
        </div>
    </div>

    <!-- Upload Modal -->
    <div x-show="showUploadModal" 
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showUploadModal = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form @submit.prevent="uploadFiles()" enctype="multipart/form-data">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-upload text-blue-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Upload Files</h3>
                                <div class="mt-4">
                                    <input type="file" 
                                           x-ref="fileInput"
                                           @change="handleFileSelect($event)"
                                           multiple
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    
                                    <!-- Selected Files -->
                                    <div x-show="selectedFiles.length > 0" class="mt-4">
                                        <h4 class="text-sm font-medium text-gray-900 mb-2">Selected Files:</h4>
                                        <div class="space-y-2 max-h-32 overflow-y-auto">
                                            <template x-for="(file, index) in selectedFiles" :key="index">
                                                <div class="flex items-center justify-between text-sm">
                                                    <span class="text-gray-700" x-text="file.name"></span>
                                                    <span class="text-gray-500" x-text="formatBytes(file.size)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload Options -->
                                    <div class="mt-4 space-y-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Category</label>
                                            <select x-model="uploadOptions.category" 
                                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                <option value="">General</option>
                                                <option value="project">Project Files</option>
                                                <option value="task">Task Files</option>
                                                <option value="template">Templates</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="flex items-center">
                                                <input type="checkbox" 
                                                       x-model="uploadOptions.is_public" 
                                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                                <span class="ml-2 text-sm text-gray-700">Make files public</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                :disabled="uploading || selectedFiles.length === 0"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <i x-show="uploading" class="fas fa-spinner fa-spin mr-2"></i>
                            <span x-text="uploading ? 'Uploading...' : 'Upload Files'"></span>
                        </button>
                        <button type="button" 
                                @click="showUploadModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('filesManager', () => ({
        loading: true,
        error: null,
        files: [],
        stats: {},
        viewMode: 'grid',
        showUploadModal: false,
        uploading: false,
        selectedFiles: [],
        uploadOptions: {
            category: '',
            is_public: false
        },
        filters: {
            search: '',
            type: '',
            category: ''
        },

        async init() {
            await Promise.all([
                this.loadFiles(),
                this.loadStats()
            ]);
        },

        async loadFiles() {
            this.loading = true;
            this.error = null;
            
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value) params.append(key, value);
                });
                
                const response = await fetch(`/api/v1/app/files?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    this.files = data.data;
                } else {
                    this.error = data.error?.message || 'Failed to load files';
                }
            } catch (error) {
                console.error('Files loading error:', error);
                this.error = 'Failed to load files';
            } finally {
                this.loading = false;
            }
        },

        async loadStats() {
            try {
                const response = await fetch('/api/v1/app/files/stats');
                const data = await response.json();
                
                if (data.success) {
                    this.stats = data.data;
                }
            } catch (error) {
                console.error('Stats loading error:', error);
            }
        },

        handleFileSelect(event) {
            this.selectedFiles = Array.from(event.target.files);
        },

        async uploadFiles() {
            if (this.selectedFiles.length === 0) return;
            
            this.uploading = true;
            
            try {
                const formData = new FormData();
                
                this.selectedFiles.forEach(file => {
                    formData.append('files[]', file);
                });
                
                Object.entries(this.uploadOptions).forEach(([key, value]) => {
                    if (value) formData.append(key, value);
                });
                
                const response = await fetch('/api/v1/app/files/upload', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showUploadModal = false;
                    this.selectedFiles = [];
                    this.uploadOptions = { category: '', is_public: false };
                    this.$refs.fileInput.value = '';
                    await this.loadFiles();
                    await this.loadStats();
                } else {
                    this.error = data.error?.message || 'Upload failed';
                }
            } catch (error) {
                console.error('Upload error:', error);
                this.error = 'Upload failed';
            } finally {
                this.uploading = false;
            }
        },

        async downloadFile(file) {
            try {
                const response = await fetch(`/api/v1/app/files/${file.id}/download`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = file.original_name;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                }
            } catch (error) {
                console.error('Download error:', error);
            }
        },

        async previewFile(file) {
            if (file.is_previewable) {
                window.open(`/api/v1/app/files/${file.id}/preview`, '_blank');
            } else {
                this.downloadFile(file);
            }
        },

        async deleteFile(file) {
            if (!confirm(`Are you sure you want to delete "${file.name}"?`)) {
                return;
            }
            
            try {
                const response = await fetch(`/api/v1/app/files/${file.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    await this.loadFiles();
                    await this.loadStats();
                } else {
                    this.error = data.error?.message || 'Delete failed';
                }
            } catch (error) {
                console.error('Delete error:', error);
                this.error = 'Delete failed';
            }
        },

        async showFileVersions(file) {
            // Implementation for showing file versions
            console.log('Show versions for file:', file.id);
        },

        selectFile(file) {
            // Implementation for file selection
            console.log('Selected file:', file);
        },

        getFileIcon(extension) {
            const iconMap = {
                'pdf': 'fas fa-file-pdf text-red-500',
                'doc': 'fas fa-file-word text-blue-500',
                'docx': 'fas fa-file-word text-blue-500',
                'txt': 'fas fa-file-alt text-gray-500',
                'jpg': 'fas fa-file-image text-purple-500',
                'jpeg': 'fas fa-file-image text-purple-500',
                'png': 'fas fa-file-image text-purple-500',
                'gif': 'fas fa-file-image text-purple-500',
                'mp4': 'fas fa-file-video text-red-600',
                'mp3': 'fas fa-file-audio text-yellow-500',
                'zip': 'fas fa-file-archive text-gray-600',
                'js': 'fas fa-file-code text-yellow-400',
                'php': 'fas fa-file-code text-purple-400',
                'html': 'fas fa-file-code text-orange-400',
                'css': 'fas fa-file-code text-blue-400'
            };
            
            return iconMap[extension.toLowerCase()] || 'fas fa-file text-gray-500';
        },

        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }));
});
</script>
