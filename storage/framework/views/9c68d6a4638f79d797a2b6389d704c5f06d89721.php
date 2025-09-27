<?php $__env->startSection('title', 'Admin Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <?php echo $__env->make('admin.dashboard._kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('admin.dashboard._alerts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <?php echo $__env->make('admin.dashboard._charts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('admin.dashboard._activities', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        
        <div class="space-y-8">
            
            <?php echo $__env->make('admin.dashboard._quick-actions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('admin.dashboard._system-status', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('admin.dashboard._activity-feed', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function adminDashboard() {
        return {
            showNotifications: false,
            showUserMenu: false,
            showAlerts: false,
            showModal: false,
            modalTitle: '',
            modalContent: '',
            currentModal: '',
            chartPeriod: '30d',
            unreadNotifications: 3,

            kpis: {
                totalUsers: 1247,
                userGrowth: '+12%',
                activeTenants: 89,
                tenantGrowth: '+5%',
                systemHealth: '99.8%',
                storageUsage: '67%'
            },

            alerts: [
                {
                    id: 1,
                    title: 'High Memory Usage',
                    message: 'Server memory usage is at 85%',
                    icon: 'fas fa-exclamation-triangle',
                    type: 'warning'
                },
                {
                    id: 2,
                    title: 'SSL Certificate Expiring',
                    message: 'SSL certificate expires in 15 days',
                    icon: 'fas fa-certificate',
                    type: 'warning'
                }
            ],

            notifications: [
                {
                    id: 1,
                    title: 'New User Registration',
                    message: 'John Doe registered for tenant ABC Corp',
                    icon: 'fas fa-user-plus',
                    type: 'info',
                    time: '2 minutes ago'
                },
                {
                    id: 2,
                    title: 'System Backup Complete',
                    message: 'Daily backup completed successfully',
                    icon: 'fas fa-download',
                    type: 'success',
                    time: '1 hour ago'
                },
                {
                    id: 3,
                    title: 'Security Alert',
                    message: 'Multiple failed login attempts detected',
                    icon: 'fas fa-shield-alt',
                    type: 'warning',
                    time: '3 hours ago'
                }
            ],

            recentActivities: [
                {
                    id: 1,
                    title: 'User Created',
                    description: 'New user "Jane Smith" added to tenant "TechCorp"',
                    icon: 'fas fa-user-plus',
                    iconColor: 'text-blue-600',
                    iconBg: 'bg-blue-100',
                    time: '5 minutes ago'
                },
                {
                    id: 2,
                    title: 'Tenant Updated',
                    description: 'Tenant "ABC Corp" settings updated',
                    icon: 'fas fa-building',
                    iconColor: 'text-green-600',
                    iconBg: 'bg-green-100',
                    time: '15 minutes ago'
                },
                {
                    id: 3,
                    title: 'System Backup',
                    description: 'Daily system backup completed',
                    icon: 'fas fa-download',
                    iconColor: 'text-purple-600',
                    iconBg: 'bg-purple-100',
                    time: '1 hour ago'
                }
            ],

            systemStatus: [
                { name: 'Database', status: 'online' },
                { name: 'Cache', status: 'online' },
                { name: 'Queue', status: 'online' },
                { name: 'Storage', status: 'online' },
                { name: 'Email', status: 'online' }
            ],

            activityFeed: [
                {
                    id: 1,
                    user: 'John Doe',
                    action: ' created a new project',
                    avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff',
                    time: '2 minutes ago'
                },
                {
                    id: 2,
                    user: 'Jane Smith',
                    action: ' updated user permissions',
                    avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff',
                    time: '5 minutes ago'
                },
                {
                    id: 3,
                    user: 'Admin',
                    action: ' performed system backup',
                    avatar: 'https://ui-avatars.com/api/?name=Admin&background=8b5cf6&color=ffffff',
                    time: '1 hour ago'
                }
            ],

            init() {
                this.initChart();
                this.startRealTimeUpdates();
            },

            initChart() {
                const ctx = document.getElementById('systemChart').getContext('2d');
                this.chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Users',
                            data: [1200, 1250, 1300, 1280, 1320, 1247],
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        }, {
                            label: 'Tenants',
                            data: [80, 85, 88, 87, 89, 89],
                            borderColor: 'rgb(16, 185, 129)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            },

            updateChart() {
                console.log('Updating chart for period:', this.chartPeriod);
            },

            toggleNotifications() {
                this.showNotifications = !this.showNotifications;
                if (this.showNotifications) {
                    this.unreadNotifications = 0;
                }
            },

            toggleUserMenu() {
                this.showUserMenu = !this.showUserMenu;
            },

            dismissAlert(alertId) {
                this.alerts = this.alerts.filter(alert => alert.id !== alertId);
            },

            dismissAllAlerts() {
                this.alerts = [];
            },

            openModal(type) {
                this.currentModal = type;
                this.showModal = true;
                
                switch(type) {
                    case 'addUser':
                        this.modalTitle = 'Add New User';
                        this.modalContent = `
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select class="w-full border border-gray-300 rounded-md px-3 py-2">
                                        <option>Admin</option>
                                        <option>Project Manager</option>
                                        <option>Member</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;
                    case 'createTenant':
                        this.modalTitle = 'Create New Tenant';
                        this.modalContent = `
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                                    <select class="w-full border border-gray-300 rounded-md px-3 py-2">
                                        <option>Basic</option>
                                        <option>Professional</option>
                                        <option>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        break;
                }
            },

            closeModal() {
                this.showModal = false;
                this.currentModal = '';
            },

            executeModalAction() {
                console.log('Executing action:', this.currentModal);
                this.closeModal();
            },

            refreshActivity() {
                console.log('Refreshing activity feed');
            },

            startRealTimeUpdates() {
                setInterval(() => {
                    this.kpis.totalUsers += Math.floor(Math.random() * 3);
                    this.kpis.activeTenants += Math.floor(Math.random() * 2);
                }, 30000);
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.admin', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/index.blade.php ENDPATH**/ ?>