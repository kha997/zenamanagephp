<?php $__env->startSection('title', '404 - Not Found'); ?>

<?php
    $error_title = 'Page Not Found';
    $error_message = 'The page you are looking for does not exist or has been moved.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/404.blade.php ENDPATH**/ ?>