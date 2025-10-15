{{-- KPI Strip Component --}}
{{-- Displays key performance indicators in a horizontal strip --}}

@props([
    'kpis' => [],
    'variant' => 'default', // default, compact, detailed
    'columns' => null, // auto-calculate if null
    'showChanges' => true,
    'showIcons' => true
])

@php
    $kpiCount = count($kpis);
    $columns = $columns ?? min($kpiCount, 6); // Max 6 columns
    $gridCols = match($columns) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        3 => 'grid-cols-3',
        4 => 'grid-cols-4',
        5 => 'grid-cols-5',
        6 => 'grid-cols-6',
        default => 'grid-cols-3'
    };
    
    $variantClasses = match($variant) {
        'compact' => 'p-4',
        'detailed' => 'p-6',
        default => 'p-5'
    };
@endphp

<div class="bg-white rounded-lg shadow-sm border border-gray-200 {{ $variantClasses }}">
    <div class="grid {{ $gridCols }} gap-4">
        @foreach($kpis as $kpi)
            <div class="flex items-center">
                @if($showIcons && isset($kpi['icon']))
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-{{ $kpi['color'] ?? 'blue' }}-500 rounded-md flex items-center justify-center">
                            <i class="fas {{ $kpi['icon'] }} text-white text-sm"></i>
                        </div>
                    </div>
                @endif
                
                <div class="{{ $showIcons ? 'ml-4' : '' }} flex-1">
                    <p class="text-sm font-medium text-gray-500">{{ $kpi['title'] ?? 'KPI' }}</p>
                    <div class="flex items-baseline">
                        <p class="text-2xl font-semibold text-gray-900">{{ $kpi['value'] ?? '0' }}</p>
                        @if($showChanges && isset($kpi['change']))
                            @php
                                $changeType = $kpi['change_type'] ?? 'neutral';
                                $changeClasses = match($changeType) {
                                    'positive' => 'text-green-600',
                                    'negative' => 'text-red-600',
                                    default => 'text-gray-600'
                                };
                            @endphp
                            <span class="ml-2 text-sm font-medium {{ $changeClasses }}">
                                {{ $kpi['change'] }}
                            </span>
                        @endif
                    </div>
                    @if(isset($kpi['description']))
                        <p class="text-xs text-gray-400 mt-1">{{ $kpi['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
