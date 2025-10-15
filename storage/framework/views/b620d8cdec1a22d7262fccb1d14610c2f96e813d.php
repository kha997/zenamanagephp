<?php $__env->startSection('title', '403 - Forbidden'); ?>

<?php
    $error_title = 'Access Denied';
    $error_message = 'You do not have permission to access this resource.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/403.blade.php ENDPATH**/ ?>