<?php $__env->startSection('title', 'Activity Log'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <?php echo $__env->make('components.zena-logo', ['subtitle' => 'Activity Log'], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                
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
        <div x-data="activitiesDashboard()" x-init="init()">
            <!-- Activities Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Total Activities</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.totalActivities || 0"></p>
                            <p class="text-white/80 text-sm">
                                Last 24 hours
                            </p>
                        </div>
                        <i class="fas fa-history text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Active Users</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.activeUsers || 0"></p>
                            <p class="text-white/80 text-sm">
                                Currently online
                            </p>
                        </div>
                        <i class="fas fa-users text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">System Events</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.systemEvents || 0"></p>
                            <p class="text-white/80 text-sm">
                                Automated actions
                            </p>
                        </div>
                        <i class="fas fa-cog text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">User Actions</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.userActions || 0"></p>
                            <p class="text-white/80 text-sm">
                                Manual activities
                            </p>
                        </div>
                        <i class="fas fa-user text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Activity Log -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Activity Log</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="text" placeholder="Search activities..." class="zena-input zena-input-sm" x-model="searchQuery">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select x-model="typeFilter" class="zena-select zena-select-sm">
                            <option value="">All Types</option>
                            <option value="login">Login</option>
                            <option value="project">Project</option>
                            <option value="file">File</option>
                            <option value="system">System</option>
                        </select>
                        <button @click="exportLogs()" class="zena-btn zena-btn-outline zena-btn-sm">
                            <i class="fas fa-download mr-2"></i>Export
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <template x-for="activity in filteredActivities" :key="activity.id">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" :class="getActivityAvatarColor(activity.user)">
                                <span class="text-white text-sm font-medium" x-text="activity.userInitials"></span>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-900" x-text="activity.action"></p>
                                <p class="text-sm text-gray-600" x-text="activity.details"></p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-500" x-text="activity.time"></span>
                                <div class="mt-1">
                                    <span class="zena-badge zena-badge-sm" :class="getActivityTypeColor(activity.type)" x-text="activity.type"></span>
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
    function activitiesDashboard() {
        return {
            mockActivities: [
                {
                    id: '1',
                    action: 'Super Admin logged in',
                    details: 'admin@zenamanage.com',
                    type: 'login',
                    user: 'Super Admin',
                    userInitials: 'SA',
                    time: '2 minutes ago'
                },
                {
                    id: '2',
                    action: 'User created new project',
                    details: 'John Doe created "Website Redesign"',
                    type: 'project',
                    user: 'John Doe',
                    userInitials: 'JD',
                    time: '15 minutes ago'
                },
                {
                    id: '3',
                    action: 'File uploaded',
                    details: 'Jane Smith uploaded "project-specs.pdf"',
                    type: 'file',
                    user: 'Jane Smith',
                    userInitials: 'JS',
                    time: '1 hour ago'
                },
                {
                    id: '4',
                    action: 'System backup completed',
                    details: 'Daily backup successful',
                    type: 'system',
                    user: 'System',
                    userInitials: 'SY',
                    time: '2 hours ago'
                },
                {
                    id: '5',
                    action: 'User updated profile',
                    details: 'Mike Johnson updated personal information',
                    type: 'user',
                    user: 'Mike Johnson',
                    userInitials: 'MJ',
                    time: '3 hours ago'
                }
            ],
            searchQuery: '',
            typeFilter: '',
            stats: {
                totalActivities: 156,
                activeUsers: 12,
                systemEvents: 45,
                userActions: 111,
            },

            init() {
                // Initialize dashboard
            },

            get filteredActivities() {
                return this.mockActivities.filter(activity => {
                    const searchMatch = activity.action.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                       activity.details.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const typeMatch = this.typeFilter === '' || activity.type === this.typeFilter;
                    return searchMatch && typeMatch;
                });
            },

            getActivityAvatarColor(user) {
                const colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-red-500'];
                const hash = user.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
                return colors[hash % colors.length];
            },

            getActivityTypeColor(type) {
                switch (type) {
                    case 'login': return 'zena-badge-success';
                    case 'project': return 'zena-badge-primary';
                    case 'file': return 'zena-badge-info';
                    case 'system': return 'zena-badge-neutral';
                    case 'user': return 'zena-badge-warning';
                    default: return 'zena-badge-neutral';
                }
            },

            exportLogs() {
                alert('Exporting activity logs...');
            }
        }
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/activities/index.blade.php ENDPATH**/ ?>