{{-- Standardized Modal Component --}}
{{-- Reusable modal with backdrop, focus trap, and consistent styling --}}

@props([
    'id' => null,
    'title' => '',
    'size' => 'md', // sm, md, lg, xl, full
    'closable' => true,
    'backdrop' => true,
    'persistent' => false,
    'theme' => 'light',
    'animation' => 'fade', // fade, slide, scale
    'position' => 'center' // center, top, bottom
])

@php
    $modalId = $id ?? 'modal-' . uniqid();
    $sizeClasses = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        'full' => 'max-w-full mx-4'
    ];
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
    
    $positionClasses = [
        'center' => 'items-center',
        'top' => 'items-start pt-16',
        'bottom' => 'items-end pb-16'
    ];
    $positionClass = $positionClasses[$position] ?? $positionClasses['center'];
@endphp

<div x-data="modalComponent('{{ $modalId }}')" 
     x-show="isOpen" 
     x-cloak
     @keydown.escape.window="close()"
     @click.self="close()"
     class="modal-overlay"
     :class="{ 'modal-overlay-open': isOpen }">
    
    {{-- Backdrop --}}
    @if($backdrop)
        <div class="modal-backdrop" 
             x-show="isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>
    @endif
    
    {{-- Modal Container --}}
    <div class="modal-container {{ $positionClass }}"
         x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>
        
        {{-- Modal Content --}}
        <div class="modal-content {{ $sizeClass }}"
             role="dialog"
             aria-modal="true"
             :aria-labelledby="$id('modal-title')"
             :aria-describedby="$id('modal-description')">
            
            {{-- Header --}}
            @if($title || $closable)
                <div class="modal-header">
                    @if($title)
                        <h3 :id="$id('modal-title')" class="modal-title">
                            {{ $title }}
                        </h3>
                    @endif
                    
                    @if($closable)
                        <button @click="close()" 
                                class="modal-close-btn"
                                aria-label="Close modal">
                            <i class="fas fa-times"></i>
                        </button>
                    @endif
                </div>
            @endif
            
            {{-- Body --}}
            <div class="modal-body" :id="$id('modal-description')">
                {{ $slot }}
            </div>
            
            {{-- Footer --}}
            @if(isset($footer))
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('modalComponent', (modalId) => ({
        isOpen: false,
        
        init() {
            // Listen for modal events
            this.$watch('isOpen', (value) => {
                if (value) {
                    this.open();
                } else {
                    this.close();
                }
            });
            
            // Listen for external open/close events
            this.$el.addEventListener('modal-open', () => this.open());
            this.$el.addEventListener('modal-close', () => this.close());
        },
        
        open() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            
            // Focus management
            this.$nextTick(() => {
                const focusable = this.$el.querySelector('[autofocus], button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) {
                    focusable.focus();
                }
            });
            
            // Dispatch event
            this.$dispatch('modal-opened', { modalId });
        },
        
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
            
            // Dispatch event
            this.$dispatch('modal-closed', { modalId });
        },
        
        toggle() {
            this.isOpen = !this.isOpen;
        }
    }));
});

// Global modal functions
window.openModal = function(modalId) {
    const modal = document.querySelector(`[x-data*="modalComponent('${modalId}')"]`);
    if (modal) {
        modal.dispatchEvent(new CustomEvent('modal-open'));
    }
};

window.closeModal = function(modalId) {
    const modal = document.querySelector(`[x-data*="modalComponent('${modalId}')"]`);
    if (modal) {
        modal.dispatchEvent(new CustomEvent('modal-close'));
    }
};

window.toggleModal = function(modalId) {
    const modal = document.querySelector(`[x-data*="modalComponent('${modalId}')"]`);
    if (modal) {
        const alpineData = Alpine.$data(modal);
        alpineData.toggle();
    }
};
</script>
@endpush
