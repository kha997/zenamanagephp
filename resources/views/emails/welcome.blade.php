@extends('emails.layout')

@section('title', 'Welcome to ' . $organizationName . '!')
@section('subtitle', 'Your account has been created successfully')

@section('content')
<!-- Welcome Message -->
<div class="content-section">
    <h2 class="content-title">🎉 Welcome aboard!</h2>
    <p class="content-text">
        Hello {{ $user->first_name ?: $user->name }},
    </p>
    <p class="content-text">
        Congratulations! Your account has been successfully created and you're now a member of 
        <strong>{{ $organizationName }}</strong>.
    </p>
</div>

<!-- Account Details -->
<div class="highlight-box">
    <div class="highlight-title">👤 Your Account Details</div>
    <div class="highlight-text">
        <strong>Name:</strong> {{ $user->name }}<br>
        <strong>Email:</strong> {{ $user->email }}<br>
        <strong>Role:</strong> {{ $roleDisplayName }}<br>
        <strong>Organization:</strong> {{ $organizationName }}<br>
        <strong>Joined:</strong> {{ $user->joined_at ? $user->joined_at->format('F d, Y \a\t g:i A') : 'Just now' }}
    </div>
</div>

<!-- Quick Start Guide -->
<div class="content-section">
    <h3 class="content-title">🚀 Quick Start Guide</h3>
    <div style="color: #4b5563;">
        <p style="margin-bottom: 12px;">
            <strong>1.</strong> Complete your profile setup
        </p>
        <p style="margin-bottom: 12px;">
            <strong>2.</strong> Explore your dashboard and projects
        </p>
        <p style="margin-bottom: 12px;">
            <strong>3.</strong> Connect with your team members
        </p>
        <p style="margin-bottom: 12px;">
            <strong>4.</strong> Start collaborating on projects!
        </p>
    </div>
</div>

<!-- Call to Action -->
<div class="content-section" style="text-align: center;">
    <a href="{{ $dashboardUrl }}" class="btn" style="font-size: 16px; padding: 16px 32px;">
        🏠 Go to Dashboard
    </a>
</div>

<!-- Features Overview -->
<div class="content-section">
    <h3 class="content-title">✨ What you can do</h3>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
        <div style="text-align: center; padding: 16px; background-color: #f0f9ff; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 8px;">📊</div>
            <h4 style="color: #1f2937; margin-bottom: 4px;">Dashboard</h4>
            <p style="color: #6b7280; font-size: 14px;">Track your projects and tasks</p>
        </div>
        <div style="text-align: center; padding: 16px; background-color: #f0fdf4; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 8px;">👥</div>
            <h4 style="color: #1f2937; margin-bottom: 4px;">Team</h4>
            <p style="color: #6b7280; font-size: 14px;">Collaborate with your team</p>
        </div>
        <div style="text-align: center; padding: 16px; background-color: #fefce8; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 8px;">📋</div>
            <h4 style="color: #1f2937; margin-bottom: 4px;">Projects</h4>
            <p style="color: #6b7280; font-size: 14px;">Manage your projects efficiently</p>
        </div>
        <div style="text-align: center; padding: 16px; background-color: #fdf2f8; border-radius: 8px;">
            <div style="font-size: 24px; margin-bottom: 8px;">📈</div>
            <h4 style="color: #1f2937; margin-bottom: 4px;">Reports</h4>
            <p style="color: #6b7280; font-size: 14px;">Generate detailed reports</p>
        </div>
    </div>
</div>

<!-- Support Information -->
<div class="content-section">
    <h3 class="content-title">🆘 Need Help?</h3>
    <p class="content-text">
        Our support team is here to help you get started. Don't hesitate to reach out if you have any questions.
    </p>
    <div style="margin-top: 16px;">
        <a href="{{ config('app.url') }}/support" class="btn btn-secondary" style="margin-right: 12px;">
            📞 Contact Support
        </a>
        <a href="{{ config('app.url') }}/docs" class="btn btn-secondary">
            📚 View Documentation
        </a>
    </div>
</div>

<!-- Security Reminder -->
<div class="content-section">
    <div style="background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 6px; padding: 16px;">
        <h4 style="color: #92400e; margin-bottom: 8px;">🔒 Security Reminder</h4>
        <p style="color: #92400e; font-size: 14px; margin: 0;">
            Keep your account secure by using a strong password and enabling two-factor authentication 
            if available. Never share your login credentials with others.
        </p>
    </div>
</div>
@endsection
