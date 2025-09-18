<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/design-system.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Main Navigation -->
        @include('components.navigation', ['currentRoute' => $currentRoute ?? 'dashboard'])

        <!-- Breadcrumb Navigation -->
        @hasSection('breadcrumb')
            @include('components.breadcrumb', ['items' => $breadcrumb ?? []])
        @endif

        <!-- Page Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">@yield('page-title', 'Dashboard')</h1>
                        <p class="text-gray-600 mt-1">@yield('page-description', 'Overview and management')</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        @hasSection('header-actions')
                            @yield('header-actions')
                        @else
                            <button class="zena-btn zena-btn-primary" onclick="location.reload()">
                                <i class="fas fa-sync-alt"></i>
                                <span>Refresh</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @yield('content')
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
</body>
</html>
