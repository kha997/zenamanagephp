{{--
    Example Dashboard Implementation
    Demonstrates ZenaManage Dashboard Design Principles
    
    This example shows how to use the reusable components and layout
    to create a new dashboard following the established patterns.
--}}

@extends('layouts.dashboard-layout')

@section('title', 'Project Manager Dashboard')

@section('kpis')
    {{-- Active Projects KPI --}}
    @include('components.dashboard-kpi-card', [
        'kpi_key' => 'projects-active',
        'label' => 'Active Projects',
        'value' => 12,
        'trend' => '+8%',
        'trend_type' => 'positive',
        'icon' => 'fas fa-project-diagram',
        'icon_color' => 'blue',
        'primary_action' => [
            'label' => 'View Projects',
            'url' => '/app/projects',
            'method' => 'GET'
        ],
        'secondary_action' => [
            'label' => 'Create Project',
            'url' => '/app/projects/create',
            'method' => 'GET'
        ]
    ])
    
    {{-- Tasks Due Today KPI --}}
    @include('components.dashboard-kpi-card', [
        'kpi_key' => 'tasks-today',
        'label' => 'Tasks Due Today',
        'value' => 7,
        'trend' => '+2',
        'trend_type' => 'neutral',
        'icon' => 'fas fa-tasks',
        'icon_color' => 'green',
        'primary_action' => [
            'label' => 'View Tasks',
            'url' => '/app/tasks?filter=today',
            'method' => 'GET'
        ],
        'secondary_action' => [
            'label' => 'Create Task',
            'url' => '/app/tasks/create',
            'method' => 'GET'
        ]
    ])
    
    {{-- Overdue Tasks KPI --}}
    @include('components.dashboard-kpi-card', [
        'kpi_key' => 'tasks-overdue',
        'label' => 'Overdue Tasks',
        'value' => 3,
        'trend' => '-12%',
        'trend_type' => 'positive',
        'icon' => 'fas fa-exclamation-triangle',
        'icon_color' => 'red',
        'primary_action' => [
            'label' => 'View Overdue',
            'url' => '/app/tasks?filter=overdue',
            'method' => 'GET'
        ]
    ])
    
    {{-- Team Utilization KPI --}}
    @include('components.dashboard-kpi-card', [
        'kpi_key' => 'team-utilization',
        'label' => 'Team Utilization',
        'value' => '85%',
        'trend' => '+5%',
        'trend_type' => 'positive',
        'icon' => 'fas fa-users',
        'icon_color' => 'purple',
        'primary_action' => [
            'label' => 'View Team',
            'url' => '/app/team',
            'method' => 'GET'
        ]
    ])
@endsection

@section('primary-content')
    {{-- Project Progress Chart --}}
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Project Progress</h3>
            <div class="flex items-center space-x-2">
                <select class="text-sm border border-gray-300 rounded px-2 py-1">
                    <option>Last 30 days</option>
                    <option>Last 90 days</option>
                    <option>Last year</option>
                </select>
            </div>
        </div>
        <div id="project-progress-chart" class="h-64"></div>
    </div>
    
    {{-- Recent Activity --}}
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
            <a href="/app/activity" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
        </div>
        <div class="space-y-4">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-tasks text-blue-600 text-sm"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900">
                        <span class="font-medium">John Doe</span> completed task 
                        <span class="font-medium">"Design Review"</span>
                    </p>
                    <p class="text-xs text-gray-500">2 hours ago</p>
                </div>
            </div>
            
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-project-diagram text-green-600 text-sm"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900">
                        Project <span class="font-medium">"Website Redesign"</span> 
                        reached 75% completion
                    </p>
                    <p class="text-xs text-gray-500">4 hours ago</p>
                </div>
            </div>
            
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-user-plus text-purple-600 text-sm"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900">
                        <span class="font-medium">Jane Smith</span> joined the team
                    </p>
                    <p class="text-xs text-gray-500">1 day ago</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('secondary-content')
    {{-- Upcoming Meetings --}}
    <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Upcoming Meetings</h3>
            <a href="/app/calendar" class="text-sm text-blue-600 hover:text-blue-800">View Calendar</a>
        </div>
        <ul data-zena-meetings class="space-y-3">
            {{-- Populated by JavaScript --}}
        </ul>
    </div>
    
    {{-- Notifications --}}
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
            <div id="notification-dropdown" class="relative">
                <button class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded">
                    <i class="fas fa-bell text-xl" aria-hidden="true"></i>
                    <span class="badge badge-primary absolute -top-1 -right-1 text-xs">0 New</span>
                </button>
            </div>
        </div>
        <div id="tabs-basic-1" class="space-y-3">
            <ul class="space-y-2">
                {{-- Populated by JavaScript --}}
            </ul>
        </div>
    </div>
@endsection

@section('full-width-content')
    {{-- Team Performance Table --}}
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Team Performance</h3>
            <div class="flex items-center space-x-2">
                <button class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <i class="fas fa-download mr-2" aria-hidden="true"></i>Export
                </button>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table id="team-performance-table" class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Team Member</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Role</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Tasks Completed</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Hours Logged</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Performance</th>
                        <th class="text-left py-3 px-4 font-medium text-gray-900">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-3 px-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-blue-600">JD</span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">John Doe</div>
                                    <div class="text-sm text-gray-500">john@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Developer
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-900">24</td>
                        <td class="py-3 px-4 text-gray-900">160h</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 85%"></div>
                                </div>
                                <span class="text-sm text-gray-600">85%</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center space-x-2">
                                <button class="text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                                <button class="text-gray-600 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-3 px-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-sm font-medium text-green-600">JS</span>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-900">Jane Smith</div>
                                    <div class="text-sm text-gray-500">jane@example.com</div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Designer
                            </span>
                        </td>
                        <td class="py-3 px-4 text-gray-900">18</td>
                        <td class="py-3 px-4 text-gray-900">120h</td>
                        <td class="py-3 px-4">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-yellow-600 h-2 rounded-full" style="width: 75%"></div>
                                </div>
                                <span class="text-sm text-gray-600">75%</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center space-x-2">
                                <button class="text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                                <button class="text-gray-600 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 rounded">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    // Initialize dashboard-specific functionality
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📊 Project Manager Dashboard initialized');
        
        // Initialize chart
        initializeProjectProgressChart();
        
        // Load dynamic content
        loadMeetings();
        loadNotifications();
    });
    
    function initializeProjectProgressChart() {
        // Example chart initialization
        const chartElement = document.getElementById('project-progress-chart');
        if (chartElement && window.ApexCharts) {
            const chart = new ApexCharts(chartElement, {
                chart: {
                    type: 'line',
                    height: 256,
                    toolbar: {
                        show: false
                    }
                },
                series: [{
                    name: 'Projects Completed',
                    data: [10, 15, 18, 22, 25, 28, 30]
                }],
                xaxis: {
                    categories: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7']
                },
                colors: ['#3b82f6'],
                stroke: {
                    width: 3,
                    curve: 'smooth'
                },
                grid: {
                    borderColor: '#f1f5f9'
                }
            });
            
            chart.render();
        }
    }
    
    function loadMeetings() {
        // Load meetings data
        const meetingsContainer = document.querySelector('[data-zena-meetings]');
        if (meetingsContainer) {
            meetingsContainer.innerHTML = `
                <li>
                    <div class="flex items-center gap-3">
                        <div class="avatar">
                            <div class="rounded-field size-10">
                                <img src="https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png" alt="avatar"/>
                            </div>
                        </div>
                        <div class="grow">
                            <h6 class="text-base-content mb-px font-medium">Project Review Meeting</h6>
                            <div class="text-base-content/50 flex items-center gap-1 text-sm">
                                <span class="icon-[tabler--calendar] size-4.5"></span>
                                <span>10:00 AM - 11:00 AM</span>
                            </div>
                        </div>
                        <span class="badge badge-soft">Meeting</span>
                    </div>
                </li>
                <li>
                    <div class="flex items-center gap-3">
                        <div class="avatar">
                            <div class="rounded-field size-10">
                                <img src="https://cdn.flyonui.com/fy-assets/avatar/avatar-2.png" alt="avatar"/>
                            </div>
                        </div>
                        <div class="grow">
                            <h6 class="text-base-content mb-px font-medium">Team Standup</h6>
                            <div class="text-base-content/50 flex items-center gap-1 text-sm">
                                <span class="icon-[tabler--calendar] size-4.5"></span>
                                <span>2:00 PM - 2:30 PM</span>
                            </div>
                        </div>
                        <span class="badge badge-soft">Standup</span>
                    </div>
                </li>
            `;
        }
    }
    
    function loadNotifications() {
        // Load notifications data
        const notificationsContainer = document.querySelector('#tabs-basic-1 ul');
        if (notificationsContainer) {
            notificationsContainer.innerHTML = `
                <li>
                    <div class="flex w-full items-center gap-3 py-3">
                        <div class="avatar"><div class="size-10 rounded-full">
                            <img src="https://cdn.flyonui.com/fy-assets/avatar/avatar-1.png" alt="avatar" />
                        </div></div>
                        <div class="flex-1">
                            <h6 class="text-base-content mb-0.5 font-medium">Task Completed</h6>
                            <div class="flex items-center gap-x-2.5">
                                <p class="text-base-content/50 text-sm">2 hours ago</p>
                                <span class="bg-neutral/20 size-1.5 rounded-full"></span>
                                <p class="text-base-content/50 text-sm">Task</p>
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-3">
                            <button class="btn btn-xs btn-circle btn-text">
                                <span class="icon-[tabler--x] text-base-content/80 size-4"></span>
                            </button>
                            <div class="bg-primary size-1.5 rounded-full"></div>
                        </div>
                    </div>
                </li>
            `;
        }
    }
</script>
@endpush
