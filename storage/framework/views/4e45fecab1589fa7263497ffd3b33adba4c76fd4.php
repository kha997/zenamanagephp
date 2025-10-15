<?php $__env->startSection('title', 'System Alerts'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <?php echo $__env->make('components.zena-logo', ['subtitle' => 'System Alerts'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                
                <!-- Header Actions -->
                <div class="flex items-center space-x-4">
                    <a href="/admin" class="zena-btn zena-btn-outline zena-btn-sm">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Admin
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div x-data="alertsDashboard()" x-init="init()">
            <!-- Alerts Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card red p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Critical Alerts</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.criticalAlerts || 0"></p>
                            <p class="text-white/80 text-sm">
                                Requires immediate attention
                            </p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Warning Alerts</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.warningAlerts || 0"></p>
                            <p class="text-white/80 text-sm">
                                Monitor closely
                            </p>
                        </div>
                        <i class="fas fa-exclamation-circle text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Info Alerts</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.infoAlerts || 0"></p>
                            <p class="text-white/80 text-sm">
                                Informational
                            </p>
                        </div>
                        <i class="fas fa-info-circle text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Resolved Today</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.resolvedToday || 0"></p>
                            <p class="text-white/80 text-sm">
                                Successfully handled
                            </p>
                        </div>
                        <i class="fas fa-check-circle text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Alerts Overview -->
            <div class="dashboard-card p-6 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">System Alerts</h3>
                    <div class="flex items-center gap-2">
                        <button class="zena-btn zena-btn-primary zena-btn-sm" @click="createAlert()">
                            <i class="fas fa-plus mr-2"></i>
                            Create Alert
                        </button>
                        <button class="zena-btn zena-btn-outline zena-btn-sm" @click="markAllRead()">
                            <i class="fas fa-check mr-2"></i>
                            Mark All Read
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <template x-for="alert in mockAlerts" :key="alert.id">
                        <div class="border rounded-lg p-4" :class="getAlertBorderColor(alert.severity)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <i :class="getAlertIcon(alert.severity)" :style="'color:' + getAlertIconColor(alert.severity)"></i>
                                    <div>
                                        <h4 class="font-medium text-gray-900" x-text="alert.title"></h4>
                                        <p class="text-sm text-gray-600" x-text="alert.description"></p>
                                        <p class="text-xs text-gray-500 mt-1" x-text="alert.time"></p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="zena-badge" :class="getAlertBadgeColor(alert.severity)" x-text="alert.severity"></span>
                                    <button @click="resolveAlert(alert.id)" class="zena-btn zena-btn-outline zena-btn-sm zena-btn-success">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button @click="dismissAlert(alert.id)" class="zena-btn zena-btn-outline zena-btn-sm">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function alertsDashboard() {
        return {
            mockAlerts: [
                {
                    id: '1',
                    title: 'High CPU Usage',
                    description: 'CPU usage is at 95% for the last 10 minutes',
                    severity: 'critical',
                    time: '2 minutes ago'
                },
                {
                    id: '2',
                    title: 'Disk Space Warning',
                    description: 'Disk usage is at 85% capacity',
                    severity: 'warning',
                    time: '1 hour ago'
                },
                {
                    id: '3',
                    title: 'Backup Completed',
                    description: 'Daily backup completed successfully',
                    severity: 'info',
                    time: '3 hours ago'
                },
                {
                    id: '4',
                    title: 'Memory Usage High',
                    description: 'Memory usage is at 80%',
                    severity: 'warning',
                    time: '4 hours ago'
                }
            ],
            stats: {
                criticalAlerts: 1,
                warningAlerts: 2,
                infoAlerts: 1,
                resolvedToday: 5,
            },

            init() {
                // Initialize dashboard
            },

            getAlertIcon(severity) {
                switch (severity) {
                    case 'critical': return 'fas fa-exclamation-triangle';
                    case 'warning': return 'fas fa-exclamation-circle';
                    case 'info': return 'fas fa-info-circle';
                    default: return 'fas fa-info-circle';
                }
            },

            getAlertIconColor(severity) {
                switch (severity) {
                    case 'critical': return '#dc2626';
                    case 'warning': return '#ea580c';
                    case 'info': return '#2563eb';
                    default: return '#6b7280';
                }
            },

            getAlertBorderColor(severity) {
                switch (severity) {
                    case 'critical': return 'border-red-200 bg-red-50';
                    case 'warning': return 'border-orange-200 bg-orange-50';
                    case 'info': return 'border-blue-200 bg-blue-50';
                    default: return 'border-gray-200 bg-gray-50';
                }
            },

            getAlertBadgeColor(severity) {
                switch (severity) {
                    case 'critical': return 'zena-badge-danger';
                    case 'warning': return 'zena-badge-warning';
                    case 'info': return 'zena-badge-primary';
                    default: return 'zena-badge-neutral';
                }
            },

            createAlert() {
                alert('Create alert functionality will be implemented here!');
            },

            markAllRead() {
                alert('Marking all alerts as read...');
            },

            resolveAlert(alertId) {
                if (confirm(`Are you sure you want to resolve alert ${alertId}?`)) {
                    alert(`Resolving alert: ${alertId}`);
                }
            },

            dismissAlert(alertId) {
                if (confirm(`Are you sure you want to dismiss alert ${alertId}?`)) {
                    alert(`Dismissing alert: ${alertId}`);
                }
            }
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/alerts/index.blade.php ENDPATH**/ ?>