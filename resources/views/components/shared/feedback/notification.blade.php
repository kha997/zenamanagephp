@props([
    'type' => 'info', // info, success, warning, error
    'title' => '',
    'message' => '',
    'dismissible' => true,
    'icon' => null
])

@php
$typeClasses = [
    'info' => [
        'bg' => 'bg-blue-50',
        'border' => 'border-blue-400',
        'icon' => 'fas fa-info-circle text-blue-400',
        'text' => 'text-blue-700'
    ],
    'success' => [
        'bg' => 'bg-green-50',
        'border' => 'border-green-400',
        'icon' => 'fas fa-check-circle text-green-400',
        'text' => 'text-green-700'
    ],
    'warning' => [
        'bg' => 'bg-yellow-50',
        'border' => 'border-yellow-400',
        'icon' => 'fas fa-exclamation-triangle text-yellow-400',
        'text' => 'text-yellow-700'
    ],
    'error' => [
        'bg' => 'bg-red-50',
        'border' => 'border-red-400',
        'icon' => 'fas fa-times-circle text-red-400',
        'text' => 'text-red-700'
    ]
];

$classes = $typeClasses[$type] ?? $typeClasses['info'];
$iconClass = $icon ?: $classes['icon'];
@endphp

<div class="{{ $classes['bg'] }} border-l-4 {{ $classes['border'] }} p-4 mb-4" 
     x-data="{ show: true }" 
     x-show="show" 
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="{{ $iconClass }}"></i>
        </div>
        <div class="ml-3 flex-1">
            @if($title)
                <p class="text-sm {{ $classes['text'] }}">
                    <strong>{{ $title }}</strong>
                    @if($message)
                        {{ $message }}
                    @endif
                </p>
            @else
                <p class="text-sm {{ $classes['text'] }}">
                    {{ $message }}
                </p>
            @endif
        </div>
        @if($dismissible)
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button @click="show = false" 
                            class="inline-flex {{ $classes['bg'] }} rounded-md p-1.5 {{ $classes['text'] }} hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-gray-600">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times h-3 w-3"></i>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
