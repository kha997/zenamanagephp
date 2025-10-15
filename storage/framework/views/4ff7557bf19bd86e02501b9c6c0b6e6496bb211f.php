


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'open' => false,
    'variant' => 'default', // 'default', 'minimal', 'overlay'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'open' => false,
    'variant' => 'default', // 'default', 'minimal', 'overlay'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'open' => false,
    'variant' => 'default', // 'default', 'minimal', 'overlay'
    'size' => 'md', // 'sm', 'md', 'lg'
    'theme' => 'light'
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $sizeClasses = [
        'sm' => 'w-6 h-6',
        'md' => 'w-8 h-8',
        'lg' => 'w-10 h-10'
    ];
    
    $lineClasses = [
        'sm' => 'h-0.5',
        'md' => 'h-0.5',
        'lg' => 'h-1'
    ];
?>

<button class="hamburger-menu <?php echo e($sizeClasses[$size]); ?> flex flex-col justify-center items-center focus:outline-none focus:ring-2 focus:ring-blue-500 rounded-md"
        x-data="hamburgerMenu()"
        :class="{ 'active': open }"
        @click="toggle()"
        aria-label="Toggle mobile menu"
        aria-expanded="false"
        :aria-expanded="open">
    
    
    <span class="hamburger-line <?php echo e($lineClasses[$size]); ?> w-full bg-gray-600 transition-all duration-300 ease-in-out transform"
          :class="{ 'rotate-45 translate-y-1.5': open }"></span>
    
    <span class="hamburger-line <?php echo e($lineClasses[$size]); ?> w-full bg-gray-600 transition-all duration-300 ease-in-out mt-1"
          :class="{ 'opacity-0': open }"></span>
    
    <span class="hamburger-line <?php echo e($lineClasses[$size]); ?> w-full bg-gray-600 transition-all duration-300 ease-in-out mt-1 transform"
          :class="{ '-rotate-45 -translate-y-1.5': open }"></span>
</button>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('hamburgerMenu', () => ({
        open: <?php echo e($open ? 'true' : 'false'); ?>,
        
        toggle() {
            this.open = !this.open;
            this.$dispatch('hamburger-toggle', { open: this.open });
        }
    }));
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/hamburger-menu.blade.php ENDPATH**/ ?>