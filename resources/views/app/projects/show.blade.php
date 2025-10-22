@extends('layouts.app')

@section('title', 'Project Details')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $project->name ?? 'Project Not Found' }}</h1>
                    <p class="text-sm text-gray-600">Project Code: {{ $project->code ?? 'N/A' }}</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('app.projects.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Projects
                    </a>
                    <a href="{{ route('app.projects.edit', $project->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-edit mr-2"></i>Edit Project
                    </a>
                    <form action="{{ route('app.projects.destroy', $project->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                            <i class="fas fa-trash mr-2"></i>Delete Project
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Project Details -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Project Information</h3>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Project Name</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $project->name ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Project Code</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $project->code ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if(($project->status ?? '') === 'active') bg-green-100 text-green-800
                                        @elseif(($project->status ?? '') === 'planning') bg-blue-100 text-blue-800
                                        @elseif(($project->status ?? '') === 'on_hold') bg-yellow-100 text-yellow-800
                                        @elseif(($project->status ?? '') === 'completed') bg-gray-100 text-gray-800
                                        @elseif(($project->status ?? '') === 'cancelled') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                        {{ ucfirst(str_replace('_', ' ', $project->status ?? 'unknown')) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Client</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $project->client->name ?? 'No client assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Project Manager</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $project->projectManager->name ?? 'No manager assigned' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Budget</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($project->budget_total ?? false)
                                        ${{ number_format($project->budget_total, 2) }}
                                    @else
                                        Not set
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Start Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($project->start_date ?? false)
                                        {{ is_string($project->start_date) ? $project->start_date : $project->start_date->format('M d, Y') }}
                                    @else
                                        Not set
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">End Date</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($project->end_date ?? false)
                                        {{ is_string($project->end_date) ? $project->end_date : $project->end_date->format('M d, Y') }}
                                    @else
                                        Not set
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        
                        @if($project->description)
                            <div class="mt-6">
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $project->description }}</dd>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Project Progress -->
                <div class="mt-8 bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Project Progress</h3>
                    </div>
                    <div class="p-6">
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-1">
                                <span>Progress</span>
                                <span>{{ $project->progress ?? 0 }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $project->progress ?? 0 }}%"></div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="flex space-x-4">
                            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-play mr-2"></i>Start Project
                            </button>
                            <button class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-pause mr-2"></i>Pause Project
                            </button>
                            <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-stop mr-2"></i>Complete Project
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-8">
                <!-- Project Stats -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Project Stats</h3>
                    </div>
                    <div class="p-6">
                        <dl class="space-y-4">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Tasks</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $project->tasks_count ?? 0 }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Completed Tasks</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $project->completed_tasks_count ?? 0 }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Team Members</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $project->team_members_count ?? 0 }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500">Documents</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $project->documents_count ?? 0 }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Team Members -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Team Members</h3>
                    </div>
                    <div class="p-6">
                        @if(isset($project->teamMembers) && $project->teamMembers->count() > 0)
                            <ul class="space-y-3">
                                @foreach($project->teamMembers as $member)
                                    <li class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-medium text-gray-600">{{ substr($member->name, 0, 1) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">{{ $member->name }}</p>
                                            <p class="text-xs text-gray-500">{{ $member->role ?? 'Team Member' }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">No team members assigned</p>
                        @endif
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Activity</h3>
                    </div>
                    <div class="p-6">
                        @if(isset($project->activities) && $project->activities->count() > 0)
                            <ul class="space-y-4">
                                @foreach($project->activities->take(5) as $activity)
                                    <li class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-bell text-blue-600 text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900">{{ $activity->description ?? 'Activity' }}</p>
                                            <p class="text-xs text-gray-500">{{ $activity->created_at->diffForHumans() }}</p>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">No recent activity</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection