@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                    <p class="text-sm text-gray-600">Welcome back, {{ Auth::user()->name ?? 'User' }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="refreshDashboard()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-sync-alt mr-2"></i>Refresh
                    </button>
                    <a href="/frontend/app/projects/create" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>New Project
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-project-diagram text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Projects</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $totalProjects ?? 0 }}</p>
                        <p class="text-sm text-green-600">{{ $projectsChange ?? '+0' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tasks text-green-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Active Tasks</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $totalTasks ?? 0 }}</p>
                        <p class="text-sm text-green-600">{{ $tasksChange ?? '+0' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Team Members</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $totalTeamMembers ?? 0 }}</p>
                        <p class="text-sm text-green-600">{{ $teamChange ?? '+0' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-dollar-sign text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Budget Used</p>
                        <p class="text-2xl font-semibold text-gray-900">${{ number_format($budgetUsed ?? 0) }}</p>
                        <p class="text-sm text-green-600">{{ $budgetChange ?? '+0%' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Projects -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Projects</h3>
                </div>
                <div class="p-6">
                    @if(isset($recentProjects) && $recentProjects->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentProjects->take(5) as $project)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ $project->name }}</h4>
                                        <p class="text-sm text-gray-500">{{ $project->status ?? 'Active' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-900">{{ $project->progress ?? 0 }}%</p>
                                        <div class="w-16 bg-gray-200 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-project-diagram text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No projects yet</p>
                            <a href="/frontend/app/projects/create" class="text-blue-600 hover:text-blue-800 font-medium">Create your first project</a>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Tasks -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Recent Tasks</h3>
                </div>
                <div class="p-6">
                    @if(isset($recentTasks) && $recentTasks->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentTasks->take(5) as $task)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ $task->name }}</h4>
                                        <p class="text-sm text-gray-500">{{ $task->status ?? 'Pending' }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if(($task->priority ?? 'medium') === 'high') bg-red-100 text-red-800
                                            @elseif(($task->priority ?? 'medium') === 'medium') bg-yellow-100 text-yellow-800
                                            @else bg-green-100 text-green-800
                                            @endif">
                                            {{ ucfirst($task->priority ?? 'medium') }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No tasks yet</p>
                            <a href="{{ route('app.tasks.create') }}" class="text-blue-600 hover:text-blue-800 font-medium">Create your first task</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- System Alerts -->
        @if(isset($systemAlerts) && $systemAlerts->count() > 0)
            <div class="mt-8">
                <h3 class="text-lg font-medium text-gray-900 mb-4">System Alerts</h3>
                <div class="space-y-4">
                    @foreach($systemAlerts as $alert)
                        <div class="bg-{{ $alert['type'] === 'error' ? 'red' : 'yellow' }}-50 border border-{{ $alert['type'] === 'error' ? 'red' : 'yellow' }}-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-{{ $alert['type'] === 'error' ? 'exclamation-triangle' : 'info-circle' }} text-{{ $alert['type'] === 'error' ? 'red' : 'yellow' }}-400"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-{{ $alert['type'] === 'error' ? 'red' : 'yellow' }}-800">
                                        {{ $alert['title'] }}
                                    </h3>
                                    <div class="mt-2 text-sm text-{{ $alert['type'] === 'error' ? 'red' : 'yellow' }}-700">
                                        <p>{{ $alert['message'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
function refreshDashboard() {
    window.location.reload();
}
</script>
@endsection
