


<div class="smart-filters" x-data="smartFilters()">
    <!-- Filter Controls -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <!-- Filter Toggle Button -->
            <button @click="toggleFilters()" 
                    class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="filtersOpen ? 'bg-blue-50 text-blue-700 border-blue-300' : ''">
                <i class="fas fa-filter"></i>
                <span>Filters</span>
                <span x-show="activeFiltersCount > 0" 
                      class="bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
                      x-text="activeFiltersCount"></span>
            </button>
            
            <!-- Quick Presets -->
            <div class="flex items-center space-x-2">
                <template x-for="preset in filterPresets.slice(0, 3)" :key="preset.id">
                    <button @click="applyPreset(preset)"
                            class="flex items-center space-x-1 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i :class="preset.icon" class="text-xs"></i>
                        <span x-text="preset.name"></span>
                    </button>
                </template>
                
                <!-- More Presets Dropdown -->
                <div class="relative" x-show="filterPresets.length > 3">
                    <button @click="showMorePresets = !showMorePresets" 
                            class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                        More <i class="fas fa-chevron-down text-xs ml-1"></i>
                    </button>
                    
                    <!-- More Presets Dropdown -->
                    <div x-show="showMorePresets" 
                         x-transition
                         @click.away="showMorePresets = false"
                         class="absolute top-full left-0 mt-1 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                        <div class="p-2">
                            <template x-for="preset in filterPresets.slice(3)" :key="preset.id">
                                <button @click="applyPreset(preset); showMorePresets = false"
                                        class="w-full text-left p-3 hover:bg-gray-50 rounded-lg transition-colors">
                                    <div class="flex items-center space-x-3">
                                        <i :class="preset.icon" class="text-gray-400"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="preset.name"></p>
                                            <p class="text-xs text-gray-500" x-text="preset.description"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="flex items-center space-x-2">
            <!-- Clear Filters -->
            <button x-show="activeFiltersCount > 0" 
                    @click="clearAllFilters()"
                    class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                Clear All
            </button>
            
            <!-- Save View -->
            <button @click="showSaveViewModal = true"
                    class="px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">
                <i class="fas fa-save mr-1"></i>
                Save View
            </button>
            
            <!-- Saved Views -->
            <div class="relative">
                <button @click="showSavedViews = !showSavedViews" 
                        class="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                    <i class="fas fa-bookmark mr-1"></i>
                    Saved Views
                </button>
                
                <!-- Saved Views Dropdown -->
                <div x-show="showSavedViews" 
                     x-transition
                     @click.away="showSavedViews = false"
                     class="absolute top-full right-0 mt-1 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                    <div class="p-2">
                        <template x-for="view in savedViews" :key="view.id">
                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded-lg">
                                <button @click="applySavedView(view); showSavedViews = false"
                                        class="flex-1 text-left">
                                    <p class="text-sm font-medium text-gray-900" x-text="view.name"></p>
                                    <p class="text-xs text-gray-500" x-text="view.description"></p>
                                </button>
                                <button @click="deleteSavedView(view.id)"
                                        class="ml-2 text-gray-400 hover:text-red-600">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </template>
                        
                        <div x-show="savedViews.length === 0" class="p-4 text-center text-gray-500">
                            <i class="fas fa-bookmark text-2xl mb-2"></i>
                            <p class="text-sm">No saved views</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Panel -->
    <div x-show="filtersOpen" 
         x-transition
         class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        
        <!-- Deep Filters -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <template x-for="filter in deepFilters" :key="filter.key">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700" x-text="filter.label"></label>
                    
                    <!-- Select Filter -->
                    <select x-show="filter.type === 'select'" 
                            x-model="activeFilters[filter.key]"
                            @change="updateFilters()"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All</option>
                        <template x-for="option in filter.options" :key="option.value">
                            <option :value="option.value" x-text="option.label"></option>
                        </template>
                    </select>
                    
                    <!-- Range Filter -->
                    <div x-show="filter.type === 'range'" class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <input type="number" 
                                   :min="filter.min" 
                                   :max="filter.max" 
                                   :step="filter.step"
                                   x-model="activeFilters[filter.key + '_min']"
                                   @change="updateFilters()"
                                   placeholder="Min"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <span class="text-gray-500">to</span>
                            <input type="number" 
                                   :min="filter.min" 
                                   :max="filter.max" 
                                   :step="filter.step"
                                   x-model="activeFilters[filter.key + '_max']"
                                   @change="updateFilters()"
                                   placeholder="Max"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div x-show="filter.type === 'date_range'" class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <input type="date" 
                                   x-model="activeFilters[filter.key + '_from']"
                                   @change="updateFilters()"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <span class="text-gray-500">to</span>
                            <input type="date" 
                                   x-model="activeFilters[filter.key + '_to']"
                                   @change="updateFilters()"
                                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                </div>
            </template>
        </div>
        
        <!-- Filter Summary -->
        <div x-show="activeFiltersCount > 0" class="mt-4 pt-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-700">Active Filters:</span>
                    <template x-for="(value, key) in activeFilters" :key="key">
                        <span x-show="value" 
                              class="inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                            <span x-text="getFilterLabel(key, value)"></span>
                            <button @click="removeFilter(key)" 
                                    class="ml-1 text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </span>
                    </template>
                </div>
                
                <div class="text-sm text-gray-500">
                    <span x-text="filteredCount"></span> results
                </div>
            </div>
        </div>
    </div>
    
    <!-- Save View Modal -->
    <div x-show="showSaveViewModal" 
         x-transition
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Save Filter View</h3>
                    <button @click="showSaveViewModal = false" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">View Name</label>
                        <input type="text" 
                               x-model="newViewName"
                               placeholder="Enter view name"
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea x-model="newViewDescription"
                                  placeholder="Enter description (optional)"
                                  rows="3"
                                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                    </div>
                </div>
                
                <div class="flex items-center justify-end space-x-3 mt-6">
                    <button @click="showSaveViewModal = false" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button @click="saveView()" 
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        Save View
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('smartFilters', () => ({
            // State
            filtersOpen: false,
            activeFilters: {},
            deepFilters: [],
            filterPresets: [],
            savedViews: [],
            showMorePresets: false,
            showSavedViews: false,
            showSaveViewModal: false,
            newViewName: '',
            newViewDescription: '',
            filteredCount: 0,
            
            // Computed Properties
            get activeFiltersCount() {
                return Object.values(this.activeFilters).filter(value => value !== '' && value !== null).length;
            },
            
            // Initialize
            async init() {
                await this.loadFilterPresets();
                await this.loadDeepFilters();
                await this.loadSavedViews();
            },
            
            // Load Filter Presets
            async loadFilterPresets() {
                try {
                    const response = await fetch('/api/universal-frame/filters/presets');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.filterPresets = data.data;
                    }
                } catch (error) {
                    console.error('Load presets error:', error);
                }
            },
            
            // Load Deep Filters
            async loadDeepFilters() {
                try {
                    const response = await fetch('/api/universal-frame/filters/deep?context=projects');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.deepFilters = data.data;
                    }
                } catch (error) {
                    console.error('Load deep filters error:', error);
                }
            },
            
            // Load Saved Views
            async loadSavedViews() {
                try {
                    const response = await fetch('/api/universal-frame/filters/saved-views');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.savedViews = data.data;
                    }
                } catch (error) {
                    console.error('Load saved views error:', error);
                }
            },
            
            // Toggle Filters
            toggleFilters() {
                this.filtersOpen = !this.filtersOpen;
            },
            
            // Apply Preset
            applyPreset(preset) {
                this.activeFilters = { ...preset.filters };
                this.updateFilters();
            },
            
            // Apply Saved View
            applySavedView(view) {
                this.activeFilters = { ...view.filters };
                this.updateFilters();
            },
            
            // Update Filters
            async updateFilters() {
                // This would typically trigger a data refresh
                console.log('Filters updated:', this.activeFilters);
                
                // Simulate filtered count
                this.filteredCount = Math.floor(Math.random() * 100) + 10;
            },
            
            // Clear All Filters
            clearAllFilters() {
                this.activeFilters = {};
                this.updateFilters();
            },
            
            // Remove Filter
            removeFilter(key) {
                delete this.activeFilters[key];
                this.updateFilters();
            },
            
            // Save View
            async saveView() {
                if (!this.newViewName.trim()) return;
                
                const viewData = {
                    name: this.newViewName,
                    description: this.newViewDescription,
                    filters: { ...this.activeFilters }
                };
                
                try {
                    const response = await fetch('/api/universal-frame/filters/saved-views', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(viewData)
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showSaveViewModal = false;
                        this.newViewName = '';
                        this.newViewDescription = '';
                        this.loadSavedViews();
                    }
                } catch (error) {
                    console.error('Save view error:', error);
                }
            },
            
            // Delete Saved View
            async deleteSavedView(viewId) {
                try {
                    const response = await fetch(`/api/universal-frame/filters/saved-views/${viewId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    });
                    
                    if (response.ok) {
                        this.loadSavedViews();
                    }
                } catch (error) {
                    console.error('Delete view error:', error);
                }
            },
            
            // Get Filter Label
            getFilterLabel(key, value) {
                const filter = this.deepFilters.find(f => f.key === key);
                if (filter && filter.type === 'select') {
                    const option = filter.options.find(o => o.value === value);
                    return option ? option.label : value;
                }
                return `${key}: ${value}`;
            }
        }));
    });
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/filters/smart-filters.blade.php ENDPATH**/ ?>