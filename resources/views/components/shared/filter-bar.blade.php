{{-- Standardized Filter Bar Component --}}
{{-- Reusable filter bar with search, dropdowns, and date filters --}}

@props([
    'search' => true,
    'searchPlaceholder' => 'Search...',
    'filters' => [],
    'sortOptions' => [],
    'viewModes' => ['table', 'grid', 'list'],
    'currentViewMode' => 'table',
    'bulkActions' => [],
    'showFilters' => true,
    'showSort' => true,
    'showViewMode' => true,
    'theme' => 'light'
])

@php
    $hasFilters = !empty($filters);
    $hasSortOptions = !empty($sortOptions);
    $hasViewModes = !empty($viewModes) && count($viewModes) > 1;
    $hasBulkActions = !empty($bulkActions);
@endphp

<div class="filter-bar" 
     x-data="filterBarComponent()" 
     :class="{ 'filters-open': filtersOpen }">
    
    {{-- Main Filter Row --}}
    <div class="filter-bar-main">
        <div class="flex items-center justify-between">
            {{-- Left Side - Search & Filters --}}
            <div class="flex items-center space-x-4 flex-1">
                {{-- Search --}}
                @if($search)
                    <div class="relative flex-1 max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               x-model="searchQuery"
                               @input.debounce.300ms="handleSearch()"
                               placeholder="{{ $searchPlaceholder }}"
                               class="filter-search-input">
                    </div>
                @endif
                
                {{-- Filter Toggle Button --}}
                @if($hasFilters)
                    <button @click="toggleFilters()" 
                            :class="{ 'bg-blue-50 text-blue-700': filtersOpen }"
                            class="filter-toggle-btn">
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                        <span x-show="activeFiltersCount > 0" 
                              x-text="activeFiltersCount"
                              class="ml-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full"></span>
                    </button>
                @endif
                
                {{-- Sort Dropdown --}}
                @if($hasSortOptions)
                    <div class="relative">
                        <select x-model="sortBy" 
                                @change="handleSort()"
                                class="filter-select">
                            @foreach($sortOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-sort text-gray-400"></i>
                        </div>
                    </div>
                @endif
            </div>
            
            {{-- Right Side - View Mode & Actions --}}
            <div class="flex items-center space-x-3">
                {{-- View Mode Toggle --}}
                @if($hasViewModes)
                    <div class="flex items-center bg-gray-100 rounded-lg p-1">
                        @foreach($viewModes as $mode)
                            <button @click="setViewMode('{{ $mode }}')"
                                    :class="{ 'bg-white shadow-sm': viewMode === '{{ $mode }}' }"
                                    class="filter-view-mode-btn">
                                <i class="fas fa-{{ $mode === 'table' ? 'table' : ($mode === 'grid' ? 'th' : 'list') }}"></i>
                            </button>
                        @endforeach
                    </div>
                @endif
                
                {{-- Bulk Actions --}}
                @if($hasBulkActions)
                    <div x-show="selectedItems.length > 0" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600" x-text="`${selectedItems.length} selected`"></span>
                        @foreach($bulkActions as $action)
                            <button @click="{{ $action['handler'] }}" 
                                    class="filter-bulk-action-btn">
                                <i class="{{ $action['icon'] }} mr-1"></i>
                                {{ $action['label'] }}
                            </button>
                        @endforeach
                    </div>
                @endif
                
                {{-- Custom Actions Slot --}}
                {{ $actions ?? '' }}
            </div>
        </div>
    </div>
    
    {{-- Filters Panel --}}
    @if($hasFilters)
        <div x-show="filtersOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="filter-panel">
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($filters as $filter)
                    <div class="filter-group">
                        <label class="filter-label">{{ $filter['label'] }}</label>
                        
                        @if($filter['type'] === 'select')
                            <select x-model="filters.{{ $filter['key'] }}" 
                                    @change="applyFilters()"
                                    class="filter-select">
                                <option value="">{{ $filter['placeholder'] ?? 'All' }}</option>
                                @foreach($filter['options'] as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @elseif($filter['type'] === 'date')
                            <input type="date" 
                                   x-model="filters.{{ $filter['key'] }}"
                                   @change="applyFilters()"
                                   class="filter-input">
                        @elseif($filter['type'] === 'date-range')
                            <div class="flex space-x-2">
                                <input type="date" 
                                       x-model="filters.{{ $filter['key'] }}_from"
                                       @change="applyFilters()"
                                       placeholder="From"
                                       class="filter-input">
                                <input type="date" 
                                       x-model="filters.{{ $filter['key'] }}_to"
                                       @change="applyFilters()"
                                       placeholder="To"
                                       class="filter-input">
                            </div>
                        @elseif($filter['type'] === 'multiselect')
                            <div class="relative">
                                <select x-model="filters.{{ $filter['key'] }}" 
                                        @change="applyFilters()"
                                        multiple
                                        class="filter-select">
                                    @foreach($filter['options'] as $option)
                                        <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            
            {{-- Filter Actions --}}
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                <button @click="clearFilters()" 
                        class="filter-clear-btn">
                    <i class="fas fa-times mr-2"></i>
                    Clear All Filters
                </button>
                
                <div class="flex items-center space-x-2">
                    <button @click="applyFilters()" 
                            class="filter-apply-btn">
                        <i class="fas fa-check mr-2"></i>
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('filterBarComponent', () => ({
        searchQuery: '',
        filtersOpen: false,
        activeFiltersCount: 0,
        viewMode: '{{ $currentViewMode }}',
        sortBy: '{{ $sortOptions[0]['value'] ?? 'updated_at' }}',
        selectedItems: [],
        filters: {},
        
        init() {
            // Initialize filters from URL params or default values
            this.initializeFilters();
            this.updateActiveFiltersCount();
        },
        
        initializeFilters() {
            // Initialize filters object
            @if($hasFilters)
                @foreach($filters as $filter)
                    this.filters.{{ $filter['key'] }} = '';
                @endforeach
            @endif
        },
        
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        handleSearch() {
            this.$dispatch('filter-search', {
                query: this.searchQuery
            });
        },
        
        handleSort() {
            this.$dispatch('filter-sort', {
                sortBy: this.sortBy,
                sortDirection: 'asc'
            });
        },
        
        setViewMode(mode) {
            this.viewMode = mode;
            this.$dispatch('filter-view-mode', {
                viewMode: mode
            });
        },
        
        applyFilters() {
            this.updateActiveFiltersCount();
            this.$dispatch('filter-apply', {
                filters: this.filters
            });
        },
        
        clearFilters() {
            this.filters = {};
            this.searchQuery = '';
            this.activeFiltersCount = 0;
            this.$dispatch('filter-clear');
        },
        
        updateActiveFiltersCount() {
            let count = 0;
            Object.values(this.filters).forEach(value => {
                if (value && value !== '') {
                    count++;
                }
            });
            if (this.searchQuery) count++;
            this.activeFiltersCount = count;
        }
    }));
});
</script>
@endpush
