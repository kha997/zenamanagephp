<?php $__env->startSection('title', '429 - Too Many Requests'); ?>

<?php
    $error_title = 'Rate Limited';
    $error_message = 'You have made too many requests. Please wait a moment before trying again.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/429.blade.php ENDPATH**/ ?>