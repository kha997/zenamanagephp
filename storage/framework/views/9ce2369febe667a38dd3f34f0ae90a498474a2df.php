<?php $__env->startSection('title', __('notifications.task_completed_title')); ?>

<?php $__env->startSection('content'); ?>
<div class="email-container">
    <div class="email-header">
        <h1><?php echo e(__('notifications.task_completed_title')); ?></h1>
    </div>
    
    <div class="email-body">
        <p><?php echo e(__('notifications.task_completed_greeting', ['name' => $user->name])); ?></p>
        
        <div class="task-info">
            <h2><?php echo e(__('notifications.task_details')); ?></h2>
            <ul>
                <li><strong><?php echo e(__('notifications.task_title')); ?>:</strong> <?php echo e($task->title); ?></li>
                <li><strong><?php echo e(__('notifications.project')); ?>:</strong> <?php echo e($task->project->name); ?></li>
                <li><strong><?php echo e(__('notifications.completed_by')); ?>:</strong> <?php echo e($task->completedBy->name); ?></li>
                <li><strong><?php echo e(__('notifications.completed_at')); ?>:</strong> <?php echo e($task->completed_at->format('d/m/Y H:i')); ?></li>
            </ul>
        </div>
        
        <div class="email-actions">
            <a href="<?php echo e(route('app.tasks.show', $task->id)); ?>" class="btn btn-primary">
                <?php echo e(__('notifications.view_task')); ?>

            </a>
            <a href="<?php echo e(route('app.projects.show', $task->project->id)); ?>" class="btn btn-secondary">
                <?php echo e(__('notifications.view_project')); ?>

            </a>
        </div>
        
        <p class="email-footer">
            <?php echo e(__('notifications.email_footer')); ?>

        </p>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/emails/task-completed.blade.php ENDPATH**/ ?>