<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Email Change</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #dc2626;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .button {
            display: inline-block;
            background: #dc2626;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #666;
        }
        .warning {
            background: #fef3cd;
            border: 1px solid #fecaca;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ZENA Manage</h1>
        <h2>Confirm Email Change</h2>
    </div>
    
    <div class="content">
        <p>Hello {{ $user->name }},</p>
        
        <p>You have requested to change your email address from <strong>{{ $user->email }}</strong> to <strong>{{ $newEmail }}</strong>.</p>
        
        <div class="info">
            <strong>Current Email:</strong> {{ $user->email }}<br>
            <strong>New Email:</strong> {{ $newEmail }}
        </div>
        
        <p>To confirm this change, please click the button below:</p>
        
        <div style="text-align: center;">
            <a href="{{ $verificationUrl }}" class="button">Confirm Email Change</a>
        </div>
        
        <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
        <p style="word-break: break-all; background: #e5e7eb; padding: 10px; border-radius: 4px;">
            {{ $verificationUrl }}
        </p>
        
        <div class="warning">
            <strong>Important:</strong> This confirmation link will expire in {{ $expiryHours }} hours for security reasons.
        </div>
        
        <p><strong>Security Notice:</strong> If you didn't request this email change, please contact our support team immediately and do not click the confirmation link.</p>
        
        <p>Best regards,<br>The ZENA Manage Team</p>
    </div>
    
    <div class="footer">
        <p>This email was sent from ZENA Manage. Please do not reply to this email.</p>
        <p>&copy; {{ date('Y') }} ZENA Manage. All rights reserved.</p>
    </div>
</body>
</html>
