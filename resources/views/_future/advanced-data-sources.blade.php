<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Data Sources - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Advanced data source integration for ZenaManage Dashboard Builder">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ZenaManage">
    
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
    
    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .data-source-card {
            transition: all 0.3s ease;
        }
        .data-source-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .connection-status {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .file-drop-zone {
            border: 2px dashed #d1d5db;
            transition: all 0.3s ease;
        }
        .file-drop-zone.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased">
    <div x-data="advancedDataSources()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-database text-blue-600 mr-2"></i>
                                Advanced Data Sources
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center space-x-3">
                        <button @click="goToBuilder()" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <i class="fas fa-cog mr-2"></i>Dashboard Builder
                        </button>
                        <button @click="goToTemplates()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-layer-group mr-2"></i>Templates
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-database text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Database Connections</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="dataSources.filter(ds => ds.type === 'database').length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-file-upload text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">File Uploads</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="dataSources.filter(ds => ds.type === 'file').length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-plug text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">API Connections</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="dataSources.filter(ds => ds.type === 'api').length"></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <i class="fas fa-sync-alt text-orange-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Active Syncs</p>
                            <p class="text-2xl font-bold text-gray-900" x-text="activeSyncs"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Source Types -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Database Integration -->
                <div class="data-source-card bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-blue-100 rounded-lg mr-4">
                            <i class="fas fa-database text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Database Integration</h3>
                            <p class="text-sm text-gray-600">Connect to MySQL, PostgreSQL, MongoDB</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button @click="showDatabaseModal = true" 
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Database Connection
                        </button>
                        
                        <div class="space-y-2">
                            <template x-for="db in dataSources.filter(ds => ds.type === 'database')" :key="db.id">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3" 
                                             :class="db.status === 'connected' ? 'bg-green-500' : 'bg-red-500'"></div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="db.name"></p>
                                            <p class="text-xs text-gray-500" x-text="db.host + ':' + db.port"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click="testConnection(db)" 
                                                class="p-1 text-gray-400 hover:text-blue-600">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button @click="editDataSource(db)" 
                                                class="p-1 text-gray-400 hover:text-green-600">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="deleteDataSource(db)" 
                                                class="p-1 text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- File Upload System -->
                <div class="data-source-card bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-green-100 rounded-lg mr-4">
                            <i class="fas fa-file-upload text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">File Upload</h3>
                            <p class="text-sm text-gray-600">CSV, Excel, JSON file imports</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <!-- File Drop Zone -->
                        <div class="file-drop-zone rounded-lg p-6 text-center cursor-pointer"
                             @click="fileInput.click()"
                             @dragover.prevent="isDragOver = true"
                             @dragleave.prevent="isDragOver = false"
                             @drop.prevent="handleFileDrop($event)"
                             :class="isDragOver ? 'dragover' : ''">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600">Drop files here or click to upload</p>
                            <p class="text-xs text-gray-500 mt-1">CSV, Excel, JSON files supported</p>
                        </div>
                        
                        <input type="file" 
                               x-ref="fileInput"
                               @change="handleFileUpload($event)"
                               multiple
                               accept=".csv,.xlsx,.xls,.json"
                               class="hidden">
                        
                        <div class="space-y-2">
                            <template x-for="file in dataSources.filter(ds => ds.type === 'file')" :key="file.id">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <i class="fas fa-file text-gray-400 mr-3"></i>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="file.name"></p>
                                            <p class="text-xs text-gray-500" x-text="file.size + ' rows'"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click="previewFile(file)" 
                                                class="p-1 text-gray-400 hover:text-blue-600">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button @click="deleteDataSource(file)" 
                                                class="p-1 text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- API Connectors -->
                <div class="data-source-card bg-white rounded-xl p-6 shadow-sm">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-purple-100 rounded-lg mr-4">
                            <i class="fas fa-plug text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">API Connectors</h3>
                            <p class="text-sm text-gray-600">REST, GraphQL, WebSocket APIs</p>
                        </div>
                    </div>
                    
                    <div class="space-y-3">
                        <button @click="showApiModal = true" 
                                class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add API Connection
                        </button>
                        
                        <div class="space-y-2">
                            <template x-for="api in dataSources.filter(ds => ds.type === 'api')" :key="api.id">
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 rounded-full mr-3" 
                                             :class="api.status === 'connected' ? 'bg-green-500' : 'bg-red-500'"></div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900" x-text="api.name"></p>
                                            <p class="text-xs text-gray-500" x-text="api.url"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button @click="testApiConnection(api)" 
                                                class="p-1 text-gray-400 hover:text-blue-600">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button @click="editDataSource(api)" 
                                                class="p-1 text-gray-400 hover:text-green-600">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button @click="deleteDataSource(api)" 
                                                class="p-1 text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Processing Pipeline -->
            <div class="bg-white rounded-xl p-6 shadow-sm mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Data Processing Pipeline</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Data Validation -->
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <h4 class="text-sm font-medium text-gray-900">Data Validation</h4>
                        </div>
                        <p class="text-xs text-gray-600 mb-3">Schema validation và data quality checks</p>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span>Valid Records</span>
                                <span class="text-green-600" x-text="validationStats.valid"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span>Invalid Records</span>
                                <span class="text-red-600" x-text="validationStats.invalid"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Data Transformation -->
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exchange-alt text-blue-600 mr-2"></i>
                            <h4 class="text-sm font-medium text-gray-900">Data Transformation</h4>
                        </div>
                        <p class="text-xs text-gray-600 mb-3">ETL pipeline với custom transformations</p>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span>Transformations</span>
                                <span class="text-blue-600" x-text="transformationStats.count"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span>Success Rate</span>
                                <span class="text-green-600" x-text="transformationStats.successRate + '%'"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Data Refresh -->
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-sync-alt text-purple-600 mr-2"></i>
                            <h4 class="text-sm font-medium text-gray-900">Data Refresh</h4>
                        </div>
                        <p class="text-xs text-gray-600 mb-3">Automated data refresh cycles</p>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span>Last Refresh</span>
                                <span class="text-purple-600" x-text="refreshStats.lastRefresh"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span>Next Refresh</span>
                                <span class="text-purple-600" x-text="refreshStats.nextRefresh"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Performance -->
                    <div class="p-4 border border-gray-200 rounded-lg">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-tachometer-alt text-orange-600 mr-2"></i>
                            <h4 class="text-sm font-medium text-gray-900">Performance</h4>
                        </div>
                        <p class="text-xs text-gray-600 mb-3">Processing performance metrics</p>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs">
                                <span>Avg Processing Time</span>
                                <span class="text-orange-600" x-text="performanceStats.avgTime + 'ms'"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span>Throughput</span>
                                <span class="text-orange-600" x-text="performanceStats.throughput + '/min'"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Source Management -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Data Source Management</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="refreshAllData()" 
                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors">
                            <i class="fas fa-sync-alt mr-1"></i>Refresh All
                        </button>
                        <button @click="exportDataSources()" 
                                class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700 transition-colors">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Sync</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="dataSource in dataSources" :key="dataSource.id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full flex items-center justify-center"
                                                     :class="getDataSourceIconBg(dataSource.type)">
                                                    <i :class="getDataSourceIcon(dataSource.type)" 
                                                       :class="getDataSourceIconColor(dataSource.type)"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900" x-text="dataSource.name"></div>
                                                <div class="text-sm text-gray-500" x-text="dataSource.description"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                              :class="getDataSourceTypeBadge(dataSource.type)"
                                              x-text="dataSource.type.toUpperCase()"></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="w-2 h-2 rounded-full mr-2" 
                                                 :class="dataSource.status === 'connected' ? 'bg-green-500' : 'bg-red-500'"></div>
                                            <span class="text-sm text-gray-900" x-text="dataSource.status"></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="dataSource.lastSync"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="dataSource.recordCount"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <button @click="testConnection(dataSource)" 
                                                    class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <button @click="editDataSource(dataSource)" 
                                                    class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button @click="deleteDataSource(dataSource)" 
                                                    class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Database Connection Modal -->
        <div x-show="showDatabaseModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showDatabaseModal = false">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">Add Database Connection</h3>
                        <button @click="showDatabaseModal = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <form @submit.prevent="addDatabaseConnection()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Connection Name</label>
                                <input type="text" 
                                       x-model="newDatabase.name"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Database Type</label>
                                    <select x-model="newDatabase.type"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select Database</option>
                                        <option value="mysql">MySQL</option>
                                        <option value="postgresql">PostgreSQL</option>
                                        <option value="mongodb">MongoDB</option>
                                        <option value="sqlite">SQLite</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Host</label>
                                    <input type="text" 
                                           x-model="newDatabase.host"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Port</label>
                                    <input type="number" 
                                           x-model="newDatabase.port"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Database Name</label>
                                    <input type="text" 
                                           x-model="newDatabase.database"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                    <input type="text" 
                                           x-model="newDatabase.username"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                    <input type="password" 
                                           x-model="newDatabase.password"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea x-model="newDatabase.description"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button @click="showDatabaseModal = false" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="testDatabaseConnection()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-play mr-2"></i>Test Connection
                        </button>
                        <button @click="addDatabaseConnection()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Connection
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Connection Modal -->
        <div x-show="showApiModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
             @click="showApiModal = false">
            <div class="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-hidden"
                 @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xl font-semibold text-gray-900">Add API Connection</h3>
                        <button @click="showApiModal = false" 
                                class="p-2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <form @submit.prevent="addApiConnection()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Connection Name</label>
                                <input type="text" 
                                       x-model="newApi.name"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">API Type</label>
                                    <select x-model="newApi.type"
                                            required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="">Select API Type</option>
                                        <option value="rest">REST API</option>
                                        <option value="graphql">GraphQL</option>
                                        <option value="websocket">WebSocket</option>
                                        <option value="soap">SOAP</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                                    <input type="url" 
                                           x-model="newApi.url"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Authentication</label>
                                    <select x-model="newApi.authType"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <option value="none">None</option>
                                        <option value="bearer">Bearer Token</option>
                                        <option value="basic">Basic Auth</option>
                                        <option value="api_key">API Key</option>
                                        <option value="oauth2">OAuth2</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">API Key/Token</label>
                                    <input type="text" 
                                           x-model="newApi.apiKey"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea x-model="newApi.description"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200">
                    <div class="flex items-center justify-end space-x-3">
                        <button @click="showApiModal = false" 
                                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button @click="testApiConnection()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-play mr-2"></i>Test Connection
                        </button>
                        <button @click="addApiConnection()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Add Connection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function advancedDataSources() {
            return {
                // State
                dataSources: [],
                activeSyncs: 0,
                isDragOver: false,
                showDatabaseModal: false,
                showApiModal: false,
                newDatabase: {
                    name: '',
                    type: '',
                    host: '',
                    port: 3306,
                    database: '',
                    username: '',
                    password: '',
                    description: ''
                },
                newApi: {
                    name: '',
                    type: '',
                    url: '',
                    authType: 'none',
                    apiKey: '',
                    description: ''
                },
                validationStats: {
                    valid: 1247,
                    invalid: 23
                },
                transformationStats: {
                    count: 15,
                    successRate: 98
                },
                refreshStats: {
                    lastRefresh: '2 min ago',
                    nextRefresh: 'in 58 min'
                },
                performanceStats: {
                    avgTime: 245,
                    throughput: 1250
                },

                // Initialize
                init() {
                    this.loadDataSources();
                    this.startDataSync();
                },

                // Load Data Sources
                loadDataSources() {
                    this.dataSources = [
                        {
                            id: 1,
                            name: 'Production MySQL',
                            type: 'database',
                            host: 'localhost',
                            port: 3306,
                            database: 'zenamanage',
                            status: 'connected',
                            lastSync: '2 min ago',
                            recordCount: '15,247',
                            description: 'Main production database'
                        },
                        {
                            id: 2,
                            name: 'Sales Data CSV',
                            type: 'file',
                            name: 'sales_data_2024.csv',
                            size: '2,456',
                            status: 'connected',
                            lastSync: '1 hour ago',
                            recordCount: '2,456',
                            description: 'Monthly sales data'
                        },
                        {
                            id: 3,
                            name: 'External API',
                            type: 'api',
                            url: 'https://api.example.com',
                            status: 'connected',
                            lastSync: '5 min ago',
                            recordCount: '8,923',
                            description: 'Third-party data API'
                        }
                    ];
                },

                // Start Data Sync
                startDataSync() {
                    setInterval(() => {
                        this.activeSyncs = Math.floor(Math.random() * 5) + 1;
                    }, 5000);
                },

                // Database Connection Methods
                addDatabaseConnection() {
                    const db = {
                        id: Date.now(),
                        ...this.newDatabase,
                        status: 'connected',
                        lastSync: 'Just now',
                        recordCount: '0',
                        description: this.newDatabase.description || 'Database connection'
                    };
                    
                    this.dataSources.push(db);
                    this.showDatabaseModal = false;
                    this.resetDatabaseForm();
                    
                    alert('Database connection added successfully!');
                },

                testDatabaseConnection() {
                    // Simulate connection test
                    setTimeout(() => {
                        alert('Database connection test successful!');
                    }, 1000);
                },

                resetDatabaseForm() {
                    this.newDatabase = {
                        name: '',
                        type: '',
                        host: '',
                        port: 3306,
                        database: '',
                        username: '',
                        password: '',
                        description: ''
                    };
                },

                // API Connection Methods
                addApiConnection() {
                    const api = {
                        id: Date.now(),
                        ...this.newApi,
                        status: 'connected',
                        lastSync: 'Just now',
                        recordCount: '0',
                        description: this.newApi.description || 'API connection'
                    };
                    
                    this.dataSources.push(api);
                    this.showApiModal = false;
                    this.resetApiForm();
                    
                    alert('API connection added successfully!');
                },

                testApiConnection() {
                    // Simulate API test
                    setTimeout(() => {
                        alert('API connection test successful!');
                    }, 1000);
                },

                resetApiForm() {
                    this.newApi = {
                        name: '',
                        type: '',
                        url: '',
                        authType: 'none',
                        apiKey: '',
                        description: ''
                    };
                },

                // File Upload Methods
                handleFileUpload(event) {
                    const files = Array.from(event.target.files);
                    files.forEach(file => this.processFile(file));
                },

                handleFileDrop(event) {
                    this.isDragOver = false;
                    const files = Array.from(event.dataTransfer.files);
                    files.forEach(file => this.processFile(file));
                },

                processFile(file) {
                    const fileSource = {
                        id: Date.now(),
                        name: file.name,
                        type: 'file',
                        size: Math.floor(Math.random() * 5000) + 1000,
                        status: 'connected',
                        lastSync: 'Just now',
                        recordCount: Math.floor(Math.random() * 5000) + 1000,
                        description: `Uploaded file: ${file.name}`
                    };
                    
                    this.dataSources.push(fileSource);
                    alert(`File ${file.name} uploaded successfully!`);
                },

                previewFile(file) {
                    alert(`Previewing file: ${file.name}`);
                },

                // Data Source Management
                testConnection(dataSource) {
                    dataSource.status = 'testing';
                    setTimeout(() => {
                        dataSource.status = 'connected';
                        alert(`${dataSource.name} connection test successful!`);
                    }, 2000);
                },

                editDataSource(dataSource) {
                    alert(`Editing ${dataSource.name}`);
                },

                deleteDataSource(dataSource) {
                    if (confirm(`Are you sure you want to delete ${dataSource.name}?`)) {
                        this.dataSources = this.dataSources.filter(ds => ds.id !== dataSource.id);
                        alert(`${dataSource.name} deleted successfully!`);
                    }
                },

                refreshAllData() {
                    alert('Refreshing all data sources...');
                    this.dataSources.forEach(ds => {
                        ds.lastSync = 'Just now';
                    });
                },

                exportDataSources() {
                    const data = JSON.stringify(this.dataSources, null, 2);
                    const blob = new Blob([data], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'data-sources.json';
                    a.click();
                    URL.revokeObjectURL(url);
                },

                // Utility Methods
                getDataSourceIcon(type) {
                    const icons = {
                        database: 'fas fa-database',
                        file: 'fas fa-file',
                        api: 'fas fa-plug'
                    };
                    return icons[type] || 'fas fa-question';
                },

                getDataSourceIconColor(type) {
                    const colors = {
                        database: 'text-blue-600',
                        file: 'text-green-600',
                        api: 'text-purple-600'
                    };
                    return colors[type] || 'text-gray-600';
                },

                getDataSourceIconBg(type) {
                    const backgrounds = {
                        database: 'bg-blue-100',
                        file: 'bg-green-100',
                        api: 'bg-purple-100'
                    };
                    return backgrounds[type] || 'bg-gray-100';
                },

                getDataSourceTypeBadge(type) {
                    const badges = {
                        database: 'bg-blue-100 text-blue-800',
                        file: 'bg-green-100 text-green-800',
                        api: 'bg-purple-100 text-purple-800'
                    };
                    return badges[type] || 'bg-gray-100 text-gray-800';
                },

                // Navigation
                goToBuilder() {
                    window.location.href = '/app/dashboard-builder';
                },

                goToTemplates() {
                    window.location.href = '/app/dashboard-templates';
                }
            };
        }
    </script>
</body>
</html>
