<!-- Admin Maintenance Content -->
<div x-data="adminMaintenance()" x-init="init()" class="space-y-6">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading maintenance tools...</span>
    </div>

    <!-- Main Content -->
    <div x-show="!loading" class="space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">System Maintenance & Administration</h1>
                    <p class="text-gray-600 mt-1">Comprehensive system maintenance and administration tools</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm text-green-600 font-medium">System Online</span>
                    </div>
                    <button @click="refreshStatus()" 
                            :disabled="refreshing"
                            class="flex items-center space-x-2 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 disabled:opacity-50">
                        <i class="fas fa-sync-alt" :class="{'animate-spin': refreshing}"></i>
                        <span>Refresh Status</span>
                    </button>
                </div>
            </div>

            <!-- System Status Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gradient-to-r from-green-50 to-green-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-server text-green-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-600">Server Status</p>
                            <p class="text-2xl font-bold text-green-900">Online</p>
                            <p class="text-xs text-green-700">Uptime: 99.9%</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-database text-blue-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-blue-600">Database</p>
                            <p class="text-2xl font-bold text-blue-900">Healthy</p>
                            <p class="text-xs text-blue-700">Size: 2.4 GB</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-hdd text-purple-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-purple-600">Storage</p>
                            <p class="text-2xl font-bold text-purple-900">42%</p>
                            <p class="text-xs text-purple-700">4.2 GB / 10 GB</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg p-4">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-memory text-orange-600"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-orange-600">Memory</p>
                            <p class="text-2xl font-bold text-orange-900">68%</p>
                            <p class="text-xs text-orange-700">6.8 GB / 10 GB</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Maintenance Tools -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Database Maintenance -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Database Maintenance</h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        Healthy
                    </span>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-download text-blue-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Backup Database</p>
                                <p class="text-xs text-gray-500">Last backup: 2 hours ago</p>
                            </div>
                        </div>
                        <button @click="backupDatabase()" 
                                :disabled="maintenanceTasks.backup"
                                class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm hover:bg-blue-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.backup">Backup</span>
                            <span x-show="maintenanceTasks.backup">Running...</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-broom text-green-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Optimize Tables</p>
                                <p class="text-xs text-gray-500">Improve performance</p>
                            </div>
                        </div>
                        <button @click="optimizeTables()" 
                                :disabled="maintenanceTasks.optimize"
                                class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.optimize">Optimize</span>
                            <span x-show="maintenanceTasks.optimize">Running...</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-trash text-red-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Clean Logs</p>
                                <p class="text-xs text-gray-500">Remove old log files</p>
                            </div>
                        </div>
                        <button @click="cleanLogs()" 
                                :disabled="maintenanceTasks.cleanLogs"
                                class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm hover:bg-red-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.cleanLogs">Clean</span>
                            <span x-show="maintenanceTasks.cleanLogs">Running...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- System Maintenance -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System Maintenance</h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                        Stable
                    </span>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-sync text-blue-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Clear Cache</p>
                                <p class="text-xs text-gray-500">Application & system cache</p>
                            </div>
                        </div>
                        <button @click="clearCache()" 
                                :disabled="maintenanceTasks.clearCache"
                                class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm hover:bg-blue-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.clearCache">Clear</span>
                            <span x-show="maintenanceTasks.clearCache">Running...</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-upload text-green-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Update System</p>
                                <p class="text-xs text-gray-500">Check for updates</p>
                            </div>
                        </div>
                        <button @click="checkUpdates()" 
                                :disabled="maintenanceTasks.checkUpdates"
                                class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm hover:bg-green-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.checkUpdates">Check</span>
                            <span x-show="maintenanceTasks.checkUpdates">Checking...</span>
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-shield-alt text-purple-600"></i>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Security Scan</p>
                                <p class="text-xs text-gray-500">Run security audit</p>
                            </div>
                        </div>
                        <button @click="securityScan()" 
                                :disabled="maintenanceTasks.securityScan"
                                class="px-3 py-1 bg-purple-100 text-purple-800 rounded text-sm hover:bg-purple-200 disabled:opacity-50">
                            <span x-show="!maintenanceTasks.securityScan">Scan</span>
                            <span x-show="maintenanceTasks.securityScan">Scanning...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Monitoring -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Performance Metrics -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Performance Metrics</h3>
                    <button @click="refreshMetrics()" class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">CPU Usage</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500" style="width: 45%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">45%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Memory Usage</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full transition-all duration-500" style="width: 68%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">68%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Disk I/O</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: 25%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">25%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Network I/O</span>
                        <div class="flex items-center space-x-2">
                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full transition-all duration-500" style="width: 30%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">30%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Maintenance -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Maintenance</h3>
                    <button @click="viewMaintenanceHistory()" class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="task in recentTasks" :key="task.id">
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="getTaskIconBg(task.status)">
                                <i :class="getTaskIcon(task.status)" 
                                   :class="getTaskIconColor(task.status)"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900" x-text="task.name"></p>
                                <p class="text-xs text-gray-500" x-text="task.timestamp"></p>
                                <span class="px-2 py-1 text-xs font-medium rounded-full mt-1"
                                      :class="getTaskStatusColor(task.status)"
                                      x-text="task.status"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- System Alerts -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System Alerts</h3>
                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                        3 Active
                    </span>
                </div>
                <div class="space-y-3">
                    <template x-for="alert in systemAlerts" :key="alert.id">
                        <div class="flex items-start space-x-3 p-3 rounded-lg"
                             :class="getAlertBgColor(alert.severity)">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="getAlertIconBg(alert.severity)">
                                <i :class="getAlertIcon(alert.severity)" 
                                   :class="getAlertIconColor(alert.severity)"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium" :class="getAlertTextColor(alert.severity)" x-text="alert.title"></p>
                                <p class="text-xs" :class="getAlertDescColor(alert.severity)" x-text="alert.description"></p>
                                <p class="text-xs mt-1" :class="getAlertDescColor(alert.severity)" x-text="alert.timestamp"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Maintenance Schedule -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Maintenance Schedule</h3>
                <div class="flex items-center space-x-2">
                    <button @click="addSchedule()" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Add Schedule
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Run</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="schedule in maintenanceSchedule" :key="schedule.id">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="schedule.task"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="schedule.schedule"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="schedule.lastRun"></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                          :class="getScheduleStatusColor(schedule.status)"
                                          x-text="schedule.status"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button @click="editSchedule(schedule)" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <button @click="runSchedule(schedule)" class="text-green-600 hover:text-green-900 mr-3">Run Now</button>
                                    <button @click="deleteSchedule(schedule)" class="text-red-600 hover:text-red-900">Delete</button>
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
function adminMaintenance() {
    return {
        loading: true,
        refreshing: false,
        
        // Maintenance tasks status
        maintenanceTasks: {
            backup: false,
            optimize: false,
            clearCache: false,
            checkUpdates: false,
            securityScan: false,
            cleanLogs: false
        },
        
        // Data
        recentTasks: [],
        systemAlerts: [],
        maintenanceSchedule: [],

        async init() {
            await this.loadMaintenanceData();
        },

        async loadMaintenanceData() {
            try {
                this.loading = true;
                
                // Mock data for demonstration
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                this.recentTasks = [
                    {
                        id: 1,
                        name: 'Database Backup',
                        status: 'completed',
                        timestamp: '2 hours ago'
                    },
                    {
                        id: 2,
                        name: 'Cache Clear',
                        status: 'completed',
                        timestamp: '4 hours ago'
                    },
                    {
                        id: 3,
                        name: 'Security Scan',
                        status: 'running',
                        timestamp: 'Started 10 minutes ago'
                    },
                    {
                        id: 4,
                        name: 'Log Cleanup',
                        status: 'failed',
                        timestamp: '6 hours ago'
                    }
                ];
                
                this.systemAlerts = [
                    {
                        id: 1,
                        title: 'High Memory Usage',
                        description: 'Memory usage has exceeded 80%',
                        severity: 'warning',
                        timestamp: '5 minutes ago'
                    },
                    {
                        id: 2,
                        title: 'Disk Space Low',
                        description: 'Available disk space is below 20%',
                        severity: 'critical',
                        timestamp: '15 minutes ago'
                    },
                    {
                        id: 3,
                        title: 'Security Update Available',
                        description: 'New security patches are available',
                        severity: 'info',
                        timestamp: '1 hour ago'
                    }
                ];
                
                this.maintenanceSchedule = [
                    {
                        id: 1,
                        task: 'Database Backup',
                        schedule: 'Daily at 2:00 AM',
                        lastRun: '2 hours ago',
                        status: 'active'
                    },
                    {
                        id: 2,
                        task: 'Log Cleanup',
                        schedule: 'Weekly on Sunday',
                        lastRun: '3 days ago',
                        status: 'active'
                    },
                    {
                        id: 3,
                        task: 'Security Scan',
                        schedule: 'Daily at 6:00 AM',
                        lastRun: '18 hours ago',
                        status: 'paused'
                    },
                    {
                        id: 4,
                        task: 'Cache Clear',
                        schedule: 'Every 4 hours',
                        lastRun: '2 hours ago',
                        status: 'active'
                    }
                ];
                
                this.loading = false;
                
            } catch (error) {
                console.error('Error loading maintenance data:', error);
                this.loading = false;
            }
        },

        // Maintenance actions
        async backupDatabase() {
            this.maintenanceTasks.backup = true;
            console.log('Starting database backup...');
            
            // Simulate backup process
            setTimeout(() => {
                this.maintenanceTasks.backup = false;
                console.log('Database backup completed');
                this.addRecentTask('Database Backup', 'completed');
            }, 5000);
        },

        async optimizeTables() {
            this.maintenanceTasks.optimize = true;
            console.log('Starting table optimization...');
            
            setTimeout(() => {
                this.maintenanceTasks.optimize = false;
                console.log('Table optimization completed');
                this.addRecentTask('Table Optimization', 'completed');
            }, 3000);
        },

        async cleanLogs() {
            this.maintenanceTasks.cleanLogs = true;
            console.log('Starting log cleanup...');
            
            setTimeout(() => {
                this.maintenanceTasks.cleanLogs = false;
                console.log('Log cleanup completed');
                this.addRecentTask('Log Cleanup', 'completed');
            }, 2000);
        },

        async clearCache() {
            this.maintenanceTasks.clearCache = true;
            console.log('Clearing cache...');
            
            setTimeout(() => {
                this.maintenanceTasks.clearCache = false;
                console.log('Cache cleared');
                this.addRecentTask('Cache Clear', 'completed');
            }, 1500);
        },

        async checkUpdates() {
            this.maintenanceTasks.checkUpdates = true;
            console.log('Checking for updates...');
            
            setTimeout(() => {
                this.maintenanceTasks.checkUpdates = false;
                console.log('Update check completed');
                this.addRecentTask('Update Check', 'completed');
            }, 4000);
        },

        async securityScan() {
            this.maintenanceTasks.securityScan = true;
            console.log('Starting security scan...');
            
            setTimeout(() => {
                this.maintenanceTasks.securityScan = false;
                console.log('Security scan completed');
                this.addRecentTask('Security Scan', 'completed');
            }, 8000);
        },

        addRecentTask(name, status) {
            this.recentTasks.unshift({
                id: Date.now(),
                name: name,
                status: status,
                timestamp: 'Just now'
            });
            
            // Keep only last 10 tasks
            if (this.recentTasks.length > 10) {
                this.recentTasks = this.recentTasks.slice(0, 10);
            }
        },

        refreshStatus() {
            this.refreshing = true;
            setTimeout(() => {
                this.loadMaintenanceData();
                this.refreshing = false;
            }, 1000);
        },

        refreshMetrics() {
            console.log('Refreshing performance metrics...');
        },

        viewMaintenanceHistory() {
            console.log('Viewing maintenance history...');
        },

        addSchedule() {
            console.log('Adding maintenance schedule...');
        },

        editSchedule(schedule) {
            console.log('Editing schedule:', schedule);
        },

        runSchedule(schedule) {
            console.log('Running schedule:', schedule);
        },

        deleteSchedule(schedule) {
            if (confirm(`Are you sure you want to delete "${schedule.task}"?`)) {
                console.log('Deleting schedule:', schedule);
                const index = this.maintenanceSchedule.findIndex(s => s.id === schedule.id);
                if (index > -1) {
                    this.maintenanceSchedule.splice(index, 1);
                }
            }
        },

        // Helper methods for styling
        getTaskIconBg(status) {
            const colors = {
                'completed': 'bg-green-100',
                'running': 'bg-blue-100',
                'failed': 'bg-red-100',
                'pending': 'bg-yellow-100'
            };
            return colors[status] || 'bg-gray-100';
        },

        getTaskIcon(status) {
            const icons = {
                'completed': 'fas fa-check',
                'running': 'fas fa-spinner fa-spin',
                'failed': 'fas fa-times',
                'pending': 'fas fa-clock'
            };
            return icons[status] || 'fas fa-question';
        },

        getTaskIconColor(status) {
            const colors = {
                'completed': 'text-green-600',
                'running': 'text-blue-600',
                'failed': 'text-red-600',
                'pending': 'text-yellow-600'
            };
            return colors[status] || 'text-gray-600';
        },

        getTaskStatusColor(status) {
            const colors = {
                'completed': 'bg-green-100 text-green-800',
                'running': 'bg-blue-100 text-blue-800',
                'failed': 'bg-red-100 text-red-800',
                'pending': 'bg-yellow-100 text-yellow-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        },

        getAlertBgColor(severity) {
            const colors = {
                'critical': 'bg-red-50',
                'warning': 'bg-yellow-50',
                'info': 'bg-blue-50'
            };
            return colors[severity] || 'bg-gray-50';
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

        getAlertTextColor(severity) {
            const colors = {
                'critical': 'text-red-800',
                'warning': 'text-yellow-800',
                'info': 'text-blue-800'
            };
            return colors[severity] || 'text-gray-800';
        },

        getAlertDescColor(severity) {
            const colors = {
                'critical': 'text-red-600',
                'warning': 'text-yellow-600',
                'info': 'text-blue-600'
            };
            return colors[severity] || 'text-gray-600';
        },

        getScheduleStatusColor(status) {
            const colors = {
                'active': 'bg-green-100 text-green-800',
                'paused': 'bg-yellow-100 text-yellow-800',
                'disabled': 'bg-red-100 text-red-800'
            };
            return colors[status] || 'bg-gray-100 text-gray-800';
        }
    }
}
</script>