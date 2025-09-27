<!-- Admin Security Content -->
<div x-data="adminSecurity()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading security data...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading security data</p>
                <p x-text="error"></p>
                <button @click="init()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Security Dashboard</h1>
                    <p class="text-gray-600 mt-1">Monitor and manage system security</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button @click="refreshData()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh</span>
                    </button>
                    <button @click="runSecurityScan()" 
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-shield-alt mr-2"></i>Run Security Scan
                    </button>
                </div>
            </div>

            <!-- Security Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shield-check text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Security Score</p>
                            <p class="text-2xl font-bold text-green-900" x-text="securityScore"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-blue-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-shield text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Active Sessions</p>
                            <p class="text-2xl font-bold text-blue-900" x-text="stats.activeSessions || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-yellow-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-600">Security Alerts</p>
                            <p class="text-2xl font-bold text-yellow-900" x-text="stats.securityAlerts || 0"></p>
                        </div>
                    </div>
                </div>
                <div class="bg-red-50 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-ban text-red-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-600">Blocked IPs</p>
                            <p class="text-2xl font-bold text-red-900" x-text="stats.blockedIPs || 0"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Security Events -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Security Events</h3>
                    <button @click="viewAllEvents()" class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="event in securityEvents.slice(0, 5)" :key="event.id">
                        <div class="flex items-start space-x-3 p-3 rounded-lg"
                             :class="getEventBgColor(event.severity)">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                     :class="getEventIconBg(event.type)">
                                    <i :class="event.icon" 
                                       :class="getEventIconColor(event.type)"
                                       class="text-sm"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900" x-text="event.title"></p>
                                <p class="text-sm text-gray-600" x-text="event.description"></p>
                                <div class="flex items-center space-x-4 mt-1">
                                    <span class="text-xs text-gray-500" x-text="event.timestamp"></span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getSeverityBadgeColor(event.severity)"
                                          x-text="event.severity"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Failed Login Attempts -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Failed Login Attempts</h3>
                    <span class="text-sm text-gray-500">Last 24 hours</span>
                </div>
                <div class="space-y-3">
                    <template x-for="attempt in failedLogins.slice(0, 5)" :key="attempt.id">
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-times text-red-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900" x-text="attempt.email"></p>
                                    <p class="text-xs text-gray-500" x-text="attempt.ip"></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500" x-text="attempt.timestamp"></p>
                                <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full"
                                      x-text="`${attempt.attempts} attempts`"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Security Settings</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Authentication Settings -->
                <div class="space-y-4">
                    <h4 class="text-md font-medium text-gray-900">Authentication</h4>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Two-Factor Authentication</p>
                                <p class="text-xs text-gray-500">Require 2FA for all users</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.twoFactorAuth" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Password Complexity</p>
                                <p class="text-xs text-gray-500">Require strong passwords</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.passwordComplexity" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Session Timeout</p>
                                <p class="text-xs text-gray-500">Auto-logout after inactivity</p>
                            </div>
                            <select x-model="settings.sessionTimeout" 
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="15">15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="120">2 hours</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Access Control -->
                <div class="space-y-4">
                    <h4 class="text-md font-medium text-gray-900">Access Control</h4>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">IP Whitelist</p>
                                <p class="text-xs text-gray-500">Restrict access by IP address</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.ipWhitelist" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Rate Limiting</p>
                                <p class="text-xs text-gray-500">Limit API requests per minute</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.rateLimiting" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Audit Logging</p>
                                <p class="text-xs text-gray-500">Log all user activities</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" x-model="settings.auditLogging" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Settings Button -->
            <div class="mt-6 flex justify-end">
                <button @click="saveSettings()" 
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Save Settings
                </button>
            </div>
        </div>

        <!-- Security Audit Log -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Security Audit Log</h3>
                <div class="flex items-center space-x-4">
                    <select x-model="auditFilter" @change="filterAuditLog()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Events</option>
                        <option value="login">Login Events</option>
                        <option value="permission">Permission Changes</option>
                        <option value="security">Security Events</option>
                        <option value="admin">Admin Actions</option>
                    </select>
                    <button @click="exportAuditLog()" 
                            class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-download mr-2"></i>Export
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="log in filteredAuditLog" :key="log.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.timestamp"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.user"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.event"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="log.ip"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full"
                                          :class="getStatusBadgeColor(log.status)"
                                          x-text="log.status"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function adminSecurity() {
    return {
        loading: true,
        error: null,
        refreshing: false,
        
        // Data
        securityScore: 85,
        stats: {
            activeSessions: 0,
            securityAlerts: 0,
            blockedIPs: 0
        },
        
        securityEvents: [],
        failedLogins: [],
        auditLog: [],
        filteredAuditLog: [],
        
        // Settings
        settings: {
            twoFactorAuth: true,
            passwordComplexity: true,
            sessionTimeout: 30,
            ipWhitelist: false,
            rateLimiting: true,
            auditLogging: true
        },
        
        // Filters
        auditFilter: '',

        async init() {
            await this.loadSecurityData();
            this.filterAuditLog();
        },

        async loadSecurityData() {
            try {
                this.loading = true;
                this.error = null;
                
                // Mock data for demonstration
                this.stats = {
                    activeSessions: 142,
                    securityAlerts: 3,
                    blockedIPs: 12
                };

                this.securityEvents = [
                    {
                        id: 1,
                        title: 'Successful Login',
                        description: 'User john.doe@example.com logged in from 192.168.1.100',
                        timestamp: '2 minutes ago',
                        type: 'login',
                        severity: 'low',
                        icon: 'fas fa-sign-in-alt'
                    },
                    {
                        id: 2,
                        title: 'Failed Login Attempt',
                        description: 'Multiple failed login attempts from 203.0.113.1',
                        timestamp: '15 minutes ago',
                        type: 'security',
                        severity: 'high',
                        icon: 'fas fa-exclamation-triangle'
                    },
                    {
                        id: 3,
                        title: 'Permission Change',
                        description: 'Admin changed user permissions for jane.smith@example.com',
                        timestamp: '1 hour ago',
                        type: 'permission',
                        severity: 'medium',
                        icon: 'fas fa-user-cog'
                    },
                    {
                        id: 4,
                        title: 'Security Scan Completed',
                        description: 'Automated security scan found 2 minor issues',
                        timestamp: '2 hours ago',
                        type: 'security',
                        severity: 'low',
                        icon: 'fas fa-shield-alt'
                    },
                    {
                        id: 5,
                        title: 'Suspicious Activity',
                        description: 'Unusual API access pattern detected',
                        timestamp: '3 hours ago',
                        type: 'security',
                        severity: 'high',
                        icon: 'fas fa-eye'
                    }
                ];

                this.failedLogins = [
                    {
                        id: 1,
                        email: 'admin@hacker.com',
                        ip: '203.0.113.1',
                        attempts: 5,
                        timestamp: '15 minutes ago'
                    },
                    {
                        id: 2,
                        email: 'test@example.com',
                        ip: '198.51.100.1',
                        attempts: 3,
                        timestamp: '1 hour ago'
                    },
                    {
                        id: 3,
                        email: 'user@test.com',
                        ip: '192.0.2.1',
                        attempts: 2,
                        timestamp: '2 hours ago'
                    }
                ];

                this.auditLog = [
                    {
                        id: 1,
                        timestamp: '2024-01-15 14:30:25',
                        user: 'john.doe@example.com',
                        event: 'User Login',
                        ip: '192.168.1.100',
                        status: 'success'
                    },
                    {
                        id: 2,
                        timestamp: '2024-01-15 14:25:10',
                        user: 'admin@system.com',
                        event: 'Permission Change',
                        ip: '192.168.1.50',
                        status: 'success'
                    },
                    {
                        id: 3,
                        timestamp: '2024-01-15 14:20:45',
                        user: 'hacker@bad.com',
                        event: 'Failed Login',
                        ip: '203.0.113.1',
                        status: 'failed'
                    },
                    {
                        id: 4,
                        timestamp: '2024-01-15 14:15:30',
                        user: 'jane.smith@example.com',
                        event: 'Password Change',
                        ip: '192.168.1.75',
                        status: 'success'
                    },
                    {
                        id: 5,
                        timestamp: '2024-01-15 14:10:15',
                        user: 'system',
                        event: 'Security Scan',
                        ip: '127.0.0.1',
                        status: 'success'
                    }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading security data:', error);
                this.error = error.message;
                this.loading = false;
            }
        },

        getEventBgColor(severity) {
            const colors = {
                'low': 'bg-green-50',
                'medium': 'bg-yellow-50',
                'high': 'bg-red-50'
            };
            return colors[severity] || 'bg-gray-50';
        },

        getEventIconBg(type) {
            const colors = {
                'login': 'bg-blue-100',
                'security': 'bg-red-100',
                'permission': 'bg-purple-100',
                'admin': 'bg-gray-100'
            };
            return colors[type] || 'bg-gray-100';
        },

        getEventIconColor(type) {
            const colors = {
                'login': 'text-blue-600',
                'security': 'text-red-600',
                'permission': 'text-purple-600',
                'admin': 'text-gray-600'
            };
            return colors[type] || 'text-gray-600';
        },

        getSeverityBadgeColor(severity) {
            const colors = {
                'low': 'bg-green-100 text-green-800',
                'medium': 'bg-yellow-100 text-yellow-800',
                'high': 'bg-red-100 text-red-800'
            };
            return colors[severity] || 'bg-gray-100 text-gray-800';
        },

        getStatusBadgeColor(status) {
            const colors = {
                'success': 'bg-green-100 text-green-800',
                'failed': 'bg-red-100 text-red-800',
                'warning': 'bg-yellow-100 text-yellow-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        filterAuditLog() {
            if (this.auditFilter) {
                this.filteredAuditLog = this.auditLog.filter(log => 
                    log.event.toLowerCase().includes(this.auditFilter.toLowerCase())
                );
            } else {
                this.filteredAuditLog = this.auditLog;
            }
        },

        viewAllEvents() {
            console.log('View all security events');
            // Implement view all events functionality
        },

        runSecurityScan() {
            console.log('Running security scan...');
            // Implement security scan functionality
        },

        saveSettings() {
            console.log('Saving security settings:', this.settings);
            // Implement save settings functionality
        },

        exportAuditLog() {
            console.log('Exporting audit log...');
            // Implement export functionality
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