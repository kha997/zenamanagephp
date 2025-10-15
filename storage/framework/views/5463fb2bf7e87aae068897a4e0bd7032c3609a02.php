


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'filled', 'outlined'
    'error' => null,
    'help' => null,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right'
    'theme' => 'light'
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'filled', 'outlined'
    'error' => null,
    'help' => null,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right'
    'theme' => 'light'
]); ?>
<?php foreach (array_filter(([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'filled', 'outlined'
    'error' => null,
    'help' => null,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right'
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
    $inputId = $name ?: 'input-' . uniqid();
    $hasError = !empty($error);
    $hasIcon = !empty($icon);
    
    $sizeClasses = [
        'sm' => 'px-3 py-2 text-sm',
        'md' => 'px-4 py-3 text-sm',
        'lg' => 'px-4 py-4 text-base'
    ];
    
    $inputClasses = [
        'form-input',
        $sizeClasses[$size],
        $variant === 'filled' ? 'filled' : '',
        $variant === 'outlined' ? 'outlined' : '',
        $hasError ? 'error' : '',
        $hasIcon ? 'with-icon' : '',
        $hasIcon && $iconPosition === 'right' ? 'icon-right' : ''
    ];
?>

<div class="form-group">
    
    <?php if($label): ?>
        <label for="<?php echo e($inputId); ?>" class="form-label">
            <?php echo e($label); ?>

            <?php if($required): ?>
                <span class="text-red-500">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>
    
    
    <div class="form-input-container">
        
        <?php if($hasIcon && $iconPosition === 'left'): ?>
            <div class="form-input-icon-left">
                <i class="<?php echo e($icon); ?>"></i>
            </div>
        <?php endif; ?>
        
        
        <input type="<?php echo e($type); ?>"
               id="<?php echo e($inputId); ?>"
               name="<?php echo e($name); ?>"
               value="<?php echo e(old($name, $value)); ?>"
               <?php if($placeholder): ?> placeholder="<?php echo e($placeholder); ?>" <?php endif; ?>
               <?php if($required): ?> required <?php endif; ?>
               <?php if($disabled): ?> disabled <?php endif; ?>
               <?php if($readonly): ?> readonly <?php endif; ?>
               <?php if($autocomplete): ?> autocomplete="<?php echo e($autocomplete); ?>" <?php endif; ?>
               class="<?php echo e(implode(' ', array_filter($inputClasses))); ?>"
               <?php echo e($attributes); ?>>
        
        
        <?php if($hasIcon && $iconPosition === 'right'): ?>
            <div class="form-input-icon-right">
                <i class="<?php echo e($icon); ?>"></i>
            </div>
        <?php endif; ?>
    </div>
    
    
    <?php if($hasError): ?>
        <div class="form-error">
            <i class="fas fa-exclamation-circle mr-1"></i>
            <?php echo e($error); ?>

        </div>
    <?php endif; ?>
    
    
    <?php if($help): ?>
        <div class="form-help">
            <?php echo e($help); ?>

        </div>
    <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/form-input.blade.php ENDPATH**/ ?>