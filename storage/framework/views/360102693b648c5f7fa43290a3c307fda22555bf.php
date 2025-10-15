<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 100px;
            resize: vertical;
        }
        .form-submit {
            background-color: #27ae60;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .form-submit:hover {
            background-color: #229954;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="form-title">Edit Project</div>
            <?php if(isset($project)): ?>
                <form method="PUT" action="/api/v1/app/projects/<?php echo e($project['id']); ?>">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PUT'); ?>
                    
                    <div class="form-group">
                        <label class="form-label" for="name">Project Name</label>
                        <input type="text" id="name" name="name" class="form-input" value="<?php echo e($project['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="code">Project Code</label>
                        <input type="text" id="code" name="code" class="form-input" value="<?php echo e($project['code'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-textarea"><?php echo e($project['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-input">
                            <option value="planning" <?php echo e(($project['status'] ?? '') === 'planning' ? 'selected' : ''); ?>>Planning</option>
                            <option value="active" <?php echo e(($project['status'] ?? '') === 'active' ? 'selected' : ''); ?>>Active</option>
                            <option value="on_hold" <?php echo e(($project['status'] ?? '') === 'on_hold' ? 'selected' : ''); ?>>On Hold</option>
                            <option value="completed" <?php echo e(($project['status'] ?? '') === 'completed' ? 'selected' : ''); ?>>Completed</option>
                            <option value="cancelled" <?php echo e(($project['status'] ?? '') === 'cancelled' ? 'selected' : ''); ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="priority">Priority</label>
                        <select id="priority" name="priority" class="form-input">
                            <option value="low" <?php echo e(($project['priority'] ?? '') === 'low' ? 'selected' : ''); ?>>Low</option>
                            <option value="normal" <?php echo e(($project['priority'] ?? '') === 'normal' ? 'selected' : ''); ?>>Normal</option>
                            <option value="high" <?php echo e(($project['priority'] ?? '') === 'high' ? 'selected' : ''); ?>>High</option>
                            <option value="urgent" <?php echo e(($project['priority'] ?? '') === 'urgent' ? 'selected' : ''); ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-input" value="<?php echo e($project['start_date'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-input" value="<?php echo e($project['end_date'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="form-submit">Update Project</button>
                </form>
            <?php else: ?>
                <p>Project not found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/edit.blade.php ENDPATH**/ ?>