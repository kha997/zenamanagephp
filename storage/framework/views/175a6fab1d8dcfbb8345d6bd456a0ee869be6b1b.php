<?php $__env->startSection('title', 'You\'re Invited!'); ?>
<?php $__env->startSection('subtitle', 'Join ' . $organizationName); ?>

<!-- Email Tracking Pixel -->
<img src="<?php echo e(config('app.url')); ?>/email-tracking/open/<?php echo e($invitation->email_tracking_id ?? 'demo-tracking-id'); ?>" width="1" height="1" style="display:none;" alt="">

<?php $__env->startSection('content'); ?>
<!-- Welcome Message -->
<div class="content-section">
    <h2 class="content-title">Welcome to <?php echo e($organizationName); ?>!</h2>
    <p class="content-text">
        Hello <?php echo e($invitation->first_name ?: 'there'); ?>,
    </p>
    <p class="content-text">
        <?php echo e($inviterName); ?> has invited you to join <strong><?php echo e($organizationName); ?></strong> 
        as a <strong><?php echo e($roleDisplayName); ?></strong>.
    </p>
    <?php if($projectName): ?>
    <p class="content-text">
        You'll be working on the project: <strong><?php echo e($projectName); ?></strong>
    </p>
    <?php endif; ?>
</div>

<!-- Invitation Details -->
<div class="highlight-box">
    <div class="highlight-title">📋 Invitation Details</div>
    <div class="highlight-text">
        <strong>Role:</strong> <?php echo e($roleDisplayName); ?><br>
        <?php if($projectName): ?>
        <strong>Project:</strong> <?php echo e($projectName); ?><br>
        <?php endif; ?>
        <strong>Organization:</strong> <?php echo e($organizationName); ?><br>
        <strong>Invited by:</strong> <?php echo e($inviterName); ?><br>
        <strong>Expires:</strong> <?php echo e($expiresAt); ?>

    </div>
</div>

<!-- Custom Message -->
<?php if($invitation->message): ?>
<div class="content-section">
    <h3 class="content-title">Personal Message</h3>
    <div style="background-color: #f8fafc; padding: 16px; border-radius: 6px; border-left: 4px solid #667eea;">
        <p style="color: #4b5563; font-style: italic; margin: 0;">
            "<?php echo e($invitation->message); ?>"
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Call to Action -->
<div class="content-section" style="text-align: center;">
    <a href="<?php echo e(config('app.url')); ?>/email-tracking/click/<?php echo e($invitation->email_tracking_id ?? 'demo-tracking-id'); ?>/<?php echo e(urlencode($acceptUrl)); ?>" class="btn" style="font-size: 16px; padding: 16px 32px;">
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
            This invitation link is unique to you and expires in <?php echo e($daysUntilExpiry); ?> days. 
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
        <?php echo e($acceptUrl); ?>

    </div>
</div>

<!-- Contact Information -->
<div class="content-section">
    <p class="content-text">
        If you have any questions about this invitation, please contact 
        <strong><?php echo e($inviterName); ?></strong> or our support team.
    </p>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/emails/invitation.blade.php ENDPATH**/ ?>