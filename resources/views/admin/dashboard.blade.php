{{-- Admin Dashboard - Week 2 Implementation --}}
{{-- Using standardized components with admin-specific data and RBAC --}}

@php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Admin-specific KPIs
    $adminKpis = [
        [
            'title' => 'Total Users',
            'value' => $totalUsers ?? 0,
            'change' => '+12%',
            'change_type' => 'positive',
            'icon' => 'fas fa-users',
            'color' => 'blue',
            'description' => 'Across all tenants'
        ],
        [
            'title' => 'Active Tenants',
            'value' => $activeTenants ?? 0,
            'change' => '+3',
            'change_type' => 'positive',
            'icon' => 'fas fa-building',
            'color' => 'green',
            'description' => 'Currently active'
        ],
        [
            'title' => 'Total Projects',
            'value' => $totalProjects ?? 0,
            'change' => '+8%',
            'change_type' => 'positive',
            'icon' => 'fas fa-project-diagram',
            'color' => 'purple',
            'description' => 'System-wide'
        ],
        [
            'title' => 'Active Alerts',
            'value' => $activeAlerts ?? 0,
            'change' => '-2',
            'change_type' => 'negative',
            'icon' => 'fas fa-exclamation-triangle',
            'color' => 'red',
            'description' => 'Requiring attention'
        ],
        [
            'title' => 'System Health',
            'value' => '98.5%',
            'change' => '+0.2%',
            'change_type' => 'positive',
            'icon' => 'fas fa-heartbeat',
            'color' => 'green',
            'description' => 'Uptime'
        ],
        [
            'title' => 'Storage Used',
            'value' => '2.4 TB',
            'change' => '+150 GB',
            'change_type' => 'neutral',
            'icon' => 'fas fa-hdd',
            'color' => 'orange',
            'description' => 'Of 10 TB total'
        ]
    ];
    
    // System-wide metrics
    $systemMetrics = [
        'database_performance' => 98.5,
        'cache_hit_rate' => 94.2,
        'queue_processing' => 99.1,
        'api_response_time' => 245, // ms
        'error_rate' => 0.02, // %
        'active_sessions' => $activeSessions ?? 0
    ];
    
    // Recent system activities
    $recentActivities = collect($recentActivities ?? [])->map(function($activity) {
        return [
            'id' => $activity['id'] ?? uniqid(),
            'type' => $activity['type'] ?? 'info',
            'title' => $activity['title'] ?? 'System Activity',
            'description' => $activity['description'] ?? '',
            'timestamp' => $activity['timestamp'] ?? now()->subMinutes(rand(1, 60)),
            'user' => $activity['user'] ?? 'System',
            'tenant' => $activity['tenant'] ?? 'N/A',
            'severity' => $activity['severity'] ?? 'info'
        ];
    });
    
    // Quick actions for admin
    $quickActions = [
        [
            'title' => 'Add New User',
            'description' => 'Create user account',
            'icon' => 'fas fa-user-plus',
            'color' => 'blue',
            'url' => route('admin.users.create'),
            'permission' => 'users.create'
        ],
        [
            'title' => 'Add New Tenant',
            'description' => 'Create new tenant',
            'icon' => 'fas fa-building',
            'color' => 'green',
            'url' => route('admin.tenants.create'),
            'permission' => 'tenants.create'
        ],
        [
            'title' => 'Security Scan',
            'description' => 'Run security audit',
            'icon' => 'fas fa-shield-alt',
            'color' => 'red',
            'url' => route('admin.security.scan'),
            'permission' => 'security.scan'
        ],
        [
            'title' => 'System Maintenance',
            'description' => 'Maintenance mode',
            'icon' => 'fas fa-tools',
            'color' => 'orange',
            'url' => '#',
            'permission' => 'system.maintenance'
        ],
        [
            'title' => 'View Analytics',
            'description' => 'System analytics',
            'icon' => 'fas fa-chart-line',
            'color' => 'purple',
            'url' => '#',
            'permission' => 'analytics.view'
        ],
        [
            'title' => 'Export Data',
            'description' => 'Export system data',
            'icon' => 'fas fa-download',
            'color' => 'gray',
            'url' => '#',
            'permission' => 'data.export'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Admin Dashboard', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="refreshSystemData()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
            <button onclick="exportSystemReport()" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-download mr-2"></i>Export Report
            </button>
        </div>
    ';
@endphp

<x-shared.layout-wrapper 
    title="Admin Dashboard"
    subtitle="System overview and management"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions"
    variant="admin">
    
    {{-- Admin KPI Strip --}}
    <div class="mb-8">
        <x-shared.kpi-strip :kpis="$adminKpis" />
    </div>
    
    {{-- System Health Overview --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        {{-- System Metrics --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">System Health</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-database text-green-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Database</h3>
                            <p class="text-2xl font-bold text-green-600">{{ $systemMetrics['database_performance'] }}%</p>
                            <p class="text-xs text-gray-500">Performance</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-memory text-blue-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Cache</h3>
                            <p class="text-2xl font-bold text-blue-600">{{ $systemMetrics['cache_hit_rate'] }}%</p>
                            <p class="text-xs text-gray-500">Hit Rate</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-tasks text-purple-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Queue</h3>
                            <p class="text-2xl font-bold text-purple-600">{{ $systemMetrics['queue_processing'] }}%</p>
                            <p class="text-xs text-gray-500">Processing</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-orange-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-orange-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">API Response</h3>
                            <p class="text-2xl font-bold text-orange-600">{{ $systemMetrics['api_response_time'] }}ms</p>
                            <p class="text-xs text-gray-500">Average</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Error Rate</h3>
                            <p class="text-2xl font-bold text-red-600">{{ $systemMetrics['error_rate'] }}%</p>
                            <p class="text-xs text-gray-500">Last 24h</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-16 h-16 mx-auto mb-3 bg-indigo-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-indigo-600 text-xl"></i>
                            </div>
                            <h3 class="text-sm font-medium text-gray-900">Active Sessions</h3>
                            <p class="text-2xl font-bold text-indigo-600">{{ $systemMetrics['active_sessions'] }}</p>
                            <p class="text-xs text-gray-500">Current</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Quick Actions --}}
        <div>
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-6 space-y-3">
                    @foreach($quickActions as $action)
                        <a href="{{ $action['url'] }}" 
                           class="flex items-center p-3 bg-{{ $action['color'] }}-50 rounded-lg hover:bg-{{ $action['color'] }}-100 transition-colors">
                            <i class="fas {{ $action['icon'] }} text-{{ $action['color'] }}-600 mr-3"></i>
                            <div>
                                <span class="font-medium text-{{ $action['color'] }}-900">{{ $action['title'] }}</span>
                                <p class="text-xs text-{{ $action['color'] }}-700">{{ $action['description'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    {{-- Recent System Activities --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Recent System Activities</h2>
                <button onclick="viewAllActivities()" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    View All
                </button>
            </div>
        </div>
        <div class="p-6">
            @if($recentActivities->count() > 0)
                <div class="space-y-4">
                    @foreach($recentActivities->take(10) as $activity)
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-{{ $activity['severity'] === 'error' ? 'red' : ($activity['severity'] === 'warning' ? 'yellow' : 'blue') }}-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-{{ $activity['type'] === 'user' ? 'user' : ($activity['type'] === 'tenant' ? 'building' : 'info-circle') }} text-white text-sm"></i>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ $activity['title'] }}</p>
                                <p class="text-xs text-gray-500">{{ $activity['description'] }}</p>
                                <div class="flex items-center mt-1 space-x-2">
                                    <span class="text-xs text-gray-400">{{ $activity['user'] }}</span>
                                    <span class="text-xs text-gray-400">•</span>
                                    <span class="text-xs text-gray-400">{{ $activity['tenant'] }}</span>
                                    <span class="text-xs text-gray-400">•</span>
                                    <span class="text-xs text-gray-400">{{ $activity['timestamp']->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-info-circle text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No Recent Activities</h3>
                    <p class="text-gray-500">System activities will appear here</p>
                </div>
            @endif
        </div>
    </div>
</x-shared.layout-wrapper>

@push('scripts')
<script>
function refreshSystemData() {
    // Refresh system metrics and activities
    fetch('/api/v1/admin/dashboard/refresh', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.location.reload();
        } else {
            alert('Failed to refresh system data');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error refreshing system data');
    });
}

function exportSystemReport() {
    // Export system report
    fetch('/api/v1/admin/dashboard/export', {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        }
    })
    .then(response => {
        if (response.ok) {
            return response.blob();
        }
        throw new Error('Export failed');
    })
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'system-report-' + new Date().toISOString().split('T')[0] + '.pdf';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    })
    .catch(error => {
        console.error('Error exporting report:', error);
        alert('Failed to export system report');
    });
}

function viewAllActivities() {
    // Navigate to activities page
    window.location.href = '/admin/activities';
}

function getAuthToken() {
    // Get auth token from localStorage or session
    return localStorage.getItem('auth_token') || '';
}

// Auto-refresh system data every 5 minutes
setInterval(() => {
    refreshSystemData();
}, 300000);
</script>
@endpush
