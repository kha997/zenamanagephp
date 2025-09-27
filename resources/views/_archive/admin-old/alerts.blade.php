<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Alerts - Super Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100" x-data="alertsManagement()">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center gap-4">
                        <a href="/admin/super-admin-dashboard" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">System Alerts</h1>
                            <p class="text-gray-600 mt-1">Monitor and manage system alerts</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Alert Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Critical Alerts</p>
                            <p class="text-3xl font-bold text-red-600" x-text="statistics.critical"></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-red-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">High Priority</p>
                            <p class="text-3xl font-bold text-orange-600" x-text="statistics.high"></p>
                        </div>
                        <i class="fas fa-exclamation-circle text-orange-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Medium Priority</p>
                            <p class="text-3xl font-bold text-yellow-600" x-text="statistics.medium"></p>
                        </div>
                        <i class="fas fa-info-circle text-yellow-600 text-4xl"></i>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Low Priority</p>
                            <p class="text-3xl font-bold text-blue-600" x-text="statistics.low"></p>
                        </div>
                        <i class="fas fa-info text-blue-600 text-4xl"></i>
                    </div>
                </div>
            </div>

            <!-- Alerts Table -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-900">All Alerts</h3>
                        <div class="flex gap-2">
                            <select x-model="selectedSeverity" @change="filterAlerts()" class="border border-gray-300 rounded-lg px-3 py-2">
                                <option value="">All Severities</option>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                            <button @click="showCreateModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Create Alert
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alert</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <template x-for="alert in filteredAlerts" :key="alert.id">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i :class="alert.icon + ' text-' + alert.color + '-600 mr-3'"></i>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900" x-text="alert.title"></div>
                                                <div class="text-sm text-gray-500" x-text="alert.description"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="'px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' + getSeverityClass(alert.severity)">
                                            <span x-text="alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)"></span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" x-text="alert.source"></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="'px-2 inline-flex text-xs leading-5 font-semibold rounded-full ' + getStatusClass(alert.status)">
                                            <span x-text="alert.status.charAt(0).toUpperCase() + alert.status.slice(1)"></span>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="formatDate(alert.created_at)"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <template x-if="alert.status === 'active'">
                                            <div>
                                                <button @click="updateAlertStatus(alert.id, 'acknowledged')" class="text-blue-600 hover:text-blue-900 mr-3">Acknowledge</button>
                                                <button @click="updateAlertStatus(alert.id, 'resolved')" class="text-green-600 hover:text-green-900 mr-3">Resolve</button>
                                            </div>
                                        </template>
                                        <template x-if="alert.status === 'acknowledged'">
                                            <div>
                                                <button @click="updateAlertStatus(alert.id, 'resolved')" class="text-green-600 hover:text-green-900 mr-3">Resolve</button>
                                            </div>
                                        </template>
                                        <button @click="deleteAlert(alert.id)" class="text-red-600 hover:text-red-900">Delete</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Alert Modal -->
    <div x-show="showCreateModal" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showCreateModal = false"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Create New Alert</h3>
                        <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form @submit.prevent="createAlert()">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alert Title</label>
                                <input type="text" x-model="newAlert.title" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="Enter alert title">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea x-model="newAlert.description" required rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                          placeholder="Enter alert description"></textarea>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                                    <select x-model="newAlert.severity" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Severity</option>
                                        <option value="critical">Critical</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                                    <input type="text" x-model="newAlert.source" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="e.g., Server-01">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                <select x-model="newAlert.category"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                                    <option value="manual">Manual</option>
                                    <option value="server">Server</option>
                                    <option value="database">Database</option>
                                    <option value="security">Security</option>
                                    <option value="network">Network</option>
                                    <option value="storage">Storage</option>
                                    <option value="backup">Backup</option>
                                    <option value="performance">Performance</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" @click="showCreateModal = false"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Create Alert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function alertsManagement() {
        return {
            // State
            alerts: [],
            filteredAlerts: [],
            statistics: {
                critical: 0,
                high: 0,
                medium: 0,
                low: 0
            },
            selectedSeverity: '',
            showCreateModal: false,
            newAlert: {
                title: '',
                description: '',
                severity: '',
                source: '',
                category: 'manual'
            },

            // Methods
            async loadAlerts() {
                try {
                    const response = await fetch('/api/alerts/');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.alerts = result.data.alerts;
                        this.filteredAlerts = this.alerts;
                    }
                } catch (error) {
                    console.error('Failed to load alerts:', error);
                }
            },

            async loadStatistics() {
                try {
                    const response = await fetch('/api/alerts/statistics');
                    const result = await response.json();
                    
                    if (result.success) {
                        this.statistics = result.data;
                    }
                } catch (error) {
                    console.error('Failed to load statistics:', error);
                }
            },

            filterAlerts() {
                if (!this.selectedSeverity) {
                    this.filteredAlerts = this.alerts;
                } else {
                    this.filteredAlerts = this.alerts.filter(alert => 
                        alert.severity === this.selectedSeverity
                    );
                }
            },

            async createAlert() {
                try {
                    const response = await fetch('/api/alerts/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(this.newAlert)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Add new alert to list
                        this.alerts.unshift(result.data.alert);
                        this.filteredAlerts = this.alerts;
                        
                        // Reset form
                        this.newAlert = {
                            title: '',
                            description: '',
                            severity: '',
                            source: '',
                            category: 'manual'
                        };
                        
                        this.showCreateModal = false;
                        alert('Alert created successfully!');
                        
                        // Reload statistics
                        this.loadStatistics();
                    } else {
                        alert('Failed to create alert: ' + result.message);
                    }
                } catch (error) {
                    console.error('Create alert error:', error);
                    alert('Failed to create alert: ' + error.message);
                }
            },

            async updateAlertStatus(alertId, newStatus) {
                try {
                    const response = await fetch('/api/alerts/status', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            alert_id: alertId,
                            status: newStatus
                        })
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        // Update local data
                        const alert = this.alerts.find(a => a.id === alertId);
                        if (alert) {
                            alert.status = newStatus;
                        }
                        alert('Alert status updated successfully!');
                    } else {
                        alert('Failed to update alert status: ' + result.message);
                    }
                } catch (error) {
                    console.error('Update status error:', error);
                    alert('Failed to update alert status: ' + error.message);
                }
            },

            async deleteAlert(alertId) {
                if (confirm('Are you sure you want to delete this alert?')) {
                    try {
                        const response = await fetch('/api/alerts/delete', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({
                                alert_id: alertId
                            })
                        });

                        const result = await response.json();
                        
                        if (result.success) {
                            // Remove from local data
                            this.alerts = this.alerts.filter(a => a.id !== alertId);
                            this.filteredAlerts = this.alerts;
                            alert('Alert deleted successfully!');
                            
                            // Reload statistics
                            this.loadStatistics();
                        } else {
                            alert('Failed to delete alert: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Delete alert error:', error);
                        alert('Failed to delete alert: ' + error.message);
                    }
                }
            },

            getSeverityClass(severity) {
                const classes = {
                    'critical': 'bg-red-100 text-red-800',
                    'high': 'bg-orange-100 text-orange-800',
                    'medium': 'bg-yellow-100 text-yellow-800',
                    'low': 'bg-blue-100 text-blue-800'
                };
                return classes[severity] || 'bg-gray-100 text-gray-800';
            },

            getStatusClass(status) {
                const classes = {
                    'active': 'bg-yellow-100 text-yellow-800',
                    'acknowledged': 'bg-blue-100 text-blue-800',
                    'resolved': 'bg-green-100 text-green-800',
                    'closed': 'bg-gray-100 text-gray-800'
                };
                return classes[status] || 'bg-gray-100 text-gray-800';
            },

            formatDate(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                const diffDays = Math.floor(diffHours / 24);

                if (diffDays > 0) {
                    return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
                } else if (diffHours > 0) {
                    return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                } else {
                    const diffMinutes = Math.floor(diffMs / (1000 * 60));
                    return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
                }
            },

            // Initialize
            init() {
                this.loadAlerts();
                this.loadStatistics();
            }
        }
    }
    </script>
</body>
</html>
