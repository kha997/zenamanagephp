{{-- Shared Table Component --}}
{{-- Reusable table component for all list views --}}

<div class="shared-table" x-data="sharedTable()">
    <!-- Table Container -->
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <!-- Table Header -->
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ $title ?? __('app.items') }}
                    <span class="text-sm font-normal text-gray-500" x-text="`(${totalItems})`"></span>
                </h3>
                
                @if(isset($showBulkActions) && $showBulkActions)
                    <div class="flex items-center space-x-2" x-show="selectedItems.length > 0">
                        <span class="text-sm text-gray-500" x-text="`${selectedItems.length} {{ __('app.selected') }}`"></span>
                        <button @click="bulkDelete()" 
                                class="px-3 py-1 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                            <i class="fas fa-trash mr-1"></i>{{ __('app.delete_selected') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <!-- Table Header -->
                <thead class="bg-gray-50">
                    <tr>
                        @if(isset($showBulkActions) && $showBulkActions)
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" 
                                       @change="toggleAllItems($event.target.checked)"
                                       :checked="selectedItems.length === totalItems && totalItems > 0"
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </th>
                        @endif
                        
                        @if(isset($columns))
                            @foreach($columns as $column)
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>{{ $column['label'] }}</span>
                                        @if(isset($column['sortable']) && $column['sortable'])
                                            <button @click="sortBy('{{ $column['key'] }}')" 
                                                    class="text-gray-400 hover:text-gray-600">
                                                <i class="fas fa-sort" 
                                                   :class="{
                                                       'text-blue-600': sortField === '{{ $column['key'] }}',
                                                       'fa-sort-up': sortField === '{{ $column['key'] }}' && sortDirection === 'asc',
                                                       'fa-sort-down': sortField === '{{ $column['key'] }}' && sortDirection === 'desc'
                                                   }"></i>
                                            </button>
                                        @endif
                                    </div>
                                </th>
                            @endforeach
                        @endif
                        
                        @if(isset($showActions) && $showActions)
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('app.actions') }}
                            </th>
                        @endif
                    </tr>
                </thead>
                
                <!-- Table Body -->
                <tbody class="bg-white divide-y divide-gray-200">
                    @if(isset($items) && count($items) > 0)
                        @foreach($items as $item)
                            <tr class="hover:bg-gray-50 transition-colors">
                                @if(isset($showBulkActions) && $showBulkActions)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" 
                                               value="{{ $item['id'] ?? $item->id }}"
                                               @change="toggleItem('{{ $item['id'] ?? $item->id }}', $event.target.checked)"
                                               :checked="selectedItems.includes('{{ $item['id'] ?? $item->id }}')"
                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    </td>
                                @endif
                                
                                @if(isset($columns))
                                    @foreach($columns as $column)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            @if(isset($column['component']))
                                                @include($column['component'], ['item' => $item, 'column' => $column])
                                            @elseif(isset($column['format']))
                                                @if($column['format'] === 'date')
                                                    {{ $item[$column['key']] ? \Carbon\Carbon::parse($item[$column['key']])->format('M d, Y') : '-' }}
                                                @elseif($column['format'] === 'datetime')
                                                    {{ $item[$column['key']] ? \Carbon\Carbon::parse($item[$column['key']])->format('M d, Y H:i') : '-' }}
                                                @elseif($column['format'] === 'currency')
                                                    {{ $item[$column['key']] ? '$' . number_format($item[$column['key']], 2) : '-' }}
                                                @elseif($column['format'] === 'percentage')
                                                    {{ $item[$column['key']] ? $item[$column['key']] . '%' : '-' }}
                                                @elseif($column['format'] === 'status')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        @if($item[$column['key']] === 'active') bg-green-100 text-green-800
                                                        @elseif($item[$column['key']] === 'inactive') bg-red-100 text-red-800
                                                        @elseif($item[$column['key']] === 'pending') bg-yellow-100 text-yellow-800
                                                        @else bg-gray-100 text-gray-800 @endif">
                                                        {{ $item[$column['key']] ?? '-' }}
                                                    </span>
                                                @elseif($column['format'] === 'badge')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $column['badge_class'] ?? 'bg-gray-100 text-gray-800' }}">
                                                        {{ $item[$column['key']] ?? '-' }}
                                                    </span>
                                                @else
                                                    {{ $item[$column['key']] ?? '-' }}
                                                @endif
                                            @else
                                                {{ $item[$column['key']] ?? '-' }}
                                            @endif
                                        </td>
                                    @endforeach
                                @endif
                                
                                @if(isset($showActions) && $showActions)
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            @if(isset($actions))
                                                @foreach($actions as $action)
                                                    @if(isset($action['condition']) && !$action['condition']($item))
                                                        @continue
                                                    @endif
                                                    
                                                    @if($action['type'] === 'link')
                                                        <a href="{{ $action['url']($item) }}" 
                                                           class="text-blue-600 hover:text-blue-900 transition-colors">
                                                            <i class="{{ $action['icon'] }}"></i>
                                                        </a>
                                                    @elseif($action['type'] === 'button')
                                                        <button @click="{{ $action['handler'] }}('{{ $item['id'] ?? $item->id }}')"
                                                                class="text-blue-600 hover:text-blue-900 transition-colors">
                                                            <i class="{{ $action['icon'] }}"></i>
                                                        </button>
                                                    @elseif($action['type'] === 'dropdown')
                                                        <div class="relative" x-data="{ open: false }">
                                                            <button @click="open = !open" 
                                                                    class="text-gray-400 hover:text-gray-600">
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
                                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                                                @foreach($action['items'] as $item)
                                                                    <a href="{{ $item['url']($item) }}" 
                                                                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                                        <i class="{{ $item['icon'] }} mr-2"></i>{{ $item['label'] }}
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
                        <tr>
                            <td colspan="{{ (isset($showBulkActions) && $showBulkActions ? 1 : 0) + (isset($columns) ? count($columns) : 0) + (isset($showActions) && $showActions ? 1 : 0) }}" 
                                class="px-6 py-12 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                                    <p>{{ __('app.no_items_found') }}</p>
                                    @if(isset($emptyStateAction))
                                        <button @click="{{ $emptyStateAction['handler'] }}" 
                                                class="mt-4 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                                            <i class="{{ $emptyStateAction['icon'] }} mr-2"></i>{{ $emptyStateAction['label'] }}
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        @if(isset($pagination) && $pagination)
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $pagination }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sharedTable', () => ({
        // State
        selectedItems: [],
        sortField: '{{ $sortField ?? 'created_at' }}',
        sortDirection: '{{ $sortDirection ?? 'desc' }}',
        totalItems: {{ $totalItems ?? 0 }},
        
        // Methods
        toggleAllItems(checked) {
            if (checked) {
                // Select all items
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
            
            // Emit sort event
            this.$dispatch('table-sort', {
                field: this.sortField,
                direction: this.sortDirection
            });
        },
        
        async bulkDelete() {
            if (this.selectedItems.length === 0) {
                return;
            }
            
            if (!confirm(`{{ __('app.confirm_delete_selected', ['count' => '']) }}${this.selectedItems.length} {{ __('app.items') }}?`)) {
                return;
            }
            
            try {
                const response = await fetch('/api/v1/app/bulk-delete', {
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
                    // Reload the page or emit refresh event
                    this.$dispatch('table-refresh');
                } else {
                    alert('{{ __("app.failed_to_delete_items") }}');
                }
            } catch (error) {
                console.error('Error deleting items:', error);
                alert('{{ __("app.failed_to_delete_items") }}');
            }
        }
    }));
});
</script>
