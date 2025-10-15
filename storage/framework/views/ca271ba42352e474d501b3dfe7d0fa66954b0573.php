

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['status' => 'draft']) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['status' => 'draft']); ?>
<?php foreach (array_filter((['status' => 'draft']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $statusConfig = [
        'draft' => [
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'fas fa-edit',
            'label' => __('quotes.draft')
        ],
        'sent' => [
            'class' => 'bg-blue-100 text-blue-800',
            'icon' => 'fas fa-paper-plane',
            'label' => __('quotes.sent')
        ],
        'viewed' => [
            'class' => 'bg-yellow-100 text-yellow-800',
            'icon' => 'fas fa-eye',
            'label' => __('quotes.viewed')
        ],
        'accepted' => [
            'class' => 'bg-green-100 text-green-800',
            'icon' => 'fas fa-check-circle',
            'label' => __('quotes.accepted')
        ],
        'rejected' => [
            'class' => 'bg-red-100 text-red-800',
            'icon' => 'fas fa-times-circle',
            'label' => __('quotes.rejected')
        ],
        'expired' => [
            'class' => 'bg-gray-100 text-gray-800',
            'icon' => 'fas fa-clock',
            'label' => __('quotes.expired')
        ],
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['draft'];
?>

<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($config['class']); ?>">
    <i class="<?php echo e($config['icon']); ?> mr-1"></i>
    <?php echo e($config['label']); ?>

</span>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/quotes/status-badge.blade.php ENDPATH**/ ?>