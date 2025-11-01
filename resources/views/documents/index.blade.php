@extends('layouts.app')

@section('title', 'Smart Documents Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div x-data="smartDocumentsDashboard()">
            <!-- Documents Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Documents</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.totalDocuments || {{ $mockDocuments->count() }}"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.approvedDocuments || {{ $mockDocuments->where('status', 'approved')->count() }}"></span> approved
                            </p>
                        </div>
                        <i class="fas fa-file-alt text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Pending Review</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.pendingDocuments || {{ $mockDocuments->where('status', 'pending')->count() }}"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.uploadedThisWeek || 3"></span> uploaded this week
                            </p>
                        </div>
                        <i class="fas fa-clock text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Storage Used</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.storageUsed || '23.8'"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.totalDownloads || 81"></span> total downloads
                            </p>
                        </div>
                        <i class="fas fa-hdd text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">File Types</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.fileTypes || 8"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.categories || 7"></span> categories
                            </p>
                        </div>
                        <i class="fas fa-layer-group text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Smart Upload Section -->
            <div class="dashboard-card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Smart Upload</h3>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Drag & drop files or click to browse</span>
                    </div>
                </div>
                
                <!-- Upload Area -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-400 transition-colors"
                     @dragover.prevent="dragOver = true"
                     @dragleave.prevent="dragOver = false"
                     @drop.prevent="handleFileDrop($event)"
                     :class="{ 'border-blue-400 bg-blue-50': dragOver }"
                     @click="openFileDialog()">
                    <div class="space-y-4">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400"></i>
                        <div>
                            <p class="text-lg font-medium text-gray-900">Drop files here or click to upload</p>
                            <p class="text-sm text-gray-500">Supports PDF, DOCX, XLSX, PSD, FIGMA, MD, SQL files up to 50MB</p>
                        </div>
                        <div class="flex justify-center space-x-4">
                            <button class="zena-btn zena-btn-primary" @click.stop="openFileDialog()">
                                <i class="fas fa-upload mr-2"></i>
                                Choose Files
                            </button>
                            <button class="zena-btn zena-btn-outline" @click.stop="openFolderDialog()">
                                <i class="fas fa-folder mr-2"></i>
                                Upload Folder
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden file input -->
                <input type="file" 
                       ref="fileInput" 
                       @change="handleFileSelect($event)" 
                       multiple 
                       accept=".pdf,.docx,.xlsx,.psd,.figma,.md,.sql,.txt,.jpg,.png,.gif"
                       class="hidden">
                
                <!-- Upload Progress -->
                <div x-show="uploading" class="mt-4">
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                             :style="`width: ${uploadProgress}%`"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2" x-text="`Uploading... ${uploadProgress}%`"></p>
                </div>
            </div>

            <!-- Advanced Filters -->
            <div class="dashboard-card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Advanced Filters</h3>
                    <button class="zena-btn zena-btn-outline zena-btn-sm" @click="resetFilters()">
                        <i class="fas fa-refresh mr-2"></i>
                        Reset Filters
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="relative">
                        <input type="text" 
                               placeholder="Search documents..." 
                               class="zena-input w-full" 
                               x-model="searchQuery"
                               @input="filterDocuments()">
                        <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    
                    <!-- Category Filter -->
                    <select class="zena-select" x-model="categoryFilter" @change="filterDocuments()">
                        @foreach($fileCategories as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    
                    <!-- File Type Filter -->
                    <select class="zena-select" x-model="typeFilter" @change="filterDocuments()">
                        @foreach($fileTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    
                    <!-- Status Filter -->
                    <select class="zena-select" x-model="statusFilter" @change="filterDocuments()">
                        <option value="">All Status</option>
                        <option value="approved">Approved</option>
                        <option value="pending">Pending</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                
                <!-- Tag Filter -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Tags:</label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="tag in availableTags" :key="tag">
                            <button class="px-3 py-1 text-xs font-medium rounded-full border transition-colors"
                                    :class="selectedTags.includes(tag) ? 'bg-blue-100 text-blue-800 border-blue-200' : 'bg-gray-100 text-gray-700 border-gray-200 hover:bg-gray-200'"
                                    @click="toggleTag(tag)">
                                <i class="fas fa-tag mr-1"></i>
                                <span x-text="tag"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Documents Overview Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Documents -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Documents</h3>
                        <div class="flex items-center gap-2">
                            <a href="/projects" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-project-diagram mr-2"></i>
                                View Projects
                            </a>
                            <a href="/tasks" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-tasks mr-2"></i>
                                View Tasks
                            </a>
                            <div class="relative group">
                                <button class="zena-btn zena-btn-outline zena-btn-sm">
                                    <i class="fas fa-download mr-2"></i>
                                    Export
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                                    <div class="py-1">
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportDocuments('excel')">
                                            <i class="fas fa-file-excel mr-2 text-green-600"></i>
                                            Excel (.xlsx)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportDocuments('pdf')">
                                            <i class="fas fa-file-pdf mr-2 text-red-600"></i>
                                            PDF (.pdf)
                                        </a>
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" @click.prevent="exportDocuments('zip')">
                                            <i class="fas fa-file-archive mr-2 text-blue-600"></i>
                                            ZIP Archive
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        @forelse($mockDocuments->take(4) as $document)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center" 
                                     style="background-color: {{ $document['file_type'] === 'pdf' ? '#ef4444' : ($document['file_type'] === 'docx' ? '#2563eb' : ($document['file_type'] === 'xlsx' ? '#16a34a' : ($document['file_type'] === 'figma' ? '#8b5cf6' : '#6b7280'))) }}20">
                                    @php
                                        $fileIcons = [
                                            'pdf' => 'fas fa-file-pdf text-red-600',
                                            'docx' => 'fas fa-file-word text-blue-600',
                                            'xlsx' => 'fas fa-file-excel text-green-600',
                                            'figma' => 'fas fa-figma text-purple-600',
                                            'psd' => 'fas fa-image text-pink-600',
                                            'md' => 'fas fa-file-alt text-gray-600',
                                            'sql' => 'fas fa-database text-orange-600',
                                            'default' => 'fas fa-file text-gray-600'
                                        ];
                                        $iconClass = $fileIcons[$document['file_type']] ?? $fileIcons['default'];
                                    @endphp
                                    <i class="{{ $iconClass }}"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $document['name'] }}</h4>
                                    <p class="text-sm text-gray-600">{{ $document['file_size'] }} • {{ \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') }}</p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                            {{ ucfirst($document['category']) }}
                                        </span>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                            v{{ $document['version'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                @php
                                    $statusConfig = [
                                        'approved' => ['color' => 'bg-green-100 text-green-800', 'text' => 'Approved'],
                                        'pending' => ['color' => 'bg-yellow-100 text-yellow-800', 'text' => 'Pending'],
                                        'rejected' => ['color' => 'bg-red-100 text-red-800', 'text' => 'Rejected'],
                                    ];
                                    $status = $statusConfig[$document['status']] ?? ['color' => 'bg-gray-100 text-gray-800', 'text' => 'Unknown'];
                                @endphp
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $status['color'] }}">
                                    {{ $status['text'] }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ $document['download_count'] }}</span>
                            </div>
                        </div>
                        @empty
                        <div class="text-center py-8">
                            <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No documents found</p>
                        </div>
                        @endforelse
                    </div>
                </div>

                <!-- Document Analytics -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Document Analytics</h3>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                            <i class="fas fa-arrow-up mr-1"></i>+15.3%
                        </span>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average File Size</span>
                            <span class="text-lg font-semibold text-gray-900">2.9 MB</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Approval Rate</span>
                            <span class="text-lg font-semibold text-gray-900">75%</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Average Review Time</span>
                            <span class="text-lg font-semibold text-gray-900">2.1 days</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 75%"></div>
                        </div>
                        <p class="text-xs text-gray-500">75% of documents approved within 3 days</p>
                        
                        <!-- File Type Distribution -->
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-3">File Type Distribution</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">PDF Documents</span>
                                    <span class="text-sm font-medium text-gray-900">25%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Design Files</span>
                                    <span class="text-sm font-medium text-gray-900">20%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Office Documents</span>
                                    <span class="text-sm font-medium text-gray-900">30%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Other</span>
                                    <span class="text-sm font-medium text-gray-900">25%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Items Section -->
            <div class="dashboard-card p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Access</h3>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500">Navigate to related sections</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Projects Card -->
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/projects'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-blue-900">My Projects</h4>
                                <p class="text-sm text-blue-700">View project documents</p>
                                <div class="mt-2 flex items-center text-sm text-blue-600">
                                    <i class="fas fa-project-diagram mr-2"></i>
                                    <span>5 active projects</span>
                                </div>
                            </div>
                            <i class="fas fa-project-diagram text-3xl text-blue-500"></i>
                        </div>
                    </div>
                    
                    <!-- Tasks Card -->
                    <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/tasks'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-green-900">Task Documents</h4>
                                <p class="text-sm text-green-700">View task-related files</p>
                                <div class="mt-2 flex items-center text-sm text-green-600">
                                    <i class="fas fa-tasks mr-2"></i>
                                    <span>8 task documents</span>
                                </div>
                            </div>
                            <i class="fas fa-tasks text-3xl text-green-500"></i>
                        </div>
                    </div>
                    
                    <!-- Team Card -->
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer" onclick="window.location.href='/team'">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-purple-900">Team Documents</h4>
                                <p class="text-sm text-purple-700">View shared documents</p>
                                <div class="mt-2 flex items-center text-sm text-purple-600">
                                    <i class="fas fa-users mr-2"></i>
                                    <span>15 shared files</span>
                                </div>
                            </div>
                            <i class="fas fa-users text-3xl text-purple-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Documents Table -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">All Documents</h3>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center space-x-2">
                            <button class="zena-btn zena-btn-outline zena-btn-sm" @click="selectAll()">
                                <i class="fas fa-check-square mr-2"></i>
                                Select All
                            </button>
                            <button class="zena-btn zena-btn-outline zena-btn-sm" @click="downloadSelected()" :disabled="selectedDocuments.length === 0">
                                <i class="fas fa-download mr-2"></i>
                                Download Selected (<span x-text="selectedDocuments.length"></span>)
                            </button>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button class="zena-btn zena-btn-outline zena-btn-sm" @click="viewMode = 'grid'" :class="{ 'zena-btn-primary': viewMode === 'grid' }">
                                <i class="fas fa-th mr-2"></i>
                                Grid
                            </button>
                            <button class="zena-btn zena-btn-outline zena-btn-sm" @click="viewMode = 'list'" :class="{ 'zena-btn-primary': viewMode === 'list' }">
                                <i class="fas fa-list mr-2"></i>
                                List
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grid View -->
                <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    @forelse($mockDocuments as $document)
                    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                         @click="toggleDocumentSelection('{{ $document['id'] }}')"
                         :class="{ 'ring-2 ring-blue-500': selectedDocuments.includes('{{ $document['id'] }}') }">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 rounded-lg flex items-center justify-center" 
                                 style="background-color: {{ $document['file_type'] === 'pdf' ? '#ef4444' : ($document['file_type'] === 'docx' ? '#2563eb' : ($document['file_type'] === 'xlsx' ? '#16a34a' : ($document['file_type'] === 'figma' ? '#8b5cf6' : '#6b7280'))) }}20">
                                @php
                                    $fileIcons = [
                                        'pdf' => 'fas fa-file-pdf text-red-600',
                                        'docx' => 'fas fa-file-word text-blue-600',
                                        'xlsx' => 'fas fa-file-excel text-green-600',
                                        'figma' => 'fas fa-figma text-purple-600',
                                        'psd' => 'fas fa-image text-pink-600',
                                        'md' => 'fas fa-file-alt text-gray-600',
                                        'sql' => 'fas fa-database text-orange-600',
                                        'default' => 'fas fa-file text-gray-600'
                                    ];
                                    $iconClass = $fileIcons[$document['file_type']] ?? $fileIcons['default'];
                                @endphp
                                <i class="{{ $iconClass }} text-xl"></i>
                            </div>
                            <div class="flex items-center space-x-1">
                                <input type="checkbox" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       :checked="selectedDocuments.includes('{{ $document['id'] }}')"
                                       @click.stop="toggleDocumentSelection('{{ $document['id'] }}')">
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <h4 class="font-medium text-gray-900 text-sm line-clamp-2">{{ $document['name'] }}</h4>
                            <p class="text-xs text-gray-500">{{ $document['file_size'] }}</p>
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                    {{ ucfirst($document['category']) }}
                                </span>
                                <span class="text-xs text-gray-500">v{{ $document['version'] }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>{{ $document['download_count'] }} downloads</span>
                                <span>{{ \Carbon\Carbon::parse($document['uploaded_at'])->format('M d') }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full text-center py-8">
                        <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No documents found</p>
                    </div>
                    @endforelse
                </div>

                <!-- List View -->
                <div x-show="viewMode === 'list'" class="overflow-x-auto">
                    <table class="zena-table">
                        <thead>
                            <tr>
                                <th class="w-12">
                                    <input type="checkbox" 
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           @change="toggleAllDocuments()"
                                           :checked="selectedDocuments.length === {{ $mockDocuments->count() }}">
                                </th>
                                <th>Document Name</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Project</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Downloads</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($mockDocuments as $document)
                            <tr class="hover:bg-gray-50">
                                <td>
                                    <input type="checkbox" 
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                           :checked="selectedDocuments.includes('{{ $document['id'] }}')"
                                           @change="toggleDocumentSelection('{{ $document['id'] }}')">
                                </td>
                                <td>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" 
                                             style="background-color: {{ $document['file_type'] === 'pdf' ? '#ef4444' : ($document['file_type'] === 'docx' ? '#2563eb' : ($document['file_type'] === 'xlsx' ? '#16a34a' : ($document['file_type'] === 'figma' ? '#8b5cf6' : '#6b7280'))) }}20">
                                            @php
                                                $fileIcons = [
                                                    'pdf' => 'fas fa-file-pdf text-red-600',
                                                    'docx' => 'fas fa-file-word text-blue-600',
                                                    'xlsx' => 'fas fa-file-excel text-green-600',
                                                    'figma' => 'fas fa-figma text-purple-600',
                                                    'psd' => 'fas fa-image text-pink-600',
                                                    'md' => 'fas fa-file-alt text-gray-600',
                                                    'sql' => 'fas fa-database text-orange-600',
                                                    'default' => 'fas fa-file text-gray-600'
                                                ];
                                                $iconClass = $fileIcons[$document['file_type']] ?? $fileIcons['default'];
                                            @endphp
                                            <i class="{{ $iconClass }} text-sm"></i>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900">{{ $document['name'] }}</div>
                                            <div class="text-sm text-gray-500">{{ Str::limit($document['description'], 50) }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full uppercase">
                                        {{ $document['file_type'] }}
                                    </span>
                                </td>
                                <td>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                        {{ ucfirst($document['category']) }}
                                    </span>
                                </td>
                                <td>
                                    @php
                                        $statusConfig = [
                                            'approved' => ['color' => 'zena-badge-success', 'text' => 'Approved'],
                                            'pending' => ['color' => 'zena-badge-warning', 'text' => 'Pending'],
                                            'rejected' => ['color' => 'zena-badge-danger', 'text' => 'Rejected'],
                                        ];
                                        $status = $statusConfig[$document['status']] ?? ['color' => 'zena-badge-neutral', 'text' => 'Unknown'];
                                    @endphp
                                    <span class="zena-badge {{ $status['color'] }}">
                                        {{ $status['text'] }}
                                    </span>
                                </td>
                                <td class="text-sm text-gray-600">{{ $document['project_name'] }}</td>
                                <td class="text-sm text-gray-600">{{ $document['uploaded_by'] }}</td>
                                <td class="text-sm text-gray-600">{{ \Carbon\Carbon::parse($document['uploaded_at'])->format('M d, Y') }}</td>
                                <td class="text-sm font-medium text-gray-900">{{ $document['download_count'] }}</td>
                                <td>
                                    <div class="flex items-center space-x-2">
                                        <button class="zena-btn zena-btn-outline zena-btn-sm" title="Download" @click.stop="downloadDocument('{{ $document['id'] }}')">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="zena-btn zena-btn-outline zena-btn-sm" title="Preview" @click.stop="previewDocument('{{ $document['id'] }}')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        @if($document['status'] === 'pending')
                                        <button class="zena-btn zena-btn-outline zena-btn-sm zena-btn-success" title="Approve" @click.stop="approveDocument('{{ $document['id'] }}')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger" title="Reject" @click.stop="rejectDocument('{{ $document['id'] }}')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        @endif
                                        <button class="zena-btn zena-btn-outline zena-btn-sm zena-btn-danger" title="Delete" @click.stop="deleteDocument('{{ $document['id'] }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center py-8">
                                    <i class="fas fa-file-alt text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500">No documents found</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span>Showing</span>
                        <select class="mx-2 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>of <span class="font-medium">{{ $mockDocuments->count() }}</span> documents</span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-left mr-1"></i>
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-1">
                            <button class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700">
                                1
                            </button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                2
                            </button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                3
                            </button>
                            <span class="px-2 py-2 text-sm text-gray-500">...</span>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                                10
                            </button>
                        </div>
                        
                        <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                            Next
                            <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function smartDocumentsDashboard() {
    return {
        // Upload functionality
        dragOver: false,
        uploading: false,
        uploadProgress: 0,
        
        // Filtering
        searchQuery: '',
        categoryFilter: 'all',
        typeFilter: 'all',
        statusFilter: '',
        selectedTags: [],
        availableTags: ['requirements', 'technical', 'design', 'documentation', 'security', 'database', 'testing', 'website', 'planning', 'specifications', 'hr', 'wireframes', 'mobile', 'ui', 'api', 'integration', 'audit', 'report', 'schema', 'sql', 'mockups', 'photoshop', 'test-cases', 'qa'],
        
        // Selection
        selectedDocuments: [],
        viewMode: 'grid',
        
        // Stats
        stats: {
            totalDocuments: {{ $mockDocuments->count() }},
            approvedDocuments: {{ $mockDocuments->where('status', 'approved')->count() }},
            pendingDocuments: {{ $mockDocuments->where('status', 'pending')->count() }},
            rejectedDocuments: {{ $mockDocuments->where('status', 'rejected')->count() }},
            uploadedThisWeek: 3,
            storageUsed: '23.8',
            totalDownloads: 81,
            fileTypes: 8,
            categories: 7
        },
        
        // Upload methods
        openFileDialog() {
            this.$refs.fileInput.click();
        },
        
        openFolderDialog() {
            // For folder upload (would need additional implementation)
            console.log('Folder upload not implemented yet');
        },
        
        handleFileSelect(event) {
            const files = Array.from(event.target.files);
            this.uploadFiles(files);
        },
        
        handleFileDrop(event) {
            this.dragOver = false;
            const files = Array.from(event.dataTransfer.files);
            this.uploadFiles(files);
        },
        
        uploadFiles(files) {
            this.uploading = true;
            this.uploadProgress = 0;
            
            // Simulate upload progress
            const interval = setInterval(() => {
                this.uploadProgress += Math.random() * 30;
                if (this.uploadProgress >= 100) {
                    this.uploadProgress = 100;
                    clearInterval(interval);
                    setTimeout(() => {
                        this.uploading = false;
                        this.uploadProgress = 0;
                        console.log('Files uploaded successfully');
                    }, 500);
                }
            }, 200);
        },
        
        // Filter methods
        filterDocuments() {
            console.log('Filtering documents...');
            // Implementation would filter the documents based on current filters
        },
        
        resetFilters() {
            this.searchQuery = '';
            this.categoryFilter = 'all';
            this.typeFilter = 'all';
            this.statusFilter = '';
            this.selectedTags = [];
            this.filterDocuments();
        },
        
        toggleTag(tag) {
            const index = this.selectedTags.indexOf(tag);
            if (index > -1) {
                this.selectedTags.splice(index, 1);
            } else {
                this.selectedTags.push(tag);
            }
            this.filterDocuments();
        },
        
        // Selection methods
        toggleDocumentSelection(documentId) {
            const index = this.selectedDocuments.indexOf(documentId);
            if (index > -1) {
                this.selectedDocuments.splice(index, 1);
            } else {
                this.selectedDocuments.push(documentId);
            }
        },
        
        selectAll() {
            this.selectedDocuments = [];
            // In real implementation, would select all visible documents
        },
        
        toggleAllDocuments() {
            if (this.selectedDocuments.length === {{ $mockDocuments->count() }}) {
                this.selectedDocuments = [];
            } else {
                // Select all documents
                this.selectedDocuments = @json($mockDocuments->pluck('id')->toArray());
            }
        },
        
        // Action methods
        downloadDocument(documentId) {
            console.log('Downloading document:', documentId);
            // Implementation would trigger download
        },
        
        downloadSelected() {
            console.log('Downloading selected documents:', this.selectedDocuments);
            // Implementation would download all selected documents as ZIP
        },
        
        previewDocument(documentId) {
            console.log('Previewing document:', documentId);
            // Implementation would open preview modal
        },
        
        approveDocument(documentId) {
            console.log('Approving document:', documentId);
            // Implementation would approve document
        },
        
        rejectDocument(documentId) {
            console.log('Rejecting document:', documentId);
            // Implementation would reject document
        },
        
        deleteDocument(documentId) {
            console.log('Deleting document:', documentId);
            // Implementation would delete document
        },
        
        exportDocuments(format) {
            console.log('Exporting documents in', format, 'format');
            // Implementation for export functionality
        }
    }
}
</script>
@endsection