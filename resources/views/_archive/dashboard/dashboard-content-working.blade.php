<!-- Fixed Dashboard Content -->
<div class="p-6">
    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600">Welcome to your dashboard</p>
    
    <!-- KPI Cards -->
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900">Total Tasks</h3>
            <p class="text-3xl font-bold text-blue-600" id="totalTasks">--</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900">Completed Today</h3>
            <p class="text-3xl font-bold text-green-600" id="completedTasks">--</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900">Team Members</h3>
            <p class="text-3xl font-bold text-purple-600" id="teamMembers">--</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900">Projects</h3>
            <p class="text-3xl font-bold text-orange-600" id="totalProjects">--</p>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="mt-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Activity</h2>
        <div class="bg-white rounded-lg shadow p-6">
            <div id="recentActivity">
                <p class="text-gray-500">Loading...</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="mt-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div id="quickActions">
                <p class="text-gray-500">Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
console.log('🚀 Dashboard script loaded');

// Simple function to load dashboard data
async function loadDashboardData() {
    try {
        console.log('📊 Fetching dashboard data...');
        const response = await fetch('/dashboard-data');
        const data = await response.json();
        
        console.log('📊 Dashboard data received:', data);
        
        if (data.status === 'success' && data.data) {
            // Update KPI cards
            document.getElementById('totalTasks').textContent = data.data.stats.totalTasks;
            document.getElementById('completedTasks').textContent = data.data.stats.completedTasks;
            document.getElementById('teamMembers').textContent = data.data.stats.teamMembers;
            document.getElementById('totalProjects').textContent = data.data.stats.totalProjects;
            
            // Update recent activity
            const activityHtml = data.data.recentActivity.map(activity => 
                `<div class="flex items-center space-x-3 py-2">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-blue-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">${activity.user}</p>
                        <p class="text-sm text-gray-500">${activity.action}</p>
                    </div>
                    <div class="ml-auto text-sm text-gray-500">${activity.time}</div>
                </div>`
            ).join('');
            document.getElementById('recentActivity').innerHTML = activityHtml;
            
            // Update quick actions
            const actionsHtml = data.data.quickActions.map(action => 
                `<button class="bg-white border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center space-x-3">
                        <i class="${action.icon} text-blue-600"></i>
                        <span class="text-sm font-medium text-gray-900">${action.title}</span>
                    </div>
                </button>`
            ).join('');
            document.getElementById('quickActions').innerHTML = actionsHtml;
            
            console.log('✅ Dashboard updated successfully');
        } else {
            console.error('❌ Invalid data format:', data);
            showError('Invalid data format');
        }
    } catch (error) {
        console.error('❌ Error loading dashboard data:', error);
        showError('Failed to load dashboard data');
    }
}

function showError(message) {
    document.getElementById('totalTasks').textContent = '--';
    document.getElementById('completedTasks').textContent = '--';
    document.getElementById('teamMembers').textContent = '--';
    document.getElementById('totalProjects').textContent = '--';
    document.getElementById('recentActivity').innerHTML = `<p class="text-red-500">${message}</p>`;
    document.getElementById('quickActions').innerHTML = `<p class="text-red-500">${message}</p>`;
}

// Load data when page is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadDashboardData);
} else {
    loadDashboardData();
}
</script>
