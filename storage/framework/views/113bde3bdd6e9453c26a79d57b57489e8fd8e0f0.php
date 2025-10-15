<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .project-detail {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .project-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .project-code {
            font-size: 16px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .project-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-completed { background-color: #cce5ff; color: #004085; }
        .status-archived { background-color: #f8d7da; color: #721c24; }
        .project-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }
        .info-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="project-detail">
            <?php if(isset($project)): ?>
                <div class="project-title"><?php echo e($project['name'] ?? 'Untitled Project'); ?></div>
                <div class="project-code"><?php echo e($project['code'] ?? 'N/A'); ?></div>
                <div class="project-status status-<?php echo e($project['status'] ?? 'active'); ?>">
                    <?php echo e(ucfirst($project['status'] ?? 'active')); ?>

                </div>
                
                <div class="project-info">
                    <div class="info-item">
                        <div class="info-label">Progress</div>
                        <div class="info-value"><?php echo e($project['progress_pct'] ?? 0); ?>%</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Budget</div>
                        <div class="info-value">$<?php echo e(number_format($project['budget_actual'] ?? 0)); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Start Date</div>
                        <div class="info-value"><?php echo e($project['start_date'] ?? 'Not set'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">End Date</div>
                        <div class="info-value"><?php echo e($project['end_date'] ?? 'Not set'); ?></div>
                    </div>
                </div>
                
                <?php if(isset($project['description'])): ?>
                    <div class="project-description">
                        <h3>Description</h3>
                        <p><?php echo e($project['description']); ?></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="project-title">Project not found</div>
                <p>The requested project could not be found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/show.blade.php ENDPATH**/ ?>