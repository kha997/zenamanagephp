
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps(['paginator' => null]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps(['paginator' => null]); ?>
<?php foreach (array_filter((['paginator' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php if($paginator && $paginator->hasPages()): ?>
<div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
    <div class="flex-1 flex justify-between sm:hidden">
        <?php if($paginator->onFirstPage()): ?>
        <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-not-allowed">
            <?php echo e(__('pagination.previous')); ?>

        </span>
        <?php else: ?>
        <a href="<?php echo e($paginator->previousPageUrl()); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            <?php echo e(__('pagination.previous')); ?>

        </a>
        <?php endif; ?>

        <?php if($paginator->hasMorePages()): ?>
        <a href="<?php echo e($paginator->nextPageUrl()); ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
            <?php echo e(__('pagination.next')); ?>

        </a>
        <?php else: ?>
        <span class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-500 bg-white cursor-not-allowed">
            <?php echo e(__('pagination.next')); ?>

        </span>
        <?php endif; ?>
    </div>
    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-gray-700">
                <?php echo e(__('pagination.showing')); ?>

                <span class="font-medium"><?php echo e($paginator->firstItem()); ?></span>
                <?php echo e(__('pagination.to')); ?>

                <span class="font-medium"><?php echo e($paginator->lastItem()); ?></span>
                <?php echo e(__('pagination.of')); ?>

                <span class="font-medium"><?php echo e($paginator->total()); ?></span>
                <?php echo e(__('pagination.results')); ?>

            </p>
        </div>
        <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                
                <?php if($paginator->onFirstPage()): ?>
                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 cursor-not-allowed">
                    <span class="sr-only"><?php echo e(__('pagination.previous')); ?></span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </span>
                <?php else: ?>
                <a href="<?php echo e($paginator->previousPageUrl()); ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only"><?php echo e(__('pagination.previous')); ?></span>
                    <i class="fas fa-chevron-left h-5 w-5"></i>
                </a>
                <?php endif; ?>

                
                <?php $__currentLoopData = $paginator->getUrlRange(1, $paginator->lastPage()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if($page == $paginator->currentPage()): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-blue-50 text-sm font-medium text-blue-600">
                    <?php echo e($page); ?>

                </span>
                <?php else: ?>
                <a href="<?php echo e($url); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <?php echo e($page); ?>

                </a>
                <?php endif; ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                
                <?php if($paginator->hasMorePages()): ?>
                <a href="<?php echo e($paginator->nextPageUrl()); ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only"><?php echo e(__('pagination.next')); ?></span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </a>
                <?php else: ?>
                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 cursor-not-allowed">
                    <span class="sr-only"><?php echo e(__('pagination.next')); ?></span>
                    <i class="fas fa-chevron-right h-5 w-5"></i>
                </span>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</div>
<?php endif; ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/pagination.blade.php ENDPATH**/ ?>