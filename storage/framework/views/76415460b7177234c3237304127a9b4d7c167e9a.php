<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Limit Exceeded - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .icon {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .message {
            color: #6b7280;
            font-size: 1.125rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .countdown {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .countdown-text {
            color: #374151;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .countdown-timer {
            color: #dc2626;
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
        }
        
        .rate-limit-info {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .rate-limit-info h3 {
            color: #1f2937;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .rate-limit-info p {
            color: #6b7280;
            font-size: 0.875rem;
            margin: 0.25rem 0;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 0.75rem;
        }
        
        @media (max-width: 640px) {
            .container {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⏱️</div>
        <h1>Rate Limit Exceeded</h1>
        <div class="message">
            You've made too many requests too quickly. Please wait a moment before trying again.
        </div>
        
        <div class="countdown">
            <div class="countdown-text">Please wait:</div>
            <div class="countdown-timer" id="countdown"><?php echo e($retry_after); ?></div>
        </div>
        
        <div class="rate-limit-info">
            <h3>Rate Limit Details</h3>
            <p><strong>Strategy:</strong> <?php echo e(ucfirst(str_replace('_', ' ', $rate_limit['strategy']))); ?></p>
            <p><strong>Current Requests:</strong> <?php echo e($rate_limit['current_requests']); ?></p>
            <p><strong>Maximum Allowed:</strong> <?php echo e($rate_limit['max_requests']); ?></p>
            <p><strong>Window Size:</strong> <?php echo e($rate_limit['window_size']); ?> seconds</p>
            <?php if(isset($rate_limit['burst_limit'])): ?>
            <p><strong>Burst Limit:</strong> <?php echo e($rate_limit['burst_limit']); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="actions">
            <button class="btn btn-primary" onclick="window.location.reload()">
                Try Again
            </button>
            <a href="<?php echo e(url('/')); ?>" class="btn btn-secondary">
                Go Home
            </a>
        </div>
        
        <div class="footer">
            <p>If you continue to experience issues, please contact support.</p>
            <p>Request ID: <?php echo e(request()->header('X-Request-ID', 'N/A')); ?></p>
        </div>
    </div>
    
    <script>
        // Countdown timer
        let timeLeft = <?php echo e($retry_after); ?>;
        const countdownElement = document.getElementById('countdown');
        
        function updateCountdown() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            if (minutes > 0) {
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            } else {
                countdownElement.textContent = `${seconds}s`;
            }
            
            if (timeLeft <= 0) {
                countdownElement.textContent = 'Ready!';
                document.querySelector('.btn-primary').textContent = 'Try Again Now';
                return;
            }
            
            timeLeft--;
            setTimeout(updateCountdown, 1000);
        }
        
        updateCountdown();
        
        // Auto-refresh when countdown reaches zero
        setTimeout(() => {
            if (timeLeft <= 0) {
                window.location.reload();
            }
        }, (<?php echo e($retry_after); ?> + 1) * 1000);
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/errors/rate-limit.blade.php ENDPATH**/ ?>