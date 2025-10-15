


<?php
    $tenantNavItems = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => '/app/dashboard',
            'badge' => null
        ],
        [
            'key' => 'projects',
            'label' => 'Projects',
            'icon' => 'fas fa-project-diagram',
            'url' => '/app/projects',
            'badge' => '8'
        ],
        [
            'key' => 'tasks',
            'label' => 'Tasks',
            'icon' => 'fas fa-tasks',
            'url' => '/app/tasks',
            'badge' => '15'
        ],
        [
            'key' => 'calendar',
            'label' => 'Calendar',
            'icon' => 'fas fa-calendar-alt',
            'url' => '/app/calendar',
            'badge' => null
        ],
        [
            'key' => 'documents',
            'label' => 'Documents',
            'icon' => 'fas fa-file-alt',
            'url' => '/app/documents',
            'badge' => '12'
        ],
        [
            'key' => 'team',
            'label' => 'Team',
            'icon' => 'fas fa-users',
            'url' => '/app/team',
            'badge' => '6'
        ],
        [
            'key' => 'templates',
            'label' => 'Templates',
            'icon' => 'fas fa-layer-group',
            'url' => '/app/templates',
            'badge' => '4'
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => '/app/settings',
            'badge' => null
        ]
    ];
?>

<?php $__currentLoopData = $tenantNavItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <a href="<?php echo e($item['url']); ?>" 
       @click="setActiveNavItem('<?php echo e($item['key']); ?>')"
       class="flex items-center space-x-2 px-4 py-3 rounded-lg text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
       :class="currentNavItem === '<?php echo e($item['key']); ?>' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'">
        <i class="<?php echo e($item['icon']); ?>"></i>
        <span><?php echo e($item['label']); ?></span>
        <?php if($item['badge']): ?>
            <span class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center ml-1">
                <?php echo e($item['badge']); ?>

            </span>
        <?php endif; ?>
    </a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/tenant-nav.blade.php ENDPATH**/ ?>