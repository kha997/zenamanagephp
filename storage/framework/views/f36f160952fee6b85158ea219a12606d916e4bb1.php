<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['breadcrumbs' => null]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['breadcrumbs' => null]); ?>
<?php foreach (array_filter((['breadcrumbs' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $breadcrumbs = $breadcrumbs ?? \App\Services\BreadcrumbService::generate();
?>

<?php if(!empty($breadcrumbs)): ?>
<nav class="flex" aria-label="Breadcrumb">
    <ol class="flex items-center space-x-2">
        <?php $__currentLoopData = $breadcrumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $breadcrumb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if($index > 0): ?>
                <li class="flex items-center">
                    <svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                    </svg>
                </li>
            <?php endif; ?>
            
            <li class="flex items-center">
                <?php if($breadcrumb['active']): ?>
                    <span class="text-gray-500 font-medium"><?php echo e($breadcrumb['title']); ?></span>
                <?php else: ?>
                    <a href="<?php echo e($breadcrumb['url']); ?>" class="text-blue-600 hover:text-blue-800 font-medium">
                        <?php echo e($breadcrumb['title']); ?>

                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ol>
</nav>
<?php endif; ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/breadcrumb.blade.php ENDPATH**/ ?>