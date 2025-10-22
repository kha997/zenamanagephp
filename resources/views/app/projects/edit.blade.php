@extends('layouts.app')

@section('title', 'Edit Project')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Edit Project</h1>
                    <p class="text-sm text-gray-600">Update project details</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('app.projects.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Projects
                    </a>
                    <a href="{{ route('app.projects.show', $project->id) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        <i class="fas fa-eye mr-2"></i>View Project
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white rounded-lg shadow">
            <form action="{{ route('app.projects.update', $project->id) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')
                
                <!-- Project Name -->
                <div class="mb-6">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Project Name *</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required
                           value="{{ old('name', $project->name ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                           placeholder="Enter project name">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Project Code -->
                <div class="mb-6">
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Project Code *</label>
                    <input type="text" 
                           id="code" 
                           name="code" 
                           required
                           value="{{ old('code', $project->code) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('code') border-red-500 @enderror"
                           placeholder="Enter project code">
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="description" 
                              name="description" 
                              rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                              placeholder="Enter project description">{{ old('description', $project->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Client Selection -->
                <div class="mb-6">
                    <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                    <select id="client_id" 
                            name="client_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('client_id') border-red-500 @enderror">
                        <option value="">Select a client</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" {{ old('client_id', $project->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Project Manager -->
                <div class="mb-6">
                    <label for="project_manager_id" class="block text-sm font-medium text-gray-700 mb-2">Project Manager</label>
                    <select id="project_manager_id" 
                            name="project_manager_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('project_manager_id') border-red-500 @enderror">
                        <option value="">Select a project manager</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('project_manager_id', $project->project_manager_id) == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_manager_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Status -->
                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" 
                            name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('status') border-red-500 @enderror">
                        <option value="planning" {{ old('status', $project->status) == 'planning' ? 'selected' : '' }}>Planning</option>
                        <option value="active" {{ old('status', $project->status) == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="on_hold" {{ old('status', $project->status) == 'on_hold' ? 'selected' : '' }}>On Hold</option>
                        <option value="completed" {{ old('status', $project->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ old('status', $project->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                        <input type="date" 
                               id="start_date" 
                               name="start_date"
                               value="{{ old('start_date', $project->start_date ? $project->start_date->format('Y-m-d') : '') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('start_date') border-red-500 @enderror">
                        @error('start_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                        <input type="date" 
                               id="end_date" 
                               name="end_date"
                               value="{{ old('end_date', $project->end_date ? $project->end_date->format('Y-m-d') : '') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('end_date') border-red-500 @enderror">
                        @error('end_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Budget -->
                <div class="mb-6">
                    <label for="budget_total" class="block text-sm font-medium text-gray-700 mb-2">Total Budget</label>
                    <input type="number" 
                           id="budget_total" 
                           name="budget_total"
                           step="0.01"
                           min="0"
                           value="{{ old('budget_total', $project->budget_total) }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('budget_total') border-red-500 @enderror"
                           placeholder="Enter total budget">
                    @error('budget_total')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                    <a href="{{ route('app.projects.index') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-2 rounded-lg font-medium">
                        Cancel
                    </a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Update Project
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection