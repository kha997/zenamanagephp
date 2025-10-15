<?php $__env->startSection('title', '500 - Internal Server Error'); ?>

<?php
    $error_title = 'Internal Server Error';
    $error_message = 'An unexpected error occurred on our end. We have been notified and are working to fix it.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/500.blade.php ENDPATH**/ ?>