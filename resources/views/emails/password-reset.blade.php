<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - ZenaManage</title>
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
            word-break: break-all;
        }
        .info-box p {
            margin: 0;
            color: #374151;
            font-size: 0.875rem;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        .warning-box p {
            margin: 0;
            color: #92400e;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>ZenaManage</h1>
        </div>
        
        <div class="content">
            <h2>Reset Your Password</h2>
            
            <p>Hello {{ $user->name }},</p>
            
            <p>We received a request to reset your password for your ZenaManage account. Click the button below to reset your password:</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetUrl }}" class="button">Reset Password</a>
            </div>
            
            <p>If the button doesn't work, you can copy and paste the following link into your browser:</p>
            
            <div class="info-box">
                <p>{{ $resetUrl }}</p>
            </div>
            
            <div class="divider"></div>
            
            <div class="warning-box">
                <p><strong>Security Notice:</strong> This password reset link will expire in {{ $expiryHours }} hour(s) for security reasons. If you didn't request a password reset, please ignore this email and your password will remain unchanged.</p>
            </div>
            
            <p>If you didn't request a password reset, you can safely ignore this email. Your account remains secure.</p>
            
            <p>Best regards,<br>The ZenaManage Team</p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} ZenaManage. All rights reserved.</p>
            <p>This email was sent to {{ $user->email }}. If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>

