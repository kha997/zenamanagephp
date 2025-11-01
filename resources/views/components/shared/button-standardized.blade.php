{{-- Standardized Button Component --}}
{{-- Reusable button with consistent styling and states --}}

@props([
    'type' => 'button', // 'button', 'submit', 'reset'
    'variant' => 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost', 'link'
    'size' => 'md', // 'xs', 'sm', 'md', 'lg', 'xl'
    'disabled' => false,
    'loading' => false,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right', 'only'
    'href' => null,
    'target' => null,
    'theme' => 'light'
])

@php
    $isLink = !empty($href);
    $hasIcon = !empty($icon);
    $isIconOnly = $hasIcon && $iconPosition === 'only';
    
    $sizeClasses = [
        'xs' => 'px-2 py-1 text-xs',
        'sm' => 'px-3 py-2 text-sm',
        'md' => 'px-4 py-2 text-sm',
        'lg' => 'px-6 py-3 text-base',
        'xl' => 'px-8 py-4 text-lg'
    ];
    
    $variantClasses = [
        'primary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500',
        'secondary' => 'bg-gray-600 text-white hover:bg-gray-700 focus:ring-gray-500',
        'success' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
        'warning' => 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500',
        'info' => 'bg-cyan-600 text-white hover:bg-cyan-700 focus:ring-cyan-500',
        'ghost' => 'bg-transparent text-gray-700 hover:bg-gray-100 focus:ring-gray-500',
        'link' => 'bg-transparent text-blue-600 hover:text-blue-800 focus:ring-blue-500 underline'
    ];
    
    $baseClasses = [
        'btn',
        $sizeClasses[$size],
        $variantClasses[$variant] ?? $variantClasses['primary'],
        $disabled || $loading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
        $isIconOnly ? 'p-2' : '',
        'inline-flex items-center justify-center border border-transparent font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-200'
    ];
@endphp

@if($isLink)
    <a href="{{ $href }}"
       @if($target) target="{{ $target }}" @endif
       @if($disabled || $loading) onclick="return false;" @endif
       class="{{ implode(' ', array_filter($baseClasses)) }}"
       {{ $attributes }}>
        
        {{-- Loading Spinner --}}
        @if($loading)
            <i class="fas fa-spinner fa-spin mr-2"></i>
        @endif
        
        {{-- Left Icon --}}
        @if($hasIcon && ($iconPosition === 'left' || $iconPosition === 'only'))
            <i class="{{ $icon }} {{ $isIconOnly ? '' : 'mr-2' }}"></i>
        @endif
        
        {{-- Button Text --}}
        @if(!$isIconOnly)
            {{ $slot }}
        @endif
        
        {{-- Right Icon --}}
        @if($hasIcon && $iconPosition === 'right')
            <i class="{{ $icon }} ml-2"></i>
        @endif
    </a>
@else
    <button type="{{ $type }}"
            @if($disabled || $loading) disabled @endif
            class="{{ implode(' ', array_filter($baseClasses)) }}"
            {{ $attributes }}>
        
        {{-- Loading Spinner --}}
        @if($loading)
            <i class="fas fa-spinner fa-spin mr-2"></i>
        @endif
        
        {{-- Left Icon --}}
        @if($hasIcon && ($iconPosition === 'left' || $iconPosition === 'only'))
            <i class="{{ $icon }} {{ $isIconOnly ? '' : 'mr-2' }}"></i>
        @endif
        
        {{-- Button Text --}}
        @if(!$isIconOnly)
            {{ $slot }}
        @endif
        
        {{-- Right Icon --}}
        @if($hasIcon && $iconPosition === 'right')
            <i class="{{ $icon }} ml-2"></i>
        @endif
    </button>
@endif
