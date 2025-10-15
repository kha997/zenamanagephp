


<nav class="hidden lg:flex items-center space-x-1">
    <a href="<?php echo e(route('dashboard')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('dashboard') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-tachometer-alt mr-2"></i><?php echo e(__('app.nav.dashboard')); ?>

    </a>
    <a href="<?php echo e(route('app.projects')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.projects*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-project-diagram mr-2"></i><?php echo e(__('app.nav.projects')); ?>

    </a>
    <a href="<?php echo e(route('app.tasks')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.tasks*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-tasks mr-2"></i><?php echo e(__('app.nav.tasks')); ?>

    </a>
    <a href="<?php echo e(route('app.calendar')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.calendar*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-calendar-alt mr-2"></i><?php echo e(__('app.nav.calendar')); ?>

    </a>
    <a href="<?php echo e(route('app.team')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.team*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-users mr-2"></i><?php echo e(__('app.nav.team')); ?>

    </a>
    <a href="<?php echo e(route('app.documents')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.documents*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-file-alt mr-2"></i><?php echo e(__('app.nav.documents')); ?>

    </a>
    <a href="<?php echo e(route('app.templates')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.templates*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-layer-group mr-2"></i><?php echo e(__('app.nav.templates')); ?>

    </a>
    <a href="<?php echo e(route('app.settings')); ?>" 
       class="nav-link <?php echo e(request()->routeIs('app.settings*') ? 'nav-link-active' : ''); ?>">
        <i class="fas fa-cog mr-2"></i><?php echo e(__('app.nav.settings')); ?>

    </a>
</nav>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation.blade.php ENDPATH**/ ?>