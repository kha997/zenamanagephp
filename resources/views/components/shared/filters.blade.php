{{-- Shared Filters Component --}}
{{-- Reusable filter component for all list views --}}

<div class="shared-filters" x-data="sharedFilters()">
    <!-- Filter Header -->
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <!-- Filter Toggle -->
            <button @click="toggleFilters()" 
                    class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="filtersOpen ? 'bg-blue-50 text-blue-700 border-blue-300' : ''">
                <i class="fas fa-filter"></i>
                <span>{{ __('app.filters') }}</span>
                <span x-show="activeFiltersCount > 0" 
                      class="bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center"
                      x-text="activeFiltersCount"></span>
            </button>
            
            <!-- Quick Filters -->
            <div class="flex items-center space-x-2">
                @if(isset($quickFilters))
                    @foreach($quickFilters as $filter)
                        <button @click="applyQuickFilter('{{ $filter['key'] }}', '{{ $filter['value'] }}')"
                                class="px-3 py-1 text-xs font-medium rounded-full border transition-colors"
                                :class="isQuickFilterActive('{{ $filter['key'] }}', '{{ $filter['value'] }}') ? 
                                    'bg-blue-100 text-blue-700 border-blue-300' : 
                                    'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200'">
                            {{ $filter['label'] }}
                        </button>
                    @endforeach
                @endif
            </div>
        </div>
        
        <!-- Filter Actions -->
        <div class="flex items-center space-x-2">
            @if(isset($showSaveView) && $showSaveView)
                <button @click="showSaveViewModal = true" 
                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-save mr-1"></i>{{ __('app.save_view') }}
                </button>
            @endif
            
            @if(isset($showExport) && $showExport)
                <button @click="exportData()" 
                        class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-download mr-1"></i>{{ __('app.export') }}
                </button>
            @endif
        </div>
    </div>
    
    <!-- Filter Panel -->
    <div x-show="filtersOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
        
        <!-- Filter Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @if(isset($filters))
                @foreach($filters as $filter)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $filter['label'] }}
                        </label>
                        
                        @if($filter['type'] === 'select')
                            <select x-model="activeFilters['{{ $filter['name'] }}']"
                                    @change="applyFilter('{{ $filter['name'] }}', $event.target.value)"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="">{{ $filter['placeholder'] ?? __('app.all') }}</option>
                                @foreach($filter['options'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @elseif($filter['type'] === 'date')
                            <input type="date" 
                                   x-model="activeFilters['{{ $filter['name'] }}']"
                                   @change="applyFilter('{{ $filter['name'] }}', $event.target.value)"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @elseif($filter['type'] === 'daterange')
                            <div class="flex space-x-2">
                                <input type="date" 
                                       x-model="activeFilters['{{ $filter['name'] }}_from']"
                                       @change="applyDateRangeFilter('{{ $filter['name'] }}')"
                                       placeholder="{{ __('app.from') }}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <input type="date" 
                                       x-model="activeFilters['{{ $filter['name'] }}_to']"
                                       @change="applyDateRangeFilter('{{ $filter['name'] }}')"
                                       placeholder="{{ __('app.to') }}"
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            </div>
                        @elseif($filter['type'] === 'multiselect')
                            <div class="relative">
                                <select x-model="activeFilters['{{ $filter['name'] }}']"
                                        @change="applyFilter('{{ $filter['name'] }}', $event.target.value)"
                                        multiple
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    @foreach($filter['options'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif($filter['type'] === 'text')
                            <input type="text" 
                                   x-model="activeFilters['{{ $filter['name'] }}']"
                                   @input.debounce.300ms="applyFilter('{{ $filter['name'] }}', $event.target.value)"
                                   placeholder="{{ $filter['placeholder'] ?? '' }}"
                                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
        
        <!-- Filter Actions -->
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">
                    {{ __('app.active_filters', ['count' => '<span x-text="activeFiltersCount"></span>']) }}
                </span>
                <button @click="clearAllFilters()" 
                        x-show="activeFiltersCount > 0"
                        class="text-sm text-red-600 hover:text-red-800">
                    {{ __('app.clear_all') }}
                </button>
            </div>
            
            <div class="flex items-center space-x-2">
                <button @click="resetFilters()" 
                        class="px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                    {{ __('app.reset') }}
                </button>
                <button @click="applyAllFilters()" 
                        class="px-3 py-1 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                    {{ __('app.apply_filters') }}
                </button>
            </div>
        </div>
    </div>
    
    <!-- Save View Modal -->
    <div x-show="showSaveViewModal" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-modal-backdrop">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('app.save_view') }}</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.view_name') }}</label>
                    <input type="text" 
                           x-model="newViewName"
                           placeholder="{{ __('app.enter_view_name') }}"
                           class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('app.description') }}</label>
                    <textarea x-model="newViewDescription"
                              placeholder="{{ __('app.enter_description') }}"
                              rows="3"
                              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                </div>
            </div>
            
            <div class="flex items-center justify-end space-x-3 mt-6">
                <button @click="showSaveViewModal = false" 
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    {{ __('app.cancel') }}
                </button>
                <button @click="saveView()" 
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    {{ __('app.save') }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sharedFilters', () => ({
        // State
        filtersOpen: false,
        activeFilters: {},
        showSaveViewModal: false,
        newViewName: '',
        newViewDescription: '',
        
        // Computed Properties
        get activeFiltersCount() {
            return Object.values(this.activeFilters).filter(value => 
                value !== '' && value !== null && value !== undefined
            ).length;
        },
        
        // Methods
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        applyFilter(name, value) {
            this.activeFilters[name] = value;
            this.triggerFilterChange();
        },
        
        applyDateRangeFilter(name) {
            const from = this.activeFilters[`${name}_from`];
            const to = this.activeFilters[`${name}_to`];
            
            if (from && to) {
                this.activeFilters[name] = `${from}|${to}`;
            } else {
                delete this.activeFilters[name];
            }
            
            this.triggerFilterChange();
        },
        
        applyQuickFilter(key, value) {
            this.activeFilters[key] = value;
            this.triggerFilterChange();
        },
        
        isQuickFilterActive(key, value) {
            return this.activeFilters[key] === value;
        },
        
        clearAllFilters() {
            this.activeFilters = {};
            this.triggerFilterChange();
        },
        
        resetFilters() {
            this.activeFilters = {};
            this.filtersOpen = false;
            this.triggerFilterChange();
        },
        
        applyAllFilters() {
            this.triggerFilterChange();
        },
        
        triggerFilterChange() {
            // Emit custom event for parent components to listen
            this.$dispatch('filters-changed', this.activeFilters);
        },
        
        exportData() {
            // Emit custom event for parent components to handle export
            this.$dispatch('export-data', this.activeFilters);
        },
        
        async saveView() {
            if (!this.newViewName.trim()) {
                alert('{{ __("app.please_enter_view_name") }}');
                return;
            }
            
            try {
                const response = await fetch('/api/v1/app/saved-views', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        name: this.newViewName,
                        description: this.newViewDescription,
                        filters: this.activeFilters,
                        type: '{{ $viewType ?? "default" }}'
                    })
                });
                
                if (response.ok) {
                    this.showSaveViewModal = false;
                    this.newViewName = '';
                    this.newViewDescription = '';
                    alert('{{ __("app.view_saved_successfully") }}');
                } else {
                    alert('{{ __("app.failed_to_save_view") }}');
                }
            } catch (error) {
                console.error('Error saving view:', error);
                alert('{{ __("app.failed_to_save_view") }}');
            }
        }
    }));
});
</script>
