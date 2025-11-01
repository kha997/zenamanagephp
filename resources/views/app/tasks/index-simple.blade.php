@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tasks</h1>
                    <p class="text-sm text-gray-600">Manage your tasks and track progress</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('app.tasks.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-plus mr-2"></i>New Task
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Tasks List -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">All Tasks</h3>
            </div>
            <div class="divide-y divide-gray-200">
                @if(isset($tasks) && $tasks->count() > 0)
                    @foreach($tasks as $task)
                        <div class="px-6 py-4 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <input type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900">{{ $task->name }}</h4>
                                        <p class="text-sm text-gray-500">{{ $task->description ?? 'No description' }}</p>
                                        <div class="mt-1 flex items-center space-x-4 text-xs text-gray-500">
                                            <span><i class="fas fa-project-diagram mr-1"></i>{{ $task->project->name ?? 'No project' }}</span>
                                            <span><i class="fas fa-user mr-1"></i>{{ $task->assignee->name ?? 'Unassigned' }}</span>
                                            @if($task->end_date)
                                                <span><i class="fas fa-calendar mr-1"></i>{{ \Carbon\Carbon::parse($task->end_date)->format('M d, Y') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if(($task->priority ?? 'medium') === 'high') bg-red-100 text-red-800
                                        @elseif(($task->priority ?? 'medium') === 'medium') bg-yellow-100 text-yellow-800
                                        @else bg-green-100 text-green-800
                                        @endif">
                                        {{ ucfirst($task->priority ?? 'medium') }}
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($task->status === 'completed') bg-green-100 text-green-800
                                        @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                        @elseif($task->status === 'pending') bg-yellow-100 text-yellow-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($task->status ?? 'pending') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-tasks text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks yet</h3>
                        <p class="text-gray-500 mb-6">Get started by creating your first task</p>
                        <a href="{{ route('app.tasks.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                            <i class="fas fa-plus mr-2"></i>Create Task
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
