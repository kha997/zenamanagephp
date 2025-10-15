{{--
    Smart Search Component
    Follows ZenaManage Dashboard Design Principles
    
    Features:
    - Debounced search (250ms)
    - Server-side processing
    - Tenant + Role scoped
    - Keyboard navigation (Ctrl+K)
    - Accessible design
    - Mobile responsive
--}}

<!-- Smart Search Modal -->
<div id="search-modal" 
     class="fixed inset-0 bg-black bg-opacity-50 hidden z-50" 
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="search-modal-title"
     x-data="smartSearch()"
     x-init="init()">
    
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-96 overflow-hidden"
             @click.away="close()"
             @keydown.escape="close()">
            
            <!-- Search Header -->
            <div class="p-6 border-b">
                <div class="flex items-center justify-between">
                    <h3 id="search-modal-title" class="text-lg font-semibold text-gray-900">
                        Smart Search
                    </h3>
                    <button @click="close()" 
                            class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                            aria-label="Close search modal">
                        <i class="fas fa-times text-xl" aria-hidden="true"></i>
                    </button>
                </div>
                
                <!-- Search Input -->
                <div class="mt-4">
                    <div class="relative">
                        <input type="text" 
                               id="kbdInput" 
                               x-model="query"
                               @input="debouncedSearch()"
                               @keydown.arrow-down="navigateResults('down')"
                               @keydown.arrow-up="navigateResults('up')"
                               @keydown.enter="selectResult()"
                               placeholder="Search projects, tasks, users, documents..."
                               class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               aria-label="Search input"
                               autocomplete="off">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400" aria-hidden="true"></i>
                        </div>
                        <div x-show="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <i class="fas fa-spinner fa-spin text-gray-400" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search Results -->
            <div class="p-6 max-h-64 overflow-y-auto">
                <div x-show="!query" class="text-center text-gray-500 py-8">
                    <i class="fas fa-search text-4xl mb-4" aria-hidden="true"></i>
                    <p>Start typing to search...</p>
                    <div class="mt-4 text-sm text-gray-400">
                        <p>Press <kbd class="px-2 py-1 bg-gray-100 rounded">Ctrl+K</kbd> to open search</p>
                    </div>
                </div>
                
                <div x-show="query && !loading && results.length === 0" class="text-center text-gray-500 py-8">
                    <i class="fas fa-search text-4xl mb-4" aria-hidden="true"></i>
                    <p>No results found for "<span x-text="query"></span>"</p>
                </div>
                
                <div x-show="error" class="text-center text-red-500 py-8">
                    <i class="fas fa-exclamation-triangle text-4xl mb-4" aria-hidden="true"></i>
                    <p x-text="error"></p>
                    <button @click="search()" class="btn btn-sm btn-outline-red mt-2">
                        Try Again
                    </button>
                </div>
                
                <!-- Results List -->
                <ul x-show="results.length > 0" 
                    class="space-y-1" 
                    role="listbox"
                    aria-label="Search results">
                    <template x-for="(result, index) in results" :key="result.id">
                        <li>
                            <a :href="result.url" 
                               class="search-result-item flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 focus:bg-gray-100 focus:outline-none"
                               :class="{ 'bg-blue-50 border border-blue-200': selectedIndex === index }"
                               :aria-selected="selectedIndex === index"
                               role="option"
                               @click="selectResult(result)">
                                <div class="flex-shrink-0">
                                    <i :class="result.icon" class="text-gray-500" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-medium text-gray-900 truncate" x-text="result.title"></h4>
                                    <p class="text-sm text-gray-500 truncate" x-text="result.subtitle"></p>
                                </div>
                                <div class="flex-shrink-0">
                                    <span class="text-xs text-gray-400" x-text="result.type"></span>
                                </div>
                            </a>
                        </li>
                    </template>
                </ul>
            </div>
            
            <!-- Search Footer -->
            <div class="px-6 py-3 border-t bg-gray-50">
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <div class="flex items-center space-x-4">
                        <span><kbd class="px-1 py-0.5 bg-gray-200 rounded">↑↓</kbd> Navigate</span>
                        <span><kbd class="px-1 py-0.5 bg-gray-200 rounded">Enter</kbd> Select</span>
                        <span><kbd class="px-1 py-0.5 bg-gray-200 rounded">Esc</kbd> Close</span>
                    </div>
                    <div x-show="results.length > 0">
                        <span x-text="results.length"></span> results
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search Trigger Button (Global) -->
<button @click="open()" 
        class="search-trigger hidden md:flex items-center space-x-2 px-3 py-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
        aria-label="Open search">
    <i class="fas fa-search" aria-hidden="true"></i>
    <span class="hidden lg:inline">Search</span>
    <kbd class="hidden xl:inline px-1 py-0.5 bg-gray-200 rounded text-xs">Ctrl+K</kbd>
</button>

@push('styles')
<style>
    .search-result-item {
        transition: background-color 0.15s ease;
    }
    
    .search-result-item:focus {
        outline: 2px solid #3b82f6;
        outline-offset: -2px;
    }
    
    .search-result-item[aria-selected="true"] {
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
    }
    
    kbd {
        font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 0.75em;
    }
    
    /* Mobile responsive */
    @media (max-width: 768px) {
        .search-trigger {
            display: none;
        }
        
        #search-modal .max-w-2xl {
            max-width: 100%;
            margin: 1rem;
        }
    }
    
    /* High contrast mode */
    @media (prefers-contrast: high) {
        .search-result-item {
            border: 1px solid #000;
        }
        
        .search-result-item[aria-selected="true"] {
            background-color: #000;
            color: #fff;
        }
    }
    
    /* Reduced motion */
    @media (prefers-reduced-motion: reduce) {
        .search-result-item {
            transition: none;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    function smartSearch() {
        return {
            query: '',
            results: [],
            loading: false,
            error: null,
            selectedIndex: -1,
            searchTimeout: null,
            
            init() {
                // Global keyboard shortcut
                document.addEventListener('keydown', (e) => {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                        e.preventDefault();
                        this.open();
                    }
                });
                
                // Close on escape
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.isOpen()) {
                        this.close();
                    }
                });
            },
            
            open() {
                const modal = document.getElementById('search-modal');
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                
                // Focus input after modal opens
                setTimeout(() => {
                    document.getElementById('kbdInput').focus();
                }, 100);
                
                // Track search modal open
                if (window.gtag) {
                    gtag('event', 'search_modal_open');
                }
            },
            
            close() {
                const modal = document.getElementById('search-modal');
                modal.classList.add('hidden');
                document.body.style.overflow = '';
                
                // Reset state
                this.query = '';
                this.results = [];
                this.loading = false;
                this.error = null;
                this.selectedIndex = -1;
                
                // Clear timeout
                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout);
                }
            },
            
            isOpen() {
                const modal = document.getElementById('search-modal');
                return !modal.classList.contains('hidden');
            },
            
            debouncedSearch() {
                // Clear existing timeout
                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout);
                }
                
                // Set new timeout
                this.searchTimeout = setTimeout(() => {
                    this.search();
                }, 250);
            },
            
            async search() {
                if (!this.query.trim()) {
                    this.results = [];
                    return;
                }
                
                this.loading = true;
                this.error = null;
                this.selectedIndex = -1;
                
                try {
                    const response = await fetch(`/api/v1/app/search?q=${encodeURIComponent(this.query)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...getTenantHeaders()
                        },
                        credentials: 'include'
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Search failed: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    this.results = this.formatResults(data);
                    
                    // Track search
                    if (window.gtag) {
                        gtag('event', 'search', {
                            'search_term': this.query,
                            'results_count': this.results.length
                        });
                    }
                    
                } catch (error) {
                    console.error('Search error:', error);
                    this.error = error.message;
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },
            
            formatResults(data) {
                const results = [];
                
                // Add projects
                if (data.projects) {
                    data.projects.forEach(project => {
                        results.push({
                            id: `project-${project.id}`,
                            title: project.name,
                            subtitle: project.description || 'Project',
                            type: 'Project',
                            icon: 'fas fa-project-diagram',
                            url: `/app/projects/${project.id}`
                        });
                    });
                }
                
                // Add tasks
                if (data.tasks) {
                    data.tasks.forEach(task => {
                        results.push({
                            id: `task-${task.id}`,
                            title: task.title,
                            subtitle: task.project_name || 'Task',
                            type: 'Task',
                            icon: 'fas fa-tasks',
                            url: `/app/tasks/${task.id}`
                        });
                    });
                }
                
                // Add users
                if (data.users) {
                    data.users.forEach(user => {
                        results.push({
                            id: `user-${user.id}`,
                            title: user.name,
                            subtitle: user.role || 'Team Member',
                            type: 'User',
                            icon: 'fas fa-user',
                            url: `/app/team/${user.id}`
                        });
                    });
                }
                
                // Add documents
                if (data.documents) {
                    data.documents.forEach(doc => {
                        results.push({
                            id: `doc-${doc.id}`,
                            title: doc.title,
                            subtitle: doc.type || 'Document',
                            type: 'Document',
                            icon: 'fas fa-file-alt',
                            url: `/app/documents/${doc.id}`
                        });
                    });
                }
                
                return results.slice(0, 10); // Limit to 10 results
            },
            
            navigateResults(direction) {
                if (this.results.length === 0) return;
                
                if (direction === 'down') {
                    this.selectedIndex = Math.min(this.selectedIndex + 1, this.results.length - 1);
                } else if (direction === 'up') {
                    this.selectedIndex = Math.max(this.selectedIndex - 1, -1);
                }
                
                // Scroll selected item into view
                if (this.selectedIndex >= 0) {
                    const selectedItem = document.querySelector(`[aria-selected="true"]`);
                    if (selectedItem) {
                        selectedItem.scrollIntoView({ block: 'nearest' });
                    }
                }
            },
            
            selectResult(result = null) {
                if (result) {
                    // Direct click
                    window.location.href = result.url;
                } else if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                    // Keyboard selection
                    window.location.href = this.results[this.selectedIndex].url;
                }
            }
        };
    }
    
    // Helper function to get tenant headers
    function getTenantHeaders() {
        const meta = document.querySelector('meta[name="x-tenant-id"]');
        return meta ? { 'X-Tenant-Id': meta.content } : {};
    }
    
    // Global functions for backward compatibility
    window.openSearchModal = function() {
        const modal = document.getElementById('search-modal');
        if (modal && modal._x_dataStack) {
            modal._x_dataStack[0].open();
        }
    };
    
    window.closeSearchModal = function() {
        const modal = document.getElementById('search-modal');
        if (modal && modal._x_dataStack) {
            modal._x_dataStack[0].close();
        }
    };
</script>
@endpush