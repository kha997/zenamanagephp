<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Mobile Dashboard Builder - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Mobile-optimized dashboard builder for ZenaManage">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaManage Mobile">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/css/design-system.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
    
    <style>
        /* Mobile-specific styles */
        .mobile-container {
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        .touch-target {
            min-height: 44px;
            min-width: 44px;
            touch-action: manipulation;
        }
        
        .mobile-widget {
            touch-action: none;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }
        
        .mobile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            padding: 16px;
        }
        
        .mobile-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-sidebar.open {
            transform: translateX(0);
        }
        
        .mobile-fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .gesture-indicator {
            position: absolute;
            pointer-events: none;
            z-index: 1001;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .gesture-indicator.active {
            opacity: 1;
        }
        
        .mobile-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 2000;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: flex-end;
        }
        
        .mobile-modal-content {
            background: white;
            border-radius: 20px 20px 0 0;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .swipe-handle {
            width: 40px;
            height: 4px;
            background: #d1d5db;
            border-radius: 2px;
            margin: 12px auto;
        }
        
        .mobile-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .mobile-widget-library {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            padding: 16px;
        }
        
        .mobile-widget-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            touch-action: manipulation;
        }
        
        .mobile-widget-card:active {
            transform: scale(0.95);
            transition: transform 0.1s ease;
        }
        
        .mobile-canvas {
            min-height: calc(100vh - 120px);
            padding: 16px;
            background: #f9fafb;
        }
        
        .mobile-drop-zone {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            background: white;
            margin: 16px 0;
        }
        
        .mobile-drop-zone.drag-over {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .mobile-widget-item {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            touch-action: none;
        }
        
        .mobile-widget-item.selected {
            border: 2px solid #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .mobile-resize-handle {
            position: absolute;
            background: #3b82f6;
            border-radius: 50%;
            width: 12px;
            height: 12px;
        }
        
        .mobile-resize-handle.se {
            bottom: -6px;
            right: -6px;
        }
        
        .mobile-resize-handle.sw {
            bottom: -6px;
            left: -6px;
        }
        
        .mobile-resize-handle.ne {
            top: -6px;
            right: -6px;
        }
        
        .mobile-resize-handle.nw {
            top: -6px;
            left: -6px;
        }
        
        /* Gesture animations */
        @keyframes swipeLeft {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        
        @keyframes swipeRight {
            0% { transform: translateX(0); }
            100% { transform: translateX(100%); }
        }
        
        @keyframes pinch {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .swipe-left {
            animation: swipeLeft 0.3s ease-out;
        }
        
        .swipe-right {
            animation: swipeRight 0.3s ease-out;
        }
        
        .pinch-effect {
            animation: pinch 0.2s ease-out;
        }
        
        /* Mobile-specific responsive adjustments */
        @media (max-width: 768px) {
            .mobile-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 12px;
            }
            
            .mobile-widget-library {
                grid-template-columns: repeat(3, 1fr);
                gap: 8px;
                padding: 12px;
            }
            
            .mobile-canvas {
                padding: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .mobile-widget-library {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .mobile-canvas {
                padding: 8px;
            }
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased mobile-container">
    <div x-data="mobileDashboardBuilder()" x-init="init()" class="min-h-screen">
        <!-- Mobile Toolbar -->
        <div class="mobile-toolbar">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Hamburger Menu -->
                <button @click="toggleSidebar()" 
                        class="touch-target p-2 text-gray-600 hover:text-gray-900">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Title -->
                <h1 class="text-lg font-semibold text-gray-900">Mobile Builder</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-2">
                    <button @click="showSettings()" 
                            class="touch-target p-2 text-gray-600 hover:text-gray-900">
                        <i class="fas fa-cog text-lg"></i>
                    </button>
                    <button @click="goToAdvanced()" 
                            class="touch-target p-2 text-purple-600 hover:text-purple-800">
                        <i class="fas fa-magic text-lg"></i>
                    </button>
                    <button @click="saveDashboard()" 
                            class="touch-target p-2 text-blue-600 hover:text-blue-800">
                        <i class="fas fa-save text-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Sidebar -->
        <div class="mobile-sidebar fixed inset-y-0 left-0 w-80 bg-white shadow-lg z-50"
             :class="{ 'open': sidebarOpen }">
            <div class="p-4">
                <!-- Sidebar Header -->
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Widget Library</h2>
                    <button @click="toggleSidebar()" 
                            class="touch-target p-2 text-gray-600">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <!-- Widget Categories -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Charts</h3>
                        <div class="mobile-widget-library">
                            <div class="mobile-widget-card" 
                                 @touchstart="startDrag($event, 'line-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-chart-line text-2xl text-blue-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Line Chart</p>
                            </div>
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'bar-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-chart-bar text-2xl text-green-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Bar Chart</p>
                            </div>
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'pie-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-chart-pie text-2xl text-purple-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Pie Chart</p>
                            </div>
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'area-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-chart-area text-2xl text-orange-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Area Chart</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">KPIs</h3>
                        <div class="mobile-widget-library">
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'kpi-card')"
                                 @touchend="endDrag()">
                                <i class="fas fa-tachometer-alt text-2xl text-red-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">KPI Card</p>
                            </div>
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'metric-card')"
                                 @touchend="endDrag()">
                                <i class="fas fa-chart-line text-2xl text-indigo-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Metric Card</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Tables</h3>
                        <div class="mobile-widget-library">
                            <div class="mobile-widget-card"
                                 @touchstart="startDrag($event, 'data-table')"
                                 @touchend="endDrag()">
                                <i class="fas fa-table text-2xl text-gray-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Data Table</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Canvas -->
        <div class="mobile-canvas" 
             @touchstart="handleCanvasTouch($event)"
             @touchmove="handleCanvasMove($event)"
             @touchend="handleCanvasEnd($event)"
             @drop="handleDrop($event)"
             @dragover="handleDragOver($event)">
            
            <!-- Drop Zone -->
            <div class="mobile-drop-zone" 
                 :class="{ 'drag-over': isDragOver }"
                 x-show="widgets.length === 0">
                <i class="fas fa-plus-circle text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Start Building Your Dashboard</h3>
                <p class="text-sm text-gray-600 mb-4">Tap the menu button to add widgets</p>
                <button @click="toggleSidebar()" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg touch-target">
                    <i class="fas fa-plus mr-2"></i>Add Widget
                </button>
            </div>
            
            <!-- Widgets -->
            <div class="space-y-4">
                <template x-for="(widget, index) in widgets" :key="widget.id">
                    <div class="mobile-widget-item mobile-widget relative"
                         :class="{ 'selected': selectedWidget === widget.id }"
                         @touchstart="selectWidget(widget.id, $event)"
                         @touchmove="moveWidget($event, widget.id)"
                         @touchend="endMoveWidget()"
                         :style="`transform: translate(${widget.x}px, ${widget.y}px)`">
                        
                        <!-- Widget Content -->
                        <div class="widget-content">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-900" x-text="widget.name"></h4>
                                <div class="flex items-center space-x-2">
                                    <button @click="duplicateWidget(widget.id)" 
                                            class="touch-target p-1 text-gray-400 hover:text-blue-600">
                                        <i class="fas fa-copy text-sm"></i>
                                    </button>
                                    <button @click="deleteWidget(widget.id)" 
                                            class="touch-target p-1 text-gray-400 hover:text-red-600">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Widget Preview -->
                            <div class="widget-preview" :id="`widget-${widget.id}`">
                                <div x-show="widget.type === 'kpi-card'" class="text-center">
                                    <div class="text-2xl font-bold text-blue-600" x-text="widget.data?.value || '0'"></div>
                                    <div class="text-sm text-gray-600" x-text="widget.data?.label || 'KPI'"></div>
                                </div>
                                
                                <div x-show="widget.type === 'line-chart'" class="h-32">
                                    <div class="flex items-center justify-center h-full text-gray-500">
                                        <i class="fas fa-chart-line text-2xl"></i>
                                    </div>
                                </div>
                                
                                <div x-show="widget.type === 'bar-chart'" class="h-32">
                                    <div class="flex items-center justify-center h-full text-gray-500">
                                        <i class="fas fa-chart-bar text-2xl"></i>
                                    </div>
                                </div>
                                
                                <div x-show="widget.type === 'pie-chart'" class="h-32">
                                    <div class="flex items-center justify-center h-full text-gray-500">
                                        <i class="fas fa-chart-pie text-2xl"></i>
                                    </div>
                                </div>
                                
                                <div x-show="widget.type === 'data-table'" class="h-32">
                                    <div class="flex items-center justify-center h-full text-gray-500">
                                        <i class="fas fa-table text-2xl"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resize Handles -->
                        <div x-show="selectedWidget === widget.id" class="resize-handles">
                            <div class="mobile-resize-handle se" 
                                 @touchstart="startResize($event, widget.id, 'se')"></div>
                            <div class="mobile-resize-handle sw" 
                                 @touchstart="startResize($event, widget.id, 'sw')"></div>
                            <div class="mobile-resize-handle ne" 
                                 @touchstart="startResize($event, widget.id, 'ne')"></div>
                            <div class="mobile-resize-handle nw" 
                                 @touchstart="startResize($event, widget.id, 'nw')"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Mobile FAB -->
        <button @click="showQuickActions()" 
                class="mobile-fab bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus text-xl"></i>
        </button>

        <!-- Gesture Indicator -->
        <div class="gesture-indicator" 
             :class="{ 'active': showGestureIndicator }"
             :style="`left: ${gestureIndicator.x}px; top: ${gestureIndicator.y}px`">
            <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                <i class="fas fa-hand-paper text-white text-sm"></i>
            </div>
        </div>

        <!-- Quick Actions Modal -->
        <div x-show="showQuickActionsModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="mobile-modal"
             @click="showQuickActionsModal = false">
            <div class="mobile-modal-content" @click.stop>
                <div class="swipe-handle"></div>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <button @click="addSampleWidget()" 
                                class="p-4 bg-blue-50 rounded-lg text-center touch-target">
                            <i class="fas fa-plus text-2xl text-blue-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-900">Add Widget</p>
                        </button>
                        <button @click="clearCanvas()" 
                                class="p-4 bg-red-50 rounded-lg text-center touch-target">
                            <i class="fas fa-trash text-2xl text-red-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-900">Clear All</p>
                        </button>
                        <button @click="saveDashboard()" 
                                class="p-4 bg-green-50 rounded-lg text-center touch-target">
                            <i class="fas fa-save text-2xl text-green-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-900">Save</p>
                        </button>
                        <button @click="loadTemplate()" 
                                class="p-4 bg-purple-50 rounded-lg text-center touch-target">
                            <i class="fas fa-layer-group text-2xl text-purple-600 mb-2"></i>
                            <p class="text-sm font-medium text-gray-900">Templates</p>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div x-show="showSettingsModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="mobile-modal"
             @click="showSettingsModal = false">
            <div class="mobile-modal-content" @click.stop>
                <div class="swipe-handle"></div>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Settings</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Auto-save</span>
                            <button @click="toggleAutoSave()" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="autoSave ? 'bg-blue-600' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                      :class="autoSave ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Offline Mode</span>
                            <button @click="toggleOfflineMode()" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="offlineMode ? 'bg-blue-600' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                      :class="offlineMode ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Gesture Hints</span>
                            <button @click="toggleGestureHints()" 
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                                    :class="gestureHints ? 'bg-blue-600' : 'bg-gray-200'">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                      :class="gestureHints ? 'translate-x-6' : 'translate-x-1'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offline Indicator -->
        <div x-show="offlineMode" 
             class="fixed top-16 left-4 right-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-2 rounded-lg z-40">
            <div class="flex items-center">
                <i class="fas fa-wifi-slash mr-2"></i>
                <span class="text-sm font-medium">Offline Mode</span>
            </div>
        </div>
    </div>

    <script>
        function mobileDashboardBuilder() {
            return {
                // State
                sidebarOpen: false,
                widgets: [],
                selectedWidget: null,
                isDragOver: false,
                showQuickActionsModal: false,
                showSettingsModal: false,
                showGestureIndicator: false,
                gestureIndicator: { x: 0, y: 0 },
                autoSave: true,
                offlineMode: false,
                gestureHints: true,
                
                // Touch handling
                touchStart: null,
                touchMove: null,
                isDragging: false,
                isResizing: false,
                resizeHandle: null,
                
                // Initialize
                init() {
                    this.loadDashboard();
                    this.setupServiceWorker();
                    this.setupOfflineDetection();
                    this.setupGestureHints();
                },

                // Load Dashboard
                loadDashboard() {
                    const saved = localStorage.getItem('mobile-dashboard');
                    if (saved) {
                        this.widgets = JSON.parse(saved);
                    }
                },

                // Save Dashboard
                saveDashboard() {
                    localStorage.setItem('mobile-dashboard', JSON.stringify(this.widgets));
                    this.showNotification('Dashboard saved successfully!');
                },

                // Toggle Sidebar
                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                },

                // Start Drag
                startDrag(event, widgetType) {
                    event.preventDefault();
                    this.touchStart = {
                        x: event.touches[0].clientX,
                        y: event.touches[0].clientY,
                        widgetType: widgetType
                    };
                    this.isDragging = true;
                    this.showGestureIndicator = true;
                    this.gestureIndicator = {
                        x: event.touches[0].clientX - 16,
                        y: event.touches[0].clientY - 16
                    };
                },

                // End Drag
                endDrag() {
                    this.isDragging = false;
                    this.showGestureIndicator = false;
                    this.touchStart = null;
                },

                // Handle Canvas Touch
                handleCanvasTouch(event) {
                    if (this.isDragging && this.touchStart) {
                        event.preventDefault();
                    }
                },

                // Handle Canvas Move
                handleCanvasMove(event) {
                    if (this.isDragging && this.touchStart) {
                        event.preventDefault();
                        this.gestureIndicator = {
                            x: event.touches[0].clientX - 16,
                            y: event.touches[0].clientY - 16
                        };
                    }
                },

                // Handle Canvas End
                handleCanvasEnd(event) {
                    if (this.isDragging && this.touchStart) {
                        event.preventDefault();
                        this.addWidget(this.touchStart.widgetType, event.changedTouches[0].clientX, event.changedTouches[0].clientY);
                        this.endDrag();
                    }
                },

                // Add Widget
                addWidget(type, x, y) {
                    const widget = {
                        id: Date.now(),
                        type: type,
                        name: this.getWidgetName(type),
                        x: x - 140, // Center the widget
                        y: y - 100,
                        width: 280,
                        height: 200,
                        data: this.getSampleData(type)
                    };
                    
                    this.widgets.push(widget);
                    this.selectedWidget = widget.id;
                    this.sidebarOpen = false;
                    
                    if (this.autoSave) {
                        this.saveDashboard();
                    }
                },

                // Get Widget Name
                getWidgetName(type) {
                    const names = {
                        'line-chart': 'Line Chart',
                        'bar-chart': 'Bar Chart',
                        'pie-chart': 'Pie Chart',
                        'area-chart': 'Area Chart',
                        'kpi-card': 'KPI Card',
                        'metric-card': 'Metric Card',
                        'data-table': 'Data Table'
                    };
                    return names[type] || 'Widget';
                },

                // Get Sample Data
                getSampleData(type) {
                    if (type === 'kpi-card') {
                        return {
                            value: '1,234',
                            label: 'Total Sales',
                            change: '+12%',
                            trend: 'up'
                        };
                    }
                    return {};
                },

                // Select Widget
                selectWidget(widgetId, event) {
                    event.stopPropagation();
                    this.selectedWidget = widgetId;
                },

                // Move Widget
                moveWidget(event, widgetId) {
                    if (this.selectedWidget === widgetId) {
                        event.preventDefault();
                        const widget = this.widgets.find(w => w.id === widgetId);
                        if (widget) {
                            widget.x = event.touches[0].clientX - 140;
                            widget.y = event.touches[0].clientY - 100;
                        }
                    }
                },

                // End Move Widget
                endMoveWidget() {
                    if (this.autoSave) {
                        this.saveDashboard();
                    }
                },

                // Start Resize
                startResize(event, widgetId, handle) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.isResizing = true;
                    this.resizeHandle = handle;
                    this.selectedWidget = widgetId;
                },

                // Duplicate Widget
                duplicateWidget(widgetId) {
                    const widget = this.widgets.find(w => w.id === widgetId);
                    if (widget) {
                        const newWidget = {
                            ...widget,
                            id: Date.now(),
                            x: widget.x + 20,
                            y: widget.y + 20
                        };
                        this.widgets.push(newWidget);
                        this.selectedWidget = newWidget.id;
                        
                        if (this.autoSave) {
                            this.saveDashboard();
                        }
                    }
                },

                // Delete Widget
                deleteWidget(widgetId) {
                    this.widgets = this.widgets.filter(w => w.id !== widgetId);
                    if (this.selectedWidget === widgetId) {
                        this.selectedWidget = null;
                    }
                    
                    if (this.autoSave) {
                        this.saveDashboard();
                    }
                },

                // Show Quick Actions
                showQuickActions() {
                    this.showQuickActionsModal = true;
                },

                // Show Settings
                showSettings() {
                    this.showSettingsModal = true;
                },

                // Add Sample Widget
                addSampleWidget() {
                    this.addWidget('kpi-card', window.innerWidth / 2, window.innerHeight / 2);
                    this.showQuickActionsModal = false;
                },

                // Clear Canvas
                clearCanvas() {
                    if (confirm('Are you sure you want to clear all widgets?')) {
                        this.widgets = [];
                        this.selectedWidget = null;
                        this.saveDashboard();
                        this.showQuickActionsModal = false;
                    }
                },

                // Load Template
                loadTemplate() {
                    // Load a mobile template
                    this.widgets = [
                        {
                            id: 1,
                            type: 'kpi-card',
                            name: 'Sales',
                            x: 20,
                            y: 20,
                            width: 280,
                            height: 120,
                            data: { value: '1,234', label: 'Total Sales', change: '+12%' }
                        },
                        {
                            id: 2,
                            type: 'kpi-card',
                            name: 'Revenue',
                            x: 20,
                            y: 160,
                            width: 280,
                            height: 120,
                            data: { value: '$5,678', label: 'Revenue', change: '+8%' }
                        }
                    ];
                    this.saveDashboard();
                    this.showQuickActionsModal = false;
                },

                // Toggle Auto Save
                toggleAutoSave() {
                    this.autoSave = !this.autoSave;
                },

                // Toggle Offline Mode
                toggleOfflineMode() {
                    this.offlineMode = !this.offlineMode;
                    if (this.offlineMode) {
                        this.enableOfflineMode();
                    } else {
                        this.disableOfflineMode();
                    }
                },

                // Toggle Gesture Hints
                toggleGestureHints() {
                    this.gestureHints = !this.gestureHints;
                },

                // Setup Service Worker
                setupServiceWorker() {
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/sw.js')
                            .then(registration => {
                                console.log('Service Worker registered:', registration);
                            })
                            .catch(error => {
                                console.log('Service Worker registration failed:', error);
                            });
                    }
                },

                // Setup Offline Detection
                setupOfflineDetection() {
                    window.addEventListener('online', () => {
                        this.offlineMode = false;
                        this.showNotification('Back online!');
                    });
                    
                    window.addEventListener('offline', () => {
                        this.offlineMode = true;
                        this.showNotification('You are offline');
                    });
                },

                // Setup Gesture Hints
                setupGestureHints() {
                    if (this.gestureHints) {
                        // Show gesture hints for first-time users
                        setTimeout(() => {
                            this.showNotification('Swipe left to open widget library');
                        }, 2000);
                    }
                },

                // Enable Offline Mode
                enableOfflineMode() {
                    // Cache dashboard data
                    localStorage.setItem('offline-dashboard', JSON.stringify(this.widgets));
                },

                // Disable Offline Mode
                disableOfflineMode() {
                    // Sync with server if online
                    if (navigator.onLine) {
                        this.saveDashboard();
                    }
                },

                // Show Notification
                showNotification(message) {
                    // Simple notification implementation
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-20 left-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg z-50';
                    notification.textContent = message;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                },

                // Handle Drop
                handleDrop(event) {
                    event.preventDefault();
                    this.isDragOver = false;
                },

                // Handle Drag Over
                handleDragOver(event) {
                    event.preventDefault();
                    this.isDragOver = true;
                },
                
                // Go to Advanced Mobile
                goToAdvanced() {
                    window.location.href = '/app/advanced-mobile-dashboard';
                }
            };
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/mobile-dashboard-builder.blade.php ENDPATH**/ ?>