<?php $__env->startSection('title', 'Email Configuration'); ?>
<?php $__env->startSection('page-title', 'Email Configuration'); ?>
<?php $__env->startSection('page-description', 'Configure email providers and settings'); ?>
<?php $__env->startSection('user-initials', 'AD'); ?>
<?php $__env->startSection('user-name', 'Admin'); ?>
<?php $__env->startSection('current-route', 'admin'); ?>

<?php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Admin Panel',
        'url' => '/admin'
    ],
    [
        'label' => 'Email Configuration',
        'url' => '/admin/email-config'
    ]
];
$currentRoute = 'admin';
?>

<?php $__env->startSection('content'); ?>
<div x-data="emailConfig()" class="space-y-6">
    <!-- Header -->
    <div class="dashboard-card p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-cog text-blue-600 mr-2"></i>
                    Email Provider Configuration
                </h3>
                <p class="text-sm text-gray-600 mt-1">Configure SMTP settings and email providers</p>
            </div>
            <div class="flex space-x-3">
                <button 
                    @click="getStatistics()"
                    class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center"
                >
                    <i class="fas fa-chart-bar mr-2"></i>
                    Statistics
                </button>
                <button 
                    @click="clearCache()"
                    class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center"
                >
                    <i class="fas fa-trash mr-2"></i>
                    Clear Cache
                </button>
            </div>
        </div>
    </div>

    <!-- Provider Selection -->
    <div class="dashboard-card p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Select Email Provider</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="(provider, key) in providers" :key="key">
                <div 
                    @click="selectProvider(key)"
                    :class="{
                        'border-blue-500 bg-blue-50': selectedProvider === key,
                        'border-gray-200 hover:border-gray-300': selectedProvider !== key
                    }"
                    class="p-4 border-2 rounded-lg cursor-pointer transition-colors"
                >
                    <div class="flex items-center mb-2">
                        <i :class="provider.icon + ' text-' + provider.color + '-600 mr-2'"></i>
                        <span class="font-medium text-gray-900" x-text="provider.name"></span>
                    </div>
                    <p class="text-sm text-gray-600" x-text="provider.description"></p>
                </div>
            </template>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="dashboard-card p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Configuration Settings</h4>
        <form @submit.prevent="updateConfig()">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <!-- SMTP Host -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-server text-gray-400 mr-1"></i>
                            SMTP Host
                        </label>
                        <input 
                            type="text" 
                            x-model="config.host"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="smtp.gmail.com"
                            required
                        >
                    </div>

                    <!-- Port -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-plug text-gray-400 mr-1"></i>
                            Port
                        </label>
                        <input 
                            type="number" 
                            x-model="config.port"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="587"
                            required
                        >
                    </div>

                    <!-- Encryption -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-gray-400 mr-1"></i>
                            Encryption
                        </label>
                        <select 
                            x-model="config.encryption"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                    </div>

                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i>
                            Username
                        </label>
                        <input 
                            type="text" 
                            x-model="config.username"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="your-email@gmail.com"
                            required
                        >
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-key text-gray-400 mr-1"></i>
                            Password / API Key
                        </label>
                        <input 
                            type="password" 
                            x-model="config.password"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Your password or API key"
                            required
                        >
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <!-- From Address -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-gray-400 mr-1"></i>
                            From Email Address
                        </label>
                        <input 
                            type="email" 
                            x-model="config.from_address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="noreply@yourcompany.com"
                            required
                        >
                    </div>

                    <!-- From Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-signature text-gray-400 mr-1"></i>
                            From Name
                        </label>
                        <input 
                            type="text" 
                            x-model="config.from_name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="Your Company Name"
                            required
                        >
                    </div>

                    <!-- Test Email -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-paper-plane text-gray-400 mr-1"></i>
                            Test Email Address
                        </label>
                        <input 
                            type="email" 
                            x-model="config.test_email"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="test@example.com"
                        >
                    </div>

                    <!-- Queue Settings -->
                    <div class="space-y-3">
                        <h5 class="font-medium text-gray-900">Queue Settings</h5>
                        
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                x-model="config.queue_enabled"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <label class="ml-2 text-sm text-gray-700">Enable Email Queuing</label>
                        </div>

                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                x-model="config.cache_enabled"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            >
                            <label class="ml-2 text-sm text-gray-700">Enable Template Caching</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    type="button"
                    @click="testConfig()"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
                    :disabled="loading"
                >
                    <i class="fas fa-flask mr-2"></i>
                    Test Configuration
                </button>
                <button 
                    type="submit"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                    :disabled="loading"
                >
                    <i class="fas fa-save mr-2"></i>
                    Save Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Panel -->
    <div x-show="showStatistics" class="dashboard-card p-6">
        <h4 class="text-md font-semibold text-gray-900 mb-4">Email Statistics</h4>
        <div x-show="statistics" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Queue Status -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h5 class="font-medium text-blue-900 mb-2">Queue Status</h5>
                <div class="text-sm text-blue-700">
                    <p>Enabled: <span x-text="statistics?.queue_status?.enabled ? 'Yes' : 'No'"></span></p>
                    <p>Connection: <span x-text="statistics?.queue_status?.connection"></span></p>
                    <p>Queue: <span x-text="statistics?.queue_status?.queue_name"></span></p>
                </div>
            </div>

            <!-- Cache Status -->
            <div class="bg-green-50 p-4 rounded-lg">
                <h5 class="font-medium text-green-900 mb-2">Cache Status</h5>
                <div class="text-sm text-green-700">
                    <p>Enabled: <span x-text="statistics?.cache_status?.enabled ? 'Yes' : 'No'"></span></p>
                    <p>Driver: <span x-text="statistics?.cache_status?.driver"></span></p>
                    <p>Cached Templates: <span x-text="statistics?.cache_status?.cached_templates"></span></p>
                </div>
            </div>

            <!-- Provider Info -->
            <div class="bg-purple-50 p-4 rounded-lg">
                <h5 class="font-medium text-purple-900 mb-2">Current Provider</h5>
                <div class="text-sm text-purple-700">
                    <p>Provider: <span x-text="statistics?.provider_info?.current"></span></p>
                    <p>Host: <span x-text="statistics?.provider_info?.host"></span></p>
                    <p>Port: <span x-text="statistics?.provider_info?.port"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function emailConfig() {
    return {
        providers: <?php echo json_encode($providers, 15, 512) ?>,
        selectedProvider: 'smtp',
        config: <?php echo json_encode($currentConfig, 15, 512) ?>,
        loading: false,
        showStatistics: false,
        statistics: null,

        init() {
            this.selectedProvider = this.config.provider || 'smtp';
            this.updateProviderConfig();
        },

        selectProvider(provider) {
            this.selectedProvider = provider;
            this.config.provider = provider;
            this.updateProviderConfig();
        },

        updateProviderConfig() {
            const providerConfigs = {
                'gmail': {
                    host: 'smtp.gmail.com',
                    port: 587,
                    encryption: 'tls'
                },
                'sendgrid': {
                    host: 'smtp.sendgrid.net',
                    port: 587,
                    encryption: 'tls'
                },
                'outlook': {
                    host: 'smtp-mail.outlook.com',
                    port: 587,
                    encryption: 'tls'
                },
                'smtp': {
                    host: '',
                    port: 587,
                    encryption: 'tls'
                }
            };

            const config = providerConfigs[this.selectedProvider] || providerConfigs['smtp'];
            this.config.host = config.host;
            this.config.port = config.port;
            this.config.encryption = config.encryption;
        },

        async updateConfig() {
            this.loading = true;
            
            try {
                const response = await fetch('/admin/email-config/update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(this.config)
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showNotification(data.message, 'success');
                    if (data.test_result) {
                        this.showNotification('Test result: ' + data.test_result.message, data.test_result.success ? 'success' : 'error');
                    }
                } else {
                    this.showNotification(data.message, 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update configuration', 'error');
            } finally {
                this.loading = false;
            }
        },

        async testConfig() {
            if (!this.config.test_email) {
                this.showNotification('Please enter a test email address', 'warning');
                return;
            }

            this.loading = true;
            
            try {
                const response = await fetch('/admin/email-config/test', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        ...this.config,
                        test_email: this.config.test_email
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Test email sent successfully!', 'success');
                } else {
                    this.showNotification('Test failed: ' + data.message, 'error');
                }
            } catch (error) {
                this.showNotification('Failed to send test email', 'error');
            } finally {
                this.loading = false;
            }
        },

        async getStatistics() {
            try {
                const response = await fetch('/admin/email-config/statistics');
                const data = await response.json();
                
                if (data.success) {
                    this.statistics = data.data;
                    this.showStatistics = true;
                } else {
                    this.showNotification('Failed to get statistics', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to get statistics', 'error');
            }
        },

        async clearCache() {
            try {
                const response = await fetch('/admin/email-config/clear-cache', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Cache cleared successfully!', 'success');
                } else {
                    this.showNotification('Failed to clear cache', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to clear cache', 'error');
            }
        },

        showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                type === 'warning' ? 'bg-yellow-600' :
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/email-config.blade.php ENDPATH**/ ?>