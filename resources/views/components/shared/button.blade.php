{{-- Button Component --}}
{{-- Unified button styles for consistent UI --}}

@props([
    'type' => 'button',
    'variant' => 'primary', // primary, secondary, outline, danger, ghost
    'size' => 'md', // sm, md, lg
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // left, right
    'class' => '',
    'href' => null,
    'target' => null
])

@php
    // Size classes
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base'
    ];
    
    // Variant classes
    $variantClasses = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 border-transparent',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500 border-transparent',
        'outline' => 'bg-white text-gray-700 hover:bg-gray-50 focus:ring-blue-500 border-gray-300',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 border-transparent',
        'ghost' => 'bg-transparent text-gray-700 hover:bg-gray-100 focus:ring-gray-500 border-transparent'
    ];
    
    // Base classes
    $baseClasses = 'inline-flex items-center justify-center font-medium rounded-md border transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';
    
    // Combine classes
    $finalClasses = $baseClasses . ' ' . $sizeClasses[$size] . ' ' . $variantClasses[$variant];
    if ($class) {
        $finalClasses .= ' ' . $class;
    }
    
    // Determine if it's a link
    $isLink = $href !== null;
    $tag = $isLink ? 'a' : 'button';
    
    // Attributes
    $attributes = $attributes->merge([
        'class' => $finalClasses,
        'disabled' => $disabled || $loading
    ]);
    
    if ($isLink) {
        $attributes = $attributes->merge([
            'href' => $href,
            'target' => $target
        ]);
    } else {
        $attributes = $attributes->merge(['type' => $type]);
    }
@endphp

<{{ $tag }} {{ $attributes }}>
    @if($loading)
        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif($icon && $iconPosition === 'left')
        <i class="{{ $icon }} mr-2"></i>
    @endif
    
    {{ $slot }}
    
    @if($icon && $iconPosition === 'right')
        <i class="{{ $icon }} ml-2"></i>
    @endif
</{{ $tag }}>
