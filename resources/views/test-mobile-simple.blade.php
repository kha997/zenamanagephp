{{-- Simple Mobile Test Page --}}
{{-- Basic mobile optimization test without complex components --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Optimization Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .mobile-only { display: block; }
            .desktop-only { display: none; }
        }
        
        @media (min-width: 769px) {
            .mobile-only { display: none; }
            .desktop-only { display: block; }
        }
        
        /* FAB styles */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: #3B82F6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0,0,0,0.2);
        }
        
        /* Mobile navigation */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-around;
            padding: 8px 0;
            z-index: 1000;
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 8px;
            color: #6b7280;
            text-decoration: none;
            font-size: 12px;
        }
        
        .mobile-nav-item.active {
            color: #3B82F6;
        }
        
        .mobile-nav-item i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        
        /* Mobile header */
        .mobile-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
        }
        
        /* Content padding for mobile */
        .mobile-content {
            padding-top: 60px;
            padding-bottom: 80px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Mobile Header -->
    <div class="mobile-only mobile-header">
        <div class="flex items-center space-x-3">
            <button class="p-2 text-gray-600">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="text-lg font-bold text-gray-900">ZenaManage</h1>
        </div>
        <div class="flex items-center space-x-2">
            <button class="p-2 text-gray-600">
                <i class="fas fa-search"></i>
            </button>
            <button class="p-2 text-gray-600">
                <i class="fas fa-bell"></i>
            </button>
        </div>
    </div>
    
    <!-- Desktop Header -->
    <div class="desktop-only bg-white border-b border-gray-200 p-4">
        <h1 class="text-2xl font-bold text-gray-900">Mobile Optimization Test</h1>
        <p class="text-gray-600 mt-2">This page demonstrates mobile optimization features</p>
    </div>
    
    <!-- Main Content -->
    <div class="mobile-content p-4">
        <!-- Page Description -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-mobile-alt text-blue-500 mt-1"></i>
                <div>
                    <h3 class="text-lg font-semibold text-blue-900">Mobile Optimization Test</h3>
                    <p class="text-blue-700 mt-1">
                        This page demonstrates mobile optimization features including:
                        FAB, Mobile Navigation, Responsive Design, and Touch Interactions
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <!-- FAB Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">FAB</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Floating Action Button for quick actions on mobile devices.
                </p>
            </div>
            
            <!-- Mobile Navigation Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bars text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Mobile Nav</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Bottom navigation bar for easy mobile navigation.
                </p>
            </div>
            
            <!-- Responsive Design Feature -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-purple-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-mobile-alt text-white text-sm"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Responsive</h3>
                </div>
                <p class="text-gray-600 text-sm">
                    Responsive design that adapts to different screen sizes.
                </p>
            </div>
        </div>
        
        <!-- Mobile Instructions -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
            <div class="flex items-start space-x-3">
                <i class="fas fa-info-circle text-yellow-600 mt-1"></i>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-900">Mobile Testing Instructions</h3>
                    <div class="text-yellow-800 mt-2">
                        <p><strong>To test mobile features:</strong></p>
                        <ul class="list-disc list-inside mt-2 space-y-1">
                            <li>Resize your browser window to mobile width (320px-768px)</li>
                            <li>Use browser developer tools mobile emulation</li>
                            <li>Test on actual mobile devices</li>
                            <li>Try the FAB button and mobile navigation</li>
                            <li>Check responsive design behavior</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sample Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Project Alpha</h4>
                <p class="text-gray-600 text-sm mb-3">Website redesign project</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">75% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: 75%"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Task Beta</h4>
                <p class="text-gray-600 text-sm mb-3">Mobile app development</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">25% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-green-600 h-2 rounded-full" style="width: 25%"></div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Document Gamma</h4>
                <p class="text-gray-600 text-sm mb-3">Requirements document</p>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-gray-500">100% Complete</span>
                    <div class="w-16 bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mobile Performance Metrics</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">95%</div>
                    <div class="text-sm text-green-800">Mobile Usability</div>
                </div>
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600">2.1s</div>
                    <div class="text-sm text-blue-800">Load Time</div>
                </div>
                <div class="text-center p-4 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">98%</div>
                    <div class="text-sm text-purple-800">Touch Target Size</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <div class="mobile-only mobile-nav">
        <a href="#" class="mobile-nav-item active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="#" class="mobile-nav-item">
            <i class="fas fa-project-diagram"></i>
            <span>Projects</span>
        </a>
        <a href="#" class="mobile-nav-item">
            <i class="fas fa-tasks"></i>
            <span>Tasks</span>
        </a>
        <a href="#" class="mobile-nav-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Calendar</span>
        </a>
        <a href="#" class="mobile-nav-item">
            <i class="fas fa-users"></i>
            <span>Team</span>
        </a>
    </div>
    
    <!-- FAB Button -->
    <div class="mobile-only fab">
        <i class="fas fa-plus"></i>
    </div>
    
    <script>
        // FAB functionality
        document.querySelector('.fab').addEventListener('click', function() {
            alert('FAB clicked! This would open a quick action menu.');
        });
        
        // Mobile navigation
        document.querySelectorAll('.mobile-nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.mobile-nav-item').forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Touch feedback
        document.querySelectorAll('.bg-white').forEach(card => {
            card.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            
            card.addEventListener('touchend', function() {
                this.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>
