{{-- Accessibility Focus Manager Component --}}
{{-- Manages focus for modals, drawers, and dynamic content --}}

<div x-data="focusManager()" class="focus-manager">
    <!-- Focus trap for modals and drawers -->
    <div 
        x-show="isModalOpen" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="modalTitleId"
        :aria-describedby="modalDescriptionId"
        @keydown.escape="closeModal()"
        @keydown.tab="handleTabKey($event)"
    >
        <!-- Modal content with proper focus management -->
        <div class="flex min-h-full items-center justify-center p-4">
            <div 
                class="relative bg-white rounded-lg shadow-xl max-w-md w-full"
                @click.stop
                x-ref="modalContent"
            >
                <!-- Modal header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 :id="modalTitleId" class="text-lg font-semibold text-gray-900">
                        <slot name="title">Modal Title</slot>
                    </h2>
                </div>
                
                <!-- Modal body -->
                <div class="px-6 py-4">
                    <p :id="modalDescriptionId" class="text-gray-600">
                        <slot name="description">Modal description</slot>
                    </p>
                    <slot name="content"></slot>
                </div>
                
                <!-- Modal footer -->
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button 
                        @click="closeModal()"
                        class="btn-secondary"
                        ref="cancelButton"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="confirmAction()"
                        class="btn-primary"
                        ref="confirmButton"
                    >
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Live region for announcements -->
    <div 
        aria-live="polite" 
        aria-atomic="true" 
        class="sr-only"
        x-ref="liveRegion"
    ></div>
    
    <!-- Focus restoration point -->
    <div x-ref="focusRestorePoint" class="sr-only"></div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('focusManager', () => ({
        isModalOpen: false,
        modalTitleId: 'modal-title-' + Math.random().toString(36).substr(2, 9),
        modalDescriptionId: 'modal-description-' + Math.random().toString(36).substr(2, 9),
        previousFocusElement: null,
        focusableElements: [],
        
        init() {
            // Set up keyboard navigation
            document.addEventListener('keydown', this.handleGlobalKeydown.bind(this));
        },
        
        openModal() {
            this.isModalOpen = true;
            this.previousFocusElement = document.activeElement;
            
            // Get focusable elements within modal
            this.$nextTick(() => {
                this.focusableElements = this.getFocusableElements(this.$refs.modalContent);
                
                // Focus first focusable element
                if (this.focusableElements.length > 0) {
                    this.focusableElements[0].focus();
                }
            });
            
            // Announce modal opening
            this.announce('Modal opened');
        },
        
        closeModal() {
            this.isModalOpen = false;
            
            // Restore focus to previous element
            if (this.previousFocusElement) {
                this.previousFocusElement.focus();
            }
            
            // Announce modal closing
            this.announce('Modal closed');
        },
        
        confirmAction() {
            // Perform action
            this.announce('Action confirmed');
            this.closeModal();
        },
        
        getFocusableElements(container) {
            const focusableSelectors = [
                'button:not([disabled])',
                'input:not([disabled])',
                'select:not([disabled])',
                'textarea:not([disabled])',
                'a[href]',
                '[tabindex]:not([tabindex="-1"])',
                '[contenteditable="true"]'
            ];
            
            return container.querySelectorAll(focusableSelectors.join(', '));
        },
        
        handleTabKey(event) {
            if (!this.isModalOpen) return;
            
            const firstElement = this.focusableElements[0];
            const lastElement = this.focusableElements[this.focusableElements.length - 1];
            
            if (event.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    event.preventDefault();
                    lastElement.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    event.preventDefault();
                    firstElement.focus();
                }
            }
        },
        
        handleGlobalKeydown(event) {
            // Alt + M to open modal (accessibility shortcut)
            if (event.altKey && event.key === 'm') {
                event.preventDefault();
                this.openModal();
            }
            
            // Alt + S to focus search
            if (event.altKey && event.key === 's') {
                event.preventDefault();
                const searchInput = document.querySelector('[data-search-input]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Alt + N to focus navigation
            if (event.altKey && event.key === 'n') {
                event.preventDefault();
                const navElement = document.querySelector('[role="navigation"]');
                if (navElement) {
                    navElement.focus();
                }
            }
        },
        
        announce(message) {
            if (this.$refs.liveRegion) {
                this.$refs.liveRegion.textContent = message;
                
                // Clear after announcement
                setTimeout(() => {
                    this.$refs.liveRegion.textContent = '';
                }, 1000);
            }
        },
        
        // Method to programmatically set focus
        setFocus(element) {
            if (element && typeof element.focus === 'function') {
                element.focus();
            }
        },
        
        // Method to trap focus within a container
        trapFocus(container) {
            const focusableElements = this.getFocusableElements(container);
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            }
        }
    }));
});
</script>

<style>
/* Focus management styles */
.focus-manager {
    position: relative;
}

/* High contrast focus indicators */
.focus-manager *:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Modal focus styles */
.focus-manager [role="dialog"] {
    outline: none;
}

/* Button focus styles */
.btn-primary:focus,
.btn-secondary:focus {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

/* Ensure focus is visible */
.focus-manager button:focus-visible,
.focus-manager input:focus-visible,
.focus-manager select:focus-visible,
.focus-manager textarea:focus-visible {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}
</style>
