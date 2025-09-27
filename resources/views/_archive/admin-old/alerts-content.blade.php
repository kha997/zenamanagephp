<!-- Admin Alerts Content -->
<div x-data="adminAlerts()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading alerts...</span>
    </div>

    <!-- Main Content -->
    <div x-show="!loading" class="space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">System Alerts</h1>
                    <p class="text-gray-600 mt-1">Monitor and manage system alerts and notifications</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh</span>
                    </button>
                    <button @click="markAllAsRead()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check-double mr-2"></i>Mark All Read
                    </button>
                </div>
            </div>

            <!-- Alert Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-600">Critical Alerts</p>
                            <p class="text-2xl font-bold text-red-900" x-text="stats.criticalAlerts || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-600">Warning Alerts</p>
                            <p class="text-2xl font-bold text-yellow-900" x-text="stats.warningAlerts || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-info-circle text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Info Alerts</p>
                            <p class="text-2xl font-bold text-blue-900" x-text="stats.infoAlerts || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Resolved</p>
                            <p class="text-2xl font-bold text-green-900" x-text="stats.resolvedAlerts || 0"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts List -->
        <div class="space-y-4">
            <template x-for="alert in alerts" :key="alert.id">
                <div class="bg-white rounded-lg shadow-lg p-6 border-l-4"
                     :class="getAlertBorderColor(alert.severity)">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-4 flex-1">
                            <!-- Alert Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                     :class="getAlertIconBg(alert.severity)">
                                    <i :class="getAlertIcon(alert.severity)" 
                                       :class="getAlertIconColor(alert.severity)"
                                       class="text-lg"></i>
                                </div>
                            </div>
                            
                            <!-- Alert Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900" x-text="alert.title"></h3>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getSeverityBadgeColor(alert.severity)"
                                          x-text="alert.severity"></span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getStatusBadgeColor(alert.status)"
                                          x-text="alert.status"></span>
                                </div>
                                
                                <p class="text-gray-600 mb-3" x-text="alert.description"></p>
                                
                                <div class="flex items-center space-x-6 text-sm text-gray-500">
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-clock"></i>
                                        <span x-text="alert.timestamp"></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-tag"></i>
                                        <span x-text="alert.category"></span>
                                    </div>
                                    <div class="flex items-center space-x-1">
                                        <i class="fas fa-user"></i>
                                        <span x-text="alert.source"></span>
                                    </div>
                                </div>
                                
                                <!-- Alert Actions -->
                                <div class="mt-4 flex items-center space-x-3">
                                    <template x-if="alert.status === 'active'">
                                        <button @click="acknowledgeAlert(alert)" 
                                                class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded text-sm hover:bg-yellow-200 transition-colors">
                                            <i class="fas fa-check mr-1"></i>Acknowledge
                                        </button>
                                    </template>
                                    <template x-if="alert.status === 'acknowledged'">
                                        <button @click="resolveAlert(alert)" 
                                                class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200 transition-colors">
                                            <i class="fas fa-check-double mr-1"></i>Resolve
                                        </button>
                                    </template>
                                    <button @click="deleteAlert(alert)" 
                                            class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm hover:bg-red-200 transition-colors">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
function adminAlerts() {
    return {
        loading: true,
        refreshing: false,
        
        // Data
        alerts: [],
        stats: {
            criticalAlerts: 0,
            warningAlerts: 0,
            infoAlerts: 0,
            resolvedAlerts: 0
        },

        async init() {
            await this.loadAlerts();
            this.calculateStats();
        },

        async loadAlerts() {
            try {
                this.loading = true;
                
                // Mock data for demonstration
                this.alerts = [
                    {
                        id: 1,
                        title: 'High CPU Usage Detected',
                        description: 'CPU usage has exceeded 90% for more than 5 minutes on server-web-01',
                        timestamp: '2 minutes ago',
                        severity: 'critical',
                        status: 'active',
                        category: 'performance',
                        source: 'Monitoring System'
                    },
                    {
                        id: 2,
                        title: 'Database Connection Pool Exhausted',
                        description: 'All database connections are in use, new requests are being queued',
                        timestamp: '15 minutes ago',
                        severity: 'warning',
                        status: 'acknowledged',
                        category: 'system',
                        source: 'Database Monitor'
                    },
                    {
                        id: 3,
                        title: 'Storage Space Low',
                        description: 'Disk usage on /var/log has reached 85% capacity',
                        timestamp: '1 hour ago',
                        severity: 'warning',
                        status: 'active',
                        category: 'storage',
                        source: 'Storage Monitor'
                    },
                    {
                        id: 4,
                        title: 'Security Scan Completed',
                        description: 'Automated security scan completed with 2 minor vulnerabilities found',
                        timestamp: '2 hours ago',
                        severity: 'info',
                        status: 'resolved',
                        category: 'security',
                        source: 'Security Scanner'
                    }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading alerts:', error);
                this.loading = false;
            }
        },

        calculateStats() {
            this.stats = {
                criticalAlerts: this.alerts.filter(a => a.severity === 'critical' && a.status !== 'resolved').length,
                warningAlerts: this.alerts.filter(a => a.severity === 'warning' && a.status !== 'resolved').length,
                infoAlerts: this.alerts.filter(a => a.severity === 'info' && a.status !== 'resolved').length,
                resolvedAlerts: this.alerts.filter(a => a.status === 'resolved').length
            };
        },

        getAlertBorderColor(severity) {
            const colors = {
                'critical': 'border-red-500',
                'warning': 'border-yellow-500',
                'info': 'border-blue-500'
            };
            return colors[severity] || 'border-gray-400';
        },

        getAlertIconBg(severity) {
            const colors = {
                'critical': 'bg-red-100',
                'warning': 'bg-yellow-100',
                'info': 'bg-blue-100'
            };
            return colors[severity] || 'bg-gray-100';
        },

        getAlertIcon(severity) {
            const icons = {
                'critical': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle'
            };
            return icons[severity] || 'fas fa-bell';
        },

        getAlertIconColor(severity) {
            const colors = {
                'critical': 'text-red-600',
                'warning': 'text-yellow-600',
                'info': 'text-blue-600'
            };
            return colors[severity] || 'text-gray-600';
        },

        getSeverityBadgeColor(severity) {
            const colors = {
                'critical': 'bg-red-100 text-red-800',
                'warning': 'bg-yellow-100 text-yellow-800',
                'info': 'bg-blue-100 text-blue-800'
            };
            return colors[severity] || 'bg-gray-100 text-gray-800';
        },

        getStatusBadgeColor(status) {
            const colors = {
                'active': 'bg-red-100 text-red-800',
                'acknowledged': 'bg-yellow-100 text-yellow-800',
                'resolved': 'bg-green-100 text-green-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        acknowledgeAlert(alert) {
            console.log('Acknowledging alert:', alert);
            alert.status = 'acknowledged';
            this.calculateStats();
        },

        resolveAlert(alert) {
            console.log('Resolving alert:', alert);
            alert.status = 'resolved';
            this.calculateStats();
        },

        deleteAlert(alert) {
            if (confirm(`Are you sure you want to delete "${alert.title}"?`)) {
                console.log('Deleting alert:', alert);
                const index = this.alerts.findIndex(a => a.id === alert.id);
                if (index > -1) {
                    this.alerts.splice(index, 1);
                    this.calculateStats();
                }
            }
        },

        markAllAsRead() {
            console.log('Marking all alerts as read');
            this.alerts.forEach(alert => {
                if (alert.status === 'active') {
                    alert.status = 'acknowledged';
                }
            });
            this.calculateStats();
        },

        refreshData() {
            this.refreshing = true;
            setTimeout(() => {
                this.init();
                this.refreshing = false;
            }, 1000);
        }
    }
}
</script>