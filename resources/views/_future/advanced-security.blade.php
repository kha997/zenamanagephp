<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Security - ZenaManage</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Advanced Security with Enhanced features, Zero-knowledge Proofs, Multi-signature, and Security Auditing">
    <meta name="theme-color" content="#e74c3c">
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
    
    <!-- Chart.js for security visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <!-- Crypto libraries for security features -->
    <script src="https://cdn.jsdelivr.net/npm/crypto-js@4.2.0/crypto-js.min.js"></script>
    
    <style>
        /* Security Dashboard Styles */
        .security-container {
            max-width: 100vw;
            overflow-x: hidden;
            position: relative;
        }
        
        .security-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0;
        }
        
        .security-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin: 16px 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #e74c3c;
        }
        
        .security-card {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
            transition: transform 0.3s ease;
        }
        
        .security-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .zk-proof {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .multi-sig {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
        
        .security-visualization {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            min-height: 300px;
        }
        
        .security-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-secure { background: #27ae60; animation: pulse 2s infinite; }
        .status-vulnerable { background: #e74c3c; }
        .status-warning { background: #f39c12; }
        .status-loading { background: #95a5a6; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .security-button {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .security-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        
        .security-button:disabled {
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
        
        .security-feedback {
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
        
        .security-feedback.show {
            transform: translateX(0);
        }
        
        .security-feedback.success {
            border-left: 4px solid #27ae60;
        }
        
        .security-feedback.error {
            border-left: 4px solid #e74c3c;
        }
        
        .security-feedback.warning {
            border-left: 4px solid #f39c12;
        }
        
        .threat-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .threat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .threat-item:last-child {
            border-bottom: none;
        }
        
        .security-metrics {
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
            color: #e74c3c;
        }
        
        .encryption-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin: 16px 0;
        }
        
        .encryption-item {
            background: white;
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #3498db;
        }
        
        .zk-proof-visualization {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .zk-indicator {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #9b59b6;
            border-radius: 50%;
            animation: zkPulse 2s infinite;
        }
        
        @keyframes zkPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.7; }
        }
        
        .multi-sig-visualization {
            background: #000;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
        }
        
        .sig-indicator {
            position: absolute;
            width: 8px;
            height: 8px;
            background: #f39c12;
            border-radius: 50%;
            animation: sigPulse 2s infinite;
        }
        
        @keyframes sigPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.5); opacity: 0.7; }
        }
        
        .audit-status {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
        }
        
        .security-level {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            border-radius: 12px;
            padding: 16px;
            margin: 8px 0;
        }
    </style>
</head>

<body class="bg-gray-50 font-inter antialiased security-container">
    <div x-data="advancedSecurity()" x-init="init()" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <h1 class="text-xl font-bold text-gray-900">
                                <i class="fas fa-shield-alt text-red-600 mr-2"></i>
                                Advanced Security
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <div class="flex items-center space-x-4">
                        <button @click="goToBlockchain()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-link mr-2"></i>Blockchain
                        </button>
                        <button @click="goToIntegration()" 
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-plug mr-2"></i>Integration
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
            <!-- Security Header -->
            <div class="security-header">
                <div class="flex items-center mb-4">
                    <i class="fas fa-shield-alt text-4xl mr-4"></i>
                    <div>
                        <h2 class="text-3xl font-bold">Advanced Security Dashboard</h2>
                        <p class="text-lg opacity-90">Enhanced Security, Zero-knowledge Proofs, Multi-signature, Security Auditing</p>
                    </div>
                </div>
                
                <!-- Security Status -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="security-visualization">
                        <div class="flex items-center">
                            <span class="security-status" :class="securityLevel === 'high' ? 'status-secure' : 'status-warning'"></span>
                            <span class="text-sm font-medium">Security Level</span>
                        </div>
                        <span class="text-sm" x-text="securityLevel"></span>
                    </div>
                    
                    <div class="security-visualization">
                        <div class="flex items-center">
                            <span class="security-status" :class="zkProofStatus === 'active' ? 'status-secure' : 'status-loading'"></span>
                            <span class="text-sm font-medium">ZK Proofs</span>
                        </div>
                        <span class="text-sm" x-text="zkProofStatus"></span>
                    </div>
                    
                    <div class="security-visualization">
                        <div class="flex items-center">
                            <span class="security-status" :class="multiSigStatus === 'active' ? 'status-secure' : 'status-loading'"></span>
                            <span class="text-sm font-medium">Multi-signature</span>
                        </div>
                        <span class="text-sm" x-text="multiSigStatus"></span>
                    </div>
                    
                    <div class="security-visualization">
                        <div class="flex items-center">
                            <span class="security-status" :class="auditStatus === 'passed' ? 'status-secure' : 'status-warning'"></span>
                            <span class="text-sm font-medium">Security Audit</span>
                        </div>
                        <span class="text-sm" x-text="auditStatus"></span>
                    </div>
                </div>
            </div>

            <!-- Enhanced Security Features -->
            <div class="security-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-lock text-red-500 mr-2"></i>
                    Enhanced Security Features
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Encryption Methods -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Encryption Methods</h4>
                        <div class="encryption-grid">
                            <div class="encryption-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">AES-256</h5>
                                    <i class="fas fa-key text-blue-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Advanced Encryption Standard</p>
                                <div class="text-sm">
                                    <div>• 256-bit key</div>
                                    <div>• Military grade</div>
                                    <div>• Symmetric encryption</div>
                                </div>
                            </div>
                            
                            <div class="encryption-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">RSA-4096</h5>
                                    <i class="fas fa-unlock text-green-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Rivest-Shamir-Adleman</p>
                                <div class="text-sm">
                                    <div>• 4096-bit key</div>
                                    <div>• Asymmetric encryption</div>
                                    <div>• Public/private key</div>
                                </div>
                            </div>
                            
                            <div class="encryption-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">ECC-256</h5>
                                    <i class="fas fa-circle text-purple-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Elliptic Curve Cryptography</p>
                                <div class="text-sm">
                                    <div>• 256-bit key</div>
                                    <div>• High security</div>
                                    <div>• Small key size</div>
                                </div>
                            </div>
                            
                            <div class="encryption-item">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-gray-900">ChaCha20</h5>
                                    <i class="fas fa-random text-orange-500"></i>
                                </div>
                                <p class="text-sm text-gray-600 mb-3">Stream Cipher</p>
                                <div class="text-sm">
                                    <div>• 256-bit key</div>
                                    <div>• High performance</div>
                                    <div>• Modern cipher</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Monitoring -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Security Monitoring</h4>
                        <div class="security-card">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Threat Detection</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="threatDetection ? 'text-green-300' : 'text-red-300'" 
                                          x-text="threatDetection ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="threat-list">
                                <div x-show="threats.length === 0" class="text-center text-sm opacity-75">
                                    No threats detected
                                </div>
                                <template x-for="threat in threats" :key="threat.id">
                                    <div class="threat-item">
                                        <div>
                                            <span class="text-sm font-medium" x-text="threat.type"></span>
                                            <span class="text-sm opacity-75 ml-2" x-text="threat.severity"></span>
                                        </div>
                                        <div class="text-sm" x-text="threat.status"></div>
                                    </div>
                                </template>
                            </div>
                            
                            <button @click="toggleThreatDetection()" 
                                    class="security-button">
                                <i class="fas fa-shield-alt mr-2"></i>
                                <span x-text="threatDetection ? 'Disable Detection' : 'Enable Detection'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Zero-knowledge Proofs -->
            <div class="security-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-eye-slash text-purple-500 mr-2"></i>
                    Zero-knowledge Proofs
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- ZK Proof Generation -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">ZK Proof Generation</h4>
                        <div class="zk-proof">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Proof Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="zkProofStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="zkProofStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Proof Type</label>
                                <select x-model="selectedZKProof" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="zk-snark">zk-SNARK</option>
                                    <option value="zk-stark">zk-STARK</option>
                                    <option value="bulletproof">Bulletproof</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Proof Data</label>
                                <textarea x-model="zkProofData" 
                                          placeholder="Enter data to prove"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                                          rows="3"></textarea>
                            </div>
                            
                            <button @click="generateZKProof()" 
                                    :disabled="!zkProofData || isGeneratingProof"
                                    class="security-button">
                                <i class="fas fa-magic mr-2" x-show="!isGeneratingProof"></i>
                                <div class="loading-spinner mr-2" x-show="isGeneratingProof"></div>
                                <span x-text="isGeneratingProof ? 'Generating...' : 'Generate Proof'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- ZK Proof Verification -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">ZK Proof Verification</h4>
                        <div class="zk-proof-visualization">
                            <div class="text-center text-white">
                                <div class="text-lg font-semibold mb-4">Zero-knowledge Proof Flow</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Prover</div>
                                        <div class="zk-indicator" style="top: 20px; left: 50px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Proof</div>
                                        <div class="zk-indicator" style="top: 20px; left: 150px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Verifier</div>
                                        <div class="zk-indicator" style="top: 20px; left: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-sm">
                                <div>Proof Size: <span x-text="zkProofMetrics.proofSize"></span></div>
                                <div>Verification Time: <span x-text="zkProofMetrics.verificationTime"></span></div>
                                <div>Trusted Setup: <span x-text="zkProofMetrics.trustedSetup"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-signature -->
            <div class="security-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-signature text-orange-500 mr-2"></i>
                    Multi-signature
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Multi-sig Setup -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Multi-signature Setup</h4>
                        <div class="multi-sig">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Multi-sig Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="multiSigStatus === 'active' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="multiSigStatus === 'active' ? 'Active' : 'Inactive'"></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Required Signatures</label>
                                <input type="number" 
                                       x-model="multiSigConfig.requiredSignatures" 
                                       min="1" 
                                       max="10"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Total Signers</label>
                                <input type="number" 
                                       x-model="multiSigConfig.totalSigners" 
                                       min="1" 
                                       max="20"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <button @click="setupMultiSig()" 
                                    :disabled="isSettingUpMultiSig"
                                    class="security-button">
                                <i class="fas fa-cog mr-2" x-show="!isSettingUpMultiSig"></i>
                                <div class="loading-spinner mr-2" x-show="isSettingUpMultiSig"></div>
                                <span x-text="isSettingUpMultiSig ? 'Setting up...' : 'Setup Multi-sig'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Multi-sig Visualization -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Multi-signature Visualization</h4>
                        <div class="multi-sig-visualization">
                            <div class="text-center text-white">
                                <div class="text-lg font-semibold mb-4">Multi-signature Flow</div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Signer 1</div>
                                        <div class="sig-indicator" style="top: 20px; left: 50px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Signer 2</div>
                                        <div class="sig-indicator" style="top: 20px; left: 150px;"></div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-sm mb-2">Signer 3</div>
                                        <div class="sig-indicator" style="top: 20px; left: 250px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="text-sm">
                                <div>Required: <span x-text="multiSigConfig.requiredSignatures"></span></div>
                                <div>Total: <span x-text="multiSigConfig.totalSigners"></span></div>
                                <div>Threshold: <span x-text="multiSigThreshold"></span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Auditing -->
            <div class="security-panel">
                <h3 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-search text-teal-500 mr-2"></i>
                    Security Auditing
                </h3>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Security Audit -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Security Audit</h4>
                        <div class="security-level">
                            <div class="flex items-center justify-between mb-4">
                                <h5 class="font-semibold">Audit Status</h5>
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm">Status:</span>
                                    <span class="text-sm" :class="auditStatus === 'passed' ? 'text-green-300' : 'text-red-300'" 
                                          x-text="auditStatus === 'passed' ? 'Passed' : 'Failed'"></span>
                                </div>
                            </div>
                            
                            <div class="audit-status">
                                <div class="text-sm mb-2">Last Audit: <span x-text="lastAuditDate"></span></div>
                                <div class="text-sm mb-2">Next Audit: <span x-text="nextAuditDate"></span></div>
                                <div class="text-sm mb-2">Auditor: <span x-text="auditorName"></span></div>
                            </div>
                            
                            <button @click="runSecurityAudit()" 
                                    :disabled="isRunningAudit"
                                    class="security-button">
                                <i class="fas fa-search mr-2" x-show="!isRunningAudit"></i>
                                <div class="loading-spinner mr-2" x-show="isRunningAudit"></div>
                                <span x-text="isRunningAudit ? 'Running Audit...' : 'Run Security Audit'"></span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Security Metrics -->
                    <div>
                        <h4 class="font-semibold text-gray-900 mb-3">Security Metrics</h4>
                        <div class="security-metrics">
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Security Score</span>
                                <span class="metric-value" x-text="securityMetrics.securityScore"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Vulnerabilities</span>
                                <span class="metric-value" x-text="securityMetrics.vulnerabilities"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Threats Blocked</span>
                                <span class="metric-value" x-text="securityMetrics.threatsBlocked"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Encryption Level</span>
                                <span class="metric-value" x-text="securityMetrics.encryptionLevel"></span>
                            </div>
                            <div class="metric-item">
                                <span class="text-sm text-gray-600">Compliance Score</span>
                                <span class="metric-value" x-text="securityMetrics.complianceScore"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Security Feedback -->
        <div class="security-feedback" 
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
        function advancedSecurity() {
            return {
                // State
                securityLevel: 'high',
                zkProofStatus: 'inactive',
                multiSigStatus: 'inactive',
                auditStatus: 'passed',
                
                // Enhanced Security
                threatDetection: true,
                threats: [
                    {
                        id: 1,
                        type: 'SQL Injection',
                        severity: 'High',
                        status: 'Blocked'
                    },
                    {
                        id: 2,
                        type: 'XSS Attack',
                        severity: 'Medium',
                        status: 'Blocked'
                    },
                    {
                        id: 3,
                        type: 'CSRF Attack',
                        severity: 'Low',
                        status: 'Blocked'
                    }
                ],
                
                // Zero-knowledge Proofs
                selectedZKProof: 'zk-snark',
                zkProofData: '',
                isGeneratingProof: false,
                zkProofMetrics: {
                    proofSize: '2.5 KB',
                    verificationTime: '15ms',
                    trustedSetup: 'Required'
                },
                
                // Multi-signature
                multiSigConfig: {
                    requiredSignatures: 2,
                    totalSigners: 3
                },
                isSettingUpMultiSig: false,
                
                // Security Auditing
                lastAuditDate: '2025-01-15',
                nextAuditDate: '2025-04-15',
                auditorName: 'Security Audit Inc.',
                isRunningAudit: false,
                securityMetrics: {
                    securityScore: '95/100',
                    vulnerabilities: 0,
                    threatsBlocked: 1,247,
                    encryptionLevel: 'AES-256',
                    complianceScore: '98/100'
                },
                
                // Feedback
                showFeedback: false,
                feedbackType: 'success',
                feedbackTitle: '',
                feedbackMessage: '',
                
                // Computed
                get multiSigThreshold() {
                    return `${this.multiSigConfig.requiredSignatures}/${this.multiSigConfig.totalSigners}`;
                },
                
                // Initialize
                init() {
                    this.initializeSecurity();
                    this.initializeZKProofs();
                    this.initializeMultiSig();
                    this.initializeAuditing();
                },

                // Security Initialization
                initializeSecurity() {
                    this.securityLevel = 'high';
                    console.log('Enhanced security initialized');
                },

                // ZK Proofs Initialization
                initializeZKProofs() {
                    this.zkProofStatus = 'active';
                    console.log('Zero-knowledge proofs initialized');
                },

                // Multi-signature Initialization
                initializeMultiSig() {
                    this.multiSigStatus = 'active';
                    console.log('Multi-signature initialized');
                },

                // Auditing Initialization
                initializeAuditing() {
                    this.auditStatus = 'passed';
                    console.log('Security auditing initialized');
                },

                // Toggle Threat Detection
                toggleThreatDetection() {
                    this.threatDetection = !this.threatDetection;
                    this.showSecurityFeedback(
                        this.threatDetection ? 'success' : 'warning',
                        'Threat Detection',
                        this.threatDetection ? 'Threat detection enabled' : 'Threat detection disabled'
                    );
                },

                // Generate ZK Proof
                async generateZKProof() {
                    if (!this.zkProofData) return;
                    
                    this.isGeneratingProof = true;
                    
                    try {
                        // Simulate ZK proof generation
                        await new Promise(resolve => setTimeout(resolve, 3000));
                        
                        this.showSecurityFeedback('success', 'ZK Proof Generated', `${this.selectedZKProof} proof generated successfully`);
                    } catch (error) {
                        console.error('ZK proof generation error:', error);
                        this.showSecurityFeedback('error', 'ZK Proof Failed', error.message);
                    } finally {
                        this.isGeneratingProof = false;
                    }
                },

                // Setup Multi-signature
                async setupMultiSig() {
                    this.isSettingUpMultiSig = true;
                    
                    try {
                        // Simulate multi-sig setup
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        this.showSecurityFeedback('success', 'Multi-sig Setup', `Multi-signature setup with ${this.multiSigThreshold} threshold`);
                    } catch (error) {
                        console.error('Multi-sig setup error:', error);
                        this.showSecurityFeedback('error', 'Multi-sig Setup Failed', error.message);
                    } finally {
                        this.isSettingUpMultiSig = false;
                    }
                },

                // Run Security Audit
                async runSecurityAudit() {
                    this.isRunningAudit = true;
                    
                    try {
                        // Simulate security audit
                        await new Promise(resolve => setTimeout(resolve, 5000));
                        
                        this.auditStatus = 'passed';
                        this.securityMetrics.securityScore = '95/100';
                        this.securityMetrics.vulnerabilities = 0;
                        
                        this.showSecurityFeedback('success', 'Security Audit Complete', 'Security audit passed successfully');
                    } catch (error) {
                        console.error('Security audit error:', error);
                        this.showSecurityFeedback('error', 'Security Audit Failed', error.message);
                    } finally {
                        this.isRunningAudit = false;
                    }
                },

                // Show Security Feedback
                showSecurityFeedback(type, title, message) {
                    this.feedbackType = type;
                    this.feedbackTitle = title;
                    this.feedbackMessage = message;
                    this.showFeedback = true;
                    
                    setTimeout(() => {
                        this.showFeedback = false;
                    }, 3000);
                },

                // Navigation
                goToBlockchain() {
                    window.location.href = '/app/blockchain-integration';
                },

                goToIntegration() {
                    window.location.href = '/app/system-integration';
                },

                goToFuture() {
                    window.location.href = '/app/future-enhancements';
                }
            };
        }
    </script>
</body>
</html>
