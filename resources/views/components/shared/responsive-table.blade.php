{{-- Mobile-Optimized Table Component --}}
{{-- Responsive table that converts to cards on mobile --}}

<div class="bg-white shadow-sm border border-gray-200 rounded-lg overflow-hidden">
    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($columns as $column)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ $column['label'] }}
                    </th>
                    @endforeach
                    @if(isset($actions))
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        {{ __('app.actions') }}
                    </th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach($items as $item)
                <tr class="hover:bg-gray-50">
                    @foreach($columns as $column)
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        @if(isset($column['component']))
                            <x-dynamic-component :component="$column['component']" :data="$item" />
                        @else
                            {{ $item[$column['key']] ?? '' }}
                        @endif
                    </td>
                    @endforeach
                    @if(isset($actions))
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        {{ $actions($item) }}
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Mobile Card View -->
    <div class="sm:hidden">
        @foreach($items as $item)
        <div class="border-b border-gray-200 last:border-b-0">
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        @if(isset($columns[0]))
                            <h3 class="text-sm font-medium text-gray-900 truncate">
                                {{ $item[$columns[0]['key']] ?? '' }}
                            </h3>
                        @endif
                    </div>
                    @if(isset($actions))
                    <div class="ml-4 flex-shrink-0">
                        {{ $actions($item) }}
                    </div>
                    @endif
                </div>
                
                <div class="space-y-2">
                    @foreach(array_slice($columns, 1) as $column)
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-500">{{ $column['label'] }}:</span>
                        <span class="text-xs text-gray-900">
                            @if(isset($column['component']))
                                <x-dynamic-component :component="$column['component']" :data="$item" />
                            @else
                                {{ $item[$column['key']] ?? '' }}
                            @endif
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>
    
    <!-- Empty State -->
    @if(count($items) === 0)
    <div class="text-center py-12">
        <div class="mx-auto h-12 w-12 text-gray-400">
            <i class="fas fa-inbox text-4xl"></i>
        </div>
        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ $emptyTitle ?? __('app.no_data') }}</h3>
        <p class="mt-1 text-sm text-gray-500">{{ $emptyMessage ?? __('app.no_data_message') }}</p>
        @if(isset($emptyAction))
        <div class="mt-6">
            {{ $emptyAction }}
        </div>
        @endif
    </div>
    @endif
</div>

<!-- Pagination -->
@if(isset($pagination) && $pagination)
<div class="mt-6">
    {{ $pagination }}
</div>
@endif
