@extends('emails.layout')

@section('title', 'You\'re Invited!')
@section('subtitle', 'Join ' . $organizationName)

<!-- Email Tracking Pixel -->
<img src="{{ config('app.url') }}/email-tracking/open/{{ $invitation->email_tracking_id ?? 'demo-tracking-id' }}" width="1" height="1" style="display:none;" alt="">

@section('content')
<!-- Welcome Message -->
<div class="content-section">
    <h2 class="content-title">Welcome to {{ $organizationName }}!</h2>
    <p class="content-text">
        Hello {{ $invitation->first_name ?: 'there' }},
    </p>
    <p class="content-text">
        {{ $inviterName }} has invited you to join <strong>{{ $organizationName }}</strong> 
        as a <strong>{{ $roleDisplayName }}</strong>.
    </p>
    @if($projectName)
    <p class="content-text">
        You'll be working on the project: <strong>{{ $projectName }}</strong>
    </p>
    @endif
</div>

<!-- Invitation Details -->
<div class="highlight-box">
    <div class="highlight-title">📋 Invitation Details</div>
    <div class="highlight-text">
        <strong>Role:</strong> {{ $roleDisplayName }}<br>
        @if($projectName)
        <strong>Project:</strong> {{ $projectName }}<br>
        @endif
        <strong>Organization:</strong> {{ $organizationName }}<br>
        <strong>Invited by:</strong> {{ $inviterName }}<br>
        <strong>Expires:</strong> {{ $expiresAt }}
    </div>
</div>

<!-- Custom Message -->
@if($invitation->message)
<div class="content-section">
    <h3 class="content-title">Personal Message</h3>
    <div style="background-color: #f8fafc; padding: 16px; border-radius: 6px; border-left: 4px solid #667eea;">
        <p style="color: #4b5563; font-style: italic; margin: 0;">
            "{{ $invitation->message }}"
        </p>
    </div>
</div>
@endif

<!-- Call to Action -->
<div class="content-section" style="text-align: center;">
    <a href="{{ config('app.url') }}/email-tracking/click/{{ $invitation->email_tracking_id ?? 'demo-tracking-id' }}/{{ urlencode($acceptUrl) }}" class="btn" style="font-size: 16px; padding: 16px 32px;">
        🚀 Accept Invitation
    </a>
</div>

<!-- Instructions -->
<div class="content-section">
    <h3 class="content-title">What happens next?</h3>
    <div style="color: #4b5563;">
        <p style="margin-bottom: 12px;">
            <strong>1.</strong> Click the "Accept Invitation" button above
        </p>
        <p style="margin-bottom: 12px;">
            <strong>2.</strong> Create your account with a secure password
        </p>
        <p style="margin-bottom: 12px;">
            <strong>3.</strong> Complete your profile information
        </p>
        <p style="margin-bottom: 12px;">
            <strong>4.</strong> Start collaborating with your team!
        </p>
    </div>
</div>

<!-- Security Notice -->
<div class="content-section">
    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 16px;">
        <h4 style="color: #92400e; margin-bottom: 8px;">🔒 Security Notice</h4>
        <p style="color: #92400e; font-size: 14px; margin: 0;">
            This invitation link is unique to you and expires in {{ $daysUntilExpiry }} days. 
            Please do not share this link with others. If you didn't expect this invitation, 
            you can safely ignore this email.
        </p>
    </div>
</div>

<!-- Alternative Access -->
<div class="content-section">
    <p class="content-text">
        If the button doesn't work, you can copy and paste this link into your browser:
    </p>
    <div style="background-color: #f3f4f6; padding: 12px; border-radius: 4px; word-break: break-all; font-family: monospace; font-size: 12px; color: #374151;">
        {{ $acceptUrl }}
    </div>
</div>

<!-- Contact Information -->
<div class="content-section">
    <p class="content-text">
        If you have any questions about this invitation, please contact 
        <strong>{{ $inviterName }}</strong> or our support team.
    </p>
</div>
@endsection
