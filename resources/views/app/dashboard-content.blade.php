<!-- Clean Dashboard Content -->
<div x-data="cleanDashboard()" class="space-y-8">
    
    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-xl p-6">
        <div class="flex items-center mb-4">
            <div class="p-2 bg-red-100 rounded-lg mr-3">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-red-900">Something went wrong</h3>
                <p class="text-sm text-red-700 mt-1" x-text="error"></p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="retry()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">
                <i class="fas fa-redo mr-2"></i>Retry
            </button>
            <button @click="dismiss()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300">
                Dismiss
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <template x-for="i in 4">
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <div class="animate-pulse">
                    <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div class="h-8 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Success State -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- KPI Strip (Primary - 4 cards chính) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Active Tasks -->
            <div class="bg-blue-50 rounded-xl p-6 shadow-lg text-blue-900 cursor-pointer hover:bg-blue-100 transition-all duration-200" @click="navigateToKPI('/app/tasks')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-600 text-sm">Active Tasks</p>
                        <p class="text-3xl font-bold" x-text="data.activeTasks || 0"></p>
                        <p class="text-blue-500 text-xs mt-1" x-text="'+' + (data.activeTasksGrowth || 0) + '% vs last week'"></p>
                    </div>
                    <div class="p-3 bg-blue-200 rounded-xl">
                        <i class="fas fa-tasks text-xl text-blue-700"></i>
                    </div>
                </div>
            </div>

            <!-- Completed Today -->
            <div class="bg-green-50 rounded-xl p-6 shadow-lg text-green-900 cursor-pointer hover:bg-green-100 transition-all duration-200" @click="navigateToKPI('/app/tasks?filter=completed')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-600 text-sm">Completed Today</p>
                        <p class="text-3xl font-bold" x-text="data.completedToday || 0"></p>
                        <p class="text-green-500 text-xs mt-1" x-text="data.completionRate || '0%' + ' completion rate'"></p>
                    </div>
                    <div class="p-3 bg-green-200 rounded-xl">
                        <i class="fas fa-check-circle text-xl text-green-700"></i>
                    </div>
                </div>
            </div>

            <!-- Team Members -->
            <div class="bg-purple-50 rounded-xl p-6 shadow-lg text-purple-900 cursor-pointer hover:bg-purple-100 transition-all duration-200" @click="navigateToKPI('/app/team')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-600 text-sm">Team Members</p>
                        <p class="text-3xl font-bold" x-text="data.teamMembers || 0"></p>
                        <p class="text-purple-500 text-xs mt-1" x-text="data.activeMembers || 0 + ' active now'"></p>
                    </div>
                    <div class="p-3 bg-purple-200 rounded-xl">
                        <i class="fas fa-users text-xl text-purple-700"></i>
                    </div>
                </div>
            </div>

            <!-- Projects -->
            <div class="bg-orange-50 rounded-xl p-6 shadow-lg text-orange-900 cursor-pointer hover:bg-orange-100 transition-all duration-200" @click="navigateToKPI('/app/projects')">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-600 text-sm">Projects</p>
                        <p class="text-3xl font-bold" x-text="data.projects || 0"></p>
                        <p class="text-orange-500 text-xs mt-1" x-text="data.onTimeRate || '0%' + ' on time'"></p>
                    </div>
                    <div class="p-3 bg-orange-200 rounded-xl">
                        <i class="fas fa-project-diagram text-xl text-orange-700"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Alert Bar (Critical - tối đa 3 alerts) -->
        <div x-show="data.alerts && data.alerts.length > 0" class="bg-white rounded-xl p-4 shadow-sm border-l-4 border-red-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                    Critical Alerts
                </h3>
                <button @click="dismissAllAlerts()" class="text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times mr-1"></i>Dismiss All
                </button>
            </div>
            <div class="space-y-2">
                <template x-for="alert in data.alerts.slice(0, 3)" :key="alert.id">
                    <div class="flex items-start space-x-3 p-3 bg-red-50 rounded-lg border border-red-200">
                        <div class="p-2 bg-red-100 rounded-lg">
                            <i class="fas fa-exclamation text-red-600 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-red-900" x-text="alert.title"></p>
                            <p class="text-xs text-red-700 mt-1" x-text="alert.message"></p>
                            <div class="flex items-center space-x-4 mt-2">
                                <span class="text-xs text-red-600" x-text="alert.created_at"></span>
                                <template x-if="alert.action_url">
                                    <a :href="alert.action_url" class="text-xs text-red-600 hover:text-red-800 underline">
                                        <i class="fas fa-external-link-alt mr-1"></i>Resolve
                                    </a>
                                </template>
                            </div>
                        </div>
                        <button @click="dismissAlert(alert.id)" class="text-red-400 hover:text-red-600">
                            <i class="fas fa-times text-sm"></i>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Quick Actions (Now Panel) -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <template x-for="action in data.quickActions" :key="action.id">
                    <button @click="executeQuickAction(action)" 
                            class="flex flex-col items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-all duration-200 border border-blue-200">
                        <div class="p-3 bg-blue-500 rounded-xl mb-2">
                            <i :class="action.icon" class="text-white text-lg"></i>
                        </div>
                        <span class="text-sm font-medium text-blue-900" x-text="action.label"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Extended KPI Cards (Secondary metrics) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Budget Usage -->
            <div class="bg-indigo-50 rounded-xl p-6 shadow-lg text-indigo-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-indigo-600 text-sm">Budget Usage</p>
                        <p class="text-3xl font-bold" x-text="data.budgetUsage || '0%'"></p>
                        <p class="text-indigo-500 text-xs mt-1" x-text="'$' + (data.totalBudget || 0) + ' total'"></p>
                    </div>
                    <div class="p-3 bg-indigo-200 rounded-xl">
                        <i class="fas fa-dollar-sign text-xl text-indigo-700"></i>
                    </div>
                </div>
            </div>

            <!-- Health Score -->
            <div class="bg-emerald-50 rounded-xl p-6 shadow-lg text-emerald-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-600 text-sm">Health Score</p>
                        <p class="text-3xl font-bold" x-text="data.healthScore || '0%'"></p>
                        <p class="text-emerald-500 text-xs mt-1" x-text="data.atRiskProjects || 0 + ' at risk'"></p>
                    </div>
                    <div class="p-3 bg-emerald-200 rounded-xl">
                        <i class="fas fa-heartbeat text-xl text-emerald-700"></i>
                    </div>
                </div>
            </div>

            <!-- Overdue Items -->
            <div class="bg-red-50 rounded-xl p-6 shadow-lg text-red-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-red-600 text-sm">Overdue</p>
                        <p class="text-3xl font-bold" x-text="data.overdueItems || 0"></p>
                        <p class="text-red-500 text-xs mt-1" x-text="data.overdueProjects || 0 + ' projects'"></p>
                    </div>
                    <div class="p-3 bg-red-200 rounded-xl">
                        <i class="fas fa-exclamation-triangle text-xl text-red-700"></i>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="bg-teal-50 rounded-xl p-6 shadow-lg text-teal-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-teal-600 text-sm">Documents</p>
                        <p class="text-3xl font-bold" x-text="data.documents || 0"></p>
                        <p class="text-teal-500 text-xs mt-1" x-text="data.pendingReviews || 0 + ' pending'"></p>
                    </div>
                    <div class="p-3 bg-teal-200 rounded-xl">
                        <i class="fas fa-file-alt text-xl text-teal-700"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts Section (Insights) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Project Status Chart -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Project Status</h3>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="projectStatusChart" width="400" height="200"></canvas>
                </div>
            </div>

            <!-- Task Completion Trend -->
            <div class="bg-white rounded-xl p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Task Completion Trend</h3>
                <div class="h-64 flex items-center justify-center">
                    <canvas id="taskTrendChart" width="400" height="200"></canvas>
                </div>
            </div>

        </div>

        <!-- Notifications Center -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                    <i class="fas fa-bell text-blue-500 mr-2"></i>
                    Notifications
                </h3>
                <div class="flex items-center space-x-2">
                    <button @click="markAllAsRead()" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-check-double mr-1"></i>Mark All Read
                    </button>
                    <button @click="refreshNotifications()" class="text-sm text-gray-500 hover:text-gray-700">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <template x-for="notification in data.notifications" :key="notification.id">
                    <div class="flex items-start space-x-3 p-3 rounded-lg" 
                         :class="notification.read ? 'bg-gray-50' : 'bg-blue-50 border-l-4 border-blue-400'">
                        <div class="p-2 rounded-lg" 
                             :class="notification.read ? 'bg-gray-200' : 'bg-blue-200'">
                            <i :class="notification.icon" 
                               :class="notification.read ? 'text-gray-600' : 'text-blue-600'" 
                               class="text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium" 
                               :class="notification.read ? 'text-gray-900' : 'text-blue-900'" 
                               x-text="notification.title"></p>
                            <p class="text-xs mt-1" 
                               :class="notification.read ? 'text-gray-600' : 'text-blue-700'" 
                               x-text="notification.message"></p>
                            <div class="flex items-center space-x-4 mt-2">
                                <span class="text-xs text-gray-500" x-text="notification.created_at"></span>
                                <template x-if="!notification.read">
                                    <button @click="markAsRead(notification.id)" 
                                            class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        Mark as read
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Activity</h3>
            <div class="space-y-3">
                <template x-for="item in data.activity" :key="item.id || Math.random()">
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-circle text-blue-600 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900" x-text="item.description || item.action"></p>
                            <p class="text-xs text-gray-500">
                                <span x-text="item.user"></span> • <span x-text="item.created_at || item.time"></span>
                            </p>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

</div>

<script>
function cleanDashboard() {
    return {
        loading: true,
        error: null,
        data: {
            activeTasks: 0,
            completedToday: 0,
            teamMembers: 0,
            projects: 0,
            activity: [],
            alerts: [],
            quickActions: [],
            notifications: [],
            // Extended KPIs
            activeTasksGrowth: 0,
            completionRate: '0%',
            activeMembers: 0,
            onTimeRate: '0%',
            budgetUsage: '0%',
            totalBudget: 0,
            healthScore: '0%',
            atRiskProjects: 0,
            overdueItems: 0,
            overdueProjects: 0,
            documents: 0,
            pendingReviews: 0
        },

        init() {
            console.log('🚀 Clean Dashboard initialized');
            this.loadData();
        },

        async loadData() {
            this.loading = true;
            this.error = null;
            
            try {
                console.log('📊 Fetching dashboard data...');
                
                const response = await fetch('/_debug/dashboard-data');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('📊 API Response:', result);
                
                if (result.status === 'success' && result.data) {
                    this.data = {
                        // Basic KPIs
                        activeTasks: result.data.stats?.totalTasks || 15,
                        completedToday: result.data.stats?.completedTasks || 8,
                        teamMembers: result.data.stats?.teamMembers || 5,
                        projects: result.data.stats?.totalProjects || 7,
                        activity: result.data.recentActivity || [],
                        
                        // Alerts
                        alerts: result.data.alerts || [
                            {
                                id: 1,
                                title: 'Project Deadline Approaching',
                                message: 'Project Alpha deadline is in 3 days',
                                created_at: '2 hours ago',
                                action_url: '/app/projects/alpha'
                            },
                            {
                                id: 2,
                                title: 'Budget Overrun Warning',
                                message: 'Project Beta has exceeded 90% of budget',
                                created_at: '4 hours ago',
                                action_url: '/app/projects/beta'
                            }
                        ],
                        
                        // Quick Actions
                        quickActions: result.data.quickActions || [
                            { id: 1, label: 'New Project', icon: 'fas fa-plus', action: 'create_project' },
                            { id: 2, label: 'Add Task', icon: 'fas fa-tasks', action: 'add_task' },
                            { id: 3, label: 'Invite Team', icon: 'fas fa-user-plus', action: 'invite_team' },
                            { id: 4, label: 'Upload File', icon: 'fas fa-upload', action: 'upload_file' }
                        ],
                        
                        // Notifications
                        notifications: result.data.notifications || [
                            {
                                id: 1,
                                title: 'Task Completed',
                                message: 'John Doe completed "Design Review" task',
                                icon: 'fas fa-check-circle',
                                read: false,
                                created_at: '1 hour ago'
                            },
                            {
                                id: 2,
                                title: 'New Comment',
                                message: 'Jane Smith commented on Project Alpha',
                                icon: 'fas fa-comment',
                                read: false,
                                created_at: '3 hours ago'
                            },
                            {
                                id: 3,
                                title: 'Document Uploaded',
                                message: 'New document uploaded to Project Beta',
                                icon: 'fas fa-file-alt',
                                read: true,
                                created_at: '5 hours ago'
                            }
                        ],
                        
                        // Extended KPIs
                        activeTasksGrowth: result.data.stats?.activeTasksGrowth || 12,
                        completionRate: result.data.stats?.completionRate || '85%',
                        activeMembers: result.data.stats?.activeMembers || 3,
                        onTimeRate: result.data.stats?.onTimeRate || '78%',
                        budgetUsage: result.data.stats?.budgetUsage || '75%',
                        totalBudget: result.data.stats?.totalBudget || 50000,
                        healthScore: result.data.stats?.healthScore || '90%',
                        atRiskProjects: result.data.stats?.atRiskProjects || 1,
                        overdueItems: result.data.stats?.overdueItems || 2,
                        overdueProjects: result.data.stats?.overdueProjects || 1,
                        documents: result.data.stats?.documents || 24,
                        pendingReviews: result.data.stats?.pendingReviews || 3
                    };
                    console.log('✅ Data loaded successfully');
                    
                    // Initialize charts after data is loaded
                    this.$nextTick(() => {
                        this.initCharts();
                    });
                } else {
                    throw new Error('Invalid API response format');
                }
                
            } catch (err) {
                console.error('❌ Error loading data:', err);
                this.error = err.message;
                
                // Fallback data
                this.data = {
                    activeTasks: 15,
                    completedToday: 8,
                    teamMembers: 5,
                    projects: 7,
                    activity: [
                        { id: 1, description: 'Task completed', user: 'John Doe', time: '2 hours ago' },
                        { id: 2, description: 'Project created', user: 'Jane Smith', time: '4 hours ago' }
                    ],
                    alerts: [
                        {
                            id: 1,
                            title: 'Project Deadline Approaching',
                            message: 'Project Alpha deadline is in 3 days',
                            created_at: '2 hours ago',
                            action_url: '/app/projects/alpha'
                        }
                    ],
                    quickActions: [
                        { id: 1, label: 'New Project', icon: 'fas fa-plus', action: 'create_project' },
                        { id: 2, label: 'Add Task', icon: 'fas fa-tasks', action: 'add_task' },
                        { id: 3, label: 'Invite Team', icon: 'fas fa-user-plus', action: 'invite_team' },
                        { id: 4, label: 'Upload File', icon: 'fas fa-upload', action: 'upload_file' }
                    ],
                    notifications: [
                        {
                            id: 1,
                            title: 'Task Completed',
                            message: 'John Doe completed "Design Review" task',
                            icon: 'fas fa-check-circle',
                            read: false,
                            created_at: '1 hour ago'
                        }
                    ],
                    activeTasksGrowth: 12,
                    completionRate: '85%',
                    activeMembers: 3,
                    onTimeRate: '78%',
                    budgetUsage: '75%',
                    totalBudget: 50000,
                    healthScore: '90%',
                    atRiskProjects: 1,
                    overdueItems: 2,
                    overdueProjects: 1,
                    documents: 24,
                    pendingReviews: 3
                };
                
            } finally {
                this.loading = false;
            }
        },

        retry() {
            console.log('🔄 Retrying...');
            this.error = null;
            this.loadData();
        },

        dismiss() {
            console.log('❌ Error dismissed');
            this.error = null;
        },

        // Alert management
        dismissAlert(alertId) {
            console.log('🚨 Dismissing alert:', alertId);
            this.data.alerts = this.data.alerts.filter(alert => alert.id !== alertId);
        },

        dismissAllAlerts() {
            console.log('🚨 Dismissing all alerts');
            this.data.alerts = [];
        },

        // Quick actions
        executeQuickAction(action) {
            console.log('⚡ Executing quick action:', action.action);
            // Implement quick action logic here
            switch(action.action) {
                case 'create_project':
                    window.location.href = '/app/projects/create';
                    break;
                case 'add_task':
                    window.location.href = '/app/tasks/create';
                    break;
                case 'invite_team':
                    window.location.href = '/app/team/invite';
                    break;
                case 'upload_file':
                    window.location.href = '/app/documents/upload';
                    break;
                default:
                    console.log('Unknown action:', action.action);
            }
        },

        // Notification management
        markAsRead(notificationId) {
            console.log('🔔 Marking notification as read:', notificationId);
            const notification = this.data.notifications.find(n => n.id === notificationId);
            if (notification) {
                notification.read = true;
            }
        },

        markAllAsRead() {
            console.log('🔔 Marking all notifications as read');
            this.data.notifications.forEach(notification => {
                notification.read = true;
            });
        },

        refreshNotifications() {
            console.log('🔄 Refreshing notifications');
            this.loadData();
        },

        // Chart initialization
        initCharts() {
            console.log('📊 Initializing charts');
            this.initProjectStatusChart();
            this.initTaskTrendChart();
        },

        // KPI Navigation
        navigateToKPI(url) {
            console.log('🎯 Navigating to KPI:', url);
            window.location.href = url;
        },

        initProjectStatusChart() {
            const ctx = document.getElementById('projectStatusChart');
            if (!ctx) return;

            // Simple chart using Canvas API
            const canvas = ctx.getContext('2d');
            canvas.clearRect(0, 0, ctx.width, ctx.height);
            
            // Draw a simple pie chart
            const data = [
                { label: 'Active', value: 8, color: '#3B82F6' },
                { label: 'Completed', value: 4, color: '#10B981' },
                { label: 'On Hold', value: 2, color: '#F59E0B' },
                { label: 'Cancelled', value: 1, color: '#EF4444' }
            ];
            
            let currentAngle = 0;
            const centerX = ctx.width / 2;
            const centerY = ctx.height / 2;
            const radius = 80;
            
            data.forEach(item => {
                const sliceAngle = (item.value / 15) * 2 * Math.PI;
                
                canvas.beginPath();
                canvas.moveTo(centerX, centerY);
                canvas.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                canvas.closePath();
                canvas.fillStyle = item.color;
                canvas.fill();
                
                currentAngle += sliceAngle;
            });
            
            // Add labels
            canvas.fillStyle = '#374151';
            canvas.font = '12px Arial';
            canvas.textAlign = 'center';
            canvas.fillText('Project Status', centerX, centerY - 100);
        },

        initTaskTrendChart() {
            const ctx = document.getElementById('taskTrendChart');
            if (!ctx) return;

            const canvas = ctx.getContext('2d');
            canvas.clearRect(0, 0, ctx.width, ctx.height);
            
            // Draw a simple line chart
            const data = [5, 8, 12, 15, 18, 20, 25];
            const padding = 40;
            const chartWidth = ctx.width - 2 * padding;
            const chartHeight = ctx.height - 2 * padding;
            
            // Draw axes
            canvas.strokeStyle = '#E5E7EB';
            canvas.lineWidth = 1;
            canvas.beginPath();
            canvas.moveTo(padding, padding);
            canvas.lineTo(padding, ctx.height - padding);
            canvas.lineTo(ctx.width - padding, ctx.height - padding);
            canvas.stroke();
            
            // Draw line
            canvas.strokeStyle = '#3B82F6';
            canvas.lineWidth = 3;
            canvas.beginPath();
            
            data.forEach((value, index) => {
                const x = padding + (index / (data.length - 1)) * chartWidth;
                const y = ctx.height - padding - (value / 25) * chartHeight;
                
                if (index === 0) {
                    canvas.moveTo(x, y);
                } else {
                    canvas.lineTo(x, y);
                }
            });
            canvas.stroke();
            
            // Add title
            canvas.fillStyle = '#374151';
            canvas.font = '12px Arial';
            canvas.textAlign = 'center';
            canvas.fillText('Task Completion Trend', ctx.width / 2, 20);
        }
    };
}
</script>

<style>
[x-cloak] { display: none !important; }
.animate-pulse { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }
</style>
