


<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag; ?>
<?php foreach($attributes->onlyProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light',
    'breadcrumbs' => []
]) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $attributes = $attributes->exceptProps([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light',
    'breadcrumbs' => []
]); ?>
<?php foreach (array_filter(([
    'variant' => 'app', // 'app' or 'admin'
    'user' => null,
    'tenant' => null,
    'navigation' => [],
    'notifications' => [],
    'unreadCount' => 0,
    'alertCount' => 0,
    'theme' => 'light',
    'breadcrumbs' => []
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
} ?>
<?php $__defined_vars = get_defined_vars(); ?>
<?php foreach ($attributes as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
} ?>
<?php unset($__defined_vars); ?>

<?php
    $user = $user ?? Auth::user();
    $tenant = $tenant ?? ($user ? $user->tenant : null);
    $isAdmin = $variant === 'admin';
    
    // Prepare navigation data
    if (empty($navigation)) {
        if ($isAdmin) {
            $navigation = [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'admin.dashboard'],
                ['key' => 'users', 'label' => 'Users', 'icon' => 'fas fa-users', 'route' => 'admin.users.index'],
                ['key' => 'tenants', 'label' => 'Tenants', 'icon' => 'fas fa-building', 'route' => 'admin.tenants.index'],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'admin.projects.index'],
                ['key' => 'security', 'label' => 'Security', 'icon' => 'fas fa-shield-alt', 'route' => 'admin.security.index'],
                ['key' => 'alerts', 'label' => 'Alerts', 'icon' => 'fas fa-exclamation-triangle', 'route' => 'admin.alerts.index', 'badge' => $alertCount],
                ['key' => 'activities', 'label' => 'Activities', 'icon' => 'fas fa-history', 'route' => 'admin.activities.index'],
                ['key' => 'analytics', 'label' => 'Analytics', 'icon' => 'fas fa-chart-bar', 'route' => 'admin.analytics.index'],
                ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'fas fa-tools', 'route' => 'admin.maintenance.index'],
                ['key' => 'settings', 'label' => 'Settings', 'icon' => 'fas fa-cog', 'route' => 'admin.settings.index']
            ];
        } else {
            $navigation = [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'route' => 'app.dashboard'],
                ['key' => 'projects', 'label' => 'Projects', 'icon' => 'fas fa-project-diagram', 'route' => 'app.projects.index'],
                ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'fas fa-tasks', 'route' => 'app.tasks.index'],
                ['key' => 'team', 'label' => 'Team', 'icon' => 'fas fa-users', 'route' => 'app.team.index'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'fas fa-chart-bar', 'route' => 'app.reports.index']
            ];
        }
    }
    
    // Prepare user data
    $userData = $user ? [
        'id' => $user->id,
        'name' => $user->name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
        'email' => $user->email,
        'avatar' => $user->avatar ?? null,
        'role' => $user->role,
        'tenant' => $tenant ? [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug
        ] : null
    ] : null;
    
    // Prepare notification data
    $notificationData = collect($notifications)->map(function($notification) {
        return [
            'id' => $notification['id'] ?? uniqid(),
            'title' => $notification['title'] ?? 'Notification',
            'message' => $notification['message'] ?? '',
            'type' => $notification['type'] ?? 'info',
            'read' => $notification['read'] ?? false,
            'created_at' => $notification['created_at'] ?? now()->toISOString()
        ];
    })->toArray();
?>

<div id="header-shell-root" 
     data-variant="<?php echo e($variant); ?>"
     data-theme="<?php echo e($theme); ?>"
     data-user="<?php echo e(json_encode($userData)); ?>"
     data-navigation="<?php echo e(json_encode($navigation)); ?>"
     data-notifications="<?php echo e(json_encode($notificationData)); ?>"
     data-unread-count="<?php echo e($unreadCount); ?>"
     data-alert-count="<?php echo e($alertCount); ?>"
     data-breadcrumbs="<?php echo e(json_encode($breadcrumbs)); ?>"
     class="header-shell-container">
</div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for React to be available
    if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
        console.error('React or ReactDOM not loaded. HeaderShell requires React.');
        return;
    }
    
    const container = document.getElementById('header-shell-root');
    if (!container) {
        console.error('HeaderShell container not found');
        return;
    }
    
    // Parse data from container attributes
    const variant = container.dataset.variant;
    const theme = container.dataset.theme;
    const user = JSON.parse(container.dataset.user || 'null');
    const navigation = JSON.parse(container.dataset.navigation || '[]');
    const notifications = JSON.parse(container.dataset.notifications || '[]');
    const unreadCount = parseInt(container.dataset.unreadCount || '0');
    const alertCount = parseInt(container.dataset.alertCount || '0');
    const breadcrumbs = JSON.parse(container.dataset.breadcrumbs || '[]');
    
    // Import HeaderShell component dynamically
    import('/src/components/ui/header/HeaderShell.tsx').then(({ HeaderShell }) => {
        // Create logo component
        const Logo = React.createElement('div', {
            className: 'flex items-center space-x-2'
        }, [
            React.createElement('div', {
                key: 'logo-icon',
                className: `w-8 h-8 ${variant === 'admin' ? 'bg-gradient-to-br from-red-600 to-orange-600' : 'bg-blue-600'} rounded-lg flex items-center justify-center`
            }, [
                variant === 'admin' 
                    ? React.createElement('span', { className: 'text-white font-bold text-lg' }, 'Z')
                    : React.createElement('i', { className: 'fas fa-cube text-white text-sm' })
            ]),
            React.createElement('span', {
                key: 'logo-text',
                className: 'text-xl font-bold text-gray-900'
            }, 'ZenaManage')
        ]);
        
        // Create primary navigation
        const PrimaryNav = React.createElement('nav', {
            className: 'hidden lg:flex items-center space-x-8'
        }, navigation.map(item => {
            const isActive = window.location.pathname.includes(item.route.replace('.', '/'));
            return React.createElement('a', {
                key: item.key,
                href: `<?php echo e(url('/')); ?>/${item.route.replace('.', '/')}`,
                className: `text-gray-600 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium transition-colors ${isActive ? 'bg-gray-100 text-gray-900' : ''}`
            }, [
                React.createElement('i', {
                    key: 'icon',
                    className: `${item.icon} mr-2`
                }),
                item.label,
                item.badge && item.badge > 0 ? React.createElement('span', {
                    key: 'badge',
                    className: 'ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold'
                }, item.badge) : null
            ]);
        }));
        
        // Create secondary actions
        const SecondaryActions = React.createElement('div', {
            className: 'flex items-center space-x-3'
        }, [
            // Notifications (app only)
            !isAdmin ? React.createElement('div', {
                key: 'notifications',
                className: 'relative'
            }, [
                React.createElement('button', {
                    className: 'p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors',
                    onClick: () => {
                        // Handle notification toggle
                        console.log('Toggle notifications');
                    }
                }, [
                    React.createElement('i', { className: 'fas fa-bell' }),
                    unreadCount > 0 ? React.createElement('span', {
                        className: 'absolute -top-1 -right-1 bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold'
                    }, unreadCount) : null
                ])
            ]) : null,
            
            // Theme toggle (app only)
            !isAdmin ? React.createElement('button', {
                key: 'theme-toggle',
                className: 'p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors',
                onClick: () => {
                    // Handle theme toggle
                    console.log('Toggle theme');
                }
            }, [
                React.createElement('i', { className: `fas fa-${theme === 'light' ? 'sun' : 'moon'}` })
            ]) : null,
            
            // Quick actions (admin only)
            isAdmin ? React.createElement('div', {
                key: 'quick-actions',
                className: 'relative'
            }, [
                React.createElement('button', {
                    className: 'flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors',
                    onClick: () => {
                        // Handle quick actions
                        console.log('Quick actions');
                    }
                }, [
                    React.createElement('i', { className: 'fas fa-plus' }),
                    'Quick Actions',
                    React.createElement('i', { className: 'fas fa-chevron-down text-xs' })
                ])
            ]) : null
        ]);
        
        // Create user menu
        const UserMenu = user ? React.createElement('div', {
            className: 'relative'
        }, [
            React.createElement('button', {
                className: 'flex items-center space-x-2 p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors',
                onClick: () => {
                    // Handle user menu toggle
                    console.log('Toggle user menu');
                }
            }, [
                React.createElement('div', {
                    className: `w-8 h-8 ${isAdmin ? 'bg-red-600' : 'bg-blue-600'} rounded-full flex items-center justify-center`
                }, [
                    React.createElement('span', {
                        className: 'text-white text-sm font-medium'
                    }, user.first_name ? user.first_name.charAt(0).toUpperCase() : 'U')
                ]),
                React.createElement('span', {
                    className: 'hidden md:block text-sm font-medium text-gray-900'
                }, `${user.first_name || 'User'} ${user.last_name || ''}`),
                React.createElement('i', { className: 'fas fa-chevron-down text-xs text-gray-500' })
            ])
        ]) : null;
        
        // Create breadcrumbs
        const Breadcrumbs = breadcrumbs.length > 0 ? React.createElement('nav', {
            className: 'flex items-center space-x-2 text-sm text-gray-500'
        }, breadcrumbs.map((crumb, index) => [
            React.createElement('a', {
                key: `crumb-${index}`,
                href: crumb.url || '#',
                className: 'hover:text-gray-700'
            }, crumb.label),
            index < breadcrumbs.length - 1 ? React.createElement('i', {
                key: `separator-${index}`,
                className: 'fas fa-chevron-right text-xs mx-2'
            }) : null
        ])) : null;
        
        // Render HeaderShell component
        ReactDOM.render(
            React.createElement(HeaderShell, {
                theme: theme,
                size: 'md',
                sticky: true,
                condensedOnScroll: true,
                withBorder: true,
                logo: Logo,
                primaryNav: PrimaryNav,
                secondaryActions: SecondaryActions,
                userMenu: UserMenu,
                notifications: null, // Will be handled by secondary actions
                breadcrumbs: Breadcrumbs,
                className: `header-shell-${variant}`
            }),
            container
        );
    }).catch(error => {
        console.error('Failed to load HeaderShell component:', error);
        // Fallback to simple header
        container.innerHTML = `
            <div class="bg-white shadow-sm border-b border-gray-200 fixed top-0 left-0 right-0 z-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                                <i class="fas fa-cube text-white text-sm"></i>
                            </div>
                            <span class="text-xl font-bold text-gray-900">ZenaManage</span>
                        </div>
                        <div class="text-sm text-gray-600">
                            HeaderShell failed to load
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/header-wrapper.blade.php ENDPATH**/ ?>