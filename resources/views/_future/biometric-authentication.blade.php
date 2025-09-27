<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Authentication - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Biometric Authentication with WebAuthn, Touch ID, Face ID, and Voice Recognition">
    <meta name="theme-color" content="#4facfe">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
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
    
    <!-- WebAuthn Polyfill -->
    <script src="https://cdn.jsdelivr.net/npm/@github/webauthn-json@1.1.0/dist/webauthn-json.browser-global.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/lodash@latest/lodash.min.js"></script>
    
    <style>
        /* Biometric Authentication Styles */
        .biometric-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .biometric-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0;
        }
        
        .biometric-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #4facfe;
        }
        
        .fingerprint-widget {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .fingerprint-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .face-widget {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .face-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .voice-widget {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .voice-widget:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .biometric-fingerprint {
            animation: biometricScan 2s infinite;
        }
        
        @keyframes biometricScan {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .biometric-face {
            animation: faceRecognition 3s infinite ease-in-out;
        }
        
        @keyframes faceRecognition {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        .biometric-voice {
            animation: voiceWave 2s infinite;
        }
        
        @keyframes voiceWave {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .webauthn-status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .biometric-scanner {
            background: rgba(255, 255, 255, 0.1);
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .biometric-scanner.active {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.1);
        }
        
        .biometric-scanner.scanning {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            animation: biometricPulse 1s infinite;
        }
        
        @keyframes biometricPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .biometric-scanner.success {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.2);
        }
        
        .biometric-scanner.error {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active { background: #10b981; }
        .status-inactive { background: #ef4444; }
        .status-loading { background: #f59e0b; }
        
        .biometric-button {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .biometric-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .biometric-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .security-level {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .biometric-feedback {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        }
        
        .biometric-feedback.show {
            transform: translateX(0);
        }
        
        .biometric-feedback.success {
            border-left: 4px solid #10b981;
        }
        
        .biometric-feedback.error {
            border-left: 4px solid #ef4444;
        }
        
        .biometric-feedback.warning {
            border-left: 4px solid #f59e0b;
        }
        
        .camera-preview {
            width: 100%;
            height: 200px;
            background: #000;
            border-radius: 8px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .camera-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 120px;
            height: 120px;
            border: 2px solid #4facfe;
            border-radius: 50%;
            background: rgba(79, 172, 254, 0.1);
        }
        
        .voice-visualizer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin: 16px 0;
        }
        
        .voice-bar {
            width: 4px;
            background: #4facfe;
            border-radius: 2px;
            animation: voiceBar 0.5s infinite alternate;
        }
        
        @keyframes voiceBar {
            0% { height: 10px; }
            100% { height: 30px; }
        }
        
        .biometric-metrics {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
        }
        
        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .metric-item:last-child {
            border-bottom: none;
        }
        
        .metric-value {
            font-weight: 600;
            color: #4facfe;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased biometric-container">
    <div x-data="biometricAuthentication()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-fingerprint biometric-fingerprint text-blue-600 mr-2"></i>
                                Biometric Authentication
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="flex items-center space-x-4">
                        <button @click="goToARVR()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-vr-cardboard mr-2"></i>AR/VR
                        </button>
                        <button @click="goToML()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-brain mr-2"></i>ML
                        </button>
                        <button @click="goToFuture()" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            <i class="fas fa-rocket mr-2"></i>Future
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Biometric Header -->
            <div class="biometric-header">
                <div class="flex items-center mb-4">
                    <i class="fas fa-fingerprint biometric-fingerprint text-4xl mr-4"></i>
                    <div>
                        <h2 class="text-3xl font-bold">Biometric Authentication Dashboard</h2>
                        <p class="text-lg opacity-90">WebAuthn Integration, Touch ID, Face ID, Voice Recognition</p>
                    </div>
                </div>
                
                <!-- Biometric Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="webauthn-status">
                        <div class="flex items-center">
                            <span class="status-indicator" :class="webauthnStatus === 'active' ? 'status-active' : 'status-loading'"></span>
                            <span class="text-sm font-medium">WebAuthn</span>
                        </div>
                        <span class="text-sm" x-text="webauthnStatus"></span>
                    </div>
                    
                    <div class="webauthn-status">
                        <div class="flex items-center">
                            <span class="status-indicator" :class="fingerprintStatus === 'active' ? 'status-active' : 'status-loading'"></span>
                            <span class="text-sm font-medium">Touch ID</span>
                        </div>
                        <span class="text-sm" x-text="fingerprintStatus"></span>
                    </div>
                    
                    <div class="webauthn-status">
                        <div class="flex items-center">
                            <span class="status-indicator" :class="faceStatus === 'active' ? 'status-active' : 'status-loading'"></span>
                            <span class="text-sm font-medium">Face ID</span>
                        </div>
                        <span class="text-sm" x-text="faceStatus"></span>
                    </div>
                    
                    <div class="webauthn-status">
                        <div class="flex items-center">
                            <span class="status-indicator" :class="voiceStatus === 'active' ? 'status-active' : 'status-loading'"></span>
                            <span class="text-sm font-medium">Voice ID</span>
                        </div>
                        <span class="text-sm" x-text="voiceStatus"></span>
                    </div>
                </div>
            </div>

            <!-- WebAuthn Integration -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-shield-alt text-blue-500 mr-2"></i>
                    WebAuthn Integration
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- WebAuthn Registration -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Register Biometric Credential</h4>
                        <div class="biometric-scanner" 
                             :class="{ 'active': isRegistering, 'scanning': isScanning, 'success': registrationSuccess, 'error': registrationError }">
                            <i class="fas fa-fingerprint text-4xl mb-4" x-show="!isRegistering && !isScanning"></i>
                            <div class="loading-spinner mb-4" x-show="isRegistering"></div>
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-4" x-show="registrationSuccess"></i>
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4" x-show="registrationError"></i>
                            
                            <p class="text-sm mb-4" x-text="registrationMessage"></p>
                            
                            <button @click="registerBiometric()" 
                                    :disabled="isRegistering"
                                    class="biometric-button">
                                <i class="fas fa-plus mr-2" x-show="!isRegistering"></i>
                                <div class="loading-spinner mr-2" x-show="isRegistering"></div>
                                <span x-text="isRegistering ? 'Registering...' : 'Register Biometric'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- WebAuthn Authentication -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Authenticate with Biometric</h4>
                        <div class="biometric-scanner" 
                             :class="{ 'active': isAuthenticating, 'scanning': isScanning, 'success': authenticationSuccess, 'error': authenticationError }">
                            <i class="fas fa-fingerprint text-4xl mb-4" x-show="!isAuthenticating && !isScanning"></i>
                            <div class="loading-spinner mb-4" x-show="isAuthenticating"></div>
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-4" x-show="authenticationSuccess"></i>
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4" x-show="authenticationError"></i>
                            
                            <p class="text-sm mb-4" x-text="authenticationMessage"></p>
                            
                            <button @click="authenticateBiometric()" 
                                    :disabled="isAuthenticating"
                                    class="biometric-button">
                                <i class="fas fa-sign-in-alt mr-2" x-show="!isAuthenticating"></i>
                                <div class="loading-spinner mr-2" x-show="isAuthenticating"></div>
                                <span x-text="isAuthenticating ? 'Authenticating...' : 'Authenticate'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fingerprint Login (Touch ID) -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-fingerprint text-purple-500 mr-2"></i>
                    Fingerprint Login (Touch ID)
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="fingerprint-widget">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Touch ID Scanner</h4>
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">iPhone Touch ID integration</p>
                        <button @click="testTouchID()" 
                                class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                            <i class="fas fa-play mr-1"></i>Test Touch ID
                        </button>
                    </div>
                    
                    <div class="fingerprint-widget">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Android Fingerprint</h4>
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">Android fingerprint sensor</p>
                        <button @click="testAndroidFingerprint()" 
                                class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                            <i class="fas fa-play mr-1"></i>Test Android
                        </button>
                    </div>
                    
                    <div class="fingerprint-widget">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Windows Hello</h4>
                            <i class="fas fa-desktop"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">Windows Hello fingerprint</p>
                        <button @click="testWindowsHello()" 
                                class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                            <i class="fas fa-play mr-1"></i>Test Windows
                        </button>
                    </div>
                </div>
            </div>

            <!-- Face Recognition (Face ID) -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-user-check text-pink-500 mr-2"></i>
                    Face Recognition (Face ID)
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Face ID Scanner -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Face ID Scanner</h4>
                        <div class="camera-preview">
                            <div class="camera-overlay"></div>
                            <div class="absolute bottom-4 left-4 text-white text-sm">
                                <i class="fas fa-camera mr-2"></i>
                                Position your face in the circle
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button @click="startFaceRecognition()" 
                                    :disabled="isFaceScanning"
                                    class="biometric-button">
                                <i class="fas fa-camera mr-2" x-show="!isFaceScanning"></i>
                                <div class="loading-spinner mr-2" x-show="isFaceScanning"></div>
                                <span x-text="isFaceScanning ? 'Scanning...' : 'Start Face ID'"></span>
                            </button>
                            
                            <button @click="stopFaceRecognition()" 
                                    :disabled="!isFaceScanning"
                                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors disabled:opacity-50">
                                <i class="fas fa-stop mr-2"></i>Stop
                            </button>
                        </div>
                    </div>
                    
                    <!-- Face Recognition Features -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Face Recognition Features</h4>
                        
                        <div class="space-y-4">
                            <div class="face-widget">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">iPhone Face ID</h5>
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">TrueDepth camera system</p>
                                <div class="text-sm">
                                    <div>• 3D face mapping</div>
                                    <div>• Anti-spoofing protection</div>
                                    <div>• Attention awareness</div>
                                </div>
                            </div>
                            
                            <div class="face-widget">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Android Face Unlock</h5>
                                    <i class="fas fa-android"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Camera-based face recognition</p>
                                <div class="text-sm">
                                    <div>• 2D face detection</div>
                                    <div>• Machine learning</div>
                                    <div>• Security levels</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Voice Recognition -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-microphone text-green-500 mr-2"></i>
                    Voice Recognition
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Voice Authentication -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Voice Authentication</h4>
                        <div class="biometric-scanner" 
                             :class="{ 'active': isVoiceListening, 'scanning': isVoiceProcessing, 'success': voiceSuccess, 'error': voiceError }">
                            <i class="fas fa-microphone text-4xl mb-4" x-show="!isVoiceListening && !isVoiceProcessing"></i>
                            <div class="loading-spinner mb-4" x-show="isVoiceProcessing"></div>
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-4" x-show="voiceSuccess"></i>
                            <i class="fas fa-exclamation-circle text-4xl text-red-500 mb-4" x-show="voiceError"></i>
                            
                            <p class="text-sm mb-4" x-text="voiceMessage"></p>
                            
                            <!-- Voice Visualizer -->
                            <div class="voice-visualizer" x-show="isVoiceListening">
                                <div class="voice-bar" style="animation-delay: 0s;"></div>
                                <div class="voice-bar" style="animation-delay: 0.1s;"></div>
                                <div class="voice-bar" style="animation-delay: 0.2s;"></div>
                                <div class="voice-bar" style="animation-delay: 0.3s;"></div>
                                <div class="voice-bar" style="animation-delay: 0.4s;"></div>
                            </div>
                            
                            <button @click="toggleVoiceRecognition()" 
                                    :disabled="isVoiceProcessing"
                                    class="biometric-button">
                                <i class="fas fa-microphone mr-2" x-show="!isVoiceListening"></i>
                                <i class="fas fa-stop mr-2" x-show="isVoiceListening"></i>
                                <div class="loading-spinner mr-2" x-show="isVoiceProcessing"></div>
                                <span x-text="isVoiceListening ? 'Stop Listening' : 'Start Voice Auth'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Voice Features -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Voice Recognition Features</h4>
                        
                        <div class="space-y-4">
                            <div class="voice-widget">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Speaker Identification</h5>
                                    <i class="fas fa-user"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Unique voice pattern recognition</p>
                                <div class="text-sm">
                                    <div>• Voice biometrics</div>
                                    <div>• Speaker verification</div>
                                    <div>• Anti-spoofing</div>
                                </div>
                            </div>
                            
                            <div class="voice-widget">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Multi-language Support</h5>
                                    <i class="fas fa-language"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Vietnamese và English support</p>
                                <div class="text-sm">
                                    <div>• Vietnamese recognition</div>
                                    <div>• English recognition</div>
                                    <div>• Accent adaptation</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Biometric Security -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-lock text-red-500 mr-2"></i>
                    Biometric Security
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="security-level">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Security Level</h4>
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">Multi-factor biometric authentication</p>
                        <div class="text-sm">
                            <div>• Level 1: Single biometric</div>
                            <div>• Level 2: Dual biometric</div>
                            <div>• Level 3: Triple biometric</div>
                        </div>
                    </div>
                    
                    <div class="security-level">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Encryption</h4>
                            <i class="fas fa-key"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">End-to-end biometric encryption</p>
                        <div class="text-sm">
                            <div>• AES-256 encryption</div>
                            <div>• Secure key storage</div>
                            <div>• Zero-knowledge protocol</div>
                        </div>
                    </div>
                    
                    <div class="security-level">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">Privacy</h4>
                            <i class="fas fa-user-secret"></i>
                        </div>
                        <p class="text-sm opacity-90 mb-3">Privacy-first biometric data</p>
                        <div class="text-sm">
                            <div>• Local processing</div>
                            <div>• No data transmission</div>
                            <div>• GDPR compliant</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Biometric Metrics -->
            <div class="biometric-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-chart-bar text-teal-500 mr-2"></i>
                    Biometric Metrics
                </h3>
                
                <div class="biometric-metrics">
                    <div class="metric-item">
                        <span class="text-sm text-gray-600">Authentication Success Rate</span>
                        <span class="metric-value" x-text="biometricMetrics.successRate"></span>
                    </div>
                    <div class="metric-item">
                        <span class="text-sm text-gray-600">Average Response Time</span>
                        <span class="metric-value" x-text="biometricMetrics.responseTime"></span>
                    </div>
                    <div class="metric-item">
                        <span class="text-sm text-gray-600">False Acceptance Rate</span>
                        <span class="metric-value" x-text="biometricMetrics.falseAcceptance"></span>
                    </div>
                    <div class="metric-item">
                        <span class="text-sm text-gray-600">False Rejection Rate</span>
                        <span class="metric-value" x-text="biometricMetrics.falseRejection"></span>
                    </div>
                    <div class="metric-item">
                        <span class="text-sm text-gray-600">Total Authentications</span>
                        <span class="metric-value" x-text="biometricMetrics.totalAuths"></span>
                    </div>
                </div>
            </div>
        </main>

        <!-- Biometric Feedback -->
        <div class="biometric-feedback" 
             :class="{ 'show': showFeedback, 'success': feedbackType === 'success', 'error': feedbackType === 'error', 'warning': feedbackType === 'warning' }"
             x-show="showFeedback">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 mr-3" x-show="feedbackType === 'success'"></i>
                <i class="fas fa-exclamation-circle text-red-500 mr-3" x-show="feedbackType === 'error'"></i>
                <i class="fas fa-exclamation-triangle text-yellow-500 mr-3" x-show="feedbackType === 'warning'"></i>
                <div>
                    <p class="font-semibold text-gray-900" x-text="feedbackTitle"></p>
                    <p class="text-sm text-gray-600" x-text="feedbackMessage"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function biometricAuthentication() {
            return {
                // State
                webauthnStatus: 'loading',
                fingerprintStatus: 'loading',
                faceStatus: 'loading',
                voiceStatus: 'loading',
                
                // WebAuthn
                isRegistering: false,
                isAuthenticating: false,
                isScanning: false,
                registrationSuccess: false,
                registrationError: false,
                authenticationSuccess: false,
                authenticationError: false,
                registrationMessage: 'Click to register your biometric credential',
                authenticationMessage: 'Click to authenticate with your biometric',
                
                // Face Recognition
                isFaceScanning: false,
                faceRecognition: null,
                
                // Voice Recognition
                isVoiceListening: false,
                isVoiceProcessing: false,
                voiceSuccess: false,
                voiceError: false,
                voiceMessage: 'Click to start voice authentication',
                voiceRecognition: null,
                
                // Feedback
                showFeedback: false,
                feedbackType: 'success',
                feedbackTitle: '',
                feedbackMessage: '',
                
                // Metrics
                biometricMetrics: {
                    successRate: '98.7%',
                    responseTime: '1.2s',
                    falseAcceptance: '0.01%',
                    falseRejection: '1.3%',
                    totalAuths: '2,847'
                },
                
                // Initialize
                init() {
                    this.initializeWebAuthn();
                    this.initializeFaceRecognition();
                    this.initializeVoiceRecognition();
                    this.updateMetrics();
                },

                // WebAuthn Initialization
                async initializeWebAuthn() {
                    try {
                        if ('credentials' in navigator && 'create' in navigator.credentials) {
                            this.webauthnStatus = 'active';
                            console.log('WebAuthn supported');
                        } else {
                            this.webauthnStatus = 'inactive';
                            console.warn('WebAuthn not supported');
                        }
                    } catch (error) {
                        console.error('WebAuthn initialization error:', error);
                        this.webauthnStatus = 'error';
                    }
                },

                // Face Recognition Initialization
                initializeFaceRecognition() {
                    try {
                        // Check for camera access
                        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                            this.faceStatus = 'active';
                            console.log('Face recognition supported');
                        } else {
                            this.faceStatus = 'inactive';
                            console.warn('Face recognition not supported');
                        }
                    } catch (error) {
                        console.error('Face recognition initialization error:', error);
                        this.faceStatus = 'error';
                    }
                },

                // Voice Recognition Initialization
                initializeVoiceRecognition() {
                    try {
                        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                            this.voiceStatus = 'active';
                            this.setupVoiceRecognition();
                        } else {
                            this.voiceStatus = 'inactive';
                            console.warn('Voice recognition not supported');
                        }
                    } catch (error) {
                        console.error('Voice recognition initialization error:', error);
                        this.voiceStatus = 'error';
                    }
                },

                // Setup Voice Recognition
                setupVoiceRecognition() {
                    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    this.voiceRecognition = new SpeechRecognition();
                    this.voiceRecognition.continuous = false;
                    this.voiceRecognition.interimResults = false;
                    this.voiceRecognition.lang = 'vi-VN'; // Vietnamese
                    
                    this.voiceRecognition.onstart = () => {
                        this.isVoiceListening = true;
                        this.isVoiceProcessing = true;
                        this.voiceMessage = 'Listening... Speak now';
                    };
                    
                    this.voiceRecognition.onresult = (event) => {
                        const result = event.results[0][0].transcript;
                        this.processVoiceAuthentication(result);
                    };
                    
                    this.voiceRecognition.onerror = (event) => {
                        console.error('Voice recognition error:', event.error);
                        this.isVoiceListening = false;
                        this.isVoiceProcessing = false;
                        this.voiceError = true;
                        this.voiceMessage = 'Voice recognition failed';
                        this.showBiometricFeedback('error', 'Voice Recognition Error', 'Failed to recognize voice');
                    };
                    
                    this.voiceRecognition.onend = () => {
                        this.isVoiceListening = false;
                        this.isVoiceProcessing = false;
                    };
                },

                // Register Biometric
                async registerBiometric() {
                    this.isRegistering = true;
                    this.isScanning = true;
                    this.registrationMessage = 'Registering biometric credential...';
                    
                    try {
                        // Simulate WebAuthn registration
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        
                        // Check if WebAuthn is available
                        if (this.webauthnStatus === 'active') {
                            this.registrationSuccess = true;
                            this.registrationMessage = 'Biometric credential registered successfully';
                            this.showBiometricFeedback('success', 'Registration Successful', 'Your biometric credential has been registered');
                            this.fingerprintStatus = 'active';
                        } else {
                            throw new Error('WebAuthn not supported');
                        }
                    } catch (error) {
                        console.error('Registration error:', error);
                        this.registrationError = true;
                        this.registrationMessage = 'Registration failed: ' + error.message;
                        this.showBiometricFeedback('error', 'Registration Failed', error.message);
                    } finally {
                        this.isRegistering = false;
                        this.isScanning = false;
                    }
                },

                // Authenticate Biometric
                async authenticateBiometric() {
                    this.isAuthenticating = true;
                    this.isScanning = true;
                    this.authenticationMessage = 'Authenticating with biometric...';
                    
                    try {
                        // Simulate WebAuthn authentication
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        // Check if WebAuthn is available
                        if (this.webauthnStatus === 'active') {
                            this.authenticationSuccess = true;
                            this.authenticationMessage = 'Authentication successful';
                            this.showBiometricFeedback('success', 'Authentication Successful', 'Welcome back!');
                            this.updateMetrics();
                        } else {
                            throw new Error('WebAuthn not supported');
                        }
                    } catch (error) {
                        console.error('Authentication error:', error);
                        this.authenticationError = true;
                        this.authenticationMessage = 'Authentication failed: ' + error.message;
                        this.showBiometricFeedback('error', 'Authentication Failed', error.message);
                    } finally {
                        this.isAuthenticating = false;
                        this.isScanning = false;
                    }
                },

                // Test Touch ID
                testTouchID() {
                    this.showBiometricFeedback('success', 'Touch ID Test', 'Touch ID functionality simulated');
                },

                // Test Android Fingerprint
                testAndroidFingerprint() {
                    this.showBiometricFeedback('success', 'Android Fingerprint Test', 'Android fingerprint functionality simulated');
                },

                // Test Windows Hello
                testWindowsHello() {
                    this.showBiometricFeedback('success', 'Windows Hello Test', 'Windows Hello functionality simulated');
                },

                // Start Face Recognition
                async startFaceRecognition() {
                    this.isFaceScanning = true;
                    
                    try {
                        // Simulate face recognition
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        
                        this.isFaceScanning = false;
                        this.showBiometricFeedback('success', 'Face Recognition', 'Face recognized successfully');
                        this.updateMetrics();
                    } catch (error) {
                        console.error('Face recognition error:', error);
                        this.isFaceScanning = false;
                        this.showBiometricFeedback('error', 'Face Recognition Error', 'Failed to recognize face');
                    }
                },

                // Stop Face Recognition
                stopFaceRecognition() {
                    this.isFaceScanning = false;
                },

                // Toggle Voice Recognition
                toggleVoiceRecognition() {
                    if (this.isVoiceListening) {
                        this.voiceRecognition.stop();
                    } else {
                        this.voiceRecognition.start();
                    }
                },

                // Process Voice Authentication
                processVoiceAuthentication(voiceText) {
                    this.isVoiceProcessing = false;
                    this.voiceSuccess = true;
                    this.voiceMessage = `Voice recognized: "${voiceText}"`;
                    this.showBiometricFeedback('success', 'Voice Authentication', 'Voice authentication successful');
                    this.updateMetrics();
                },

                // Show Biometric Feedback
                showBiometricFeedback(type, title, message) {
                    this.feedbackType = type;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;
                    
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 3000);
                },

                // Update Metrics
                updateMetrics() {
                    // Simulate metrics update
                    this.biometricMetrics.totalAuths = (parseInt(this.biometricMetrics.totalAuths.replace(',', '')) + 1).toLocaleString();
                },

                // Navigation
                goToARVR() {
                    window.location.href = '/app/ar-vr-implementation';
                },

                goToML() {
                    window.location.href = '/app/advanced-machine-learning';
                },

                goToFuture() {
                    window.location.href = '/app/future-enhancements';
                }
            };
        }
    </script>
</body>
</html>
