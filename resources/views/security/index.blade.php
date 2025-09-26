@extends('layouts.app')

@section('title', 'Security Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header with Logo -->
    <header class="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                @include('components.zena-logo', ['subtitle' => 'Security Dashboard'])
                
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
        <div x-data="securityDashboard()" x-init="init()">
            <!-- Security Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="dashboard-card metric-card green p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Security Score</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.securityScore || 0"></p>
                            <p class="text-white/80 text-sm">
                                <span x-text="stats?.securityLevel || 'Good'"></span>
                            </p>
                        </div>
                        <i class="fas fa-shield-alt text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card blue p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Active Sessions</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.activeSessions || 0"></p>
                            <p class="text-white/80 text-sm">
                                All secure
                            </p>
                        </div>
                        <i class="fas fa-users text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card orange p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Failed Logins</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.failedLogins || 0"></p>
                            <p class="text-white/80 text-sm">
                                Last 24h
                            </p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-4xl text-white/60"></i>
                    </div>
                </div>

                <div class="dashboard-card metric-card purple p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white/80 text-sm">Security Events</p>
                            <p class="text-3xl font-bold text-white" x-text="stats?.securityEvents || 0"></p>
                            <p class="text-white/80 text-sm">
                                This week
                            </p>
                        </div>
                        <i class="fas fa-eye text-4xl text-white/60"></i>
                    </div>
                </div>
            </div>

            <!-- Security Overview Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Recent Security Events -->
                <div class="dashboard-card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Recent Security Events</h3>
                        <div class="flex items-center gap-2">
                            <button class="zena-btn zena-btn-primary zena-btn-sm" @click="runSecurityScan()">
                                <i class="fas fa-search mr-2"></i>
                                Run Scan
                            </button>
                            <a href="/admin" class="zena-btn zena-btn-outline zena-btn-sm">
                                <i class="fas fa-cog mr-2"></i>
                                Admin Panel
                            </a>
                        </div>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <template x-for="event in recentEvents.slice(0, 4)" :key="event.id">
                            <li class="py-3 flex items-center justify-between hover:bg-gray-50 rounded-lg px-2 -mx-2 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center" :class="getEventIconColor(event.type)">
                                        <i class="text-white text-sm" :class="getEventIcon(event.type)"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900" x-text="event.title"></p>
                                        <p class="text-sm text-gray-600">
                                            <span x-text="event.user"></span> • <span x-text="event.time"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="zena-badge" :class="{'zena-badge-success': event.severity === 'low', 'zena-badge-warning': event.severity === 'medium', 'zena-badge-danger': event.severity === 'high'}" x-text="event.severity"></span>
                                </div>
                            </li>
                        </template>
                        <template x-if="recentEvents.length === 0">
                            <li class="py-3 text-center text-gray-500">No recent security events.</li>
                        </template>
                    </ul>
                </div>

                <!-- Security Status -->
                <div class="dashboard-card p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Security Status</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Two-Factor Authentication</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Enabled</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">SSL Certificate</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Valid</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Firewall Status</span>
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Last Security Scan</span>
                            <span class="text-sm font-medium text-gray-900">2 hours ago</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: 95%"></div>
                        </div>
                        <p class="text-xs text-gray-500">95% security compliance achieved</p>
                    </div>
                </div>
            </div>

            <!-- Security Events Table -->
            <div class="dashboard-card p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Security Events Log</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <input type="text" placeholder="Search events..." class="zena-input zena-input-sm" x-model="searchQuery">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <select x-model="severityFilter" class="zena-select zena-select-sm">
                            <option value="">All Severity</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                        <select x-model="typeFilter" class="zena-select zena-select-sm">
                            <option value="">All Types</option>
                            <option value="login">Login</option>
                            <option value="failed_login">Failed Login</option>
                            <option value="permission">Permission</option>
                            <option value="system">System</option>
                        </select>
                        <button @click="resetFilters()" class="zena-btn zena-btn-outline zena-btn-sm">
                            <i class="fas fa-redo mr-2"></i>Reset
                        </button>
                    </div>
                </div>

                <!-- Events Table -->
                <div class="overflow-x-auto">
                    <table class="zena-table w-full">
                        <thead>
                            <tr>
                                <th class="text-left">Event</th>
                                <th class="text-left">Type</th>
                                <th class="text-left">Severity</th>
                                <th class="text-left">User</th>
                                <th class="text-left">IP Address</th>
                                <th class="text-left">Time</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="event in filteredEvents" :key="event.id">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="font-medium text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <i :class="getEventIcon(event.type)" class="text-lg" :style="'color:' + getEventIconColor(event.type, true)"></i>
                                            <span x-text="event.title"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="zena-badge zena-badge-sm" :class="getTypeColor(event.type)" x-text="event.type"></span>
                                    </td>
                                    <td>
                                        <span class="zena-badge" :class="{'zena-badge-success': event.severity === 'low', 'zena-badge-warning': event.severity === 'medium', 'zena-badge-danger': event.severity === 'high'}" x-text="event.severity"></span>
                                    </td>
                                    <td x-text="event.user"></td>
                                    <td x-text="event.ipAddress"></td>
                                    <td x-text="event.time"></td>
                                    <td class="text-right">
                                        <button @click="viewEvent(event.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-eye"></i></button>
                                        <button @click="investigateEvent(event.id)" class="zena-btn zena-btn-outline zena-btn-sm"><i class="fas fa-search"></i></button>
                                        <button @click="resolveEvent(event.id)" class="zena-btn zena-btn-outline zena-btn-sm zena-btn-success"><i class="fas fa-check"></i></button>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredEvents.length === 0">
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-gray-500">No security events found matching your criteria.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center text-sm text-gray-700">
                        <span>Showing </span>
                        <select class="mx-2 border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                        </select>
                        <span> of <span class="font-medium" x-text="mockEvents.length"></span> results</span>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-chevron-left mr-1"></i> Previous
                        </button>
                        <div class="flex items-center space-x-1">
                            <button class="px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700">1</button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">2</button>
                            <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">3</button>
                        </div>
                        <button class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-gray-900">
                            Next <i class="fas fa-chevron-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    function securityDashboard() {
        return {
            mockEvents: [
                {
                    id: '1',
                    title: 'Successful Login',
                    type: 'login',
                    severity: 'low',
                    user: 'admin@zenamanage.com',
                    ipAddress: '192.168.1.100',
                    time: '2 minutes ago'
                },
                {
                    id: '2',
                    title: 'Failed Login Attempt',
                    type: 'failed_login',
                    severity: 'medium',
                    user: 'unknown@example.com',
                    ipAddress: '203.0.113.42',
                    time: '15 minutes ago'
                },
                {
                    id: '3',
                    title: 'Permission Denied',
                    type: 'permission',
                    severity: 'medium',
                    user: 'user@company.com',
                    ipAddress: '192.168.1.50',
                    time: '1 hour ago'
                },
                {
                    id: '4',
                    title: 'System Configuration Change',
                    type: 'system',
                    severity: 'high',
                    user: 'admin@zenamanage.com',
                    ipAddress: '192.168.1.100',
                    time: '2 hours ago'
                },
                {
                    id: '5',
                    title: 'Suspicious Activity Detected',
                    type: 'system',
                    severity: 'high',
                    user: 'unknown',
                    ipAddress: '203.0.113.99',
                    time: '3 hours ago'
                }
            ],
            searchQuery: '',
            severityFilter: '',
            typeFilter: '',
            stats: {
                securityScore: 95,
                securityLevel: 'Excellent',
                activeSessions: 42,
                failedLogins: 3,
                securityEvents: 15,
            },
            recentEvents: [],

            init() {
                this.recentEvents = this.mockEvents.slice(0, 4);
                this.$watch('searchQuery', () => this.updateFilteredEvents());
                this.$watch('severityFilter', () => this.updateFilteredEvents());
                this.$watch('typeFilter', () => this.updateFilteredEvents());
            },

            get filteredEvents() {
                return this.mockEvents.filter(event => {
                    const searchMatch = event.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                                       event.user.toLowerCase().includes(this.searchQuery.toLowerCase());
                    const severityMatch = this.severityFilter === '' || event.severity === this.severityFilter;
                    const typeMatch = this.typeFilter === '' || event.type === this.typeFilter;
                    return searchMatch && severityMatch && typeMatch;
                });
            },

            updateFilteredEvents() {
                // This function is primarily for reactivity
            },

            getEventIcon(type) {
                switch (type) {
                    case 'login': return 'fas fa-sign-in-alt';
                    case 'failed_login': return 'fas fa-times-circle';
                    case 'permission': return 'fas fa-lock';
                    case 'system': return 'fas fa-cog';
                    default: return 'fas fa-info-circle';
                }
            },

            getEventIconColor(type, isText = false) {
                let colorClass = '';
                switch (type) {
                    case 'login': colorClass = 'bg-green-500'; break;
                    case 'failed_login': colorClass = 'bg-red-500'; break;
                    case 'permission': colorClass = 'bg-orange-500'; break;
                    case 'system': colorClass = 'bg-blue-500'; break;
                    default: colorClass = 'bg-gray-500'; break;
                }
                return isText ? `color: var(--${type}-color, ${colorClass.replace('bg-', 'text-')})` : colorClass;
            },

            getTypeColor(type) {
                switch (type) {
                    case 'login': return 'zena-badge-success';
                    case 'failed_login': return 'zena-badge-danger';
                    case 'permission': return 'zena-badge-warning';
                    case 'system': return 'zena-badge-primary';
                    default: return 'zena-badge-neutral';
                }
            },

            runSecurityScan() {
                alert('Running security scan...');
            },

            viewEvent(eventId) {
                alert(`Viewing security event: ${eventId}`);
            },

            investigateEvent(eventId) {
                alert(`Investigating security event: ${eventId}`);
            },

            resolveEvent(eventId) {
                if (confirm(`Are you sure you want to resolve event ${eventId}?`)) {
                    alert(`Resolving security event: ${eventId}`);
                }
            },

            resetFilters() {
                this.searchQuery = '';
                this.severityFilter = '';
                this.typeFilter = '';
            }
        }
    }
</script>
@endsection
