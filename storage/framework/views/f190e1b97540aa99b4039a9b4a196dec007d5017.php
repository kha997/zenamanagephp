<?php $__env->startSection('title', '400 - Bad Request'); ?>

<?php
    $error_title = 'Bad Request';
    $error_message = 'The request was invalid or malformed. Please check your input and try again.';
?>

<?php echo $__env->make('errors.generic', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/400.blade.php ENDPATH**/ ?>