


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'type' => 'text',
    'label' => null,
    'error' => null,
    'helperText' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'value' => null,
    'name' => null,
    'id' => null,
    'class' => '',
    'inputClass' => '',
    'labelClass' => '',
    'errorClass' => '',
    'helperClass' => ''
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'type' => 'text',
    'label' => null,
    'error' => null,
    'helperText' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'value' => null,
    'name' => null,
    'id' => null,
    'class' => '',
    'inputClass' => '',
    'labelClass' => '',
    'errorClass' => '',
    'helperClass' => ''
]); ?>
<?php foreach (array_filter(([
    'type' => 'text',
    'label' => null,
    'error' => null,
    'helperText' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'value' => null,
    'name' => null,
    'id' => null,
    'class' => '',
    'inputClass' => '',
    'labelClass' => '',
    'errorClass' => '',
    'helperClass' => ''
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $inputId = $id ?? $name ?? uniqid('input_');
    $inputName = $name ?? '';
    $inputValue = $value ?? old($inputName);
    
    // Base classes
    $baseInputClass = 'block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors duration-200';
    $errorInputClass = 'border-red-300 focus:ring-red-500 focus:border-red-500';
    $disabledInputClass = 'bg-gray-50 text-gray-500 cursor-not-allowed';
    
    // Combine classes
    $finalInputClass = $baseInputClass;
    if ($error) {
        $finalInputClass .= ' ' . $errorInputClass;
    }
    if ($disabled) {
        $finalInputClass .= ' ' . $disabledInputClass;
    }
    if ($inputClass) {
        $finalInputClass .= ' ' . $inputClass;
    }
    
    $finalClass = $class ?: '';
?>

<div class="space-y-1 <?php echo e($finalClass); ?>">
    <?php if($label): ?>
        <label for="<?php echo e($inputId); ?>" class="block text-sm font-medium text-gray-700 <?php echo e($labelClass); ?>">
            <?php echo e($label); ?>

            <?php if($required): ?>
                <span class="text-red-500 ml-1">*</span>
            <?php endif; ?>
        </label>
    <?php endif; ?>
    
    <?php if($type === 'textarea'): ?>
        <textarea
            id="<?php echo e($inputId); ?>"
            name="<?php echo e($inputName); ?>"
            rows="3"
            class="<?php echo e($finalInputClass); ?>"
            placeholder="<?php echo e($placeholder); ?>"
            <?php echo e($required ? 'required' : ''); ?>

            <?php echo e($disabled ? 'disabled' : ''); ?>

            <?php echo e($attributes->merge(['class' => ''])); ?>

        ><?php echo e($inputValue); ?></textarea>
    <?php elseif($type === 'select'): ?>
        <select
            id="<?php echo e($inputId); ?>"
            name="<?php echo e($inputName); ?>"
            class="<?php echo e($finalInputClass); ?>"
            <?php echo e($required ? 'required' : ''); ?>

            <?php echo e($disabled ? 'disabled' : ''); ?>

            <?php echo e($attributes->merge(['class' => ''])); ?>

        >
            <?php if($placeholder): ?>
                <option value=""><?php echo e($placeholder); ?></option>
            <?php endif; ?>
            <?php echo e($slot); ?>

        </select>
    <?php else: ?>
        <input
            type="<?php echo e($type); ?>"
            id="<?php echo e($inputId); ?>"
            name="<?php echo e($inputName); ?>"
            value="<?php echo e($inputValue); ?>"
            class="<?php echo e($finalInputClass); ?>"
            placeholder="<?php echo e($placeholder); ?>"
            <?php echo e($required ? 'required' : ''); ?>

            <?php echo e($disabled ? 'disabled' : ''); ?>

            <?php echo e($attributes->merge(['class' => ''])); ?>

        />
    <?php endif; ?>
    
    <?php if($error): ?>
        <p class="text-sm text-red-600 <?php echo e($errorClass); ?>"><?php echo e($error); ?></p>
    <?php elseif($helperText): ?>
        <p class="text-sm text-gray-500 <?php echo e($helperClass); ?>"><?php echo e($helperText); ?></p>
    <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/form-controls.blade.php ENDPATH**/ ?>