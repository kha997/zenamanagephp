<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation to Join {{ $tenantName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h1 style="color: #2563eb; margin-top: 0;">You've been invited!</h1>
        <p style="font-size: 16px; margin-bottom: 0;">
            You've been invited to join <strong>{{ $tenantName }}</strong> on Zenamanage.
        </p>
    </div>

    <div style="background-color: #ffffff; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 20px;">
        <p style="margin-top: 0;">Hello,</p>
        
        <p>
            <strong>{{ $inviterName }}</strong> has invited you to join <strong>{{ $tenantName }}</strong> as a <strong>{{ $roleLabel }}</strong>.
        </p>

        <p>
            Click the button below to view your invitation and accept or decline:
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $inviteUrl }}" 
               style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600;">
                View Invitation
            </a>
        </div>

        <p style="font-size: 14px; color: #6b7280; margin-bottom: 0;">
            This invitation will expire on <strong>{{ $expiresAt }}</strong>.
        </p>
    </div>

    <div style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 30px;">
        <p>If you're unable to click the button, copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #2563eb;">{{ $inviteUrl }}</p>
    </div>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 12px;">
        <p>This is an automated message from Zenamanage. Please do not reply to this email.</p>
    </div>
</body>
</html>

