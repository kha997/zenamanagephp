


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'id' => null,
    'title' => '',
    'size' => 'md', // sm, md, lg, xl, full
    'closable' => true,
    'backdrop' => true,
    'persistent' => false,
    'theme' => 'light',
    'animation' => 'fade', // fade, slide, scale
    'position' => 'center' // center, top, bottom
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'id' => null,
    'title' => '',
    'size' => 'md', // sm, md, lg, xl, full
    'closable' => true,
    'backdrop' => true,
    'persistent' => false,
    'theme' => 'light',
    'animation' => 'fade', // fade, slide, scale
    'position' => 'center' // center, top, bottom
]); ?>
<?php foreach (array_filter(([
    'id' => null,
    'title' => '',
    'size' => 'md', // sm, md, lg, xl, full
    'closable' => true,
    'backdrop' => true,
    'persistent' => false,
    'theme' => 'light',
    'animation' => 'fade', // fade, slide, scale
    'position' => 'center' // center, top, bottom
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
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
?>

<div x-data="modalComponent('<?php echo e($modalId); ?>')" 
     x-show="isOpen" 
     x-cloak
     @keydown.escape.window="close()"
     @click.self="close()"
     class="modal-overlay"
     :class="{ 'modal-overlay-open': isOpen }">
    
    
    <?php if($backdrop): ?>
        <div class="modal-backdrop" 
             x-show="isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>
    <?php endif; ?>
    
    
    <div class="modal-container <?php echo e($positionClass); ?>"
         x-show="isOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         @click.stop>
        
        
        <div class="modal-content <?php echo e($sizeClass); ?>"
             role="dialog"
             aria-modal="true"
             :aria-labelledby="$id('modal-title')"
             :aria-describedby="$id('modal-description')">
            
            
            <?php if($title || $closable): ?>
                <div class="modal-header">
                    <?php if($title): ?>
                        <h3 :id="$id('modal-title')" class="modal-title">
                            <?php echo e($title); ?>

                        </h3>
                    <?php endif; ?>
                    
                    <?php if($closable): ?>
                        <button @click="close()" 
                                class="modal-close-btn"
                                aria-label="Close modal">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            
            <div class="modal-body" :id="$id('modal-description')">
                <?php echo e($slot); ?>

            </div>
            
            
            <?php if(isset($footer)): ?>
                <div class="modal-footer">
                    <?php echo e($footer); ?>

                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $__env->startPush('scripts'); ?>
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
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/modal.blade.php ENDPATH**/ ?>