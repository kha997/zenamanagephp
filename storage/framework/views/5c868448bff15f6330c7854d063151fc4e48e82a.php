<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', 'Admin - ' . config('app.name', 'ZenaManage')); ?></title>

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .admin-kpi-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        .admin-kpi-card-success {
            background: linear-gradient(135deg, #2ed573 0%, #7bed9f 100%);
        }
        .admin-kpi-card-warning {
            background: linear-gradient(135deg, #ffa502 0%, #ff6348 100%);
        }
        .admin-kpi-card-info {
            background: linear-gradient(135deg, #3742fa 0%, #2f3542 100%);
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50" x-data="adminLayout()">
    <!-- HeaderShell Wrapper for Admin -->
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.header-wrapper','data' => ['variant' => 'admin','user' => Auth::user(),'tenant' => Auth::user()?->tenant,'navigation' => app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'admin'),'notifications' => app(App\Services\HeaderService::class)->getNotifications(Auth::user()),'unreadCount' => app(App\Services\HeaderService::class)->getUnreadCount(Auth::user()),'alertCount' => app(App\Services\HeaderService::class)->getAlertCount(Auth::user()),'theme' => app(App\Services\HeaderService::class)->getUserTheme(Auth::user()),'breadcrumbs' => app(App\Services\HeaderService::class)->getBreadcrumbs(request()->route()->getName(), request()->route()->parameters())]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.header-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'admin','user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()),'tenant' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(Auth::user()?->tenant),'navigation' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getNavigation(Auth::user(), 'admin')),'notifications' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getNotifications(Auth::user())),'unread-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getUnreadCount(Auth::user())),'alert-count' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getAlertCount(Auth::user())),'theme' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getUserTheme(Auth::user())),'breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(app(App\Services\HeaderService::class)->getBreadcrumbs(request()->route()->getName(), request()->route()->parameters()))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

    <!-- Main Content with proper spacing -->
    <main class="pt-20">
        <!-- KPI Strip -->
        <?php echo $__env->yieldContent('kpi-strip'); ?>
        
        <!-- Alert Bar -->
        <?php echo $__env->yieldContent('alert-bar'); ?>
        
        <!-- Page Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <?php echo $__env->yieldContent('content'); ?>
        </div>
        
        <!-- Activity/History -->
        <?php echo $__env->yieldContent('activity'); ?>
    </main>

    <!-- Alpine.js Data -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('adminLayout', () => ({
                // Theme management
                theme: '<?php echo e(app(App\Services\HeaderService::class)->getUserTheme(Auth::user())); ?>',
                
                // Methods
                toggleTheme() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    // Update user preference via API
                    fetch('/api/user/preferences/theme', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ theme: this.theme })
                    });
                }
            }));
        });
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/admin-layout.blade.php ENDPATH**/ ?>