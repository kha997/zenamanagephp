<?php $__env->startSection('title', '401 - Unauthorized'); ?>

<?php
    $error_title = 'Authentication Required';
    $error_message = 'You need to log in to access this resource.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/401.blade.php ENDPATH**/ ?>