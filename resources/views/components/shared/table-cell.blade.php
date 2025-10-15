{{-- Table Cell Component --}}
{{-- Handles different data formats and displays --}}

@props([
    'item' => null,
    'column' => [],
    'index' => 0
])

@php
    $key = $column['key'] ?? 'id';
    $format = $column['format'] ?? 'text';
    $value = $item[$key] ?? $item->{$key} ?? null;
    $class = $column['class'] ?? '';
@endphp

<div class="table-cell {{ $class }}">
    @switch($format)
        @case('date')
            <span class="text-sm text-gray-900">
                {{ $value ? \Carbon\Carbon::parse($value)->format('M d, Y') : '-' }}
            </span>
            @break
            
        @case('datetime')
            <span class="text-sm text-gray-900">
                {{ $value ? \Carbon\Carbon::parse($value)->format('M d, Y H:i') : '-' }}
            </span>
            @break
            
        @case('time')
            <span class="text-sm text-gray-900">
                {{ $value ? \Carbon\Carbon::parse($value)->format('H:i') : '-' }}
            </span>
            @break
            
        @case('currency')
            <span class="text-sm text-gray-900 font-medium">
                {{ $value ? '$' . number_format($value, 2) : '-' }}
            </span>
            @break
            
        @case('percentage')
            <span class="text-sm text-gray-900">
                {{ $value ? $value . '%' : '-' }}
            </span>
            @break
            
        @case('number')
            <span class="text-sm text-gray-900 font-mono">
                {{ $value ? number_format($value) : '-' }}
            </span>
            @break
            
        @case('status')
            @php
                $statusConfig = $column['status_config'] ?? [];
                $statusClass = $statusConfig[$value] ?? 'bg-gray-100 text-gray-800';
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                {{ $value ?? '-' }}
            </span>
            @break
            
        @case('badge')
            @php
                $badgeClass = $column['badge_class'] ?? 'bg-gray-100 text-gray-800';
                $badgeColor = $column['badge_color'] ?? null;
                if ($badgeColor && $value) {
                    $badgeClass = "bg-{$badgeColor}-100 text-{$badgeColor}-800";
                }
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeClass }}">
                {{ $value ?? '-' }}
            </span>
            @break
            
        @case('avatar')
            <div class="flex items-center">
                <div class="flex-shrink-0 h-8 w-8">
                    @if($value)
                        <img class="h-8 w-8 rounded-full object-cover" 
                             src="{{ $value }}" 
                             alt="{{ $item['name'] ?? $item->name ?? 'Avatar' }}">
                    @else
                        <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                            <i class="fas fa-user text-gray-600 text-sm"></i>
                        </div>
                    @endif
                </div>
                @if(isset($column['show_name']) && $column['show_name'])
                    <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">
                            {{ $item['name'] ?? $item->name ?? 'Unknown' }}
                        </div>
                        @if(isset($column['show_email']) && $column['show_email'])
                            <div class="text-sm text-gray-500">
                                {{ $item['email'] ?? $item->email ?? '' }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            @break
            
        @case('progress')
            <div class="flex items-center">
                <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                    <div class="bg-blue-600 h-2 rounded-full" 
                         style="width: {{ min(100, max(0, $value ?? 0)) }}%"></div>
                </div>
                <span class="text-sm text-gray-600 font-medium">
                    {{ $value ?? 0 }}%
                </span>
            </div>
            @break
            
        @case('tags')
            @if($value && is_array($value))
                <div class="flex flex-wrap gap-1">
                    @foreach(array_slice($value, 0, 3) as $tag)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                            {{ $tag }}
                        </span>
                    @endforeach
                    @if(count($value) > 3)
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            +{{ count($value) - 3 }}
                        </span>
                    @endif
                </div>
            @elseif($value)
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ $value }}
                </span>
            @else
                <span class="text-gray-400">-</span>
            @endif
            @break
            
        @case('boolean')
            <span class="inline-flex items-center">
                @if($value)
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span class="ml-1 text-sm text-green-700">Yes</span>
                @else
                    <i class="fas fa-times-circle text-red-500"></i>
                    <span class="ml-1 text-sm text-red-700">No</span>
                @endif
            </span>
            @break
            
        @case('link')
            @if($value)
                <a href="{{ $value }}" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    {{ $column['link_text'] ?? 'View' }}
                    <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                </a>
            @else
                <span class="text-gray-400">-</span>
            @endif
            @break
            
        @case('json')
            <div class="text-sm">
                @if($value)
                    <pre class="bg-gray-100 p-2 rounded text-xs overflow-x-auto max-w-xs">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                @else
                    <span class="text-gray-400">-</span>
                @endif
            </div>
            @break
            
        @case('html')
            <div class="text-sm">
                {!! $value ?? '-' !!}
            </div>
            @break
            
        @case('text')
        @default
            <span class="text-sm text-gray-900">
                {{ $value ?? '-' }}
            </span>
            @break
    @endswitch
</div>
