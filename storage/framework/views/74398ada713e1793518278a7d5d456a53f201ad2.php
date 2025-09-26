<!-- Documents Content - Modern Design System -->
<style>
    [x-cloak] { display: none !important; }
    .animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
    
    /* Document Card Animations */
    .document-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }
    
    .document-card:hover {
        transform: translateY(-4px) scale(1.02);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    /* File Type Icons */
    .file-pdf { @apply text-red-500; }
    .file-doc { @apply text-blue-500; }
    .file-xls { @apply text-green-500; }
    .file-ppt { @apply text-orange-500; }
    .file-image { @apply text-purple-500; }
    .file-video { @apply text-pink-500; }
    .file-audio { @apply text-indigo-500; }
    .file-archive { @apply text-gray-500; }
    .file-other { @apply text-gray-400; }
    
    /* Status Colors */
    .status-draft { @apply bg-gray-100 text-gray-800 border-gray-200; }
    .status-review { @apply bg-yellow-100 text-yellow-800 border-yellow-200; }
    .status-approved { @apply bg-green-100 text-green-800 border-green-200; }
    .status-rejected { @apply bg-red-100 text-red-800 border-red-200; }
    .status-archived { @apply bg-blue-100 text-blue-800 border-blue-200; }
    
    /* Upload Zone */
    .upload-zone {
        border: 2px dashed #3B82F6;
        background-color: #EBF8FF;
        transition: all 0.3s ease;
    }
    
    .upload-zone:hover {
        border-color: #1D4ED8;
        background-color: #DBEAFE;
    }
    
    .upload-zone.dragover {
        border-color: #1D4ED8;
        background-color: #DBEAFE;
        transform: scale(1.02);
    }
</style>

<div x-data="documentsPage()" x-init="loadDocuments()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Documents</h1>
            <p class="mt-1 text-sm text-gray-500">Manage and organize your documents</p>
        </div>
        <div class="mt-4 sm:mt-0 flex space-x-3">
            <button @click="toggleView()" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i :class="viewMode === 'grid' ? 'fas fa-list' : 'fas fa-th-large'" class="mr-2"></i>
                <span x-text="viewMode === 'grid' ? 'List View' : 'Grid View'"></span>
            </button>
            <button @click="showFilters = !showFilters" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <i class="fas fa-filter mr-2"></i>
                Filters
            </button>
            <button @click="showUploadModal = true" 
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <i class="fas fa-upload mr-2"></i>
                Upload
            </button>
        </div>
    </div>

    <!-- Global Search & Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <!-- Search Bar -->
        <div class="relative mb-4">
            <input type="text" 
                   placeholder="Search documents..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   x-model="searchQuery"
                   @input="debounceSearch()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>

        <!-- Filter Panel -->
        <div x-show="showFilters" x-transition class="border-t pt-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- File Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">File Type</label>
                    <select x-model="filters.fileType" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="pdf">PDF</option>
                        <option value="doc">Word Document</option>
                        <option value="xls">Excel Spreadsheet</option>
                        <option value="ppt">PowerPoint</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select x-model="filters.status" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="review">Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                <!-- Project Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                    <select x-model="filters.project" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Projects</option>
                        <option value="website">Website Redesign</option>
                        <option value="mobile">Mobile App</option>
                        <option value="marketing">Marketing Campaign</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <select x-model="filters.dateRange" @change="applyFilters()" 
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>

            <!-- Active Filters -->
            <div x-show="activeFiltersCount > 0" class="mt-4">
                <div class="flex flex-wrap gap-2">
                    <template x-for="filter in activeFilters" :key="filter.key">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                            <span x-text="filter.label + ': ' + filter.value"></span>
                            <button @click="removeFilter(filter.key)" class="ml-2 hover:text-blue-600">×</button>
                        </span>
                    </template>
                    <button @click="clearAllFilters()" 
                            class="text-sm text-gray-500 hover:text-gray-700 underline">
                        Clear all
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="space-y-4">
        <template x-for="i in 6" :key="i">
            <div class="bg-white rounded-lg shadow p-6 animate-pulse">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gray-200 rounded-lg"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                    </div>
                    <div class="w-20 h-6 bg-gray-200 rounded"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading documents</p>
                <p x-text="error"></p>
                <button @click="loadDocuments()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Documents Grid -->
    <div x-show="!loading && !error" 
         :class="viewMode === 'grid' ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6' : 'space-y-4'">
        <template x-for="document in filteredDocuments" :key="document.id">
            <div class="document-card bg-white rounded-lg shadow p-6 cursor-pointer" 
                 @click="viewDocument(document.id)">
                
                <!-- Document Icon -->
                <div class="text-4xl mb-4" :class="'file-' + document.fileType">
                    <i :class="getFileIcon(document.mimeType)"></i>
                </div>
                
                <!-- Document Info -->
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2" x-text="document.name"></h3>
                    <p class="text-sm text-gray-500 mb-2" x-text="document.description"></p>
                    
                    <!-- Document Meta -->
                    <div class="flex items-center space-x-4 text-sm text-gray-500 mb-3">
                        <span><i class="fas fa-file mr-1"></i><span x-text="formatFileSize(document.size)"></span></span>
                        <span><i class="fas fa-calendar mr-1"></i><span x-text="document.uploadedAt"></span></span>
                        <span><i class="fas fa-user mr-1"></i><span x-text="document.uploadedBy"></span></span>
                    </div>
                    
                    <!-- Status Badge -->
                    <span class="px-2 py-1 text-xs font-medium rounded-full border mb-4"
                          :class="'status-' + document.status"
                          x-text="document.status"></span>
                    
                    <!-- Actions -->
                    <div class="flex space-x-2">
                        <button @click.stop="downloadDocument(document.id)" 
                                class="flex-1 bg-gray-100 text-gray-700 px-3 py-2 rounded text-sm hover:bg-gray-200">
                            <i class="fas fa-download mr-1"></i>
                            Download
                        </button>
                        <button @click.stop="shareDocument(document.id)" 
                                class="flex-1 bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
                            <i class="fas fa-share mr-1"></i>
                            Share
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="!loading && !error && filteredDocuments.length === 0" class="text-center py-12">
        <i class="fas fa-file-alt text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No documents found</h3>
        <p class="text-gray-500 mb-4">Get started by uploading your first document.</p>
        <button @click="showUploadModal = true" 
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class="fas fa-upload mr-2"></i>
            Upload Document
        </button>
    </div>
</div><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/documents-content.blade.php ENDPATH**/ ?>