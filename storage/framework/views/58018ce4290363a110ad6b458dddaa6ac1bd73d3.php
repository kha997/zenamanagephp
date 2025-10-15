<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Email - ZenaManage</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9fafb;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background-color: #3b82f6;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        .content {
            padding: 2rem;
        }
        .content h2 {
            color: #1f2937;
            margin-top: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .content p {
            margin-bottom: 1.5rem;
            color: #6b7280;
        }
        .button {
            display: inline-block;
            background-color: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            margin: 1rem 0;
        }
        .button:hover {
            background-color: #2563eb;
        }
        .footer {
            background-color: #f9fafb;
            padding: 1.5rem 2rem;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            margin: 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 1.5rem 0;
        }
        .info-box {
            background-color: #f3f4f6;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        .info-box p {
            margin: 0;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ZenaManage</h1>
        </div>
        
        <div class="content">
            <h2>Verify Your Email Address</h2>
            
            <p>Hello <?php echo e($user->name); ?>,</p>
            
            <p>Thank you for registering with ZenaManage! To complete your account setup, please verify your email address by clicking the button below:</p>
            
            <div style="text-align: center;">
                <a href="<?php echo e($verificationUrl); ?>" class="button">Verify Email Address</a>
            </div>
            
            <p>If the button doesn't work, you can copy and paste the following link into your browser:</p>
            
            <div class="info-box">
                <p><?php echo e($verificationUrl); ?></p>
            </div>
            
            <div class="divider"></div>
            
            <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
            
            <p>If you didn't create an account with ZenaManage, please ignore this email.</p>
            
            <p>Welcome to ZenaManage!</p>
            
            <p>Best regards,<br>The ZenaManage Team</p>
        </div>
        
        <div class="footer">
            <p>© <?php echo e(date('Y')); ?> ZenaManage. All rights reserved.</p>
            <p>This email was sent to <?php echo e($user->email); ?>. If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/emails/verification.blade.php ENDPATH**/ ?>