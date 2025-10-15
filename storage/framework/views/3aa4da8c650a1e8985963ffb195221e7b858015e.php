<?php $__env->startSection('title', 'Simple Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">Simple Dashboard Test</h1>
    
    <div x-data="{ show: true }">
        <p>Alpine.js working: <span x-text="show ? 'YES' : 'NO'"></span></p>
        <button @click="show = !show" class="bg-blue-500 text-white px-4 py-2 rounded">
            Toggle
        </button>
        
        <div x-show="show" class="mt-4 p-4 bg-green-100 rounded">
            <p>Alpine.js is working correctly!</p>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    console.log('Simple dashboard script loaded');
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/simple.blade.php ENDPATH**/ ?>