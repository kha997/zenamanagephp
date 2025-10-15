<?php $__env->startSection('title', '422 - Validation Error'); ?>

<?php
    $error_title = 'Validation Failed';
    $error_message = 'The data you submitted is invalid. Please check your input and try again.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/422.blade.php ENDPATH**/ ?>