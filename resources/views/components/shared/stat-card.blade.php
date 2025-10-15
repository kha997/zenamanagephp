{{--
StatCard Component - Reusable KPI card matching Users page style
Props: $title, $value, $delta, $deltaType, $suffix, $icon, $variant, $sparkline, $linkHref, $loading, $tooltip
--}}

@props([
    'title' => '',
    'value' => 0,
    'delta' => null,
    'deltaType' => 'neutral',
    'suffix' => '',
    'icon' => '',
    'variant' => 'gray',
    'sparkline' => null,
    'linkHref' => null,
    'loading' => false,
    'tooltip' => '',
    'ariaLabel' => ''
])

@php
    // Variant classes
    $variants = [
        'blue' => 'bg-blue-50 text-blue-700 border-blue-100',
        'red' => 'bg-red-50 text-red-700 border-red-100',
        'amber' => 'bg-amber-50 text-amber-700 border-amber-100',
        'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
        'orange' => 'bg-orange-50 text-orange-700 border-orange-100',
        'gray' => 'bg-gray-50 text-gray-700 border-gray-100'
    ];
    
    $iconVariants = [
        'blue' => 'bg-blue-100 text-blue-600',
        'red' => 'bg-red-100 text-red-600',
        'amber' => 'bg-amber-100 text-amber-600',
        'indigo' => 'bg-indigo-100 text-indigo-600',
        'orange' => 'bg-orange-100 text-orange-600',
        'gray' => 'bg-gray-100 text-gray-600'
    ];
    
    // Calculate classes
    $containerClass = 'border rounded-lg p-6 transition-all duration-200 hover:shadow-md ' . ($variants[$variant] ?? $variants['gray']);
    $iconClass = 'inline-flex items-center justify-center w-8 h- h-8 rounded-full ' . ($iconVariants[$variant] ?? $iconVariants['gray']);
    
    // Delta formatting
    $deltaClasses = [
        'up' => 'bg-green-100 text-green-800',
        'down' => 'bg-red-100 text-red-800',
        'neutral' => 'bg-gray-100 text-gray-800'
    ];
    
    $deltaClass = $deltaClasses[$deltaType] ?? $deltaClasses['neutral'];
    
    // Delta icon and text
    $deltaIcon = match($deltaType) {
        'up' => 'fas fa-arrow-up',
        'down' => 'fas fa-arrow-down',
        default => 'fas fa-minus'
    };
    
    $deltaText = '';
    if ($delta !== null && $delta !== '') {
        $deltaNum = is_numeric($delta) ? (float)$delta : 0;
        if (abs($deltaNum) < 0.1) {
            $deltaText = '0%';
        } else {
            $sign = $deltaNum > 0 ? '+' : '';
            if ($suffix === '%') {
                $deltaText = $sign . number_format($deltaNum, 1) . '%';
            } else {
                $deltaText = $sign . number_format($deltaNum);
            }
        }
    }
@endphp

<div class="{{ $containerClass }}" 
     @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
     @if($tooltip) title="{{ $tooltip }}" @endif
     @if($linkHref) onclick="window.location.href='{{ $linkHref }}'" style="cursor: pointer;" @endif>
    <div class="flex items-center justify-between">
        <div class="flex-1">
            <div class="flex items-center">
                <div class="{{ $iconClass }} mr-3">
                    <i class="fas fa-{{ $icon }} text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium leading-none mb-1">{{ $title }}</h3>
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold tabular-nums" aria-live="polite">
                            @if($loading)
                                <div class="animate-pulse bg-gray-200 rounded w-12 h-8"></div>
                            @else
                                {{ $value }}
                            @endif
                        </span>
                        @if($suffix && !$loading)
                            <span class="text-sm font-normal opacity-80">{{ $suffix }}</span>
                        @endif
                    </div>
                </div>
            </div>
            
            @if($delta !== null && !$loading)
                <div class="mt-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $deltaClass }}">
                        <i class="{{ $deltaIcon }} mr-1"></i>
                        {{ $deltaText }}
                    </span>
                </div>
            @endif
            
            @if(!$loading)
                {{-- Sparkline placeholder --}}
                <div class="mt-3">
                    <div class="min-h-[32px] flex items-center">
                        @if($sparkline && is_array($sparkline) && count($sparkline) > 1)
                            <svg width="120" height="32" class="stroke-current opacity-60" viewBox="0 0 120 32">
                                @foreach($sparkline as $index => $value)
                                    @if($index < count($sparkline) - 1)
                                        <line x1="{{ $index * 10 }}" y1="{{ 16 - $value * 0.3 }}" 
                                              x2="{{ ($index + 1) * 10 }}" y2="{{ 16 - $sparkline[$index + 1] * 0.3 }}" 
                                              stroke="currentColor" stroke-width="1.5"/>
                                    @endif
                                @endforeach
                            </svg>
                        @else
                            {{-- Empty sparkline area for consistent height --}}
                            <div class="w-30 h-8 opacity-30"></div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
        
        @if($loading)
            {{-- Loading skeleton for consistent height --}}
            <div class="flex items-center space-x-3 animate-pulse">
                <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
                <div class="flex-1">
                    <div class="h-3 bg-gray-200 rounded w-20 mb-2"></div>
                    <div class="h-6 bg-gray-200 rounded w-16"></div>
                </div>
            </div>
        @endif
    </div>
</div>
