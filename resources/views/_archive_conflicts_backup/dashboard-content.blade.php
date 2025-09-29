<!-- Admin Dashboard Content -->
<div x-data="adminDashboard()" x-init="init()">
    <!-- Loading State -->
    <div x-show="loading" class="flex justify-center items-center py-8">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span class="ml-2 text-gray-600">Loading system data...</span>
    </div>

    <!-- Error State -->
    <div x-show="error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <div class="flex">
            <div class="py-1">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="ml-3">
                <p class="font-bold">Error loading dashboard</p>
                <p x-text="error"></p>
                <button @click="init()" class="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                    Retry
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- 1. KPI Strip (4 system-wide cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Tenants -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-blue-500" 
                 @click="navigateTo('/admin/tenants?status=active')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Tenants</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="kpis.totalTenants || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-600 font-medium" x-text="`${kpis.activeTenants || 0} active`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500" x-text="`${Math.round((kpis.activeTenants || 0) / (kpis.totalTenants || 1) * 100)}% active`"></span>
                    </div>
                </div>
            </div>

            <!-- Users (System-wide) -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-green-500" 
                 @click="navigateTo('/admin/users?status=active')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="kpis.totalUsers || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-green-600 font-medium" x-text="`${kpis.activeUsers || 0} active`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500" x-text="`${Math.round((kpis.activeUsers || 0) / (kpis.totalUsers || 1) * 100)}% active`"></span>
                    </div>
                </div>
            </div>

            <!-- System Health -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-red-500" 
                 @click="navigateTo('/admin/security')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">System Health</p>
                    <p class="text-3xl font-bold" 
                       :class="getHealthColor(kpis.systemHealth)"
                       x-text="kpis.systemHealth || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-red-600 font-medium" x-text="`${kpis.errors24h || 0} errors`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500">24h</span>
                    </div>
                </div>
            </div>

            <!-- Performance -->
            <div class="bg-white rounded-lg border p-4 cursor-pointer border-l-4 border-purple-500" 
                 @click="navigateTo('/admin/maintenance')">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Performance</p>
                    <p class="text-3xl font-bold text-gray-900" x-text="kpis.p95Latency || '--'"></p>
                    <div class="flex items-center mt-2">
                        <span class="text-xs text-blue-600 font-medium" x-text="`${kpis.throughput || 0} req/min`"></span>
                        <span class="text-xs text-gray-400 mx-2">•</span>
                        <span class="text-xs text-gray-500">P95 latency</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. System Status Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- System Status -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">System Status</h3>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm text-green-600 font-medium">Online</span>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Database</span>
                        <span class="text-sm font-medium text-green-600">Connected</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">API Status</span>
                        <span class="text-sm font-medium text-green-600">Healthy</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Last Backup</span>
                        <span class="text-sm font-medium text-gray-900">2 hours ago</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Uptime</span>
                        <span class="text-sm font-medium text-green-600">99.9%</span>
                    </div>
                </div>
            </div>

            <!-- Storage Usage -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Storage Usage</h3>
                    <span class="text-sm text-gray-500">4.2 GB / 10 GB</span>
                </div>
                <div class="mb-4">
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-500 h-3 rounded-full transition-all duration-500" style="width: 42%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span>42% used</span>
                        <span>5.8 GB available</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Documents</span>
                        <span class="font-medium">2.4 GB</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Images & Media</span>
                        <span class="font-medium">1.8 GB</span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                    <button @click="navigateTo('/admin/activities')" class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="activity in activities.slice(0, 3)" :key="activity.id">
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="'bg-' + activity.type + '-100'">
                                <i :class="activity.icon" 
                                   :class="'text-' + activity.type + '-600'"
                                   class="text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900" x-text="activity.title"></p>
                                <p class="text-xs text-gray-500" x-text="activity.timestamp"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- 3. Alert Bar (System/Compliance) -->
        <div x-show="alerts.length > 0" x-transition class="mb-6">
            <div class="bg-gradient-to-r from-red-50 to-red-100 border border-red-200 rounded-lg p-4 shadow-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-semibold text-red-800 mb-2">System Alerts</h3>
                        <div class="space-y-2">
                            <template x-for="alert in alerts.slice(0, 3)" :key="alert.id">
                                <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
                                    <div class="flex-1">
                                        <p class="text-sm text-red-700" x-text="alert.message"></p>
                                    </div>
                                    <button @click="handleAlert(alert)" 
                                            class="ml-3 px-3 py-1 bg-red-500 text-white text-xs rounded-md hover:bg-red-600 transition-colors">
                                        <span x-text="alert.action"></span>
                                    </button>
                                </div>
                            </template>
                            <div x-show="alerts.length > 3" class="text-center pt-2">
                                <button @click="viewAllAlerts()" class="text-sm text-red-600 hover:text-red-800 font-medium">
                                    View all <span x-text="alerts.length - 3"></span> more alerts
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Now Panel (Admin Actions) -->
        <div x-show="nowPanelActions.length > 0" x-transition class="mb-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-sm font-medium text-blue-800 mb-3">Admin Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <template x-for="action in nowPanelActions" :key="action.id">
                        <button @click="executeNowAction(action)" 
                                class="bg-white border border-blue-200 rounded-lg p-3 text-left hover:bg-blue-50 transition-colors">
                            <div class="flex items-center space-x-2">
                                <i :class="action.icon" class="text-blue-600"></i>
                                <span class="text-sm font-medium text-blue-800" x-text="action.title"></span>
                            </div>
                            <p class="text-xs text-blue-600 mt-1" x-text="action.description"></p>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- 4. Scope & Time Controls -->
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Scope:</label>
                        <select x-model="scope" @change="applyScope()" 
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="all">All Tenants</option>
                            <template x-for="tenant in tenants" :key="tenant.id">
                                <option :value="tenant.id" x-text="tenant.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700">Time Range:</label>
                        <select x-model="timeRange" @change="applyTimeRange()" 
                                class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="today">Today</option>
                            <option value="7d">7 days</option>
                            <option value="30d">30 days</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button @click="refreshData()" 
                            class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                    <span class="text-xs text-gray-500" x-text="`Last updated: ${lastUpdated}`"></span>
                </div>
            </div>
        </div>

        <!-- 5. System Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Tenants at Risk -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Tenants at Risk</h3>
                    <button @click="navigateTo('/admin/tenants?filter=at_risk')" 
                            class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="tenant in tenantsAtRisk" :key="tenant.id">
                        <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="tenant.name"></p>
                                <p class="text-xs text-red-600" x-text="tenant.riskReason"></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full"
                                  x-text="tenant.riskLevel"></span>
                        </div>
                    </template>
                    <div x-show="tenantsAtRisk.length === 0" class="text-center py-4">
                        <i class="fas fa-check-circle text-2xl text-green-500 mb-2"></i>
                        <p class="text-sm text-gray-500">No tenants at risk</p>
                    </div>
                </div>
            </div>

            <!-- Incidents & Alerts -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Incidents & Alerts</h3>
                    <button @click="navigateTo('/admin/alerts')" 
                            class="text-sm text-blue-600 hover:text-blue-800">
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <template x-for="incident in incidents" :key="incident.id">
                        <div class="flex items-center justify-between p-3 rounded-lg"
                             :class="getIncidentBgColor(incident.severity)">
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="incident.title"></p>
                                <p class="text-xs text-gray-600" x-text="incident.description"></p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full"
                                  :class="getIncidentBadgeColor(incident.severity)"
                                  x-text="incident.severity"></span>
                        </div>
                    </template>
                    <div x-show="incidents.length === 0" class="text-center py-4">
                        <i class="fas fa-shield-alt text-2xl text-green-500 mb-2"></i>
                        <p class="text-sm text-gray-500">No active incidents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Insights (2-4 charts) -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-900">System Insights</h3>
                <div class="flex items-center space-x-2">
                    <select x-model="insightsTimeRange" @change="updateInsights()" 
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="24h">24 hours</option>
                        <option value="7d">7 days</option>
                        <option value="30d">30 days</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Error Rate Trend -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Error Rate Trend</h4>
                    <canvas id="errorRateChart" width="400" height="200"></canvas>
                </div>
                
                <!-- P95 Latency Trend -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">P95 Latency Trend</h4>
                    <canvas id="latencyChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Active Users Trend -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Active Users Trend</h4>
                    <canvas id="usersChart" width="400" height="200"></canvas>
                </div>
                
                <!-- Tenant Growth -->
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Tenant Growth</h4>
                    <canvas id="tenantsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- 7. Activity (System-wide) -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">System Activity</h3>
                <div class="flex items-center space-x-2">
                    <select x-model="activityFilter" @change="filterActivities()" 
                            class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="all">All Activities</option>
                        <option value="security">Security</option>
                        <option value="migration">Migration</option>
                        <option value="billing">Billing</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            
            <div class="space-y-3">
                <template x-for="activity in filteredActivities" :key="activity.id">
                    <div class="flex items-start space-x-3 p-3 border-b border-gray-100 last:border-b-0">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 :class="'bg-' + activity.type + '-100'">
                                <i :class="activity.icon" 
                                   :class="'text-' + activity.type + '-600'"
                                   class="text-sm"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900" x-text="activity.title"></p>
                            <p class="text-sm text-gray-600" x-text="activity.description"></p>
                            <div class="flex items-center space-x-4 mt-1">
                                <span class="text-xs text-gray-500" x-text="activity.timestamp"></span>
                                <span class="px-2 py-1 text-xs font-medium rounded-full"
                                      :class="'bg-' + activity.type + '-100 text-' + activity.type + '-800'"
                                      x-text="activity.type"></span>
                            </div>
                        </div>
                    </div>
                </template>
                
                <div x-show="filteredActivities.length === 0" class="text-center py-8">
                    <i class="fas fa-history text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Activities</h3>
                    <p class="text-sm text-gray-500">No activities match your current filter</p>
                </div>
            </div>
        </div>

        <!-- 8. Shortcuts (≤8) -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
            </div>
            
            <div class="flex flex-wrap gap-3">
                <template x-for="shortcut in adminShortcuts" :key="shortcut.id">
                    <button @click="executeShortcut(shortcut)" 
                            class="flex items-center space-x-2 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors border border-gray-200">
                        <div class="w-6 h-6 rounded flex items-center justify-center"
                             :class="'bg-' + shortcut.color + '-100'">
                            <i :class="shortcut.icon" 
                               :class="'text-' + shortcut.color + '-600'"
                               class="text-sm"></i>
                        </div>
                        <span class="text-sm font-medium text-gray-700" x-text="shortcut.title"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function adminDashboard() {
    return {
        loading: true,
        error: null,
        
        // KPI Data
        kpis: {
            totalTenants: 0,
            activeTenants: 0,
            totalUsers: 0,
            activeUsers: 0,
            systemHealth: 'OK',
            errors24h: 0,
            p95Latency: '0ms',
            throughput: 0
        },
        
        // Alert Data
        alerts: [],
        
        // Now Panel Actions
        nowPanelActions: [],
        
        // Scope & Time Controls
        scope: 'all',
        timeRange: 'today',
        tenants: [],
        lastUpdated: '',
        
        // System Overview
        tenantsAtRisk: [],
        incidents: [],
        
        // Insights
        insightsTimeRange: '24h',
        charts: {
            errorRateChart: null,
            latencyChart: null,
            usersChart: null,
            tenantsChart: null
        },
        
        // Activity
        activityFilter: 'all',
        activities: [],
        filteredActivities: [],
        
        // Shortcuts
        adminShortcuts: [
            { id: 1, title: 'Tenants', icon: 'fas fa-building', color: 'blue', action: 'tenants' },
            { id: 2, title: 'Users', icon: 'fas fa-users', color: 'green', action: 'users' },
            { id: 3, title: 'Tasks', icon: 'fas fa-tasks', color: 'blue', action: 'tasks' },
            { id: 4, title: 'Security', icon: 'fas fa-shield-alt', color: 'red', action: 'security' },
            { id: 5, title: 'Alerts', icon: 'fas fa-bell', color: 'yellow', action: 'alerts' },
            { id: 6, title: 'Activities', icon: 'fas fa-history', color: 'purple', action: 'activities' },
            { id: 7, title: 'Settings', icon: 'fas fa-cog', color: 'gray', action: 'settings' },
            { id: 8, title: 'Maintenance', icon: 'fas fa-tools', color: 'orange', action: 'maintenance' },
            { id: 9, title: 'Reports', icon: 'fas fa-chart-bar', color: 'indigo', action: 'reports' }
        ],

        async init() {
            // Load data immediately without delay
            this.loadDashboardData();
            this.loadMockData();
            
            // Setup charts after DOM is ready
            this.$nextTick(() => {
                this.setupCharts();
            });
        },

        loadDashboardData() {
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading admin dashboard data...');
                
                // Set mock KPI data immediately (no delay)
                this.kpis = {
                    totalTenants: 12,
                    activeTenants: 10,
                    totalUsers: 156,
                    activeUsers: 142,
                    systemHealth: 'OK',
                    errors24h: 3,
                    p95Latency: '245ms',
                    throughput: 1250
                };
                
                this.loading = false;
                console.log('✅ Admin dashboard data loaded successfully');
                
            } catch (error) {
                console.error('❌ Error loading admin dashboard data:', error);
                this.error = error.message;
                this.loading = false;
                
                // Set default values on error
                this.kpis = {
                    totalTenants: 0,
                    activeTenants: 0,
                    totalUsers: 0,
                    activeUsers: 0,
                    systemHealth: 'OK',
                    errors24h: 0,
                    p95Latency: '0ms',
                    throughput: 0
                };
            }
        },

        loadMockData() {
            // Load mock data for demonstration (synchronous)
            this.alerts = [
                { id: 1, message: 'High error rate detected on Tenant A', action: 'Investigate' },
                { id: 2, message: 'Storage usage exceeds 80%', action: 'Clean up' },
                { id: 3, message: 'Security scan completed with 2 warnings', action: 'Review' }
            ];

            this.nowPanelActions = [
                { id: 1, title: 'Backup System', icon: 'fas fa-database', description: 'Create system backup' },
                { id: 2, title: 'Update Security', icon: 'fas fa-shield-alt', description: 'Run security updates' },
                { id: 3, title: 'Clean Logs', icon: 'fas fa-broom', description: 'Clear old log files' },
                { id: 4, title: 'Monitor Performance', icon: 'fas fa-chart-line', description: 'Check system performance' },
                { id: 5, title: 'Tenant Audit', icon: 'fas fa-search', description: 'Audit tenant activities' }
            ];

            this.tenants = [
                { id: 1, name: 'Acme Corp' },
                { id: 2, name: 'TechStart Inc' },
                { id: 3, name: 'Global Solutions' }
            ];

            this.tenantsAtRisk = [
                { id: 1, name: 'OldCorp Ltd', riskReason: 'No activity for 30 days', riskLevel: 'High' },
                { id: 2, name: 'TestTenant', riskReason: 'Storage limit exceeded', riskLevel: 'Medium' }
            ];

            this.incidents = [
                { id: 1, title: 'Database Connection Timeout', description: 'Multiple connection failures', severity: 'High' },
                { id: 2, title: 'API Rate Limit Exceeded', description: 'Tenant A exceeded API limits', severity: 'Medium' },
                { id: 3, title: 'Security Alert', description: 'Unusual login pattern detected', severity: 'Low' }
            ];

            this.activities = [
                { id: 1, title: 'System Backup Completed', description: 'Daily backup completed successfully', timestamp: '2 minutes ago', type: 'maintenance', icon: 'fas fa-database' },
                { id: 2, title: 'New Tenant Created', description: 'TechStart Inc joined the platform', timestamp: '15 minutes ago', type: 'migration', icon: 'fas fa-building' },
                { id: 3, title: 'Security Scan', description: 'Automated security scan completed', timestamp: '1 hour ago', type: 'security', icon: 'fas fa-shield-alt' },
                { id: 4, title: 'Performance Optimization', description: 'Database queries optimized', timestamp: '2 hours ago', type: 'maintenance', icon: 'fas fa-tachometer-alt' },
                { id: 5, title: 'User Activity Audit', description: 'Monthly user activity audit completed', timestamp: '3 hours ago', type: 'security', icon: 'fas fa-user-check' }
            ];

            this.filteredActivities = this.activities;
            this.lastUpdated = new Date().toLocaleTimeString();
        },

        setupCharts() {
            // Only setup charts if Chart.js is available and DOM is ready
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, skipping chart initialization');
                return;
            }
            
            // Destroy existing charts first
            Object.values(this.charts).forEach(chart => {
                if (chart) chart.destroy();
            });
            this.charts = {};
            
            // Initialize charts with error handling
            try {
                this.initErrorRateChart();
                this.initLatencyChart();
                this.initUsersChart();
                this.initTenantsChart();
            } catch (error) {
                console.warn('Chart initialization failed:', error);
            }
        },

        initErrorRateChart() {
            const ctx = document.getElementById('errorRateChart');
            if (ctx && !this.charts.errorRateChart) {
                this.charts.errorRateChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                        datasets: [{
                            label: 'Error Rate %',
                            data: [0.2, 0.1, 0.3, 0.5, 0.2, 0.1],
                            borderColor: 'rgb(239, 68, 68)',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 1
                            }
                        }
                    }
                });
            }
        },

        initLatencyChart() {
            const ctx = document.getElementById('latencyChart');
            if (ctx && !this.charts.latencyChart) {
                this.charts.latencyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                        datasets: [{
                            label: 'P95 Latency (ms)',
                            data: [180, 220, 190, 245, 200, 180],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },

        initUsersChart() {
            const ctx = document.getElementById('usersChart');
            if (ctx && !this.charts.usersChart) {
                this.charts.usersChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [{
                            label: 'Active Users',
                            data: [120, 135, 142, 138, 145, 98, 85],
                            backgroundColor: 'rgba(34, 197, 94, 0.8)',
                            borderColor: 'rgb(34, 197, 94)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },

        initTenantsChart() {
            const ctx = document.getElementById('tenantsChart');
            if (ctx && !this.charts.tenantsChart) {
                this.charts.tenantsChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Active', 'Inactive', 'Suspended'],
                        datasets: [{
                            data: [10, 2, 0],
                            backgroundColor: [
                                'rgb(34, 197, 94)',
                                'rgb(156, 163, 175)',
                                'rgb(239, 68, 68)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        },

        startRealTimeUpdates() {
            // Update data every 30 seconds
            setInterval(() => {
                this.refreshData();
            }, 30000);
        },

        refreshData() {
            this.loadDashboardData();
            this.lastUpdated = new Date().toLocaleTimeString();
        },

        // Helper methods
        getHealthColor(health) {
            const colors = {
                'OK': 'text-green-600',
                'Warning': 'text-yellow-600',
                'Critical': 'text-red-600'
            };
            return colors[health] || 'text-gray-600';
        },

        getHealthBgColor(health) {
            const colors = {
                'OK': 'bg-green-100',
                'Warning': 'bg-yellow-100',
                'Critical': 'bg-red-100'
            };
            return colors[health] || 'bg-gray-100';
        },

        getHealthIconColor(health) {
            const colors = {
                'OK': 'text-green-600',
                'Warning': 'text-yellow-600',
                'Critical': 'text-red-600'
            };
            return colors[health] || 'text-gray-600';
        },

        getHealthProgressColor(health) {
            const colors = {
                'OK': 'bg-green-500',
                'Warning': 'bg-yellow-500',
                'Critical': 'bg-red-500'
            };
            return colors[health] || 'bg-gray-500';
        },

        getHealthProgress(health) {
            const progress = {
                'OK': 95,
                'Warning': 70,
                'Critical': 30
            };
            return progress[health] || 50;
        },

        getPerformanceProgress(latency) {
            if (!latency) return 50;
            const latencyValue = parseInt(latency.replace('ms', ''));
            if (latencyValue <= 100) return 95;
            if (latencyValue <= 200) return 80;
            if (latencyValue <= 300) return 60;
            if (latencyValue <= 500) return 40;
            return 20;
        },

        getIncidentBgColor(severity) {
            const colors = {
                'High': 'bg-red-50',
                'Medium': 'bg-yellow-50',
                'Low': 'bg-blue-50'
            };
            return colors[severity] || 'bg-gray-50';
        },

        getIncidentBadgeColor(severity) {
            const colors = {
                'High': 'bg-red-100 text-red-800',
                'Medium': 'bg-yellow-100 text-yellow-800',
                'Low': 'bg-blue-100 text-blue-800'
            };
            return colors[severity] || 'bg-gray-100 text-gray-800';
        },

        navigateTo(path) {
            window.location.href = path;
        },

        handleAlert(alert) {
            console.log('Handling alert:', alert);
            // Implement alert handling logic
        },

        viewAllAlerts() {
            this.navigateTo('/admin/alerts');
        },

        executeNowAction(action) {
            console.log('Executing action:', action);
            // Implement action execution logic
        },

        applyScope() {
            console.log('Applying scope:', this.scope);
            // Implement scope filtering
        },

        applyTimeRange() {
            console.log('Applying time range:', this.timeRange);
            // Implement time range filtering
        },

        updateInsights() {
            console.log('Updating insights for:', this.insightsTimeRange);
            // Implement insights update
        },

        filterActivities() {
            if (this.activityFilter === 'all') {
                this.filteredActivities = this.activities;
            } else {
                this.filteredActivities = this.activities.filter(activity => 
                    activity.type === this.activityFilter
                );
            }
        },

        executeShortcut(shortcut) {
            console.log('Executing shortcut:', shortcut);
            this.navigateTo(`/admin/${shortcut.action}`);
        }
    }
}
</script>
