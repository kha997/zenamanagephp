<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $__env->yieldContent('title', 'ZenaManage'); ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Modern project management dashboard with real-time analytics">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaManage">
    <meta name="msapplication-TileColor" content="#2563eb">
    <meta name="msapplication-config" content="/browserconfig.xml">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180x180.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/icon-16x16.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="stylesheet" href="<?php echo e(asset('css/tailwind.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/design-system.css')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- Chart.js - Multiple CDN fallbacks with different versions -->
    <script>
        // Try multiple Chart.js versions and CDNs
        const chartJsSources = [
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js',
            'https://unpkg.com/chart.js@4.4.0/dist/chart.min.js',
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
            'https://unpkg.com/chart.js@3.9.1/dist/chart.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js'
        ];
        
        let currentSourceIndex = 0;
        
        function loadChartJS() {
            if (currentSourceIndex >= chartJsSources.length) {
                console.log('❌ All Chart.js sources failed, creating mock Chart object');
                createMockChart();
                return;
            }
            
            const script = document.createElement('script');
            script.src = chartJsSources[currentSourceIndex];
            
            script.onload = function() {
                console.log('✅ Chart.js loaded from:', chartJsSources[currentSourceIndex]);
                if (typeof Chart !== 'undefined') {
                    console.log('✅ Chart object available:', Chart.version);
                } else {
                    console.log('⚠️ Chart.js loaded but Chart object not available');
                    currentSourceIndex++;
                    loadChartJS();
                }
            };
            
            script.onerror = function() {
                console.log('❌ Failed to load:', chartJsSources[currentSourceIndex]);
                currentSourceIndex++;
                loadChartJS();
            };
            
            document.head.appendChild(script);
        }
        
        function createMockChart() {
            window.Chart = function(ctx, config) {
                console.log('📊 Using mock Chart object');
                // Create a simple fallback visualization
                if (ctx && ctx.canvas) {
                    const canvas = ctx.canvas;
                    const width = canvas.width;
                    const height = canvas.height;
                    
                    // Clear canvas
                    ctx.clearRect(0, 0, width, height);
                    
                    // Draw background
                    ctx.fillStyle = '#f3f4f6';
                    ctx.fillRect(0, 0, width, height);
                    
                    // Draw title
                    ctx.fillStyle = '#374151';
                    ctx.font = '14px Arial';
                    ctx.textAlign = 'center';
                    ctx.fillText(config?.options?.plugins?.title?.text || 'Chart', width/2, height/2 - 10);
                    
                    // Draw fallback message
                    ctx.fillStyle = '#6b7280';
                    ctx.font = '12px Arial';
                    ctx.fillText('Chart.js not available', width/2, height/2 + 10);
                }
            };
            window.Chart.version = '4.4.0-mock';
            console.log('✅ Mock Chart object created');
        }
        
        // Start loading Chart.js
        loadChartJS();
    </script>
    
    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('Service Worker registered successfully:', registration.scope);
                        
                        // Handle updates
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // New content is available, show update notification
                                    if (confirm('New version available! Reload to update?')) {
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch(error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
            
            // Handle offline/online status
            window.addEventListener('online', () => {
                console.log('Connection restored');
                // Trigger background sync
                navigator.serviceWorker.ready.then(registration => {
                    return registration.sync.register('dashboard-sync');
                });
            });
            
            window.addEventListener('offline', () => {
                console.log('Connection lost');
            });
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
           <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
           <style>
               /* Fixed Header Styles */
               .zena-main-nav {
                   position: fixed !important;
                   top: 0 !important;
                   left: 0 !important;
                   right: 0 !important;
                   z-index: 1000 !important;
                   background: white !important;
                   border-bottom: 1px solid #e5e7eb !important;
                   box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
               }
               
               /* Ensure ZenaManage is always visible */
               .zena-nav-brand-text {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: inline-block !important;
                   color: #2563eb !important;
                   font-weight: 700 !important;
               }
               
               .zena-nav-logo {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: flex !important;
               }
               
               .zena-nav-logo div {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: flex !important;
               }
               
               .zena-nav-logo span {
                   visibility: visible !important;
                   opacity: 1 !important;
                   display: inline-block !important;
                   color: #2563eb !important;
                   font-weight: 700 !important;
               }
               
               /* Ensure body has proper padding for fixed header */
               body {
                   padding-top: 0 !important;
               }
               
               /* Breadcrumb styles */
               .zena-breadcrumb {
                   background: #f9fafb;
                   border-bottom: 1px solid #e5e7eb;
                   padding: 1rem 0;
               }
               
               .zena-breadcrumb nav {
                   max-width: 1280px;
                   margin: 0 auto;
                   padding: 0 1rem;
               }
               
               .zena-breadcrumb a:hover {
                   color: #1d4ed8;
               }
               
               /* Adjust main content padding to account for breadcrumb */
               .app-content {
                   padding-top: 0;
               }
               
               /* Alpine.js x-cloak styles */
               [x-cloak] {
                   display: none !important;
               }
           </style>
</head>
<body class="bg-gray-50">
    <?php echo $__env->yieldContent('content'); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/layouts/app-base.blade.php ENDPATH**/ ?>