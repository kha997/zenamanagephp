{{-- Legacy Banner Component --}}
{{-- Displays on all Blade views to indicate they are view-only --}}
{{-- Part of Blade deprecation plan (ADR-002) --}}

@props([
    'dismissible' => false,
    'variant' => 'warning', // warning, info, danger
])

@php
    $variant = in_array($variant, ['warning', 'info', 'danger']) ? $variant : 'warning';
    $dismissible = filter_var($dismissible, FILTER_VALIDATE_BOOLEAN);
    
    $variantClasses = [
        'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
        'info' => 'bg-blue-50 border-blue-200 text-blue-800',
        'danger' => 'bg-red-50 border-red-200 text-red-800',
    ];
    
    $iconClasses = [
        'warning' => 'text-yellow-600',
        'info' => 'text-blue-600',
        'danger' => 'text-red-600',
    ];
    
    $classes = $variantClasses[$variant] ?? $variantClasses['warning'];
    $iconClass = $iconClasses[$variant] ?? $iconClasses['warning'];
@endphp

<div 
    x-data="{ dismissed: false }"
    x-show="!dismissed"
    x-transition
    class="legacy-banner border-b {{ $classes }} px-4 py-3 flex items-center justify-between"
    role="alert"
    aria-live="polite"
>
    <div class="flex items-center gap-3 flex-1">
        <svg 
            class="w-5 h-5 {{ $iconClass }} flex-shrink-0" 
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
            aria-hidden="true"
        >
            <path 
                stroke-linecap="round" 
                stroke-linejoin="round" 
                stroke-width="2" 
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
        </svg>
        
        <div class="flex-1">
            <p class="text-sm font-medium">
                <strong>Legacy View - Read Only</strong>
            </p>
            <p class="text-xs mt-1 opacity-90">
                This Blade view is deprecated. All business logic is handled via API. 
                For full functionality, use the React SPA at <code class="bg-white/50 px-1 rounded">/app/*</code>.
            </p>
        </div>
    </div>
    
    @if($dismissible)
        <button
            @click="dismissed = true"
            type="button"
            class="ml-4 flex-shrink-0 {{ $iconClass }} hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-current rounded"
            aria-label="Dismiss banner"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>

<style>
    .legacy-banner {
        z-index: 1000;
    }
</style>

