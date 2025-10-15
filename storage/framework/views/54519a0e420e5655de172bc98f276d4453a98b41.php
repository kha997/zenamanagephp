<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Documents - ZenaManage</title>
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
        .documents-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .document-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .document-item:last-child {
            border-bottom: none;
        }
        .document-info {
            flex: 1;
        }
        .document-name {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .document-meta {
            font-size: 14px;
            color: #7f8c8d;
        }
        .document-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Project Documents</h1>
            <p>Manage documents for project: <?php echo e($project['name'] ?? 'Unknown Project'); ?></p>
        </div>
        
        <div class="documents-list">
            <?php if(isset($documents) && count($documents) > 0): ?>
                <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $document): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-name"><?php echo e($document['name'] ?? 'Untitled Document'); ?></div>
                            <div class="document-meta">
                                Type: <?php echo e($document['type'] ?? 'Unknown'); ?> | 
                                Size: <?php echo e($document['size'] ?? 0); ?> bytes
                            </div>
                        </div>
                        <div class="document-actions">
                            <a href="#" class="btn btn-primary">View</a>
                            <a href="#" class="btn btn-danger">Delete</a>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <?php else: ?>
                <div class="document-item">
                    <div class="document-info">
                        <div class="document-name">No documents found</div>
                        <div class="document-meta">Upload documents to get started.</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/documents.blade.php ENDPATH**/ ?>