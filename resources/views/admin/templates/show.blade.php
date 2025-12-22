{{-- Admin Template Set Show --}}
{{-- Displays detailed view of a template set with phases, disciplines, tasks, and dependencies --}}

@php
    $user = Auth::user();
    $set->load(['phases.tasks', 'disciplines.tasks', 'tasks.dependencies.dependsOn', 'presets']);
@endphp

@extends('layouts.dashboard')

@section('title', $set->name)
@section('page-title', $set->name)
@section('page-description', $set->description ?? 'Template Set Details')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $set->name }}</h1>
            <div class="mt-2 flex gap-2">
                <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded">{{ $set->code }}</span>
                <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">v{{ $set->version }}</span>
                @if($set->is_global)
                    <span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded">Global</span>
                @else
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">Tenant</span>
                @endif
                @if($set->is_active)
                    <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded">Active</span>
                @else
                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">Inactive</span>
                @endif
            </div>
            @if($set->description)
                <p class="mt-2 text-gray-600">{{ $set->description }}</p>
            @endif
        </div>
        <div class="flex gap-3">
            @can('publish', $set)
                <form action="{{ route('admin.templates.publish', $set) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-publish mr-2"></i>
                        Publish New Version
                    </button>
                </form>
            @endcan
            @can('update', $set)
                <a href="{{ route('admin.templates.edit', $set) }}" 
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    <i class="fas fa-edit mr-2"></i>
                    Edit
                </a>
            @endcan
        </div>
    </div>

    {{-- Statistics --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Phases</div>
            <div class="text-2xl font-bold text-gray-900">{{ $set->phases->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Disciplines</div>
            <div class="text-2xl font-bold text-gray-900">{{ $set->disciplines->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Tasks</div>
            <div class="text-2xl font-bold text-gray-900">{{ $set->tasks->count() }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="text-sm text-gray-500">Presets</div>
            <div class="text-2xl font-bold text-gray-900">{{ $set->presets->count() }}</div>
        </div>
    </div>

    {{-- Presets --}}
    @if($set->presets->isNotEmpty())
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Presets</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($set->presets as $preset)
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h3 class="font-medium text-gray-900">{{ $preset->name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ $preset->code }}</p>
                            @if($preset->description)
                                <p class="text-sm text-gray-600 mt-2">{{ $preset->description }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Phases Table --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Phases</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tasks</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($set->phases->sortBy('order_index') as $phase)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $phase->order_index }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $phase->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $phase->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $phase->tasks->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Disciplines Table --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Disciplines</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Color</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tasks</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($set->disciplines->sortBy('order_index') as $discipline)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $discipline->order_index }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $discipline->code }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $discipline->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($discipline->color_hex)
                                    <span class="inline-block w-6 h-6 rounded" style="background-color: {{ $discipline->color_hex }}"></span>
                                    <span class="ml-2 text-sm text-gray-600">{{ $discipline->color_hex }}</span>
                                @else
                                    <span class="text-sm text-gray-400">No color</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $discipline->tasks->count() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tasks Table --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Tasks</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phase</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Discipline</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dependencies</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($set->tasks->sortBy('order_index') as $task)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $task->order_index }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $task->code }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div>{{ $task->name }}</div>
                                @if($task->description)
                                    <div class="text-xs text-gray-500 mt-1">{{ Str::limit($task->description, 50) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $task->phase->code ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $task->discipline->code ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($task->est_duration_days)
                                    {{ $task->est_duration_days }} days
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $task->role_key ?? '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($task->dependencies->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($task->dependencies as $dep)
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                                {{ $dep->dependsOn->code ?? '-' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

