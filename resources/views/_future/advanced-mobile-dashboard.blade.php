<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Advanced Mobile Dashboard - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Advanced mobile dashboard with gestures, voice, haptic feedback">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaMobile Advanced">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
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
        /* Advanced Mobile Styles */
        .advanced-mobile-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .gesture-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            pointer-events: none;
        }
        
        .gesture-path {
            position: absolute;
            background: rgba(59, 130, 246, 0.3);
            border-radius: 50%;
            pointer-events: none;
            animation: gesturePulse 0.5s ease-out;
        }
        
        @keyframes gesturePulse {
            0% { transform: scale(0); opacity: 1; }
            100% { transform: scale(1); opacity: 0; }
        }
        
        .voice-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            display: none;
        }
        
        .voice-indicator.active {
            display: block;
            animation: voicePulse 1s infinite;
        }
        
        @keyframes voicePulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -50%) scale(1.1); }
        }
        
        .haptic-feedback {
            animation: hapticVibrate 0.1s ease-out;
        }
        
        @keyframes hapticVibrate {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-1px); }
            75% { transform: translateX(1px); }
        }
        
        .mobile-theme-dark {
            background: #1a1a1a;
            color: #ffffff;
        }
        
        .mobile-theme-dark .mobile-widget {
            background: #2d2d2d;
            border-color: #404040;
        }
        
        .mobile-theme-dark .mobile-sidebar {
            background: #1a1a1a;
            border-color: #404040;
        }
        
        .mobile-theme-light {
            background: #ffffff;
            color: #000000;
        }
        
        .mobile-theme-auto {
            background: var(--system-background);
            color: var(--system-text);
        }
        
        .advanced-widget {
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin: 8px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            touch-action: none;
            transition: all 0.3s ease;
        }
        
        .advanced-widget.selected {
            border: 2px solid #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        .advanced-widget.gesture-active {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }
        
        .gesture-hint {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .gesture-hint.show {
            opacity: 1;
        }
        
        .mobile-analytics-panel {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-radius: 20px 20px 0 0;
            padding: 20px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .mobile-analytics-panel.open {
            transform: translateY(0);
        }
        
        .notification-toast {
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-100px);
            transition: transform 0.3s ease;
            z-index: 10000;
        }
        
        .notification-toast.show {
            transform: translateY(0);
        }
        
        .collaboration-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
        }
        
        .gesture-recognition-area {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }
        
        .multi-touch-indicator {
            position: absolute;
            width: 20px;
            height: 20px;
            background: #3b82f6;
            border-radius: 50%;
            pointer-events: none;
            z-index: 1001;
        }
        
        .voice-waveform {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            margin: 10px 0;
        }
        
        .voice-bar {
            width: 3px;
            background: #3b82f6;
            border-radius: 2px;
            animation: voiceWave 0.5s infinite ease-in-out;
        }
        
        .voice-bar:nth-child(1) { animation-delay: 0s; }
        .voice-bar:nth-child(2) { animation-delay: 0.1s; }
        .voice-bar:nth-child(3) { animation-delay: 0.2s; }
        .voice-bar:nth-child(4) { animation-delay: 0.3s; }
        .voice-bar:nth-child(5) { animation-delay: 0.4s; }
        
        @keyframes voiceWave {
            0%, 100% { height: 10px; }
            50% { height: 20px; }
        }
        
        .theme-selector {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .theme-button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .theme-button:active {
            transform: scale(0.95);
        }
        
        .theme-light { background: #ffffff; }
        .theme-dark { background: #1a1a1a; }
        .theme-auto { background: linear-gradient(45deg, #ffffff 50%, #1a1a1a 50%); }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased advanced-mobile-container">
    <div x-data="advancedMobileDashboard()" x-init="init()" class="min-h-screen">
        <!-- Theme Selector -->
        <div class="theme-selector">
            <button @click="toggleTheme()" 
                    class="theme-button"
                    :class="getThemeClass()"
                    :title="`Current theme: ${currentTheme}`">
            </button>
        </div>

        <!-- Voice Indicator -->
        <div class="voice-indicator" 
             :class="{ 'active': isListening }"
             x-show="isListening">
            <div class="voice-waveform">
                <div class="voice-bar"></div>
                <div class="voice-bar"></div>
                <div class="voice-bar"></div>
                <div class="voice-bar"></div>
                <div class="voice-bar"></div>
            </div>
            <p class="text-sm">Listening...</p>
            <p class="text-xs text-gray-300" x-text="voiceCommand"></p>
        </div>

        <!-- Gesture Overlay -->
        <div class="gesture-overlay" 
             @touchstart="handleAdvancedTouch($event)"
             @touchmove="handleAdvancedMove($event)"
             @touchend="handleAdvancedEnd($event)">
        </div>

        <!-- Collaboration Indicator -->
        <div class="collaboration-indicator" x-show="collaborationMode">
            <i class="fas fa-users mr-1"></i>
            <span x-text="`${activeUsers} users`"></span>
        </div>

        <!-- Mobile Toolbar -->
        <div class="mobile-toolbar sticky top-0 z-50 bg-white border-b">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Hamburger Menu -->
                <button @click="toggleSidebar()" 
                        class="touch-target p-2 text-gray-600 hover:text-gray-900"
                        @touchstart="hapticFeedback('light')">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <!-- Title -->
                <h1 class="text-lg font-semibold text-gray-900">Advanced Mobile</h1>
                
                <!-- Actions -->
                <div class="flex items-center space-x-2">
                    <button @click="toggleVoiceCommands()" 
                            class="touch-target p-2 text-gray-600 hover:text-gray-900"
                            :class="{ 'text-blue-600': isListening }"
                            @touchstart="hapticFeedback('medium')">
                        <i class="fas fa-microphone text-lg"></i>
                    </button>
                    <button @click="showAnalytics()" 
                            class="touch-target p-2 text-gray-600 hover:text-gray-900"
                            @touchstart="hapticFeedback('light')">
                        <i class="fas fa-chart-bar text-lg"></i>
                    </button>
                    <button @click="toggleCollaboration()" 
                            class="touch-target p-2 text-gray-600 hover:text-gray-900"
                            :class="{ 'text-green-600': collaborationMode }"
                            @touchstart="hapticFeedback('medium')">
                        <i class="fas fa-users text-lg"></i>
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
                    <h2 class="text-lg font-semibold text-gray-900">Advanced Widgets</h2>
                    <button @click="toggleSidebar()" 
                            class="touch-target p-2 text-gray-600"
                            @touchstart="hapticFeedback('light')">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                
                <!-- Advanced Widget Categories -->
                <div class="space-y-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Smart Widgets</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="advanced-widget-card" 
                                 @touchstart="startAdvancedDrag($event, 'smart-kpi')"
                                 @touchend="endDrag()">
                                <i class="fas fa-brain text-2xl text-purple-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Smart KPI</p>
                            </div>
                            <div class="advanced-widget-card"
                                 @touchstart="startAdvancedDrag($event, 'predictive-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-crystal-ball text-2xl text-indigo-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Predictive</p>
                            </div>
                            <div class="advanced-widget-card"
                                 @touchstart="startAdvancedDrag($event, 'voice-widget')"
                                 @touchend="endDrag()">
                                <i class="fas fa-microphone text-2xl text-red-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Voice Widget</p>
                            </div>
                            <div class="advanced-widget-card"
                                 @touchstart="startAdvancedDrag($event, 'gesture-chart')"
                                 @touchend="endDrag()">
                                <i class="fas fa-hand-paper text-2xl text-orange-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Gesture Chart</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Collaborative Widgets</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="advanced-widget-card"
                                 @touchstart="startAdvancedDrag($event, 'live-chat')"
                                 @touchend="endDrag()">
                                <i class="fas fa-comments text-2xl text-green-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Live Chat</p>
                            </div>
                            <div class="advanced-widget-card"
                                 @touchstart="startAdvancedDrag($event, 'shared-whiteboard')"
                                 @touchend="endDrag()">
                                <i class="fas fa-palette text-2xl text-blue-600 mb-2"></i>
                                <p class="text-sm font-medium text-gray-900">Whiteboard</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Canvas -->
        <div class="mobile-canvas min-h-screen p-4 bg-gray-50">
            <!-- Advanced Widgets -->
            <div class="space-y-4">
                <template x-for="(widget, index) in advancedWidgets" :key="widget.id">
                    <div class="advanced-widget"
                         :class="{ 
                             'selected': selectedWidget === widget.id,
                             'gesture-active': widget.gestureActive 
                         }"
                         @touchstart="selectAdvancedWidget(widget.id, $event)"
                         @touchmove="moveAdvancedWidget($event, widget.id)"
                         @touchend="endMoveAdvancedWidget()"
                         :style="`transform: translate(${widget.x}px, ${widget.y}px)`">
                        
                        <!-- Gesture Recognition Area -->
                        <div class="gesture-recognition-area"
                             @touchstart="handleWidgetGesture($event, widget.id)"
                             @touchmove="handleWidgetGestureMove($event, widget.id)"
                             @touchend="handleWidgetGestureEnd($event, widget.id)">
                        </div>
                        
                        <!-- Widget Content -->
                        <div class="widget-content">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-medium text-gray-900" x-text="widget.name"></h4>
                                <div class="flex items-center space-x-2">
                                    <button @click="duplicateAdvancedWidget(widget.id)" 
                                            class="touch-target p-1 text-gray-400 hover:text-blue-600"
                                            @touchstart="hapticFeedback('light')">
                                        <i class="fas fa-copy text-sm"></i>
                                    </button>
                                    <button @click="deleteAdvancedWidget(widget.id)" 
                                            class="touch-target p-1 text-gray-400 hover:text-red-600"
                                            @touchstart="hapticFeedback('heavy')">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Advanced Widget Preview -->
                            <div class="widget-preview" :id="`advanced-widget-${widget.id}`">
                                <div x-show="widget.type === 'smart-kpi'" class="text-center">
                                    <div class="text-2xl font-bold text-purple-600" x-text="widget.data?.value || 'AI'"></div>
                                    <div class="text-sm text-gray-600" x-text="widget.data?.label || 'Smart KPI'"></div>
                                    <div class="text-xs text-green-600" x-text="widget.data?.prediction || '↑ +15%'"></div>
                                </div>
                                
                                <div x-show="widget.type === 'voice-widget'" class="text-center">
                                    <div class="text-2xl font-bold text-red-600" x-text="widget.data?.command || 'Voice'"></div>
                                    <div class="text-sm text-gray-600" x-text="widget.data?.status || 'Ready'"></div>
                                    <div class="text-xs text-blue-600" x-text="widget.data?.lastCommand || 'Tap to speak'"></div>
                                </div>
                                
                                <div x-show="widget.type === 'gesture-chart'" class="text-center">
                                    <div class="text-2xl font-bold text-orange-600" x-text="widget.data?.gesture || '👆'"></div>
                                    <div class="text-sm text-gray-600" x-text="widget.data?.action || 'Gesture Chart'"></div>
                                    <div class="text-xs text-purple-600" x-text="widget.data?.hint || 'Swipe to interact'"></div>
                                </div>
                                
                                <div x-show="widget.type === 'live-chat'" class="text-center">
                                    <div class="text-2xl font-bold text-green-600" x-text="widget.data?.messages || '💬'"></div>
                                    <div class="text-sm text-gray-600" x-text="widget.data?.users || 'Live Chat'"></div>
                                    <div class="text-xs text-blue-600" x-text="widget.data?.status || 'Active'"></div>
                                </div>
                            </div>
                            
                            <!-- Gesture Hints -->
                            <div class="gesture-hint" 
                                 :class="{ 'show': widget.showGestureHint }"
                                 x-text="widget.gestureHint">
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Mobile Analytics Panel -->
        <div class="mobile-analytics-panel" 
             :class="{ 'open': showAnalyticsPanel }">
            <div class="swipe-handle w-10 h-1 bg-gray-300 rounded mx-auto mb-4"></div>
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mobile Analytics</h3>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600" x-text="analyticsData.gestures"></div>
                    <div class="text-sm text-gray-600">Gestures</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600" x-text="analyticsData.voiceCommands"></div>
                    <div class="text-sm text-gray-600">Voice Commands</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600" x-text="analyticsData.hapticFeedback"></div>
                    <div class="text-sm text-gray-600">Haptic Events</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600" x-text="analyticsData.collaboration"></div>
                    <div class="text-sm text-gray-600">Collaborations</div>
                </div>
            </div>
            
            <button @click="showAnalyticsPanel = false" 
                    class="w-full py-2 bg-gray-100 text-gray-700 rounded-lg">
                Close
            </button>
        </div>

        <!-- Notification Toast -->
        <div class="notification-toast" 
             :class="{ 'show': showNotification }"
             x-show="showNotification">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i :class="notificationIcon" class="text-lg mr-3"></i>
                    <div>
                        <p class="font-medium text-gray-900" x-text="notificationTitle"></p>
                        <p class="text-sm text-gray-600" x-text="notificationMessage"></p>
                    </div>
                </div>
                <button @click="showNotification = false" 
                        class="p-1 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        function advancedMobileDashboard() {
            return {
                // State
                sidebarOpen: false,
                advancedWidgets: [],
                selectedWidget: null,
                isListening: false,
                voiceCommand: '',
                currentTheme: 'light',
                collaborationMode: false,
                activeUsers: 0,
                showAnalyticsPanel: false,
                showNotification: false,
                notificationTitle: '',
                notificationMessage: '',
                notificationIcon: 'fas fa-info-circle text-blue-600',
                
                // Analytics
                analyticsData: {
                    gestures: 0,
                    voiceCommands: 0,
                    hapticFeedback: 0,
                    collaboration: 0
                },
                
                // Gesture handling
                gestureStart: null,
                gestureActive: false,
                multiTouchPoints: [],
                
                // Initialize
                init() {
                    this.loadAdvancedDashboard();
                    this.setupVoiceRecognition();
                    this.setupHapticFeedback();
                    this.setupCollaboration();
                    this.setupTheme();
                    this.setupAnalytics();
                },

                // Load Advanced Dashboard
                loadAdvancedDashboard() {
                    const saved = localStorage.getItem('advanced-mobile-dashboard');
                    if (saved) {
                        this.advancedWidgets = JSON.parse(saved);
                    } else {
                        // Load sample advanced widgets
                        this.advancedWidgets = [
                            {
                                id: 1,
                                type: 'smart-kpi',
                                name: 'Smart Sales KPI',
                                x: 20,
                                y: 20,
                                width: 280,
                                height: 120,
                                data: { value: 'AI', label: 'Smart KPI', prediction: '↑ +15%' },
                                gestureActive: false,
                                showGestureHint: false,
                                gestureHint: 'Swipe up for details'
                            },
                            {
                                id: 2,
                                type: 'voice-widget',
                                name: 'Voice Assistant',
                                x: 20,
                                y: 160,
                                width: 280,
                                height: 120,
                                data: { command: 'Voice', status: 'Ready', lastCommand: 'Tap to speak' },
                                gestureActive: false,
                                showGestureHint: false,
                                gestureHint: 'Long press to activate'
                            }
                        ];
                    }
                },

                // Save Advanced Dashboard
                saveAdvancedDashboard() {
                    localStorage.setItem('advanced-mobile-dashboard', JSON.stringify(this.advancedWidgets));
                    this.showAdvancedNotification('Dashboard saved!', 'fas fa-save text-green-600');
                },

                // Toggle Sidebar
                toggleSidebar() {
                    this.sidebarOpen = !this.sidebarOpen;
                    this.hapticFeedback('light');
                },

                // Advanced Touch Handling
                handleAdvancedTouch(event) {
                    this.gestureStart = {
                        x: event.touches[0].clientX,
                        y: event.touches[0].clientY,
                        time: Date.now()
                    };
                    
                    this.multiTouchPoints = Array.from(event.touches).map(touch => ({
                        id: touch.identifier,
                        x: touch.clientX,
                        y: touch.clientY
                    }));
                    
                    // Detect gesture type
                    if (event.touches.length === 1) {
                        this.handleSingleTouch(event);
                    } else if (event.touches.length === 2) {
                        this.handlePinchGesture(event);
                    } else if (event.touches.length >= 3) {
                        this.handleMultiTouchGesture(event);
                    }
                },

                // Handle Single Touch
                handleSingleTouch(event) {
                    const touch = event.touches[0];
                    const duration = Date.now() - this.gestureStart.time;
                    
                    if (duration > 500) {
                        // Long press
                        this.hapticFeedback('medium');
                        this.showAdvancedNotification('Long press detected', 'fas fa-hand-paper text-orange-600');
                        this.analyticsData.gestures++;
                    }
                },

                // Handle Pinch Gesture
                handlePinchGesture(event) {
                    if (event.touches.length === 2) {
                        const touch1 = event.touches[0];
                        const touch2 = event.touches[1];
                        
                        const distance = Math.sqrt(
                            Math.pow(touch2.clientX - touch1.clientX, 2) + 
                            Math.pow(touch2.clientY - touch1.clientY, 2)
                        );
                        
                        if (distance > 100) {
                            this.hapticFeedback('heavy');
                            this.showAdvancedNotification('Pinch gesture detected', 'fas fa-compress text-purple-600');
                            this.analyticsData.gestures++;
                        }
                    }
                },

                // Handle Multi-Touch Gesture
                handleMultiTouchGesture(event) {
                    this.hapticFeedback('heavy');
                    this.showAdvancedNotification('Multi-touch gesture detected', 'fas fa-hand-rock text-red-600');
                    this.analyticsData.gestures++;
                },

                // Handle Advanced Move
                handleAdvancedMove(event) {
                    if (this.gestureStart) {
                        const touch = event.touches[0];
                        const deltaX = touch.clientX - this.gestureStart.x;
                        const deltaY = touch.clientY - this.gestureStart.y;
                        
                        // Detect swipe gestures
                        if (Math.abs(deltaX) > 50 || Math.abs(deltaY) > 50) {
                            if (Math.abs(deltaX) > Math.abs(deltaY)) {
                                // Horizontal swipe
                                if (deltaX > 0) {
                                    this.handleSwipeRight();
                                } else {
                                    this.handleSwipeLeft();
                                }
                            } else {
                                // Vertical swipe
                                if (deltaY > 0) {
                                    this.handleSwipeDown();
                                } else {
                                    this.handleSwipeUp();
                                }
                            }
                        }
                    }
                },

                // Handle Advanced End
                handleAdvancedEnd(event) {
                    this.gestureStart = null;
                    this.multiTouchPoints = [];
                },

                // Swipe Gestures
                handleSwipeLeft() {
                    this.hapticFeedback('light');
                    this.showAdvancedNotification('Swipe left', 'fas fa-arrow-left text-blue-600');
                },

                handleSwipeRight() {
                    this.hapticFeedback('light');
                    this.showAdvancedNotification('Swipe right', 'fas fa-arrow-right text-blue-600');
                },

                handleSwipeUp() {
                    this.hapticFeedback('medium');
                    this.showAdvancedNotification('Swipe up', 'fas fa-arrow-up text-green-600');
                },

                handleSwipeDown() {
                    this.hapticFeedback('medium');
                    this.showAdvancedNotification('Swipe down', 'fas fa-arrow-down text-green-600');
                },

                // Voice Commands
                setupVoiceRecognition() {
                    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                        this.recognition = new SpeechRecognition();
                        this.recognition.continuous = false;
                        this.recognition.interimResults = false;
                        this.recognition.lang = 'vi-VN';
                        
                        this.recognition.onstart = () => {
                            this.isListening = true;
                            this.hapticFeedback('medium');
                        };
                        
                        this.recognition.onresult = (event) => {
                            const command = event.results[0][0].transcript.toLowerCase();
                            this.voiceCommand = command;
                            this.processVoiceCommand(command);
                            this.analyticsData.voiceCommands++;
                        };
                        
                        this.recognition.onend = () => {
                            this.isListening = false;
                        };
                        
                        this.recognition.onerror = (event) => {
                            this.isListening = false;
                            this.showAdvancedNotification('Voice recognition error', 'fas fa-exclamation-triangle text-red-600');
                        };
                    }
                },

                // Toggle Voice Commands
                toggleVoiceCommands() {
                    if (this.isListening) {
                        this.recognition.stop();
                    } else {
                        this.recognition.start();
                    }
                },

                // Process Voice Command
                processVoiceCommand(command) {
                    this.hapticFeedback('medium');
                    
                    if (command.includes('thêm') || command.includes('add')) {
                        this.addSampleAdvancedWidget();
                        this.showAdvancedNotification('Widget added via voice', 'fas fa-microphone text-green-600');
                    } else if (command.includes('xóa') || command.includes('delete')) {
                        if (this.selectedWidget) {
                            this.deleteAdvancedWidget(this.selectedWidget);
                            this.showAdvancedNotification('Widget deleted via voice', 'fas fa-microphone text-red-600');
                        }
                    } else if (command.includes('lưu') || command.includes('save')) {
                        this.saveAdvancedDashboard();
                    } else if (command.includes('tối') || command.includes('dark')) {
                        this.currentTheme = 'dark';
                        this.showAdvancedNotification('Theme changed to dark', 'fas fa-moon text-purple-600');
                    } else if (command.includes('sáng') || command.includes('light')) {
                        this.currentTheme = 'light';
                        this.showAdvancedNotification('Theme changed to light', 'fas fa-sun text-yellow-600');
                    } else {
                        this.showAdvancedNotification(`Voice command: ${command}`, 'fas fa-microphone text-blue-600');
                    }
                },

                // Haptic Feedback
                setupHapticFeedback() {
                    // Haptic feedback is supported on mobile devices
                    this.hapticSupported = 'vibrate' in navigator;
                },

                // Haptic Feedback
                hapticFeedback(type) {
                    if (this.hapticSupported) {
                        const patterns = {
                            light: [10],
                            medium: [20],
                            heavy: [50],
                            success: [10, 10, 10],
                            error: [50, 50, 50],
                            warning: [20, 20, 20]
                        };
                        
                        navigator.vibrate(patterns[type] || [10]);
                        this.analyticsData.hapticFeedback++;
                        
                        // Visual feedback
                        document.body.classList.add('haptic-feedback');
                        setTimeout(() => {
                            document.body.classList.remove('haptic-feedback');
                        }, 100);
                    }
                },

                // Collaboration
                setupCollaboration() {
                    // Simulate collaboration
                    this.activeUsers = Math.floor(Math.random() * 5) + 1;
                    this.collaborationMode = Math.random() > 0.5;
                },

                // Toggle Collaboration
                toggleCollaboration() {
                    this.collaborationMode = !this.collaborationMode;
                    this.activeUsers = this.collaborationMode ? Math.floor(Math.random() * 5) + 1 : 0;
                    this.analyticsData.collaboration++;
                    this.hapticFeedback('medium');
                    this.showAdvancedNotification(
                        this.collaborationMode ? 'Collaboration enabled' : 'Collaboration disabled',
                        'fas fa-users text-green-600'
                    );
                },

                // Theme Management
                setupTheme() {
                    this.currentTheme = localStorage.getItem('mobile-theme') || 'light';
                    this.applyTheme();
                },

                // Toggle Theme
                toggleTheme() {
                    const themes = ['light', 'dark', 'auto'];
                    const currentIndex = themes.indexOf(this.currentTheme);
                    this.currentTheme = themes[(currentIndex + 1) % themes.length];
                    
                    localStorage.setItem('mobile-theme', this.currentTheme);
                    this.applyTheme();
                    this.hapticFeedback('light');
                    this.showAdvancedNotification(`Theme: ${this.currentTheme}`, 'fas fa-palette text-purple-600');
                },

                // Apply Theme
                applyTheme() {
                    document.body.className = document.body.className.replace(/mobile-theme-\w+/g, '');
                    document.body.classList.add(`mobile-theme-${this.currentTheme}`);
                },

                // Get Theme Class
                getThemeClass() {
                    return `theme-${this.currentTheme}`;
                },

                // Analytics
                setupAnalytics() {
                    // Load analytics data
                    const saved = localStorage.getItem('mobile-analytics');
                    if (saved) {
                        this.analyticsData = JSON.parse(saved);
                    }
                },

                // Show Analytics
                showAnalytics() {
                    this.showAnalyticsPanel = true;
                    this.hapticFeedback('light');
                },

                // Save Analytics
                saveAnalytics() {
                    localStorage.setItem('mobile-analytics', JSON.stringify(this.analyticsData));
                },

                // Advanced Widget Management
                startAdvancedDrag(event, widgetType) {
                    event.preventDefault();
                    this.gestureStart = {
                        x: event.touches[0].clientX,
                        y: event.touches[0].clientY,
                        widgetType: widgetType
                    };
                    this.hapticFeedback('light');
                },

                addSampleAdvancedWidget() {
                    const widget = {
                        id: Date.now(),
                        type: 'smart-kpi',
                        name: 'Smart Widget',
                        x: Math.random() * 200,
                        y: Math.random() * 200,
                        width: 280,
                        height: 120,
                        data: { value: 'AI', label: 'Smart Widget', prediction: '↑ +10%' },
                        gestureActive: false,
                        showGestureHint: false,
                        gestureHint: 'Swipe to interact'
                    };
                    
                    this.advancedWidgets.push(widget);
                    this.selectedWidget = widget.id;
                    this.hapticFeedback('success');
                    this.saveAdvancedDashboard();
                },

                selectAdvancedWidget(widgetId, event) {
                    event.stopPropagation();
                    this.selectedWidget = widgetId;
                    this.hapticFeedback('light');
                },

                moveAdvancedWidget(event, widgetId) {
                    if (this.selectedWidget === widgetId) {
                        event.preventDefault();
                        const widget = this.advancedWidgets.find(w => w.id === widgetId);
                        if (widget) {
                            widget.x = event.touches[0].clientX - 140;
                            widget.y = event.touches[0].clientY - 100;
                        }
                    }
                },

                endMoveAdvancedWidget() {
                    this.saveAdvancedDashboard();
                },

                duplicateAdvancedWidget(widgetId) {
                    const widget = this.advancedWidgets.find(w => w.id === widgetId);
                    if (widget) {
                        const newWidget = {
                            ...widget,
                            id: Date.now(),
                            x: widget.x + 20,
                            y: widget.y + 20
                        };
                        this.advancedWidgets.push(newWidget);
                        this.selectedWidget = newWidget.id;
                        this.hapticFeedback('success');
                        this.saveAdvancedDashboard();
                    }
                },

                deleteAdvancedWidget(widgetId) {
                    this.advancedWidgets = this.advancedWidgets.filter(w => w.id !== widgetId);
                    if (this.selectedWidget === widgetId) {
                        this.selectedWidget = null;
                    }
                    this.hapticFeedback('error');
                    this.saveAdvancedDashboard();
                },

                // Widget Gesture Handling
                handleWidgetGesture(event, widgetId) {
                    const widget = this.advancedWidgets.find(w => w.id === widgetId);
                    if (widget) {
                        widget.gestureActive = true;
                        widget.showGestureHint = true;
                        this.hapticFeedback('light');
                    }
                },

                handleWidgetGestureMove(event, widgetId) {
                    // Handle widget-specific gestures
                },

                handleWidgetGestureEnd(event, widgetId) {
                    const widget = this.advancedWidgets.find(w => w.id === widgetId);
                    if (widget) {
                        widget.gestureActive = false;
                        widget.showGestureHint = false;
                    }
                },

                // Advanced Notifications
                showAdvancedNotification(message, icon) {
                    this.notificationTitle = 'Advanced Mobile';
                    this.notificationMessage = message;
                    this.notificationIcon = icon;
                    this.showNotification = true;
                    
                    setTimeout(() => {
                        this.showNotification = false;
                    }, 3000);
                }
            };
        }
    </script>
</body>
</html>
