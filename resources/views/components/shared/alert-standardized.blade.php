{{-- Standardized Alert Component --}}
{{-- Reusable alert component with different variants --}}

@props([
    'type' => 'info', // 'success', 'warning', 'error', 'info'
    'title' => null,
    'message' => null,
    'dismissible' => true,
    'icon' => null,
    'actions' => null,
    'variant' => 'default', // 'default', 'bordered', 'filled'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
])

@php
    $typeConfig = [
        'success' => [
            'icon' => 'fas fa-check-circle',
            'bg' => 'bg-green-50',
            'border' => 'border-green-200',
            'text' => 'text-green-800',
            'icon-color' => 'text-green-400'
        ],
        'warning' => [
            'icon' => 'fas fa-exclamation-triangle',
            'bg' => 'bg-yellow-50',
            'border' => 'border-yellow-200',
            'text' => 'text-yellow-800',
            'icon-color' => 'text-yellow-400'
        ],
        'error' => [
            'icon' => 'fas fa-exclamation-circle',
            'bg' => 'bg-red-50',
            'border' => 'border-red-200',
            'text' => 'text-red-800',
            'icon-color' => 'text-red-400'
        ],
        'info' => [
            'icon' => 'fas fa-info-circle',
            'bg' => 'bg-blue-50',
            'border' => 'border-blue-200',
            'text' => 'text-blue-800',
            'icon-color' => 'text-blue-400'
        ]
    ];
    
    $config = $typeConfig[$type] ?? $typeConfig['info'];
    $alertIcon = $icon ?? $config['icon'];
    
    $sizeClasses = [
        'sm' => 'p-3',
        'md' => 'p-4',
        'lg' => 'p-6'
    ];
    
    $alertClasses = [
        'alert',
        $config['bg'],
        $config['border'],
        $config['text'],
        $sizeClasses[$size],
        $variant === 'bordered' ? 'border-2' : 'border',
        $variant === 'filled' ? 'bg-opacity-100' : '',
        'rounded-md'
    ];
@endphp

<div class="{{ implode(' ', array_filter($alertClasses)) }}" 
     x-data="alertComponent()"
     x-show="visible"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform scale-95"
     x-transition:enter-end="opacity-100 transform scale-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform scale-100"
     x-transition:leave-end="opacity-0 transform scale-95"
     role="alert">
    
    <div class="flex">
        {{-- Icon --}}
        <div class="flex-shrink-0">
            <i class="{{ $alertIcon }} {{ $config['icon-color'] }}"></i>
        </div>
        
        {{-- Content --}}
        <div class="ml-3 flex-1">
            @if($title)
                <h3 class="text-sm font-medium mb-1">
                    {{ $title }}
                </h3>
            @endif
            
            @if($message)
                <div class="text-sm">
                    {{ $message }}
                </div>
            @else
                {{ $slot }}
            @endif
            
            {{-- Actions --}}
            @if($actions)
                <div class="mt-3">
                    {{ $actions }}
                </div>
            @endif
        </div>
        
        {{-- Dismiss Button --}}
        @if($dismissible)
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button @click="dismiss()" 
                            class="inline-flex rounded-md p-1.5 hover:bg-opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-50 focus:ring-gray-600">
                        <span class="sr-only">Dismiss</span>
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('alertComponent', () => ({
        visible: true,
        
        dismiss() {
            this.visible = false;
            this.$dispatch('alert-dismissed', { type: '{{ $type }}' });
        }
    }));
});
</script>
@endpush
