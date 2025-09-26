<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Dashboard'); ?> - ZenaManage</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/tailwind.css')); ?>">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo e(asset('css/design-system.css')); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Main Navigation -->
        <?php echo $__env->make('components.navigation', ['currentRoute' => $currentRoute ?? 'dashboard'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <!-- Breadcrumb Navigation -->
        <?php if (! empty(trim($__env->yieldContent('breadcrumb')))): ?>
            <?php echo $__env->make('components.breadcrumb', ['items' => $breadcrumb ?? []], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></h1>
                        <p class="text-gray-600 mt-1"><?php echo $__env->yieldContent('page-description', 'Overview and management'); ?></p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <?php if (! empty(trim($__env->yieldContent('header-actions')))): ?>
                            <?php echo $__env->yieldContent('header-actions'); ?>
                        <?php else: ?>
                            <button class="zena-btn zena-btn-primary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                                <span>Refresh</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php echo $__env->yieldContent('content'); ?>
        </main>
    </div>

    <script>
        // Global dashboard functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh functionality
            const refreshBtn = document.querySelector('button[class*="zena-btn-primary"]');
            if (refreshBtn && refreshBtn.textContent.includes('Refresh')) {
                refreshBtn.addEventListener('click', function() {
                    location.reload();
                });
            }

            // Mobile menu toggle functionality
            const mobileToggle = document.querySelector('.zena-nav-mobile-toggle');
            const mobileMenu = document.querySelector('.zena-nav-mobile');
            
            if (mobileToggle && mobileMenu) {
                mobileToggle.addEventListener('click', function() {
                    const isOpen = mobileMenu.style.display === 'block';
                    mobileMenu.style.display = isOpen ? 'none' : 'block';
                });
            }
        });
    </script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/dashboard.blade.php ENDPATH**/ ?>