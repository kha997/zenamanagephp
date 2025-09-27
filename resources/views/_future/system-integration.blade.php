<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Integration - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="System Integration with Enterprise Integration, API Gateway, Microservices Architecture, and System Monitoring">
    <meta name="theme-color" content="#8e44ad">
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
    
    <!-- Chart.js for system visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <!-- D3.js for advanced visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/d3@7.8.5/dist/d3.min.js"></script>
    
    <style>
        /* System Integration Styles */
        .integration-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .integration-header {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0;
        }
        
        .integration-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #8e44ad;
        }
        
        .integration-card {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .integration-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .api-gateway {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .microservice {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .system-visualization {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            min-height: 300px;
        }
        
        .service-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-running { background: #27ae60; animation: pulse 2s infinite; }
        .status-stopped { background: #e74c3c; }
        .status-warning { background: #f39c12; }
        .status-loading { background: #95a5a6; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .integration-button {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .integration-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .integration-button:disabled {
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
        
        .integration-feedback {
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
        
        .integration-feedback.show {
            transform: translateX(0);
        }
        
        .integration-feedback.success {
            border-left: 4px solid #27ae60;
        }
        
        .integration-feedback.error {
            border-left: 4px solid #e74c3c;
        }
        
        .integration-feedback.warning {
            border-left: 4px solid #f39c12;
        }
        
        .service-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .service-item:last-child {
            border-bottom: none;
        }
        
        .system-metrics {
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
            color: #8e44ad;
        }
        
        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .api-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #e67e22;
        }
        
        .microservice-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .microservice-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #27ae60;
        }
        
        .system-flow {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .flow-indicator {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #8e44ad;
            border-radius: 50%;
            animation: flowPulse 2s infinite;
        }
        
        @keyframes flowPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.7; }
        }
        
        .monitoring-status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .integration-level {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .service-health {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .api-endpoint {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            font-family: monospace;
            font-size: 14px;
        }
        
        .endpoint-method {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .method-get { background: #27ae60; color: white; }
        .method-post { background: #3498db; color: white; }
        .method-put { background: #f39c12; color: white; }
        .method-delete { background: #e74c3c; color: white; }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased integration-container">
    <div x-data="systemIntegration()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-plug text-purple-600 mr-2"></i>
                                System Integration
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="flex items-center space-x-4">
                        <button @click="goToSecurity()" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-shield-alt mr-2"></i>Security
                        </button>
                        <button @click="goToFinal()" 
                                class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                            <i class="fas fa-trophy mr-2"></i>Final
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
            <!-- Integration Header -->
            <div class="integration-header">
                <div class="flex items-center mb-4">
                    <i class="fas fa-plug text-4xl mr-4"></i>
                    <div>
                        <h2 class="text-3xl font-bold">System Integration Dashboard</h2>
                        <p class="text-lg opacity-90">Enterprise Integration, API Gateway, Microservices Architecture, System Monitoring</p>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="system-visualization">
                        <div class="flex items-center">
                            <span class="service-status" :class="integrationLevel === 'high' ? 'status-running' : 'status-warning'"></span>
                            <span class="text-sm font-medium">Integration Level</span>
                        </div>
                        <span class="text-sm" x-text="integrationLevel"></span>
                    </div>
                    
                    <div class="system-visualization">
                        <div class="flex items-center">
                            <span class="service-status" :class="apiGatewayStatus === 'active' ? 'status-running' : 'status-loading'"></span>
                            <span class="text-sm font-medium">API Gateway</span>
                        </div>
                        <span class="text-sm" x-text="apiGatewayStatus"></span>
                    </div>
                    
                    <div class="system-visualization">
                        <div class="flex items-center">
                            <span class="service-status" :class="microservicesStatus === 'active' ? 'status-running' : 'status-loading'"></span>
                            <span class="text-sm font-medium">Microservices</span>
                        </div>
                        <span class="text-sm" x-text="microservicesStatus"></span>
                    </div>
                    
                    <div class="system-visualization">
                        <div class="flex items-center">
                            <span class="service-status" :class="monitoringStatus === 'active' ? 'status-running' : 'status-warning'"></span>
                            <span class="text-sm font-medium">Monitoring</span>
                        </div>
                        <span class="text-sm" x-text="monitoringStatus"></span>
                    </div>
                </div>
            </div>

            <!-- Enterprise Integration -->
            <div class="integration-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-building text-purple-500 mr-2"></i>
                    Enterprise Integration
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Integration Services -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Integration Services</h4>
                        <div class="integration-grid">
                            <div class="api-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">ERP Systems</h5>
                                    <i class="fas fa-database text-blue-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Enterprise Resource Planning</p>
                                <div class="text-sm">
                                    <div>• SAP Integration</div>
                                    <div>• Oracle ERP</div>
                                    <div>• Microsoft Dynamics</div>
                                </div>
                            </div>
                            
                            <div class="api-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">CRM Systems</h5>
                                    <i class="fas fa-users text-green-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Customer Relationship Management</p>
                                <div class="text-sm">
                                    <div>• Salesforce</div>
                                    <div>• HubSpot</div>
                                    <div>• Pipedrive</div>
                                </div>
                            </div>
                            
                            <div class="api-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">HR Systems</h5>
                                    <i class="fas fa-user-tie text-purple-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Human Resources Management</p>
                                <div class="text-sm">
                                    <div>• Workday</div>
                                    <div>• BambooHR</div>
                                    <div>• ADP</div>
                                </div>
                            </div>
                            
                            <div class="api-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Financial Systems</h5>
                                    <i class="fas fa-chart-line text-orange-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Financial Management</p>
                                <div class="text-sm">
                                    <div>• QuickBooks</div>
                                    <div>• Xero</div>
                                    <div>• Sage</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Integration Monitoring -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Integration Monitoring</h4>
                        <div class="integration-card">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Integration Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="integrationStatus ? 'text-green-300' : 'text-red-300'" 
                                          x-text="integrationStatus ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="service-list">
                                <div x-show="integrations.length === 0" class="text-center text-sm opacity-75">
                                    No integrations configured
                                </div>
                                <template x-for="integration in integrations" :key="integration.id">
                                    <div class="service-item">
                                        <div>
                                            <span class="text-sm font-medium" x-text="integration.name"></span>
                                            <span class="text-sm opacity-75 ml-2" x-text="integration.type"></span>
                                        </div>
                                        <div class="text-sm" x-text="integration.status"></div>
                                    </div>
                                </template>
                            </div>
                            
                            <button @click="toggleIntegration()" 
                                    class="integration-button">
                                <i class="fas fa-plug mr-2"></i>
                                <span x-text="integrationStatus ? 'Disable Integration' : 'Enable Integration'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Gateway -->
            <div class="integration-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-server text-orange-500 mr-2"></i>
                    API Gateway
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- API Management -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">API Management</h4>
                        <div class="api-gateway">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Gateway Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="apiGatewayStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="apiGatewayStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Endpoint</label>
                                <input type="text" 
                                       x-model="apiEndpoint" 
                                       placeholder="https://api.zenamanage.com"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Rate Limit</label>
                                <input type="number" 
                                       x-model="rateLimit" 
                                       min="1" 
                                       max="10000"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <button @click="configureAPIGateway()" 
                                    :disabled="isConfiguringGateway"
                                    class="integration-button">
                                <i class="fas fa-cog mr-2" x-show="!isConfiguringGateway"></i>
                                <div class="loading-spinner mr-2" x-show="isConfiguringGateway"></div>
                                <span x-text="isConfiguringGateway ? 'Configuring...' : 'Configure Gateway'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- API Endpoints -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">API Endpoints</h4>
                        <div class="api-grid">
                            <div class="api-item">
                                <div class="api-endpoint">
                                    <span class="endpoint-method method-get">GET</span>
                                    <span>/api/v1/projects</span>
                                </div>
                                <div class="text-sm text-gray-600">List all projects</div>
                            </div>
                            
                            <div class="api-item">
                                <div class="api-endpoint">
                                    <span class="endpoint-method method-post">POST</span>
                                    <span>/api/v1/projects</span>
                                </div>
                                <div class="text-sm text-gray-600">Create new project</div>
                            </div>
                            
                            <div class="api-item">
                                <div class="api-endpoint">
                                    <span class="endpoint-method method-put">PUT</span>
                                    <span>/api/v1/projects/{id}</span>
                                </div>
                                <div class="text-sm text-gray-600">Update project</div>
                            </div>
                            
                            <div class="api-item">
                                <div class="api-endpoint">
                                    <span class="endpoint-method method-delete">DELETE</span>
                                    <span>/api/v1/projects/{id}</span>
                                </div>
                                <div class="text-sm text-gray-600">Delete project</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Microservices Architecture -->
            <div class="integration-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-cubes text-green-500 mr-2"></i>
                    Microservices Architecture
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Microservices Management -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Microservices Management</h4>
                        <div class="microservice">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Services Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="microservicesStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="microservicesStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Service Name</label>
                                <input type="text" 
                                       x-model="serviceName" 
                                       placeholder="user-service"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Service Port</label>
                                <input type="number" 
                                       x-model="servicePort" 
                                       min="1000" 
                                       max="65535"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <button @click="deployMicroservice()" 
                                    :disabled="isDeployingService"
                                    class="integration-button">
                                <i class="fas fa-rocket mr-2" x-show="!isDeployingService"></i>
                                <div class="loading-spinner mr-2" x-show="isDeployingService"></div>
                                <span x-text="isDeployingService ? 'Deploying...' : 'Deploy Service'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Microservices Visualization -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Microservices Visualization</h4>
                        <div class="system-flow">
                            <div class="text-center text-white">
                                <div class="text-lg font-semibold mb-4">Microservices Flow</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm mb-2">API Gateway</div>
                                        <div class="flow-indicator" style="top: 20px; left: 50px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">User Service</div>
                                        <div class="flow-indicator" style="top: 20px; left: 150px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Project Service</div>
                                        <div class="flow-indicator" style="top: 20px; left: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-sm">
                                <div>Services: <span x-text="microservicesMetrics.services"></span></div>
                                <div>Instances: <span x-text="microservicesMetrics.instances"></span></div>
                                <div>Load Balance: <span x-text="microservicesMetrics.loadBalance"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Monitoring -->
            <div class="integration-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-chart-line text-teal-500 mr-2"></i>
                    System Monitoring
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- System Health -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">System Health</h4>
                        <div class="service-health">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Health Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="monitoringStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="monitoringStatus === 'active' ? 'Healthy' : 'Unhealthy'"></span>
                                </div>
                            </div>
                            
                            <div class="monitoring-status">
                                <div class="text-sm mb-2">CPU Usage: <span x-text="systemMetrics.cpuUsage"></span></div>
                                <div class="text-sm mb-2">Memory Usage: <span x-text="systemMetrics.memoryUsage"></span></div>
                                <div class="text-sm mb-2">Disk Usage: <span x-text="systemMetrics.diskUsage"></span></div>
                                <div class="text-sm mb-2">Network I/O: <span x-text="systemMetrics.networkIO"></span></div>
                            </div>
                            
                            <button @click="runSystemCheck()" 
                                    :disabled="isRunningCheck"
                                    class="integration-button">
                                <i class="fas fa-heartbeat mr-2" x-show="!isRunningCheck"></i>
                                <div class="loading-spinner mr-2" x-show="isRunningCheck"></div>
                                <span x-text="isRunningCheck ? 'Checking...' : 'Run System Check'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- System Metrics -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">System Metrics</h4>
                        <div class="system-metrics">
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Response Time</span>
                                <span class="metric-value" x-text="systemMetrics.responseTime"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Throughput</span>
                                <span class="metric-value" x-text="systemMetrics.throughput"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Error Rate</span>
                                <span class="metric-value" x-text="systemMetrics.errorRate"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Uptime</span>
                                <span class="metric-value" x-text="systemMetrics.uptime"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Active Users</span>
                                <span class="metric-value" x-text="systemMetrics.activeUsers"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Integration Feedback -->
        <div class="integration-feedback" 
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
        function systemIntegration() {
            return {
                // State
                integrationLevel: 'high',
                apiGatewayStatus: 'inactive',
                microservicesStatus: 'inactive',
                monitoringStatus: 'active',
                
                // Enterprise Integration
                integrationStatus: true,
                integrations: [
                    {
                        id: 1,
                        name: 'SAP ERP',
                        type: 'ERP',
                        status: 'Connected'
                    },
                    {
                        id: 2,
                        name: 'Salesforce CRM',
                        type: 'CRM',
                        status: 'Connected'
                    },
                    {
                        id: 3,
                        name: 'Workday HR',
                        type: 'HR',
                        status: 'Connected'
                    }
                ],
                
                // API Gateway
                apiEndpoint: 'https://api.zenamanage.com',
                rateLimit: 1000,
                isConfiguringGateway: false,
                
                // Microservices
                serviceName: '',
                servicePort: 3000,
                isDeployingService: false,
                microservicesMetrics: {
                    services: 5,
                    instances: 12,
                    loadBalance: 'Round Robin'
                },
                
                // System Monitoring
                isRunningCheck: false,
                systemMetrics: {
                    cpuUsage: '45%',
                    memoryUsage: '67%',
                    diskUsage: '23%',
                    networkIO: '125 MB/s',
                    responseTime: '150ms',
                    throughput: '1,250 req/s',
                    errorRate: '0.02%',
                    uptime: '99.9%',
                    activeUsers: 1,247
                },
                
                // Feedback
                showFeedback: false,
                feedbackType: 'success',
                feedbackTitle: '',
                feedbackMessage: '',
                
                // Initialize
                init() {
                    this.initializeIntegration();
                    this.initializeAPIGateway();
                    this.initializeMicroservices();
                    this.initializeMonitoring();
                },

                // Integration Initialization
                initializeIntegration() {
                    this.integrationLevel = 'high';
                    console.log('Enterprise integration initialized');
                },

                // API Gateway Initialization
                initializeAPIGateway() {
                    this.apiGatewayStatus = 'active';
                    console.log('API Gateway initialized');
                },

                // Microservices Initialization
                initializeMicroservices() {
                    this.microservicesStatus = 'active';
                    console.log('Microservices initialized');
                },

                // Monitoring Initialization
                initializeMonitoring() {
                    this.monitoringStatus = 'active';
                    console.log('System monitoring initialized');
                },

                // Toggle Integration
                toggleIntegration() {
                    this.integrationStatus = !this.integrationStatus;
                    this.showIntegrationFeedback(
                        this.integrationStatus ? 'success' : 'warning',
                        'Integration Status',
                        this.integrationStatus ? 'Integration enabled' : 'Integration disabled'
                    );
                },

                // Configure API Gateway
                async configureAPIGateway() {
                    this.isConfiguringGateway = true;
                    
                    try {
                        // Simulate API Gateway configuration
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        this.showIntegrationFeedback('success', 'API Gateway Configured', `Gateway configured with rate limit: ${this.rateLimit}`);
                    } catch (error) {
                        console.error('API Gateway configuration error:', error);
                        this.showIntegrationFeedback('error', 'Gateway Configuration Failed', error.message);
                    } finally {
                        this.isConfiguringGateway = false;
                    }
                },

                // Deploy Microservice
                async deployMicroservice() {
                    if (!this.serviceName) return;
                    
                    this.isDeployingService = true;
                    
                    try {
                        // Simulate microservice deployment
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        
                        this.microservicesMetrics.services++;
                        this.microservicesMetrics.instances += 2;
                        
                        this.showIntegrationFeedback('success', 'Microservice Deployed', `${this.serviceName} deployed on port ${this.servicePort}`);
                        this.serviceName = '';
                        this.servicePort = 3000;
                    } catch (error) {
                        console.error('Microservice deployment error:', error);
                        this.showIntegrationFeedback('error', 'Deployment Failed', error.message);
                    } finally {
                        this.isDeployingService = false;
                    }
                },

                // Run System Check
                async runSystemCheck() {
                    this.isRunningCheck = true;
                    
                    try {
                        // Simulate system check
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        // Update metrics
                        this.systemMetrics.cpuUsage = (Math.random() * 100).toFixed(1) + '%';
                        this.systemMetrics.memoryUsage = (Math.random() * 100).toFixed(1) + '%';
                        this.systemMetrics.diskUsage = (Math.random() * 100).toFixed(1) + '%';
                        this.systemMetrics.networkIO = (Math.random() * 200).toFixed(0) + ' MB/s';
                        
                        this.showIntegrationFeedback('success', 'System Check Complete', 'System health check completed successfully');
                    } catch (error) {
                        console.error('System check error:', error);
                        this.showIntegrationFeedback('error', 'System Check Failed', error.message);
                    } finally {
                        this.isRunningCheck = false;
                    }
                },

                // Show Integration Feedback
                showIntegrationFeedback(type, title, message) {
                    this.feedbackType = type;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;
                    
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 3000);
                },

                // Navigation
                goToSecurity() {
                    window.location.href = '/app/advanced-security';
                },

                goToFinal() {
                    window.location.href = '/app/final-integration';
                },

                goToFuture() {
                    window.location.href = '/app/future-enhancements';
                }
            };
        }
    </script>
</body>
</html>
