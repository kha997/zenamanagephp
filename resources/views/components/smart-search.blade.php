{{-- Smart Search Component --}}
{{-- Intelligent search with fuzzy matching, recent searches, and suggestions --}}

<div class="smart-search relative" x-data="smartSearch()">
    <!-- Search Input -->
    <div class="relative">
        <input type="text" 
               x-model="searchQuery"
               @input="handleSearchInput($event.target.value)"
               @keydown.enter="performSearch()"
               @keydown.arrow-down="navigateResults(1)"
               @keydown.arrow-up="navigateResults(-1)"
               @keydown.escape="closeSearch()"
               @focus="showSearchResults = true"
               placeholder="Search projects, tasks, documents..." 
               data-search-input
               class="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
               :class="searchQuery ? 'pr-10' : ''">
        
        <!-- Search Icon -->
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
        </div>
        
        <!-- Clear Button -->
        <button x-show="searchQuery" 
                @click="clearSearch()"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Search Shortcut Hint -->
        <div x-show="!searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <kbd class="text-xs text-gray-400">/</kbd>
        </div>
    </div>
    
    <!-- Search Results Dropdown -->
    <div x-show="showSearchResults && (searchResults.length > 0 || recentSearches.length > 0 || suggestions.length > 0)" 
         x-transition
         @click.away="showSearchResults = false"
         class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 z-50 max-h-96 overflow-y-auto">
        
        <!-- Search Results -->
        <div x-show="searchResults.length > 0" class="p-2">
            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Search Results
            </div>
            <template x-for="(result, index) in searchResults" :key="result.id">
                <a :href="result.url" 
                   @click="saveRecentSearch(searchQuery)"
                   class="block p-3 hover:bg-gray-50 rounded-lg transition-colors"
                   :class="selectedResultIndex === index ? 'bg-blue-50' : ''">
                    <div class="flex items-center space-x-3">
                        <i :class="result.icon" class="text-gray-400 text-lg"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900" x-text="result.title"></p>
                            <p class="text-xs text-gray-500" x-text="result.description"></p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-xs text-blue-600 font-medium" x-text="result.type"></span>
                                <template x-if="result.metadata">
                                    <div class="flex items-center space-x-1">
                                        <template x-for="(value, key) in result.metadata" :key="key">
                                            <span class="text-xs text-gray-400" x-text="`${key}: ${value}`"></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <span class="text-xs text-gray-400" x-text="Math.round(result.score) + '%'"></span>
                        </div>
                    </div>
                </a>
            </template>
        </div>
        
        <!-- Recent Searches -->
        <div x-show="searchResults.length === 0 && recentSearches.length > 0" class="p-2">
            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Recent Searches
            </div>
            <template x-for="search in recentSearches" :key="search.timestamp">
                <button @click="searchQuery = search.query; performSearch()"
                        class="w-full text-left p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex items-center space-x-3">
                        <i class="fas fa-history text-gray-400"></i>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900" x-text="search.query"></p>
                            <p class="text-xs text-gray-500" x-text="formatTime(search.timestamp)"></p>
                        </div>
                    </div>
                </button>
            </template>
        </div>
        
        <!-- Suggestions -->
        <div x-show="searchResults.length === 0 && recentSearches.length === 0 && suggestions.length > 0" class="p-2">
            <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                Suggestions
            </div>
            <template x-for="suggestion in suggestions" :key="suggestion.text">
                <button @click="searchQuery = suggestion.text; performSearch()"
                        class="w-full text-left p-3 hover:bg-gray-50 rounded-lg transition-colors">
                    <div class="flex items-center space-x-3">
                        <i :class="getSuggestionIcon(suggestion.type)" class="text-gray-400"></i>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900" x-text="suggestion.text"></p>
                            <p class="text-xs text-gray-500" x-text="suggestion.type"></p>
                        </div>
                    </div>
                </button>
            </template>
        </div>
        
        <!-- No Results -->
        <div x-show="searchResults.length === 0 && recentSearches.length === 0 && suggestions.length === 0 && searchQuery" 
             class="p-4 text-center text-gray-500">
            <i class="fas fa-search text-2xl mb-2"></i>
            <p class="text-sm">No results found for "<span x-text="searchQuery"></span>"</p>
            <p class="text-xs mt-1">Try different keywords or check your spelling</p>
        </div>
        
        <!-- Search Tips -->
        <div x-show="!searchQuery" class="p-4 text-center text-gray-500">
            <i class="fas fa-lightbulb text-2xl mb-2"></i>
            <p class="text-sm font-medium mb-1">Search Tips</p>
            <ul class="text-xs space-y-1">
                <li>• Use project codes for quick access</li>
                <li>• Search by task status or priority</li>
                <li>• Find documents by filename or type</li>
                <li>• Press <kbd class="px-1 py-0.5 bg-gray-100 rounded text-xs">/</kbd> to focus search</li>
            </ul>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('smartSearch', () => ({
            // State
            searchQuery: '',
            searchResults: [],
            recentSearches: [],
            suggestions: [],
            showSearchResults: false,
            selectedResultIndex: -1,
            searchTimeout: null,
            
            // Initialize
            init() {
                this.loadRecentSearches();
                this.setupKeyboardShortcuts();
            },
            
            // Search Input Handler
            handleSearchInput(query) {
                this.searchQuery = query;
                
                // Clear previous timeout
                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout);
                }
                
                // Debounce search
                this.searchTimeout = setTimeout(() => {
                    if (query.length >= 2) {
                        this.performSearch();
                        this.loadSuggestions();
                    } else {
                        this.searchResults = [];
                        this.suggestions = [];
                    }
                }, 300);
            },
            
            // Perform Search
            async performSearch() {
                if (!this.searchQuery.trim()) {
                    this.searchResults = [];
                    return;
                }
                
                try {
                    const response = await fetch('/api/universal-frame/search', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            query: this.searchQuery,
                            context: 'all'
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.searchResults = data.data;
                        this.selectedResultIndex = -1;
                    } else {
                        console.error('Search failed:', data.error);
                        this.searchResults = [];
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    this.searchResults = [];
                }
            },
            
            // Load Suggestions
            async loadSuggestions() {
                if (this.searchQuery.length < 2) {
                    this.suggestions = [];
                    return;
                }
                
                try {
                    const response = await fetch(`/api/universal-frame/search/suggestions?q=${encodeURIComponent(this.searchQuery)}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        this.suggestions = data.data;
                    }
                } catch (error) {
                    console.error('Suggestions error:', error);
                }
            },
            
            // Load Recent Searches
            async loadRecentSearches() {
                try {
                    const response = await fetch('/api/universal-frame/search/recent');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.recentSearches = data.data;
                    }
                } catch (error) {
                    console.error('Recent searches error:', error);
                }
            },
            
            // Save Recent Search
            async saveRecentSearch(query) {
                if (!query.trim()) return;
                
                try {
                    await fetch('/api/universal-frame/search/recent', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ query })
                    });
                    
                    this.loadRecentSearches();
                } catch (error) {
                    console.error('Save recent search error:', error);
                }
            },
            
            // Clear Search
            clearSearch() {
                this.searchQuery = '';
                this.searchResults = [];
                this.suggestions = [];
                this.selectedResultIndex = -1;
            },
            
            // Close Search
            closeSearch() {
                this.showSearchResults = false;
                this.selectedResultIndex = -1;
            },
            
            // Navigate Results
            navigateResults(direction) {
                const totalResults = this.searchResults.length;
                
                if (totalResults === 0) return;
                
                this.selectedResultIndex += direction;
                
                if (this.selectedResultIndex < 0) {
                    this.selectedResultIndex = totalResults - 1;
                } else if (this.selectedResultIndex >= totalResults) {
                    this.selectedResultIndex = 0;
                }
            },
            
            // Setup Keyboard Shortcuts
            setupKeyboardShortcuts() {
                document.addEventListener('keydown', (e) => {
                    // Search shortcut (/)
                    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        const activeElement = document.activeElement;
                        if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                            e.preventDefault();
                            this.$refs.searchInput?.focus();
                        }
                    }
                });
            },
            
            // Utility Functions
            formatTime(timestamp) {
                const date = new Date(timestamp);
                const now = new Date();
                const diff = now - date;
                
                if (diff < 60000) return 'Just now';
                if (diff < 3600000) return Math.floor(diff / 60000) + ' minutes ago';
                if (diff < 86400000) return Math.floor(diff / 3600000) + ' hours ago';
                return Math.floor(diff / 86400000) + ' days ago';
            },
            
            getSuggestionIcon(type) {
                const icons = {
                    'common': 'fas fa-tag',
                    'project': 'fas fa-project-diagram',
                    'task': 'fas fa-tasks',
                    'document': 'fas fa-file-alt',
                    'user': 'fas fa-user'
                };
                
                return icons[type] || 'fas fa-search';
            }
        }));
    });
</script>
