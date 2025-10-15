<?php $__env->startSection('title', __('notifications.client_created_title')); ?>

<?php $__env->startSection('content'); ?>
<div class="email-container">
    <div class="email-header">
        <h1><?php echo e(__('notifications.client_created_title')); ?></h1>
    </div>
    
    <div class="email-body">
        <p><?php echo e(__('notifications.client_created_greeting', ['name' => $user->name])); ?></p>
        
        <div class="client-info">
            <h2><?php echo e(__('notifications.client_details')); ?></h2>
            <ul>
                <li><strong><?php echo e(__('notifications.client_name')); ?>:</strong> <?php echo e($client->name); ?></li>
                <li><strong><?php echo e(__('notifications.client_email')); ?>:</strong> <?php echo e($client->email); ?></li>
                <li><strong><?php echo e(__('notifications.client_phone')); ?>:</strong> <?php echo e($client->phone); ?></li>
                <li><strong><?php echo e(__('notifications.client_type')); ?>:</strong> <?php echo e($client->type === 'potential' ? __('notifications.potential_client') : __('notifications.signed_client')); ?></li>
                <li><strong><?php echo e(__('notifications.created_by')); ?>:</strong> <?php echo e($client->createdBy->name); ?></li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="<?php echo e(route('app.clients.show', $client->id)); ?>" class="btn btn-primary">
                <?php echo e(__('notifications.view_client')); ?>

            </a>
            <a href="<?php echo e(route('app.clients.index')); ?>" class="btn btn-secondary">
                <?php echo e(__('notifications.view_all_clients')); ?>

            </a>
        </div>
        
        <p class="email-footer">
            <?php echo e(__('notifications.email_footer')); ?>

        </p>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/emails/client-created.blade.php ENDPATH**/ ?>