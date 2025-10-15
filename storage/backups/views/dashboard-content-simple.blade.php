<!-- Dashboard Content - Simple Version -->
<div x-data="simpleDashboard()" x-init="init()" class="space-y-8">
    
    <!-- Error State -->
    <div x-show="error" class="bg-red-50 border border-red-200 rounded-xl p-6" role="alert" aria-live="polite">
        <div class="flex items-center mb-4">
            <div class="p-2 bg-red-100 rounded-lg mr-3" aria-hidden="true">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-red-900">Something went wrong</h3>
                <p class="text-sm text-red-700 mt-1" x-text="error"></p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="retryLoad()" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                <i class="fas fa-redo mr-2"></i>
                Retry
            </button>
            <button @click="dismissError()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                Dismiss
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <template x-for="i in 4" :key="'kpi-' + i">
                <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
                    <div class="animate-pulse">
                        <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                        <div class="h-8 bg-gray-200 rounded w-1/2"></div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Main Content -->
    <div x-show="!loading && !error" class="space-y-6">
        
        <!-- KPI Strip -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Active Tasks KPI -->
            <div class="bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 rounded-xl p-4 sm:p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-100 mb-1">Active Tasks</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.activeTasks || '--'"></p>
                        <p class="text-xs text-blue-200 mt-1">+12% from last week</p>
                    </div>
                    <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                        <i class="fas fa-tasks text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Completed Today KPI -->
            <div class="bg-gradient-to-br from-emerald-500 via-green-600 to-teal-700 rounded-xl p-4 sm:p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-emerald-100 mb-1">Completed Today</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.completedToday || '--'"></p>
                        <p class="text-xs text-emerald-200 mt-1">+8% from yesterday</p>
                    </div>
                    <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Team Members KPI -->
            <div class="bg-gradient-to-br from-purple-500 via-violet-600 to-purple-700 rounded-xl p-4 sm:p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-100 mb-1">Team Members</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.teamMembers || '--'"></p>
                        <p class="text-xs text-purple-200 mt-1">+2 new members</p>
                    </div>
                    <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                        <i class="fas fa-users text-white text-xl"></i>
                    </div>
                </div>
            </div>

            <!-- Projects KPI -->
            <div class="bg-gradient-to-br from-orange-500 via-red-500 to-pink-600 rounded-xl p-4 sm:p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-orange-100 mb-1">Active Projects</p>
                        <p class="text-2xl sm:text-3xl font-bold text-white" x-text="kpis.projects || '--'"></p>
                        <p class="text-xs text-orange-200 mt-1">+1 new project</p>
                    </div>
                    <div class="p-3 bg-white/20 backdrop-blur-sm rounded-xl">
                        <i class="fas fa-project-diagram text-white text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-history text-purple-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
                        <p class="text-sm text-gray-500">Latest updates and changes</p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-3">
                <template x-for="item in activity" :key="item.id">
                    <div class="flex items-center space-x-3 p-3 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-circle text-purple-600 text-xs"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-purple-900" x-text="item.description"></p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-xs text-purple-600" x-text="item.user"></span>
                                <span class="text-xs text-purple-500">•</span>
                                <span class="text-xs text-purple-600" x-text="item.created_at"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

</div>

<script>
function simpleDashboard() {
    return {
        loading: true,
        error: null,
        kpis: {
            activeTasks: 0,
            completedToday: 0,
            teamMembers: 0,
            projects: 0
        },
        activity: [],

        async init() {
            console.log('🚀 Simple Dashboard init started');
            await this.loadDashboardData();
        },

        async loadDashboardData() {
            try {
                this.loading = true;
                this.error = null;
                console.log('📊 Loading dashboard data...');
                
                const response = await fetch('/_debug/dashboard-data', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('📊 API Response:', data);
                
                if (data.status === 'success') {
                    this.kpis = {
                        activeTasks: data.data.stats.totalTasks || 0,
                        completedToday: data.data.stats.completedTasks || 0,
                        teamMembers: data.data.stats.teamMembers || 0,
                        projects: data.data.stats.totalProjects || 0
                    };
                    
                    this.activity = data.data.recentActivity || [];
                    
                    console.log('✅ Dashboard data loaded successfully');
                } else {
                    throw new Error(data.error || 'Failed to load dashboard data');
                }
                
            } catch (error) {
                console.error('Error loading dashboard data:', error);
                this.error = error.message || 'Failed to load dashboard data. Please try again.';
                
                // Fallback to mock data
                console.log('🔄 Falling back to mock data...');
                this.kpis = {
                    activeTasks: 15,
                    completedToday: 8,
                    teamMembers: 5,
                    projects: 7
                };
                
                this.activity = [
                    {
                        id: 1,
                        description: 'Task "Fix Login Bug" was updated',
                        user: 'John Smith',
                        created_at: '2 hours ago'
                    },
                    {
                        id: 2,
                        description: 'New project "Mobile App" was created',
                        user: 'Sarah Johnson',
                        created_at: '4 hours ago'
                    }
                ];
                
            } finally {
                this.loading = false;
            }
        },

        retryLoad() {
            console.log('🔄 Retrying dashboard data load...');
            this.error = null;
            this.loading = true;
            this.loadDashboardData();
        },
        
        dismissError() {
            console.log('❌ Dismissing error, using fallback data');
            this.error = null;
            this.loading = false;
        }
    }
}
</script>