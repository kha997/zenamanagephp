<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Integration - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Blockchain Integration with Decentralized features, Smart Contracts, IPFS Storage, and Cryptocurrency support">
    <meta name="theme-color" content="#6c5ce7">
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
    
    <!-- Chart.js for blockchain visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <!-- Web3.js for blockchain connectivity -->
    <script src="https://cdn.jsdelivr.net/npm/web3@4.3.0/dist/web3.min.js"></script>
    
    <!-- IPFS for decentralized storage -->
    <script src="https://cdn.jsdelivr.net/npm/ipfs-core@0.18.0/dist/index.min.js"></script>
    
    <style>
        /* Blockchain Dashboard Styles */
        .blockchain-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .blockchain-header {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0;
        }
        
        .blockchain-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6c5ce7;
        }
        
        .contract-card {
            background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .contract-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .crypto-wallet {
            background: linear-gradient(135deg, #00b894 0%, #00a085 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .ipfs-storage {
            background: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .blockchain-visualization {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            min-height: 300px;
        }
        
        .blockchain-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-connected { background: #00b894; animation: pulse 2s infinite; }
        .status-disconnected { background: #636e72; }
        .status-error { background: #e17055; }
        .status-loading { background: #74b9ff; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .blockchain-button {
            background: linear-gradient(135deg, #6c5ce7 0%, #a29bfe 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .blockchain-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .blockchain-button:disabled {
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
        
        .blockchain-feedback {
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
        
        .blockchain-feedback.show {
            transform: translateX(0);
        }
        
        .blockchain-feedback.success {
            border-left: 4px solid #00b894;
        }
        
        .blockchain-feedback.error {
            border-left: 4px solid #e17055;
        }
        
        .blockchain-feedback.warning {
            border-left: 4px solid #fdcb6e;
        }
        
        .transaction-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .transaction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .wallet-metrics {
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
            color: #6c5ce7;
        }
        
        .smart-contract {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .contract-indicator {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #6c5ce7;
            border-radius: 50%;
            animation: contractPulse 2s infinite;
        }
        
        @keyframes contractPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.7; }
        }
        
        .crypto-balance {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .ipfs-status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .blockchain-network {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .network-node {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #6c5ce7;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased blockchain-container">
    <div x-data="blockchainIntegration()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-link text-purple-600 mr-2"></i>
                                Blockchain Integration
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="flex items-center space-x-4">
                        <button @click="goToIoT()" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-microchip mr-2"></i>IoT
                        </button>
                        <button @click="goToSecurity()" 
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-shield-alt mr-2"></i>Security
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
            <!-- Blockchain Header -->
            <div class="blockchain-header">
                <div class="flex items-center mb-4">
                    <i class="fas fa-link text-4xl mr-4"></i>
                    <div>
                        <h2 class="text-3xl font-bold">Blockchain Integration Dashboard</h2>
                        <p class="text-lg opacity-90">Decentralized Features, Smart Contracts, IPFS Storage, Cryptocurrency</p>
                    </div>
                </div>
                
                <!-- Blockchain Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="blockchain-visualization">
                        <div class="flex items-center">
                            <span class="blockchain-status" :class="web3Status === 'connected' ? 'status-connected' : 'status-disconnected'"></span>
                            <span class="text-sm font-medium">Web3</span>
                        </div>
                        <span class="text-sm" x-text="web3Status"></span>
                    </div>
                    
                    <div class="blockchain-visualization">
                        <div class="flex items-center">
                            <span class="blockchain-status" :class="contractStatus === 'active' ? 'status-connected' : 'status-disconnected'"></span>
                            <span class="text-sm font-medium">Smart Contracts</span>
                        </div>
                        <span class="text-sm" x-text="contractStatus"></span>
                    </div>
                    
                    <div class="blockchain-visualization">
                        <div class="flex items-center">
                            <span class="blockchain-status" :class="ipfsStatus === 'active' ? 'status-connected' : 'status-disconnected'"></span>
                            <span class="text-sm font-medium">IPFS</span>
                        </div>
                        <span class="text-sm" x-text="ipfsStatus"></span>
                    </div>
                    
                    <div class="blockchain-visualization">
                        <div class="flex items-center">
                            <span class="blockchain-status" :class="cryptoStatus === 'active' ? 'status-connected' : 'status-disconnected'"></span>
                            <span class="text-sm font-medium">Crypto Wallet</span>
                        </div>
                        <span class="text-sm" x-text="cryptoStatus"></span>
                    </div>
                </div>
            </div>

            <!-- Blockchain Connectivity -->
            <div class="blockchain-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-network-wired text-purple-500 mr-2"></i>
                    Blockchain Connectivity
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Web3 Connection -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Web3 Connection</h4>
                        <div class="crypto-balance">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Network Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="web3Status === 'connected' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="web3Status === 'connected' ? 'Connected' : 'Disconnected'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Network URL</label>
                                <input type="text" 
                                       x-model="web3Config.networkUrl" 
                                       placeholder="https://mainnet.infura.io/v3/YOUR_KEY"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Account Address</label>
                                <input type="text" 
                                       x-model="web3Config.accountAddress" 
                                       placeholder="0x..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <button @click="toggleWeb3Connection()" 
                                    class="blockchain-button">
                                <i class="fas fa-plug mr-2" x-show="web3Status !== 'connected'"></i>
                                <i class="fas fa-unlink mr-2" x-show="web3Status === 'connected'"></i>
                                <span x-text="web3Status === 'connected' ? 'Disconnect' : 'Connect'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Blockchain Networks -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Supported Networks</h4>
                        <div class="blockchain-network">
                            <div class="network-node">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Ethereum</h5>
                                    <i class="fab fa-ethereum text-blue-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Mainnet & Testnets</p>
                                <div class="text-sm">
                                    <div>• Smart contracts</div>
                                    <div>• DApps support</div>
                                    <div>• ERC-20 tokens</div>
                                </div>
                            </div>
                            
                            <div class="network-node">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">Polygon</h5>
                                    <i class="fas fa-layer-group text-purple-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Layer 2 scaling</p>
                                <div class="text-sm">
                                    <div>• Low fees</div>
                                    <div>• Fast transactions</div>
                                    <div>• EVM compatible</div>
                                </div>
                            </div>
                            
                            <div class="network-node">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">BSC</h5>
                                    <i class="fas fa-coins text-yellow-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Binance Smart Chain</p>
                                <div class="text-sm">
                                    <div>• Low cost</div>
                                    <div>• High throughput</div>
                                    <div>• BEP-20 tokens</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Smart Contracts -->
            <div class="blockchain-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-file-contract text-pink-500 mr-2"></i>
                    Smart Contracts
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Contract Management -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Smart Contract Management</h4>
                        <div class="space-y-3">
                            <div class="contract-card">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Project Contract</h5>
                                    <i class="fas fa-project-diagram"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Project management smart contract</p>
                                <div class="text-sm mb-3">
                                    <div>Address: <span x-text="contracts.projectContract.address"></span></div>
                                    <div>Version: <span x-text="contracts.projectContract.version"></span></div>
                                    <div>Gas Used: <span x-text="contracts.projectContract.gasUsed"></span></div>
                                </div>
                                <button @click="deployContract('project')" 
                                        class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                                    <i class="fas fa-upload mr-1"></i>Deploy
                                </button>
                            </div>
                            
                            <div class="contract-card">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold">Payment Contract</h5>
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <p class="text-sm opacity-90 mb-3">Payment processing smart contract</p>
                                <div class="text-sm mb-3">
                                    <div>Address: <span x-text="contracts.paymentContract.address"></span></div>
                                    <div>Version: <span x-text="contracts.paymentContract.version"></span></div>
                                    <div>Gas Used: <span x-text="contracts.paymentContract.gasUsed"></span></div>
                                </div>
                                <button @click="deployContract('payment')" 
                                        class="px-3 py-1 bg-white bg-opacity-20 text-white rounded text-sm hover:bg-opacity-30 transition-colors">
                                    <i class="fas fa-upload mr-1"></i>Deploy
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contract Interaction -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Contract Interaction</h4>
                        <div class="smart-contract">
                            <div class="text-center text-white">
                                <div class="text-lg font-semibold mb-4">Smart Contract Execution</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Deploy</div>
                                        <div class="contract-indicator" style="top: 20px; left: 50px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Execute</div>
                                        <div class="contract-indicator" style="top: 20px; left: 150px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Verify</div>
                                        <div class="contract-indicator" style="top: 20px; left: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-sm">
                                <div>Gas Price: <span x-text="contractMetrics.gasPrice"></span></div>
                                <div>Gas Limit: <span x-text="contractMetrics.gasLimit"></span></div>
                                <div>Transaction Fee: <span x-text="contractMetrics.transactionFee"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Decentralized Storage (IPFS) -->
            <div class="blockchain-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-cloud text-orange-500 mr-2"></i>
                    Decentralized Storage (IPFS)
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- IPFS Upload -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">IPFS File Upload</h4>
                        <div class="ipfs-storage">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Storage Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="ipfsStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="ipfsStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">File Upload</label>
                                <input type="file" 
                                       @change="handleFileUpload($event)"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">IPFS Hash</label>
                                <input type="text" 
                                       x-model="ipfsHash" 
                                       placeholder="Qm..."
                                       readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                            </div>
                            
                            <button @click="uploadToIPFS()" 
                                    :disabled="!selectedFile"
                                    class="blockchain-button">
                                <i class="fas fa-upload mr-2"></i>
                                <span>Upload to IPFS</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- IPFS Storage -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">IPFS Storage Management</h4>
                        <div class="ipfs-status">
                            <div class="mb-4">
                                <h5 class="font-semibold">Storage Metrics</h5>
                                <div class="text-sm mt-2">
                                    <div>Files Stored: <span x-text="ipfsMetrics.filesStored"></span></div>
                                    <div>Total Size: <span x-text="ipfsMetrics.totalSize"></span></div>
                                    <div>Network Peers: <span x-text="ipfsMetrics.networkPeers"></span></div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="font-semibold">Recent Files</h5>
                                <div class="transaction-list">
                                    <template x-for="file in ipfsFiles" :key="file.id">
                                        <div class="transaction-item">
                                            <div>
                                                <span class="text-sm font-medium" x-text="file.name"></span>
                                                <span class="text-sm opacity-75 ml-2" x-text="file.size"></span>
                                            </div>
                                            <div class="text-sm" x-text="file.hash"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cryptocurrency -->
            <div class="blockchain-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-coins text-green-500 mr-2"></i>
                    Cryptocurrency Support
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Crypto Wallet -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Crypto Wallet</h4>
                        <div class="crypto-wallet">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Wallet Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="cryptoStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="cryptoStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="crypto-balance">
                                <div class="text-sm mb-2">ETH Balance</div>
                                <div class="text-2xl font-bold" x-text="walletBalance.eth + ' ETH'"></div>
                            </div>
                            
                            <div class="crypto-balance">
                                <div class="text-sm mb-2">USDT Balance</div>
                                <div class="text-2xl font-bold" x-text="walletBalance.usdt + ' USDT'"></div>
                            </div>
                            
                            <div class="crypto-balance">
                                <div class="text-sm mb-2">BNB Balance</div>
                                <div class="text-2xl font-bold" x-text="walletBalance.bnb + ' BNB'"></div>
                            </div>
                            
                            <button @click="connectWallet()" 
                                    :disabled="cryptoStatus === 'active'"
                                    class="blockchain-button mt-4">
                                <i class="fas fa-wallet mr-2"></i>
                                <span x-text="cryptoStatus === 'active' ? 'Wallet Connected' : 'Connect Wallet'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Transaction History -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Transaction History</h4>
                        <div class="wallet-metrics">
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Total Transactions</span>
                                <span class="metric-value" x-text="walletMetrics.totalTransactions"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Total Volume</span>
                                <span class="metric-value" x-text="walletMetrics.totalVolume"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Gas Spent</span>
                                <span class="metric-value" x-text="walletMetrics.gasSpent"></span>
                            </div>
                        </div>
                        
                        <div class="transaction-list">
                            <template x-for="transaction in transactions" :key="transaction.id">
                                <div class="transaction-item">
                                    <div>
                                        <span class="text-sm font-medium" x-text="transaction.type"></span>
                                        <span class="text-sm opacity-75 ml-2" x-text="transaction.amount"></span>
                                    </div>
                                    <div class="text-sm" x-text="transaction.status"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Blockchain Feedback -->
        <div class="blockchain-feedback" 
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
        function blockchainIntegration() {
            return {
                // State
                web3Status: 'disconnected',
                contractStatus: 'inactive',
                ipfsStatus: 'inactive',
                cryptoStatus: 'inactive',
                
                // Web3 Configuration
                web3Config: {
                    networkUrl: 'https://mainnet.infura.io/v3/YOUR_KEY',
                    accountAddress: '0x...'
                },
                web3: null,
                
                // Smart Contracts
                contracts: {
                    projectContract: {
                        address: '0x...',
                        version: '1.0',
                        gasUsed: '2,100,000'
                    },
                    paymentContract: {
                        address: '0x...',
                        version: '1.0',
                        gasUsed: '1,800,000'
                    }
                },
                contractMetrics: {
                    gasPrice: '20 Gwei',
                    gasLimit: '2,100,000',
                    transactionFee: '0.042 ETH'
                },
                
                // IPFS
                selectedFile: null,
                ipfsHash: '',
                ipfsFiles: [
                    {
                        id: 1,
                        name: 'project-data.json',
                        size: '2.5 KB',
                        hash: 'QmX...'
                    },
                    {
                        id: 2,
                        name: 'user-avatar.png',
                        size: '45 KB',
                        hash: 'QmY...'
                    }
                ],
                ipfsMetrics: {
                    filesStored: 2,
                    totalSize: '47.5 KB',
                    networkPeers: 1,247
                },
                
                // Cryptocurrency
                walletBalance: {
                    eth: '1.25',
                    usdt: '500.00',
                    bnb: '2.50'
                },
                transactions: [
                    {
                        id: 1,
                        type: 'Send',
                        amount: '0.1 ETH',
                        status: 'Confirmed'
                    },
                    {
                        id: 2,
                        type: 'Receive',
                        amount: '100 USDT',
                        status: 'Confirmed'
                    },
                    {
                        id: 3,
                        type: 'Swap',
                        amount: '1 BNB',
                        status: 'Pending'
                    }
                ],
                walletMetrics: {
                    totalTransactions: 3,
                    totalVolume: '1.35 ETH',
                    gasSpent: '0.021 ETH'
                },
                
                // Feedback
                showFeedback: false,
                feedbackType: 'success',
                feedbackTitle: '',
                feedbackMessage: '',
                
                // Initialize
                init() {
                    this.initializeWeb3();
                    this.initializeSmartContracts();
                    this.initializeIPFS();
                    this.initializeCryptocurrency();
                },

                // Web3 Initialization
                initializeWeb3() {
                    try {
                        if (typeof Web3 !== 'undefined') {
                            console.log('Web3.js loaded successfully');
                        } else {
                            console.warn('Web3.js not loaded');
                        }
                    } catch (error) {
                        console.error('Web3 initialization error:', error);
                    }
                },

                // Smart Contracts Initialization
                initializeSmartContracts() {
                    this.contractStatus = 'active';
                    console.log('Smart contracts initialized');
                },

                // IPFS Initialization
                initializeIPFS() {
                    try {
                        if (typeof IpfsCore !== 'undefined') {
                            this.ipfsStatus = 'active';
                            console.log('IPFS initialized');
                        } else {
                            console.warn('IPFS not loaded');
                        }
                    } catch (error) {
                        console.error('IPFS initialization error:', error);
                    }
                },

                // Cryptocurrency Initialization
                initializeCryptocurrency() {
                    this.cryptoStatus = 'active';
                    console.log('Cryptocurrency initialized');
                },

                // Toggle Web3 Connection
                async toggleWeb3Connection() {
                    if (this.web3Status === 'connected') {
                        this.disconnectWeb3();
                    } else {
                        await this.connectWeb3();
                    }
                },

                // Connect Web3
                async connectWeb3() {
                    try {
                        // Simulate Web3 connection
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        this.web3Status = 'connected';
                        this.showBlockchainFeedback('success', 'Web3 Connected', 'Successfully connected to blockchain network');
                    } catch (error) {
                        console.error('Web3 connection error:', error);
                        this.showBlockchainFeedback('error', 'Web3 Connection Failed', error.message);
                    }
                },

                // Disconnect Web3
                disconnectWeb3() {
                    this.web3Status = 'disconnected';
                    this.showBlockchainFeedback('warning', 'Web3 Disconnected', 'Disconnected from blockchain network');
                },

                // Deploy Contract
                deployContract(contractType) {
                    this.showBlockchainFeedback('success', 'Contract Deployed', `${contractType} contract deployed successfully`);
                },

                // Handle File Upload
                handleFileUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.selectedFile = file;
                        console.log('File selected:', file.name);
                    }
                },

                // Upload to IPFS
                async uploadToIPFS() {
                    if (!this.selectedFile) return;
                    
                    try {
                        // Simulate IPFS upload
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        const hash = 'Qm' + Math.random().toString(36).substr(2, 9);
                        this.ipfsHash = hash;
                        
                        this.ipfsFiles.unshift({
                            id: Date.now(),
                            name: this.selectedFile.name,
                            size: (this.selectedFile.size / 1024).toFixed(1) + ' KB',
                            hash: hash
                        });
                        
                        this.ipfsMetrics.filesStored++;
                        this.ipfsMetrics.totalSize = (parseFloat(this.ipfsMetrics.totalSize) + this.selectedFile.size / 1024).toFixed(1) + ' KB';
                        
                        this.showBlockchainFeedback('success', 'File Uploaded', 'File uploaded to IPFS successfully');
                    } catch (error) {
                        console.error('IPFS upload error:', error);
                        this.showBlockchainFeedback('error', 'Upload Failed', error.message);
                    }
                },

                // Connect Wallet
                async connectWallet() {
                    try {
                        // Simulate wallet connection
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        this.cryptoStatus = 'active';
                        this.showBlockchainFeedback('success', 'Wallet Connected', 'Cryptocurrency wallet connected successfully');
                    } catch (error) {
                        console.error('Wallet connection error:', error);
                        this.showBlockchainFeedback('error', 'Wallet Connection Failed', error.message);
                    }
                },

                // Show Blockchain Feedback
                showBlockchainFeedback(type, title, message) {
                    this.feedbackType = type;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;
                    
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 3000);
                },

                // Navigation
                goToIoT() {
                    window.location.href = '/app/iot-integration';
                },

                goToSecurity() {
                    window.location.href = '/app/advanced-security';
                },

                goToFuture() {
                    window.location.href = '/app/future-enhancements';
                }
            };
        }
    </script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/_future/blockchain-integration.blade.php ENDPATH**/ ?>