{{-- Standardized Table Component --}}
{{-- Enhanced table component with better props and design tokens --}}

@props([
    'title' => null,
    'subtitle' => null,
    'columns' => [],
    'items' => [],
    'actions' => [],
    'showBulkActions' => false,
    'showActions' => true,
    'showSearch' => false,
    'showFilters' => false,
    'pagination' => null,
    'emptyState' => null,
    'loading' => false,
    'sortable' => true,
    'sticky' => false,
    'variant' => 'default', // 'default', 'compact', 'bordered'
    'theme' => 'light'
])

@php
    $isCompact = $variant === 'compact';
    $isBordered = $variant === 'bordered';
    $hasItems = !empty($items) && count($items) > 0;
    
    // Default empty state
    if (!$emptyState) {
        $emptyState = [
            'icon' => 'fas fa-inbox',
            'title' => 'No items found',
            'description' => 'There are no items to display at the moment.',
            'action' => null
        ];
    }
@endphp

<div class="table-container" 
     x-data="standardizedTable()" 
     :class="{ 'loading': loading }">
    
    {{-- Table Header --}}
    <div class="table-header">
        <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
                @if($title)
                    <h3 class="table-title">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="table-subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            
            {{-- Header Actions --}}
            <div class="flex items-center space-x-3">
                {{-- Search --}}
                @if($showSearch)
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" 
                               x-model="searchQuery"
                               @input.debounce.300ms="handleSearch()"
                               placeholder="Search..."
                               class="table-search-input">
                    </div>
                @endif
                
                {{-- Filters --}}
                @if($showFilters)
                    <button @click="toggleFilters()" 
                            :class="{ 'bg-blue-50 text-blue-700': filtersOpen }"
                            class="table-filter-btn">
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                        <span x-show="activeFiltersCount > 0" 
                              x-text="activeFiltersCount"
                              class="ml-2 bg-blue-600 text-white text-xs px-2 py-1 rounded-full"></span>
                    </button>
                @endif
                
                {{-- Bulk Actions --}}
                @if($showBulkActions && $hasItems)
                    <div x-show="selectedItems.length > 0" 
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="flex items-center space-x-2">
                        <span class="text-sm text-gray-600" x-text="`${selectedItems.length} selected`"></span>
                        <button @click="bulkDelete()" 
                                class="table-bulk-action-btn">
                            <i class="fas fa-trash mr-1"></i>
                            Delete Selected
                        </button>
                    </div>
                @endif
            </div>
        </div>
        
        {{-- Filters Panel --}}
        @if($showFilters)
            <div x-show="filtersOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-2"
                 class="table-filters-panel">
                {{-- Filter content will be provided via slot --}}
                {{ $filters ?? '' }}
            </div>
        @endif
    </div>
    
    {{-- Table Wrapper --}}
    <div class="table-wrapper {{ $isBordered ? 'bordered' : '' }}">
        {{-- Loading Overlay --}}
        <div x-show="loading" 
             class="table-loading-overlay">
            <div class="table-loading-spinner">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
                <p class="mt-2 text-sm text-gray-500">Loading...</p>
            </div>
        </div>
        
        {{-- Table --}}
        <div class="table-scroll-container">
            <table class="table {{ $isCompact ? 'compact' : '' }} {{ $sticky ? 'sticky-header' : '' }}">
                {{-- Table Head --}}
                <thead class="table-head">
                    <tr>
                        {{-- Bulk Selection Column --}}
                        @if($showBulkActions && $hasItems)
                            <th class="table-th bulk-select">
                                <input type="checkbox" 
                                       @change="toggleAllItems($event.target.checked)"
                                       :checked="selectedItems.length === totalItems && totalItems > 0"
                                       class="table-checkbox">
                            </th>
                        @endif
                        
                        {{-- Data Columns --}}
                        @foreach($columns as $column)
                            <th class="table-th {{ $column['class'] ?? '' }}"
                                :class="{ 'sortable': {{ $sortable && ($column['sortable'] ?? true) ? 'true' : 'false' }} }">
                                <div class="table-th-content">
                                    <span class="table-th-label">{{ $column['label'] }}</span>
                                    
                                    @if($sortable && ($column['sortable'] ?? true))
                                        <button @click="sortBy('{{ $column['key'] }}')" 
                                                class="table-sort-btn"
                                                :class="{
                                                    'active': sortField === '{{ $column['key'] }}',
                                                    'asc': sortField === '{{ $column['key'] }}' && sortDirection === 'asc',
                                                    'desc': sortField === '{{ $column['key'] }}' && sortDirection === 'desc'
                                                }">
                                            <i class="fas fa-sort"></i>
                                        </button>
                                    @endif
                                </div>
                            </th>
                        @endforeach
                        
                        {{-- Actions Column --}}
                        @if($showActions)
                            <th class="table-th actions">
                                <span class="table-th-label">Actions</span>
                            </th>
                        @endif
                    </tr>
                </thead>
                
                {{-- Table Body --}}
                <tbody class="table-body">
                    @if($hasItems)
                        @foreach($items as $index => $item)
                            <tr class="table-row" 
                                :class="{ 'selected': selectedItems.includes('{{ $item['id'] ?? $item->id }}') }">
                                
                                {{-- Bulk Selection Cell --}}
                                @if($showBulkActions)
                                    <td class="table-td bulk-select">
                                        <input type="checkbox" 
                                               value="{{ $item['id'] ?? $item->id }}"
                                               @change="toggleItem('{{ $item['id'] ?? $item->id }}', $event.target.checked)"
                                               :checked="selectedItems.includes('{{ $item['id'] ?? $item->id }}')"
                                               class="table-checkbox">
                                    </td>
                                @endif
                                
                                {{-- Data Cells --}}
                                @foreach($columns as $column)
                                    <td class="table-td {{ $column['class'] ?? '' }}">
                                        @if(isset($column['component']))
                                            @include($column['component'], ['item' => $item, 'column' => $column, 'index' => $index])
                                        @else
                                            <x-shared.table-cell 
                                                :item="$item" 
                                                :column="$column" 
                                                :index="$index" />
                                        @endif
                                    </td>
                                @endforeach
                                
                                {{-- Actions Cell --}}
                                @if($showActions)
                                    <td class="table-td actions">
                                        <div class="table-actions">
                                            @if(!empty($actions))
                                                @foreach($actions as $action)
                                                    @if(isset($action['condition']) && !$action['condition']($item))
                                                        @continue
                                                    @endif
                                                    
                                                    @if($action['type'] === 'link')
                                                        <a href="{{ $action['url']($item) }}" 
                                                           class="table-action-link"
                                                           title="{{ $action['title'] ?? $action['label'] }}">
                                                            <i class="{{ $action['icon'] }}"></i>
                                                            @if(!$isCompact)
                                                                <span class="ml-1">{{ $action['label'] }}</span>
                                                            @endif
                                                        </a>
                                                    @elseif($action['type'] === 'button')
                                                        <button @click="{{ $action['handler'] }}('{{ $item['id'] ?? $item->id }}')"
                                                                class="table-action-btn"
                                                                title="{{ $action['title'] ?? $action['label'] }}">
                                                            <i class="{{ $action['icon'] }}"></i>
                                                            @if(!$isCompact)
                                                                <span class="ml-1">{{ $action['label'] }}</span>
                                                            @endif
                                                        </button>
                                                    @elseif($action['type'] === 'dropdown')
                                                        <div class="relative" x-data="{ open: false }">
                                                            <button @click="open = !open" 
                                                                    class="table-action-btn"
                                                                    title="More actions">
                                                                <i class="fas fa-ellipsis-v"></i>
                                                            </button>
                                                            
                                                            <div x-show="open" 
                                                                 @click.away="open = false"
                                                                 x-transition:enter="transition ease-out duration-100"
                                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                                 x-transition:leave="transition ease-in duration-75"
                                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                                 class="table-dropdown">
                                                                @foreach($action['items'] as $dropdownItem)
                                                                    <a href="{{ $dropdownItem['url']($item) }}" 
                                                                       class="table-dropdown-item">
                                                                        <i class="{{ $dropdownItem['icon'] }} mr-2"></i>
                                                                        {{ $dropdownItem['label'] }}
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    @else
                        {{-- Empty State --}}
                        <tr>
                            <td colspan="{{ ($showBulkActions ? 1 : 0) + count($columns) + ($showActions ? 1 : 0) }}" 
                                class="table-empty-cell">
                                <div class="table-empty-state">
                                    <i class="{{ $emptyState['icon'] }} text-4xl text-gray-300 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ $emptyState['title'] }}</h3>
                                    <p class="text-gray-500 mb-4">{{ $emptyState['description'] }}</p>
                                    @if($emptyState['action'])
                                        <button @click="{{ $emptyState['action']['handler'] }}" 
                                                class="table-empty-action-btn">
                                            <i class="{{ $emptyState['action']['icon'] }} mr-2"></i>
                                            {{ $emptyState['action']['label'] }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    
    {{-- Pagination --}}
    @if($pagination)
        <div class="table-pagination">
            {{ $pagination }}
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('standardizedTable', () => ({
        // State
        selectedItems: [],
        sortField: '{{ $sortField ?? 'created_at' }}',
        sortDirection: '{{ $sortDirection ?? 'desc' }}',
        totalItems: {{ count($items ?? []) }},
        searchQuery: '',
        filtersOpen: false,
        activeFiltersCount: 0,
        loading: {{ $loading ? 'true' : 'false' }},
        
        // Methods
        toggleAllItems(checked) {
            if (checked) {
                this.selectedItems = @json(collect($items ?? [])->pluck('id')->toArray());
            } else {
                this.selectedItems = [];
            }
        },
        
        toggleItem(itemId, checked) {
            if (checked) {
                if (!this.selectedItems.includes(itemId)) {
                    this.selectedItems.push(itemId);
                }
            } else {
                this.selectedItems = this.selectedItems.filter(id => id !== itemId);
            }
        },
        
        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
            
            this.$dispatch('table-sort', {
                field: this.sortField,
                direction: this.sortDirection
            });
        },
        
        handleSearch() {
            this.$dispatch('table-search', {
                query: this.searchQuery
            });
        },
        
        toggleFilters() {
            this.filtersOpen = !this.filtersOpen;
        },
        
        async bulkDelete() {
            if (this.selectedItems.length === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${this.selectedItems.length} selected items?`)) {
                return;
            }
            
            try {
                this.loading = true;
                
                const response = await fetch('/api/v1/bulk-delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        ids: this.selectedItems,
                        type: '{{ $bulkDeleteType ?? 'default' }}'
                    })
                });
                
                if (response.ok) {
                    this.selectedItems = [];
                    this.$dispatch('table-refresh');
                } else {
                    alert('Failed to delete items');
                }
            } catch (error) {
                console.error('Error deleting items:', error);
                alert('Failed to delete items');
            } finally {
                this.loading = false;
            }
        }
    }));
});
</script>
@endpush
