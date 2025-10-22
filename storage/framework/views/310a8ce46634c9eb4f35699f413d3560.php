<?php $__env->startSection('title', 'Test Template'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mx-auto px-4 py-8">
    <h1>Test Template</h1>
    <p>This is a test template to verify script sections work.</p>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
    <script>
        console.log('=== TEST TEMPLATE SCRIPT ===');
        console.log('Script section is working!');
    </script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-script.blade.php ENDPATH**/ ?>