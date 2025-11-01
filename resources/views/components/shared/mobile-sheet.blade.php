{{-- Mobile Sheet Component --}}
{{-- Bottom sheet for mobile interactions --}}

@props([
    'open' => false,
    'position' => 'bottom', // 'bottom', 'top', 'left', 'right'
    'size' => 'md', // 'sm', 'md', 'lg', 'full'
    'backdrop' => true,
    'dismissible' => true,
    'title' => null,
    'actions' => null,
    'theme' => 'light'
])

@php
    $positionClasses = [
        'bottom' => 'bottom-0 left-0 right-0',
        'top' => 'top-0 left-0 right-0',
        'left' => 'left-0 top-0 bottom-0',
        'right' => 'right-0 top-0 bottom-0'
    ];
    
    $sizeClasses = [
        'sm' => 'h-1/3',
        'md' => 'h-1/2',
        'lg' => 'h-2/3',
        'full' => 'h-full'
    ];
    
    $transformClasses = [
        'bottom' => 'translate-y-full',
        'top' => '-translate-y-full',
        'left' => '-translate-x-full',
        'right' => 'translate-x-full'
    ];
    
    $sheetClasses = [
        'mobile-sheet',
        'fixed',
        'z-modal',
        $positionClasses[$position],
        $sizeClasses[$size],
        'bg-white',
        'shadow-xl',
        'transform',
        'transition-transform',
        'duration-300',
        'ease-in-out'
    ];
@endphp

<div x-data="mobileSheet()" 
     x-show="open"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mobile-sheet-container">
    
    {{-- Backdrop --}}
    @if($backdrop)
        <div class="mobile-sheet-backdrop"
             @click="dismiss()"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>
    @endif
    
    {{-- Sheet Content --}}
    <div class="{{ implode(' ', array_filter($sheetClasses)) }}"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="transform {{ $transformClasses[$position] }}"
         x-transition:enter-end="transform translate-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="transform translate-0"
         x-transition:leave-end="transform {{ $transformClasses[$position] }}">
        
        {{-- Handle (for bottom sheets) --}}
        @if($position === 'bottom')
            <div class="flex justify-center pt-2 pb-1">
                <div class="w-8 h-1 bg-gray-300 rounded-full"></div>
            </div>
        @endif
        
        {{-- Header --}}
        @if($title || $actions)
            <div class="mobile-sheet-header">
                <div class="flex items-center justify-between">
                    @if($title)
                        <h3 class="mobile-sheet-title">{{ $title }}</h3>
                    @endif
                    
                    @if($dismissible)
                        <button @click="dismiss()" 
                                class="mobile-sheet-close-btn">
                            <i class="fas fa-times"></i>
                        </button>
                    @endif
                </div>
                
                @if($actions)
                    <div class="mobile-sheet-actions">
                        {{ $actions }}
                    </div>
                @endif
            </div>
        @endif
        
        {{-- Body --}}
        <div class="mobile-sheet-body">
            {{ $slot }}
        </div>
    </div>
</div>

<style>
.mobile-sheet-container {
    @apply fixed inset-0 z-modal;
}

.mobile-sheet-backdrop {
    @apply absolute inset-0 bg-black bg-opacity-50;
}

.mobile-sheet-header {
    @apply px-4 py-3 border-b border-gray-200 bg-gray-50;
}

.mobile-sheet-title {
    @apply text-lg font-semibold text-gray-900;
}

.mobile-sheet-close-btn {
    @apply p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100;
}

.mobile-sheet-actions {
    @apply mt-3 flex space-x-2;
}

.mobile-sheet-body {
    @apply flex-1 overflow-y-auto p-4;
}
</style>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mobileSheet', () => ({
        open: {{ $open ? 'true' : 'false' }},
        
        dismiss() {
            if ({{ $dismissible ? 'true' : 'false' }}) {
                this.open = false;
                this.$dispatch('sheet-dismissed');
            }
        },
        
        show() {
            this.open = true;
            this.$dispatch('sheet-shown');
        }
    }));
});
</script>
@endpush
