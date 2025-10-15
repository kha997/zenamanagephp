<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Demo - ZenaManage</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
        <nav class="zena-main-nav" role="navigation" aria-label="Main navigation">
            <div class="zena-nav-container">
                
                <div class="zena-nav-brand">
                    <a href="/dashboard" class="zena-nav-brand-link">
                        <div class="zena-nav-logo">
                            <i class="fas fa-cube text-blue-600"></i>
                        </div>
                        <span class="zena-nav-brand-text">ZenaManage</span>
                    </a>
                </div>

                
                <div class="zena-nav-desktop">
                    <ul class="zena-nav-list">
                        <li class="zena-nav-item-wrapper">
                            <a href="/dashboard" 
                               class="zena-nav-item zena-nav-item-active"
                               aria-current="page">
                                <i class="fas fa-home zena-nav-icon"></i>
                                <span class="zena-nav-label">Dashboard</span>
                                <div class="zena-nav-indicator"></div>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/tasks" 
                               class="zena-nav-item">
                                <i class="fas fa-tasks zena-nav-icon"></i>
                                <span class="zena-nav-label">Tasks</span>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/projects" 
                               class="zena-nav-item">
                                <i class="fas fa-project-diagram zena-nav-icon"></i>
                                <span class="zena-nav-label">Projects</span>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/documents" 
                               class="zena-nav-item">
                                <i class="fas fa-file-alt zena-nav-icon"></i>
                                <span class="zena-nav-label">Documents</span>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/team" 
                               class="zena-nav-item">
                                <i class="fas fa-users zena-nav-icon"></i>
                                <span class="zena-nav-label">Team</span>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/templates" 
                               class="zena-nav-item">
                                <i class="fas fa-magic zena-nav-icon"></i>
                                <span class="zena-nav-label">Templates</span>
                            </a>
                        </li>

                        <li class="zena-nav-item-wrapper">
                            <a href="/admin" 
                               class="zena-nav-item">
                                <i class="fas fa-cog zena-nav-icon"></i>
                                <span class="zena-nav-label">Admin</span>
                            </a>
                        </li>
                    </ul>
                </div>

                
                <div class="zena-nav-user">
                    <div class="zena-nav-user-menu" x-data="{ open: false }">
                        <button @click="open = !open" 
                                class="zena-nav-user-button"
                                aria-expanded="false"
                                aria-haspopup="true">
                            <div class="zena-nav-user-avatar">AD</div>
                            <span class="zena-nav-user-name">Admin User</span>
                            <i class="fas fa-chevron-down zena-nav-user-chevron" :class="{ 'rotate-180': open }"></i>
                        </button>

                        
                        <div x-show="open" 
                             @click.away="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="zena-nav-user-dropdown">
                            <div class="zena-nav-user-info">
                                <div class="zena-nav-user-info-name">Admin User</div>
                                <div class="zena-nav-user-info-email">admin@zenamanage.com</div>
                            </div>
                            
                            <div class="zena-nav-user-divider"></div>
                            
                            <a href="/profile" class="zena-nav-user-item">
                                <i class="fas fa-user zena-nav-user-item-icon"></i>
                                <span>Profile</span>
                            </a>
                            
                            <a href="/settings" class="zena-nav-user-item">
                                <i class="fas fa-cog zena-nav-user-item-icon"></i>
                                <span>Settings</span>
                            </a>
                            
                            <a href="/help" class="zena-nav-user-item">
                                <i class="fas fa-question-circle zena-nav-user-item-icon"></i>
                                <span>Help & Support</span>
                            </a>
                            
                            <div class="zena-nav-user-divider"></div>
                            
                            <a href="/logout" class="zena-nav-user-item zena-nav-user-item-danger">
                                <i class="fas fa-sign-out-alt zena-nav-user-item-icon"></i>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>

                
                <button class="zena-nav-mobile-toggle" 
                        x-data="{ open: false }"
                        @click="open = !open"
                        aria-expanded="false"
                        aria-label="Toggle navigation menu">
                    <span class="zena-nav-mobile-toggle-line"></span>
                    <span class="zena-nav-mobile-toggle-line"></span>
                    <span class="zena-nav-mobile-toggle-line"></span>
                </button>
            </div>

            
            <div class="zena-nav-mobile" 
                 x-data="{ open: false }"
                 x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-2">
                <div class="zena-nav-mobile-content">
                    <ul class="zena-nav-mobile-list">
                        <li class="zena-nav-mobile-item">
                            <a href="/dashboard" 
                               class="zena-nav-mobile-link zena-nav-mobile-link-active">
                                <i class="fas fa-home zena-nav-mobile-icon"></i>
                                <span>Dashboard</span>
                                <i class="fas fa-check zena-nav-mobile-check"></i>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/tasks" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-tasks zena-nav-mobile-icon"></i>
                                <span>Tasks</span>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/projects" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-project-diagram zena-nav-mobile-icon"></i>
                                <span>Projects</span>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/documents" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-file-alt zena-nav-mobile-icon"></i>
                                <span>Documents</span>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/team" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-users zena-nav-mobile-icon"></i>
                                <span>Team</span>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/templates" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-magic zena-nav-mobile-icon"></i>
                                <span>Templates</span>
                            </a>
                        </li>

                        <li class="zena-nav-mobile-item">
                            <a href="/admin" 
                               class="zena-nav-mobile-link">
                                <i class="fas fa-cog zena-nav-mobile-icon"></i>
                                <span>Admin</span>
                            </a>
                        </li>
                    </ul>

                    
                    <div class="zena-nav-mobile-user">
                        <div class="zena-nav-mobile-user-info">
                            <div class="zena-nav-mobile-user-avatar">AD</div>
                            <div>
                                <div class="zena-nav-mobile-user-name">Admin User</div>
                                <div class="zena-nav-mobile-user-email">admin@zenamanage.com</div>
                            </div>
                        </div>
                        
                        <div class="zena-nav-mobile-user-actions">
                            <a href="/profile" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-user mr-2"></i>
                                Profile
                            </a>
                            <a href="/logout" class="zena-btn zena-btn-danger zena-btn-sm">
                                <i class="fas fa-sign-out-alt mr-2"></i>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Breadcrumb Navigation -->
        <nav class="zena-breadcrumb" aria-label="Breadcrumb">
            <ol class="zena-breadcrumb-list">
                <li class="zena-breadcrumb-item">
                    <a href="/dashboard" class="zena-breadcrumb-link">
                        <i class="fas fa-home mr-2"></i>
                        Dashboard
                    </a>
                </li>
                <li class="zena-breadcrumb-separator">
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="zena-breadcrumb-item">
                    <span class="zena-breadcrumb-current" aria-current="page">
                        Navigation Demo
                    </span>
                </li>
            </ol>
        </nav>

        <!-- Page Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Navigation Demo</h1>
                        <p class="text-gray-600 mt-1">Showcase of enhanced navigation features</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <button class="zena-btn zena-btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i>
                            <span>Refresh</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="space-y-8">
                <!-- Navigation Features -->
                <div class="zena-card zena-p-lg">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">🧭 Navigation Features</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Breadcrumb Navigation -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900">Breadcrumb Navigation</h3>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <nav class="zena-breadcrumb" aria-label="Breadcrumb">
                                    <ol class="zena-breadcrumb-list">
                                        <li class="zena-breadcrumb-item">
                                            <a href="/dashboard" class="zena-breadcrumb-link">
                                                <i class="fas fa-home mr-1"></i>
                                                Dashboard
                                            </a>
                                        </li>
                                        <li class="zena-breadcrumb-separator">
                                            <i class="fas fa-chevron-right"></i>
                                        </li>
                                        <li class="zena-breadcrumb-item">
                                            <a href="/projects" class="zena-breadcrumb-link">Projects</a>
                                        </li>
                                        <li class="zena-breadcrumb-separator">
                                            <i class="fas fa-chevron-right"></i>
                                        </li>
                                        <li class="zena-breadcrumb-item">
                                            <span class="zena-breadcrumb-current">Current Page</span>
                                        </li>
                                    </ol>
                                </nav>
                            </div>
                        </div>

                        <!-- Active State Indicators -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900">Active State Indicators</h3>
                            <div class="space-y-2">
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-home text-blue-600 mr-3"></i>
                                        <span class="font-medium text-blue-900">Dashboard (Active)</span>
                                        <div class="ml-auto w-2 h-2 bg-blue-600 rounded-full"></div>
                                    </div>
                                </div>
                                <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-tasks text-gray-600 mr-3"></i>
                                        <span class="font-medium text-gray-700">Tasks (Inactive)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Menu -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900">Mobile Menu</h3>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-600 mb-3">Responsive navigation with:</p>
                                <ul class="text-sm text-gray-600 space-y-1">
                                    <li>• Hamburger menu toggle</li>
                                    <li>• Touch-friendly interface</li>
                                    <li>• User profile section</li>
                                    <li>• Smooth animations</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Components -->
                <div class="zena-card zena-p-lg">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">🎨 Navigation Components</h2>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Desktop Navigation -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900">Desktop Navigation</h3>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-2 mb-4">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-cube text-blue-600"></i>
                                    </div>
                                    <span class="font-semibold text-gray-900">ZenaManage</span>
                                </div>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2 p-2 bg-blue-50 rounded">
                                        <i class="fas fa-home text-blue-600"></i>
                                        <span class="text-sm font-medium text-blue-900">Dashboard</span>
                                    </div>
                                    <div class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded">
                                        <i class="fas fa-tasks text-gray-600"></i>
                                        <span class="text-sm text-gray-700">Tasks</span>
                                    </div>
                                    <div class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded">
                                        <i class="fas fa-project-diagram text-gray-600"></i>
                                        <span class="text-sm text-gray-700">Projects</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Menu -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900">User Menu</h3>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                        AD
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">Admin User</div>
                                        <div class="text-sm text-gray-600">admin@zenamanage.com</div>
                                    </div>
                                </div>
                                
                                <div class="space-y-1">
                                    <a href="#" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded text-sm">
                                        <i class="fas fa-user text-gray-600"></i>
                                        <span>Profile</span>
                                    </a>
                                    <a href="#" class="flex items-center space-x-2 p-2 hover:bg-gray-100 rounded text-sm">
                                        <i class="fas fa-cog text-gray-600"></i>
                                        <span>Settings</span>
                                    </a>
                                    <a href="#" class="flex items-center space-x-2 p-2 hover:bg-red-50 rounded text-sm text-red-600">
                                        <i class="fas fa-sign-out-alt"></i>
                                        <span>Sign Out</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Responsive Design -->
                <div class="zena-card zena-p-lg">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">📱 Responsive Design</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-desktop text-blue-600 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">Desktop</h4>
                            <p class="text-sm text-gray-600">Full navigation with all features visible</p>
                        </div>
                        
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-tablet text-green-600 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">Tablet</h4>
                            <p class="text-sm text-gray-600">Condensed navigation with touch optimization</p>
                        </div>
                        
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-mobile text-purple-600 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-gray-900 mb-2">Mobile</h4>
                            <p class="text-sm text-gray-600">Hamburger menu with slide-out navigation</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/navigation-demo.blade.php ENDPATH**/ ?>