<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project History - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .history-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .history-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-info {
            flex: 1;
        }
        .history-action {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .history-meta {
            font-size: 14px;
            color: #7f8c8d;
        }
        .history-timestamp {
            font-size: 12px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Project History</h1>
            <p>Activity history for project: <?php echo e($project['name'] ?? 'Unknown Project'); ?></p>
        </div>
        
        <div class="history-list">
            <?php if(isset($history) && count($history) > 0): ?>
                <?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="history-item">
                        <div class="history-info">
                            <div class="history-action"><?php echo e(ucfirst($entry['action'] ?? 'Unknown Action')); ?></div>
                            <div class="history-meta">
                                User ID: <?php echo e($entry['user_id'] ?? 'Unknown'); ?>

                            </div>
                        </div>
                        <div class="history-timestamp">
                            <?php echo e($entry['timestamp'] ?? 'Unknown time'); ?>

                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
                <div class="history-item">
                    <div class="history-info">
                        <div class="history-action">No history found</div>
                        <div class="history-meta">Project activity will appear here.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/history.blade.php ENDPATH**/ ?>