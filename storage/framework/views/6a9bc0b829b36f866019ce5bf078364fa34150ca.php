<?php $__env->startSection('title', __('notifications.quote_sent_title')); ?>

<?php $__env->startSection('content'); ?>
<div class="email-container">
    <div class="email-header">
        <h1><?php echo e(__('notifications.quote_sent_title')); ?></h1>
    </div>
    
    <div class="email-body">
        <p><?php echo e(__('notifications.quote_sent_greeting', ['name' => $client->name])); ?></p>
        
        <div class="quote-info">
            <h2><?php echo e(__('notifications.quote_details')); ?></h2>
            <ul>
                <li><strong><?php echo e(__('notifications.quote_number')); ?>:</strong> <?php echo e($quote->quote_number); ?></li>
                <li><strong><?php echo e(__('notifications.project_type')); ?>:</strong> <?php echo e($quote->project_type); ?></li>
                <li><strong><?php echo e(__('notifications.total_amount')); ?>:</strong> <?php echo e(number_format($quote->total_amount, 0, ',', '.')); ?> VND</li>
                <li><strong><?php echo e(__('notifications.valid_until')); ?>:</strong> <?php echo e($quote->valid_until->format('d/m/Y')); ?></li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="<?php echo e(route('app.quotes.show', $quote->id)); ?>" class="btn btn-primary">
                <?php echo e(__('notifications.view_quote')); ?>

            </a>
            <a href="<?php echo e(route('app.quotes.download', $quote->id)); ?>" class="btn btn-secondary">
                <?php echo e(__('notifications.download_pdf')); ?>

            </a>
        </div>
        
        <p class="email-footer">
            <?php echo e(__('notifications.email_footer')); ?>

        </p>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/emails/quote-sent.blade.php ENDPATH**/ ?>