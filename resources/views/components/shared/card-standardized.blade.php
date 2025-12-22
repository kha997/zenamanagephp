{{-- Standardized Card Component --}}
{{-- Reusable card component with consistent styling --}}

@props([
    'title' => null,
    'subtitle' => null,
    'header' => null,
    'footer' => null,
    'variant' => 'default', // 'default', 'bordered', 'elevated', 'flat'
    'size' => 'md', // 'sm', 'md', 'lg'
    'padding' => null, // 'none', 'sm', 'md', 'lg'
    'hover' => false,
    'clickable' => false,
    'loading' => false,
    'theme' => 'light'
])

@php
    $isBordered = $variant === 'bordered';
    $isElevated = $variant === 'elevated';
    $isFlat = $variant === 'flat';
    
    $sizeClasses = [
        'sm' => 'p-4',
        'md' => 'p-6',
        'lg' => 'p-8'
    ];
    
    $paddingClass = $padding ? $sizeClasses[$padding] : $sizeClasses[$size];
    
    $cardClasses = [
        'card',
        $isBordered ? 'bordered' : '',
        $isElevated ? 'elevated' : '',
        $isFlat ? 'flat' : '',
        $hover ? 'hover' : '',
        $clickable ? 'clickable' : '',
        $loading ? 'loading' : ''
    ];
@endphp

<div class="{{ implode(' ', array_filter($cardClasses)) }}" 
     @if($clickable) onclick="{{ $clickable }}" @endif
     x-data="cardComponent()">
    
    {{-- Loading Overlay --}}
    @if($loading)
        <div class="card-loading-overlay">
            <div class="card-loading-spinner">
                <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
            </div>
        </div>
    @endif
    
    {{-- Card Header --}}
    @if($header || $title || $subtitle)
        <div class="card-header">
            @if($header)
                {{ $header }}
            @else
                <div class="card-header-content">
                    @if($title)
                        <h3 class="card-title">{{ $title }}</h3>
                    @endif
                    @if($subtitle)
                        <p class="card-subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
    
    {{-- Card Body --}}
    <div class="card-body {{ $paddingClass }}">
        {{ $slot }}
    </div>
    
    {{-- Card Footer --}}
    @if($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cardComponent', () => ({
        loading: {{ $loading ? 'true' : 'false' }},
        
        init() {
            // Initialize card component
        }
    }));
});
</script>
@endpush
