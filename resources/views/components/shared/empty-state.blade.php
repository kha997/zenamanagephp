{{-- Standardized Empty State Component --}}
{{-- Reusable empty state component for various scenarios --}}

@props([
    'icon' => 'fas fa-inbox',
    'title' => 'No items found',
    'description' => 'There are no items to display at the moment.',
    'action' => null,
    'actionText' => null,
    'actionIcon' => null,
    'actionHandler' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'minimal', 'illustrated'
    'theme' => 'light'
])

@php
    $sizeClasses = [
        'sm' => 'py-8',
        'md' => 'py-12',
        'lg' => 'py-16'
    ];
    
    $iconSizes = [
        'sm' => 'text-3xl',
        'md' => 'text-4xl',
        'lg' => 'text-6xl'
    ];
    
    $titleSizes = [
        'sm' => 'text-lg',
        'md' => 'text-lg',
        'lg' => 'text-xl'
    ];
    
    $emptyStateClasses = [
        'empty-state',
        'text-center',
        $sizeClasses[$size]
    ];
@endphp

<div class="{{ implode(' ', array_filter($emptyStateClasses)) }}">
    {{-- Icon --}}
    <div class="flex justify-center mb-4">
        @if($variant === 'illustrated')
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center">
                <i class="{{ $icon }} {{ $iconSizes[$size] }} text-gray-400"></i>
            </div>
        @else
            <i class="{{ $icon }} {{ $iconSizes[$size] }} text-gray-300"></i>
        @endif
    </div>
    
    {{-- Title --}}
    <h3 class="{{ $titleSizes[$size] }} font-medium text-gray-900 mb-2">
        {{ $title }}
    </h3>
    
    {{-- Description --}}
    <p class="text-gray-500 mb-6 max-w-sm mx-auto">
        {{ $description }}
    </p>
    
    {{-- Action Button --}}
    @if($action || $actionHandler)
        <div class="flex justify-center">
            @if($action)
                {{ $action }}
            @elseif($actionHandler)
                <button @click="{{ $actionHandler }}" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    @if($actionIcon)
                        <i class="{{ $actionIcon }} mr-2"></i>
                    @endif
                    {{ $actionText ?? 'Get Started' }}
                </button>
            @endif
        </div>
    @endif
    
    {{-- Custom Content Slot --}}
    @if($slot->isNotEmpty())
        <div class="mt-6">
            {{ $slot }}
        </div>
    @endif
</div>