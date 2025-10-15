


<?php
    $adminNavItems = [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'url' => '/admin/dashboard',
            'badge' => null
        ],
        [
            'key' => 'users',
            'label' => 'Users',
            'icon' => 'fas fa-users',
            'url' => '/admin/users',
            'badge' => '12' // This would come from actual data
        ],
        [
            'key' => 'tenants',
            'label' => 'Tenants',
            'icon' => 'fas fa-building',
            'url' => '/admin/tenants',
            'badge' => '5'
        ],
        [
            'key' => 'projects',
            'label' => 'Projects',
            'icon' => 'fas fa-project-diagram',
            'url' => '/admin/projects',
            'badge' => '24'
        ],
        [
            'key' => 'security',
            'label' => 'Security',
            'icon' => 'fas fa-shield-alt',
            'url' => '/admin/security',
            'badge' => null
        ],
        [
            'key' => 'alerts',
            'label' => 'Alerts',
            'icon' => 'fas fa-exclamation-triangle',
            'url' => '/admin/alerts',
            'badge' => '3'
        ],
        [
            'key' => 'activities',
            'label' => 'Activities',
            'icon' => 'fas fa-history',
            'url' => '/admin/activities',
            'badge' => null
        ],
        [
            'key' => 'analytics',
            'label' => 'Analytics',
            'icon' => 'fas fa-chart-bar',
            'url' => '/admin/analytics',
            'badge' => null
        ],
        [
            'key' => 'settings',
            'label' => 'Settings',
            'icon' => 'fas fa-cog',
            'url' => '/admin/settings',
            'badge' => null
        ]
    ];
?>

<?php $__currentLoopData = $adminNavItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/navigation/admin-nav.blade.php ENDPATH**/ ?>