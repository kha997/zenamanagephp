
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'type' => 'info', // info, success, warning, error
    'title' => null,
    'message' => null,
    'dismissible' => true
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'type' => 'info', // info, success, warning, error
    'title' => null,
    'message' => null,
    'dismissible' => true
]); ?>
<?php foreach (array_filter(([
    'type' => 'info', // info, success, warning, error
    'title' => null,
    'message' => null,
    'dismissible' => true
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
$alertClasses = [
    'info' => 'bg-blue-50 border-blue-200 text-blue-800',
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
    'error' => 'bg-red-50 border-red-200 text-red-800'
];

$iconClasses = [
    'info' => 'fas fa-info-circle text-blue-400',
    'success' => 'fas fa-check-circle text-green-400',
    'warning' => 'fas fa-exclamation-triangle text-yellow-400',
    'error' => 'fas fa-exclamation-circle text-red-400'
];
?>

<div class="rounded-md border p-4 <?php echo e($alertClasses[$type]); ?>" role="alert">
    <div class="flex">
        <div class="flex-shrink-0">
            <i class="<?php echo e($iconClasses[$type]); ?>"></i>
        </div>
        <div class="ml-3 flex-1">
            <?php if($title): ?>
            <h3 class="text-sm font-medium"><?php echo e($title); ?></h3>
            <?php endif; ?>
            <?php if($message): ?>
            <div class="mt-2 text-sm">
                <p><?php echo e($message); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php if($dismissible): ?>
        <div class="ml-auto pl-3">
            <div class="-mx-1.5 -my-1.5">
                <button type="button" class="inline-flex rounded-md p-1.5 hover:opacity-75 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-blue-50 focus:ring-blue-600" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()">
                    <span class="sr-only"><?php echo e(__('common.dismiss')); ?></span>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/alert.blade.php ENDPATH**/ ?>