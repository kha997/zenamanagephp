<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    
    <title><?php echo e($status_code ?? 500); ?> - <?php echo e(config('app.name', 'ZenaManage')); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Styles -->
    <style>
        body {
            font-family: 'Figtree', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .error-code {
            font-size: 4rem;
            font-weight: 700;
            color: #667eea;
            margin: 0;
            line-height: 1;
        }
        
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
            margin: 1rem 0 0.5rem 0;
        }
        
        .error-message {
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .error-details {
            background: #f7fafc;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        
        .btn:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            margin-left: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo e($status_code ?? 500); ?></div>
        <h1 class="error-title"><?php echo e($error_title ?? 'Error'); ?></h1>
        <p class="error-message"><?php echo e($error_message ?? 'An unexpected error occurred.'); ?></p>
        
        <?php if(isset($error_code) && app()->environment('local', 'testing')): ?>
        <div class="error-details">
            <strong>Error Code:</strong> <?php echo e($error_code); ?><br>
            <?php if(isset($request_id)): ?>
            <strong>Request ID:</strong> <?php echo e($request_id); ?>

            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div>
            <a href="<?php echo e(url()->previous()); ?>" class="btn">Go Back</a>
            <a href="<?php echo e(route('app.dashboard')); ?>" class="btn btn-secondary">Dashboard</a>
        </div>
        
        <div class="footer">
            If this problem persists, please contact support with Request ID: <?php echo e($request_id ?? 'N/A'); ?>

        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/generic.blade.php ENDPATH**/ ?>