


<?php
    $mobileTenantNavItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => '/app/dashboard'],
        ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'url' => '/app/projects'],
        ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'fas fa-tasks', 'url' => '/app/tasks'],
        ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'fas fa-calendar-alt', 'url' => '/app/calendar'],
        ['key' => 'documents', 'label' => 'Documents', 'icon' => 'fas fa-file-alt', 'url' => '/app/documents'],
        ['key' => 'team', 'label' => 'Team', 'icon' => 'fas fa-users', 'url' => '/app/team'],
        ['key' => 'templates', 'label' => 'Templates', 'icon' => 'fas fa-layer-group', 'url' => '/app/templates'],
        ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'url' => '/app/settings']
    ];
?>

<?php $__currentLoopData = $mobileTenantNavItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e($item['url']); ?>" 
       @click="setActiveNavItem('<?php echo e($item['key']); ?>'); mobileMenuOpen = false"
       class="flex items-center space-x-3 px-3 py-2 text-sm font-medium rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
       :class="currentNavItem === '<?php echo e($item['key']); ?>' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
        <i class="<?php echo e($item['icon']); ?> w-5"></i>
        <span><?php echo e($item['label']); ?></span>
    </a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/mobile-tenant-nav.blade.php ENDPATH**/ ?>