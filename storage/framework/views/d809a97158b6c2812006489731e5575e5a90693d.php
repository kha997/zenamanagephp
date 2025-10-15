<!DOCTYPE html>
<html>
<head>
    <title>Test Simple View</title>
</head>
<body>
    <h1>Test Simple View</h1>
    <p>This is a simple test view.</p>
    <p>Clients count: <?php echo e($clients->count()); ?></p>
    <p>Stats: <?php echo e(json_encode($stats)); ?></p>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/test-simple.blade.php ENDPATH**/ ?>