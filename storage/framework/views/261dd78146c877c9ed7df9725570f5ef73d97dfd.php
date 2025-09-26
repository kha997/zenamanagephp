<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e(config('app.name', 'ZenaManage')); ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    
    <!-- Tailwind CSS -->
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        [x-cloak] { display: none !important; }
        
        /* Custom CSS Variables */
        :root {
            --primary-50: #eff6ff;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --success-500: #10b981;
            --warning-500: #f59e0b;
            --danger-500: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-900: #111827;
        }
        
        /* Dashboard specific styles */
        .dashboard-card {
            @apply bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-gray-200 transition-all duration-200;
        }
        
        .metric-card {
            @apply min-h-[120px] p-6;
        }
        
        .metric-card.green {
            @apply bg-gradient-to-br from-emerald-500 to-teal-600 text-white;
        }
        
        .metric-card.blue {
            @apply bg-gradient-to-br from-blue-500 to-indigo-600 text-white;
        }
        
        .metric-card.orange {
            @apply bg-gradient-to-br from-orange-500 to-red-500 text-white;
        }
        
        .metric-card.purple {
            @apply bg-gradient-to-br from-purple-500 to-pink-600 text-white;
        }
        
        /* Chart containers */
        .chart-container {
            @apply relative h-64 w-full;
        }
        
        /* Loading states */
        .skeleton {
            @apply animate-pulse bg-gray-200 rounded;
        }
        
        /* Dark mode support */
        .dark .dashboard-card {
            @apply bg-gray-800 border-gray-700 text-white;
        }
    </style>
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen">
        <?php echo $__env->yieldContent('content'); ?>
    </div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/app.blade.php ENDPATH**/ ?>