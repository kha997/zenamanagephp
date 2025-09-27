<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <?php echo $__env->make('app.dashboard._kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <?php echo $__env->make('app.dashboard._alerts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2 space-y-8">
            
            <?php echo $__env->make('app.dashboard._projects', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('app.dashboard._activities', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
        
        
        <div class="space-y-8">
            
            <?php echo $__env->make('app.dashboard._quick-actions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('app.dashboard._team-status', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            
            
            <?php echo $__env->make('app.dashboard._activity-feed', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function appDashboard() {
        return {
            showNotifications: false,
            showUserMenu: false,
            showAlerts: false,
            showModal: false,
            modalTitle: '',
            modalContent: '',
            currentModal: '',
            chartPeriod: '30d',
            unreadNotifications: 2,

            kpis: {
                totalProjects: 12,
                projectGrowth: '+8%',
                activeTasks: 45,
                taskGrowth: '+15%',
                teamMembers: 8,
                teamGrowth: '+2%',
                completionRate: '87%'
            },

            alerts: [
                {
                    id: 1,
                    title: 'Project Deadline Approaching',
                    message: 'Project "Website Redesign" deadline in 3 days',
                    icon: 'fas fa-exclamation-triangle',
                    type: 'warning'
                }
            ],

            notifications: [
                {
                    id: 1,
                    title: 'Task Assigned',
                    message: 'You have been assigned to "Update Documentation"',
                    icon: 'fas fa-tasks',
                    type: 'info',
                    time: '5 minutes ago'
                },
                {
                    id: 2,
                    title: 'Project Update',
                    message: 'Project "Mobile App" status updated to "In Progress"',
                    icon: 'fas fa-project-diagram',
                    type: 'success',
                    time: '1 hour ago'
                }
            ],

            recentActivities: [
                {
                    id: 1,
                    title: 'Task Completed',
                    description: 'Task "Design Mockups" marked as completed',
                    icon: 'fas fa-check-circle',
                    iconColor: 'text-green-600',
                    iconBg: 'bg-green-100',
                    time: '10 minutes ago'
                },
                {
                    id: 2,
                    title: 'Project Created',
                    description: 'New project "E-commerce Platform" created',
                    icon: 'fas fa-project-diagram',
                    iconColor: 'text-blue-600',
                    iconBg: 'bg-blue-100',
                    time: '2 hours ago'
                },
                {
                    id: 3,
                    title: 'Team Member Added',
                    description: 'John Doe added to project "Website Redesign"',
                    icon: 'fas fa-user-plus',
                    iconColor: 'text-purple-600',
                    iconBg: 'bg-purple-100',
                    time: '4 hours ago'
                }
            ],

            teamStatus: [
                { name: 'John Doe', status: 'online', role: 'Developer' },
                { name: 'Jane Smith', status: 'away', role: 'Designer' },
                { name: 'Mike Johnson', status: 'online', role: 'PM' },
                { name: 'Sarah Wilson', status: 'offline', role: 'QA' }
            ],

            activityFeed: [
                {
                    id: 1,
                    user: 'John Doe',
                    action: ' completed task "Fix Bug #123"',
                    avatar: 'https://ui-avatars.com/api/?name=John+Doe&background=3b82f6&color=ffffff',
                    time: '5 minutes ago'
                },
                {
                    id: 2,
                    user: 'Jane Smith',
                    action: ' uploaded design files',
                    avatar: 'https://ui-avatars.com/api/?name=Jane+Smith&background=10b981&color=ffffff',
                    time: '15 minutes ago'
                },
                {
                    id: 3,
                    user: 'Mike Johnson',
                    action: ' created new project milestone',
                    avatar: 'https://ui-avatars.com/api/?name=Mike+Johnson&background=8b5cf6&color=ffffff',
                    time: '1 hour ago'
                }
            ],

            init() {
                this.initChart();
                this.startRealTimeUpdates();
            },

            initChart() {
                // Chart initialization will be handled by individual chart components
                console.log('App dashboard initialized');
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
                    case 'createProject':
                        this.modalTitle = 'Create New Project';
                        this.modalContent = `
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Name</label>
                                    <input type="text" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea class="w-full border border-gray-300 rounded-md px-3 py-2" rows="3"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deadline</label>
                                    <input type="date" class="w-full border border-gray-300 rounded-md px-3 py-2">
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
                    this.kpis.totalProjects += Math.floor(Math.random() * 2);
                    this.kpis.activeTasks += Math.floor(Math.random() * 5);
                }, 30000);
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/index.blade.php ENDPATH**/ ?>