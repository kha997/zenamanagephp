<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Integration - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="IoT Integration with Internet of Things connectivity, Sensor Data Processing, Device Management, and Edge Computing">
    <meta name="theme-color" content="#ff6b6b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.json">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/css/design-system.css" rel="stylesheet">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Chart.js for IoT visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <!-- MQTT.js for IoT connectivity -->
    <script src="https://cdn.jsdelivr.net/npm/mqtt@5.3.0/dist/mqtt.min.js"></script>
    
    <style>
        /* IoT Dashboard Styles */
        .iot-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .iot-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0;
        }
        
        .iot-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #ff6b6b;
        }
        
        .device-card {
            background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .sensor-data {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .edge-computing {
            background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .iot-visualization {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            min-height: 300px;
        }
        
        .device-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background: #00b894; animation: pulse 2s infinite; }
        .status-offline { background: #636e72; }
        .status-error { background: #e17055; }
        .status-loading { background: #74b9ff; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .iot-button {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .iot-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .iot-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        .iot-feedback {
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
        
        .iot-feedback.show {
            transform: translateX(0);
        }
        
        .iot-feedback.success {
            border-left: 4px solid #00b894;
        }
        
        .iot-feedback.error {
            border-left: 4px solid #e17055;
        }
        
        .iot-feedback.warning {
            border-left: 4px solid #fdcb6e;
        }
        
        .data-stream {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .stream-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .stream-item:last-child {
            border-bottom: none;
        }
        
        .device-metrics {
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
            color: #ff6b6b;
        }
        
        .sensor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .sensor-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #00b894;
        }
        
        .edge-node {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .edge-indicator {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #00b894;
            border-radius: 50%;
            animation: edgePulse 2s infinite;
        }
        
        @keyframes edgePulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.7; }
        }
        
        .mqtt-status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased iot-container">
    <div x-data="iotIntegration()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-microchip text-red-600 mr-2"></i>
                                IoT Integration
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="flex items-center space-x-4">
                        <button @click="goToML()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-brain mr-2"></i>ML
                        </button>
                        <button @click="goToBlockchain()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-link mr-2"></i>Blockchain
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
            <!-- IoT Header -->
            <div class="iot-header">
                <div class="flex items-center mb-4">
                    <i class="fas fa-microchip text-4xl mr-4"></i>
                    <div>
                        <h2 class="text-3xl font-bold">IoT Integration Dashboard</h2>
                        <p class="text-lg opacity-90">Internet of Things, Sensor Data, Device Management, Edge Computing</p>
                    </div>
                </div>
                
                <!-- IoT Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="iot-visualization">
                        <div class="flex items-center">
                            <span class="device-status" :class="mqttStatus === 'connected' ? 'status-online' : 'status-offline'"></span>
                            <span class="text-sm font-medium">MQTT Broker</span>
                        </div>
                        <span class="text-sm" x-text="mqttStatus"></span>
                    </div>
                    
                    <div class="iot-visualization">
                        <div class="flex items-center">
                            <span class="device-status" :class="sensorStatus === 'active' ? 'status-online' : 'status-offline'"></span>
                            <span class="text-sm font-medium">Sensors</span>
                        </div>
                        <span class="text-sm" x-text="sensorStatus"></span>
                    </div>
                    
                    <div class="iot-visualization">
                        <div class="flex items-center">
                            <span class="device-status" :class="deviceStatus === 'active' ? 'status-online' : 'status-offline'"></span>
                            <span class="text-sm font-medium">Devices</span>
                        </div>
                        <span class="text-sm" x-text="deviceStatus"></span>
                    </div>
                    
                    <div class="iot-visualization">
                        <div class="flex items-center">
                            <span class="device-status" :class="edgeStatus === 'active' ? 'status-online' : 'status-offline'"></span>
                            <span class="text-sm font-medium">Edge Nodes</span>
                        </div>
                        <span class="text-sm" x-text="edgeStatus"></span>
                    </div>
                </div>
            </div>

            <!-- IoT Connectivity -->
            <div class="iot-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-wifi text-red-500 mr-2"></i>
                    IoT Connectivity
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- MQTT Broker -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">MQTT Broker Connection</h4>
                        <div class="mqtt-status">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Broker Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="mqttStatus === 'connected' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="mqttStatus === 'connected' ? 'Connected' : 'Disconnected'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Broker URL</label>
                                <input type="text" 
                                       x-model="mqttConfig.brokerUrl" 
                                       placeholder="mqtt://localhost:1883"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Client ID</label>
                                <input type="text" 
                                       x-model="mqttConfig.clientId" 
                                       placeholder="zena-iot-client"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                            </div>
                            
                            <button @click="toggleMQTTConnection()" 
                                    class="iot-button">
                                <i class="fas fa-plug mr-2" x-show="mqttStatus !== 'connected'"></i>
                                <i class="fas fa-unlink mr-2" x-show="mqttStatus === 'connected'"></i>
                                <span x-text="mqttStatus === 'connected' ? 'Disconnect' : 'Connect'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- IoT Protocols -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">IoT Protocols</h4>
                        <div class="space-y-3">
                            <div class="device-card">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">MQTT</h5>
                                    <i class="fas fa-broadcast-tower"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Message Queuing Telemetry Transport</p>
                                <div class="text-sm">
                                    <div>• Lightweight messaging</div>
                                    <div>• QoS levels</div>
                                    <div>• Retained messages</div>
                                </div>
                            </div>
                            
                            <div class="device-card">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">CoAP</h5>
                                    <i class="fas fa-satellite"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Constrained Application Protocol</p>
                                <div class="text-sm">
                                    <div>• RESTful protocol</div>
                                    <div>• UDP-based</div>
                                    <div>• Low overhead</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sensor Data Processing -->
            <div class="iot-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-thermometer-half text-green-500 mr-2"></i>
                    Sensor Data Processing
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Sensor Grid -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Sensor Data Stream</h4>
                        <div class="sensor-grid">
                            <div class="sensor-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Temperature</h5>
                                    <i class="fas fa-thermometer-half text-red-500"></i>
                                </div>
                                <div class="text-2xl font-bold text-gray-900" x-text="sensorData.temperature + '°C'"></div>
                                <div class="text-sm text-gray-600">Last updated: <span x-text="sensorData.temperatureTime"></span></div>
                            </div>
                            
                            <div class="sensor-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Humidity</h5>
                                    <i class="fas fa-tint text-blue-500"></i>
                                </div>
                                <div class="text-2xl font-bold text-gray-900" x-text="sensorData.humidity + '%'"></div>
                                <div class="text-sm text-gray-600">Last updated: <span x-text="sensorData.humidityTime"></span></div>
                            </div>
                            
                            <div class="sensor-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Pressure</h5>
                                    <i class="fas fa-compress text-purple-500"></i>
                                </div>
                                <div class="text-2xl font-bold text-gray-900" x-text="sensorData.pressure + ' hPa'"></div>
                                <div class="text-sm text-gray-600">Last updated: <span x-text="sensorData.pressureTime"></span></div>
                            </div>
                            
                            <div class="sensor-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Light</h5>
                                    <i class="fas fa-sun text-yellow-500"></i>
                                </div>
                                <div class="text-2xl font-bold text-gray-900" x-text="sensorData.light + ' lux'"></div>
                                <div class="text-sm text-gray-600">Last updated: <span x-text="sensorData.lightTime"></span></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Data Processing -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Real-time Data Processing</h4>
                        <div class="sensor-data">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Processing Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="isProcessing ? 'text-green-300' : 'text-red-300'" 
                                          x-text="isProcessing ? 'Processing' : 'Stopped'"></span>
                                </div>
                            </div>
                            
                            <div class="data-stream">
                                <div x-show="processedData.length === 0" class="text-center text-sm opacity-75">
                                    No processed data yet
                                </div>
                                <template x-for="item in processedData" :key="item.id">
                                    <div class="stream-item">
                                        <div>
                                            <span class="text-sm font-medium" x-text="item.timestamp"></span>
                                            <span class="text-sm opacity-75 ml-2" x-text="item.sensor"></span>
                                        </div>
                                        <div class="text-sm" x-text="item.value"></div>
                                    </div>
                                </template>
                            </div>
                            
                            <button @click="toggleProcessing()" 
                                    class="iot-button">
                                <i class="fas fa-play mr-2" x-show="!isProcessing"></i>
                                <i class="fas fa-stop mr-2" x-show="isProcessing"></i>
                                <span x-text="isProcessing ? 'Stop Processing' : 'Start Processing'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IoT Device Management -->
            <div class="iot-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-cogs text-blue-500 mr-2"></i>
                    IoT Device Management
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Device List -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Connected Devices</h4>
                        <div class="space-y-3">
                            <template x-for="device in deviceList" :key="device.id">
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h5 class="font-semibold text-gray-900" x-text="device.name"></h5>
                                        <span class="device-status" :class="'status-' + device.status"></span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-2" x-text="device.description"></p>
                                    <div class="text-sm text-gray-500 mb-3">
                                        <div>Type: <span x-text="device.type"></span></div>
                                        <div>IP: <span x-text="device.ip"></span></div>
                                        <div>Last Seen: <span x-text="device.lastSeen"></span></div>
                                    </div>
                                    <div class="flex space-x-2">
                                        <button @click="configureDevice(device.id)" 
                                                class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600 transition-colors">
                                            <i class="fas fa-cog mr-1"></i>Configure
                                        </button>
                                        <button @click="removeDevice(device.id)" 
                                                class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600 transition-colors">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    
                    <!-- Device Metrics -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Device Metrics</h4>
                        <div class="device-metrics">
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Total Devices</span>
                                <span class="metric-value" x-text="deviceMetrics.totalDevices"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Online Devices</span>
                                <span class="metric-value" x-text="deviceMetrics.onlineDevices"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Offline Devices</span>
                                <span class="metric-value" x-text="deviceMetrics.offlineDevices"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Data Points/Min</span>
                                <span class="metric-value" x-text="deviceMetrics.dataPointsPerMinute"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Average Latency</span>
                                <span class="metric-value" x-text="deviceMetrics.averageLatency"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edge Computing -->
            <div class="iot-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-server text-orange-500 mr-2"></i>
                    Edge Computing
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Edge Nodes -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Edge Computing Nodes</h4>
                        <div class="space-y-3">
                            <div class="edge-computing">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Edge Node 1</h5>
                                    <div class="edge-indicator"></div>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Local ML processing</p>
                                <div class="text-sm mb-3">
                                    <div>CPU Usage: <span x-text="edgeNodes.node1.cpuUsage"></span></div>
                                    <div>Memory: <span x-text="edgeNodes.node1.memoryUsage"></span></div>
                                    <div>ML Models: <span x-text="edgeNodes.node1.mlModels"></span></div>
                                </div>
                                <button @click="deployToEdge('node1')" 
                                        class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                                    <i class="fas fa-upload mr-1"></i>Deploy ML Model
                                </button>
                            </div>
                            
                            <div class="edge-computing">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Edge Node 2</h5>
                                    <div class="edge-indicator"></div>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Real-time analytics</p>
                                <div class="text-sm mb-3">
                                    <div>CPU Usage: <span x-text="edgeNodes.node2.cpuUsage"></span></div>
                                    <div>Memory: <span x-text="edgeNodes.node2.memoryUsage"></span></div>
                                    <div>ML Models: <span x-text="edgeNodes.node2.mlModels"></span></div>
                                </div>
                                <button @click="deployToEdge('node2')" 
                                        class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                                    <i class="fas fa-upload mr-1"></i>Deploy ML Model
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Edge Processing -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Edge Processing Status</h4>
                        <div class="edge-node">
                            <div class="text-center text-white">
                                <div class="text-lg font-semibold mb-4">Edge Processing Pipeline</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Data Ingestion</div>
                                        <div class="edge-indicator" style="top: 20px; left: 50px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">ML Processing</div>
                                        <div class="edge-indicator" style="top: 20px; left: 150px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Result Output</div>
                                        <div class="edge-indicator" style="top: 20px; left: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-sm">
                                <div>Processing Rate: <span x-text="edgeProcessing.processingRate"></span></div>
                                <div>Model Accuracy: <span x-text="edgeProcessing.modelAccuracy"></span></div>
                                <div>Latency: <span x-text="edgeProcessing.latency"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- IoT Feedback -->
        <div class="iot-feedback" 
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
        function iotIntegration() {
            return {
                // State
                mqttStatus: 'disconnected',
                sensorStatus: 'inactive',
                deviceStatus: 'inactive',
                edgeStatus: 'inactive',
                
                // MQTT Configuration
                mqttConfig: {
                    brokerUrl: 'mqtt://localhost:1883',
                    clientId: 'zena-iot-client'
                },
                mqttClient: null,
                
                // Sensor Data
                sensorData: {
                    temperature: 23.5,
                    humidity: 65.2,
                    pressure: 1013.25,
                    light: 450,
                    temperatureTime: '12:34:56',
                    humidityTime: '12:34:56',
                    pressureTime: '12:34:56',
                    lightTime: '12:34:56'
                },
                
                // Data Processing
                isProcessing: false,
                processedData: [],
                
                // Device Management
                deviceList: [
                    {
                        id: 1,
                        name: 'Temperature Sensor 1',
                        description: 'DHT22 temperature and humidity sensor',
                        type: 'Sensor',
                        ip: '192.168.1.100',
                        status: 'online',
                        lastSeen: '2 minutes ago'
                    },
                    {
                        id: 2,
                        name: 'Motion Detector 1',
                        description: 'PIR motion detection sensor',
                        type: 'Sensor',
                        ip: '192.168.1.101',
                        status: 'online',
                        lastSeen: '1 minute ago'
                    },
                    {
                        id: 3,
                        name: 'Smart Light 1',
                        description: 'WiFi-enabled smart light bulb',
                        type: 'Actuator',
                        ip: '192.168.1.102',
                        status: 'offline',
                        lastSeen: '15 minutes ago'
                    }
                ],
                deviceMetrics: {
                    totalDevices: 3,
                    onlineDevices: 2,
                    offlineDevices: 1,
                    dataPointsPerMinute: 150,
                    averageLatency: '25ms'
                },
                
                // Edge Computing
                edgeNodes: {
                    node1: {
                        cpuUsage: '45%',
                        memoryUsage: '2.1GB',
                        mlModels: 3
                    },
                    node2: {
                        cpuUsage: '32%',
                        memoryUsage: '1.8GB',
                        mlModels: 2
                    }
                },
                edgeProcessing: {
                    processingRate: '150 req/min',
                    modelAccuracy: '94.2%',
                    latency: '12ms'
                },
                
                // Feedback
                showFeedback: false,
                feedbackType: 'success',
                feedbackTitle: '',
                feedbackMessage: '',
                
                // Initialize
                init() {
                    this.initializeMQTT();
                    this.initializeSensors();
                    this.initializeDevices();
                    this.initializeEdgeComputing();
                    this.startSensorDataSimulation();
                },

                // MQTT Initialization
                initializeMQTT() {
                    try {
                        if (typeof mqtt !== 'undefined') {
                            console.log('MQTT.js loaded successfully');
                        } else {
                            console.warn('MQTT.js not loaded');
                        }
                    } catch (error) {
                        console.error('MQTT initialization error:', error);
                    }
                },

                // Sensor Initialization
                initializeSensors() {
                    this.sensorStatus = 'active';
                    console.log('Sensors initialized');
                },

                // Device Initialization
                initializeDevices() {
                    this.deviceStatus = 'active';
                    console.log('Devices initialized');
                },

                // Edge Computing Initialization
                initializeEdgeComputing() {
                    this.edgeStatus = 'active';
                    console.log('Edge computing initialized');
                },

                // Toggle MQTT Connection
                async toggleMQTTConnection() {
                    if (this.mqttStatus === 'connected') {
                        this.disconnectMQTT();
                    } else {
                        await this.connectMQTT();
                    }
                },

                // Connect MQTT
                async connectMQTT() {
                    try {
                        // Simulate MQTT connection
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        this.mqttStatus = 'connected';
                        this.showIoTFeedback('success', 'MQTT Connected', 'Successfully connected to MQTT broker');
                        
                        // Start receiving messages
                        this.startMQTTMessageSimulation();
                    } catch (error) {
                        console.error('MQTT connection error:', error);
                        this.showIoTFeedback('error', 'MQTT Connection Failed', error.message);
                    }
                },

                // Disconnect MQTT
                disconnectMQTT() {
                    this.mqttStatus = 'disconnected';
                    this.showIoTFeedback('warning', 'MQTT Disconnected', 'Disconnected from MQTT broker');
                },

                // Start MQTT Message Simulation
                startMQTTMessageSimulation() {
                    if (this.mqttMessageInterval) {
                        clearInterval(this.mqttMessageInterval);
                    }
                    
                    this.mqttMessageInterval = setInterval(() => {
                        if (this.mqttStatus === 'connected') {
                            // Simulate receiving MQTT messages
                            console.log('Received MQTT message');
                        }
                    }, 5000);
                },

                // Start Sensor Data Simulation
                startSensorDataSimulation() {
                    setInterval(() => {
                        // Simulate sensor data updates
                        this.sensorData.temperature = (20 + Math.random() * 10).toFixed(1);
                        this.sensorData.humidity = (50 + Math.random() * 30).toFixed(1);
                        this.sensorData.pressure = (1000 + Math.random() * 50).toFixed(2);
                        this.sensorData.light = Math.floor(200 + Math.random() * 800);
                        
                        const now = new Date().toLocaleTimeString();
                        this.sensorData.temperatureTime = now;
                        this.sensorData.humidityTime = now;
                        this.sensorData.pressureTime = now;
                        this.sensorData.lightTime = now;
                    }, 3000);
                },

                // Toggle Processing
                toggleProcessing() {
                    if (this.isProcessing) {
                        this.stopProcessing();
                    } else {
                        this.startProcessing();
                    }
                },

                // Start Processing
                startProcessing() {
                    this.isProcessing = true;
                    
                    // Simulate data processing
                    this.processingInterval = setInterval(() => {
                        const timestamp = new Date().toLocaleTimeString();
                        const sensors = ['Temperature', 'Humidity', 'Pressure', 'Light'];
                        const sensor = sensors[Math.floor(Math.random() * sensors.length)];
                        const value = (Math.random() * 100).toFixed(2);
                        
                        this.processedData.unshift({
                            id: Date.now(),
                            timestamp,
                            sensor,
                            value
                        });
                        
                        // Keep only last 10 items
                        if (this.processedData.length > 10) {
                            this.processedData.pop();
                        }
                    }, 2000);
                },

                // Stop Processing
                stopProcessing() {
                    this.isProcessing = false;
                    if (this.processingInterval) {
                        clearInterval(this.processingInterval);
                    }
                },

                // Configure Device
                configureDevice(deviceId) {
                    const device = this.deviceList.find(d => d.id === deviceId);
                    if (device) {
                        this.showIoTFeedback('success', 'Device Configuration', `Configuring device "${device.name}"`);
                    }
                },

                // Remove Device
                removeDevice(deviceId) {
                    const deviceIndex = this.deviceList.findIndex(d => d.id === deviceId);
                    if (deviceIndex !== -1) {
                        const device = this.deviceList[deviceIndex];
                        this.deviceList.splice(deviceIndex, 1);
                        this.deviceMetrics.totalDevices--;
                        if (device.status === 'online') {
                            this.deviceMetrics.onlineDevices--;
                        } else {
                            this.deviceMetrics.offlineDevices--;
                        }
                        this.showIoTFeedback('success', 'Device Removed', `Device "${device.name}" removed successfully`);
                    }
                },

                // Deploy to Edge
                deployToEdge(nodeId) {
                    this.showIoTFeedback('success', 'Edge Deployment', `ML model deployed to ${nodeId} successfully`);
                },

                // Show IoT Feedback
                showIoTFeedback(type, title, message) {
                    this.feedbackType = type;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;
                    
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 3000);
                },

                // Navigation
                goToML() {
                    window.location.href = '/app/advanced-machine-learning';
                },

                goToBlockchain() {
                    window.location.href = '/app/blockchain-integration';
                },

                goToFuture() {
                    window.location.href = '/app/future-enhancements';
                }
            };
        }
    </script>
</body>
</html>
