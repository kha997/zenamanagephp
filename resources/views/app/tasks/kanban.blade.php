@extends('layouts.app')

@section('title', __('tasks.kanban_title'))

@section('content')
<div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="taskKanban()" x-init="init()">
    <!-- Header -->
    <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ __('tasks.kanban_title') }}
                    </h1>
                    <div class="flex items-center space-x-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('tasks.total_tasks') }}: <span x-text="totalTasks"></span>
                        </span>
                    </div>
                </div>
                
                <div class="flex items-center space-x-3">
                    <!-- View Toggle -->
                    <div class="flex bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                        <button 
                            @click="currentView = 'kanban'" 
                            :class="currentView === 'kanban' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">
                            <i class="fas fa-columns mr-1"></i>
                            {{ __('tasks.kanban_view') }}
                        </button>
                        <button 
                            @click="currentView = 'list'" 
                            :class="currentView === 'list' ? 'bg-white dark:bg-gray-600 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'"
                            class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors">
                            <i class="fas fa-list mr-1"></i>
                            {{ __('tasks.list_view') }}
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="relative">
                        <button 
                            @click="showFilters = !showFilters"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-filter mr-2"></i>
                            {{ __('tasks.filters') }}
                            <i class="fas fa-chevron-down ml-2" :class="showFilters ? 'rotate-180' : ''"></i>
                        </button>
                        
                        <!-- Filter Dropdown -->
                        <div 
                            x-show="showFilters" 
                            x-transition
                            class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 z-10">
                            <div class="p-4 space-y-4">
                                <!-- Project Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ __('tasks.project') }}
                                    </label>
                                    <select 
                                        x-model="filters.project_id"
                                        @change="applyFilters"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        <option value="">{{ __('tasks.all_projects') }}</option>
                                        <template x-for="project in projects" :key="project.id">
                                            <option :value="project.id" x-text="project.name"></option>
                                        </template>
                                    </select>
                                </div>
                                
                                <!-- Assignee Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ __('tasks.assignee') }}
                                    </label>
                                    <select 
                                        x-model="filters.assignee_id"
                                        @change="applyFilters"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        <option value="">{{ __('tasks.all_assignees') }}</option>
                                        <template x-for="user in users" :key="user.id">
                                            <option :value="user.id" x-text="user.name"></option>
                                        </template>
                                    </select>
                                </div>
                                
                                <!-- Priority Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ __('tasks.priority') }}
                                    </label>
                                    <select 
                                        x-model="filters.priority"
                                        @change="applyFilters"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                        <option value="">{{ __('tasks.all_priorities') }}</option>
                                        <option value="low">{{ __('tasks.priority_low') }}</option>
                                        <option value="normal">{{ __('tasks.priority_normal') }}</option>
                                        <option value="high">{{ __('tasks.priority_high') }}</option>
                                        <option value="urgent">{{ __('tasks.priority_urgent') }}</option>
                                    </select>
                                </div>
                                
                                <!-- Search -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ __('tasks.search') }}
                                    </label>
                                    <input 
                                        type="text"
                                        x-model="filters.search"
                                        @input.debounce.500ms="applyFilters"
                                        placeholder="{{ __('tasks.search_placeholder') }}"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                </div>
                                
                                <!-- Clear Filters -->
                                <div class="flex justify-end">
                                    <button 
                                        @click="clearFilters"
                                        class="px-3 py-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                                        {{ __('tasks.clear_filters') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Create Task Button -->
                    <a 
                        href="{{ route('app.tasks.create') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                        <i class="fas fa-plus mr-2"></i>
                        {{ __('tasks.create_task') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Kanban Board -->
        <div x-show="currentView === 'kanban'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <!-- Backlog Column -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('tasks.status_backlog') }}
                            </h3>
                            <span class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-medium px-2.5 py-0.5 rounded-full" 
                                  x-text="getTasksByStatus('backlog').length"></span>
                        </div>
                    </div>
                    <div 
                        class="p-4 min-h-[400px]"
                        @drop="handleDrop($event, 'backlog')"
                        @dragover.prevent
                        @dragenter.prevent>
                        <template x-for="task in getTasksByStatus('backlog')" :key="task.id">
                            <div 
                                :draggable="true"
                                @dragstart="handleDragStart($event, task)"
                                class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-3 cursor-move hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="task.name"></h4>
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :class="getPriorityClass(task.priority)"
                                        x-text="getPriorityLabel(task.priority)"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2" x-text="task.description"></p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="task.project?.name"></span>
                                    <span x-text="formatDate(task.end_date)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <template x-if="task.assignee">
                                            <img 
                                                :src="task.assignee.avatar || '/images/default-avatar.png'" 
                                                :alt="task.assignee.name"
                                                class="w-6 h-6 rounded-full border border-gray-200 dark:border-gray-600">
                                        </template>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            @click="editTask(task)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task)"
                                            class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="getTasksByStatus('backlog').length === 0" class="text-center py-8">
                            <i class="fas fa-inbox text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('tasks.no_tasks') }}</p>
                        </div>
                    </div>
                </div>

                <!-- In Progress Column -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('tasks.status_in_progress') }}
                            </h3>
                            <span class="bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 text-sm font-medium px-2.5 py-0.5 rounded-full" 
                                  x-text="getTasksByStatus('in_progress').length"></span>
                        </div>
                    </div>
                    <div 
                        class="p-4 min-h-[400px]"
                        @drop="handleDrop($event, 'in_progress')"
                        @dragover.prevent
                        @dragenter.prevent>
                        <template x-for="task in getTasksByStatus('in_progress')" :key="task.id">
                            <div 
                                :draggable="true"
                                @dragstart="handleDragStart($event, task)"
                                class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-3 cursor-move hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="task.name"></h4>
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :class="getPriorityClass(task.priority)"
                                        x-text="getPriorityLabel(task.priority)"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2" x-text="task.description"></p>
                                
                                <!-- Progress Bar -->
                                <div class="mb-2">
                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                        <span>{{ __('tasks.progress') }}</span>
                                        <span x-text="task.progress_percent + '%'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                        <div 
                                            class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                            :style="`width: ${task.progress_percent}%`"></div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="task.project?.name"></span>
                                    <span x-text="formatDate(task.end_date)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <template x-if="task.assignee">
                                            <img 
                                                :src="task.assignee.avatar || '/images/default-avatar.png'" 
                                                :alt="task.assignee.name"
                                                class="w-6 h-6 rounded-full border border-gray-200 dark:border-gray-600">
                                        </template>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            @click="editTask(task)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task)"
                                            class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="getTasksByStatus('in_progress').length === 0" class="text-center py-8">
                            <i class="fas fa-play text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('tasks.no_tasks') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Blocked Column -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('tasks.status_blocked') }}
                            </h3>
                            <span class="bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300 text-sm font-medium px-2.5 py-0.5 rounded-full" 
                                  x-text="getTasksByStatus('blocked').length"></span>
                        </div>
                    </div>
                    <div 
                        class="p-4 min-h-[400px]"
                        @drop="handleDrop($event, 'blocked')"
                        @dragover.prevent
                        @dragenter.prevent>
                        <template x-for="task in getTasksByStatus('blocked')" :key="task.id">
                            <div 
                                :draggable="true"
                                @dragstart="handleDragStart($event, task)"
                                class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-3 cursor-move hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="task.name"></h4>
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :class="getPriorityClass(task.priority)"
                                        x-text="getPriorityLabel(task.priority)"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2" x-text="task.description"></p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="task.project?.name"></span>
                                    <span x-text="formatDate(task.end_date)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <template x-if="task.assignee">
                                            <img 
                                                :src="task.assignee.avatar || '/images/default-avatar.png'" 
                                                :alt="task.assignee.name"
                                                class="w-6 h-6 rounded-full border border-gray-200 dark:border-gray-600">
                                        </template>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            @click="editTask(task)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task)"
                                            class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="getTasksByStatus('blocked').length === 0" class="text-center py-8">
                            <i class="fas fa-ban text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('tasks.no_tasks') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Done Column -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('tasks.status_done') }}
                            </h3>
                            <span class="bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-300 text-sm font-medium px-2.5 py-0.5 rounded-full" 
                                  x-text="getTasksByStatus('done').length"></span>
                        </div>
                    </div>
                    <div 
                        class="p-4 min-h-[400px]"
                        @drop="handleDrop($event, 'done')"
                        @dragover.prevent
                        @dragenter.prevent>
                        <template x-for="task in getTasksByStatus('done')" :key="task.id">
                            <div 
                                :draggable="true"
                                @dragstart="handleDragStart($event, task)"
                                class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-3 cursor-move hover:shadow-md transition-shadow opacity-75">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="task.name"></h4>
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :class="getPriorityClass(task.priority)"
                                        x-text="getPriorityLabel(task.priority)"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2" x-text="task.description"></p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="task.project?.name"></span>
                                    <span x-text="formatDate(task.end_date)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <template x-if="task.assignee">
                                            <img 
                                                :src="task.assignee.avatar || '/images/default-avatar.png'" 
                                                :alt="task.assignee.name"
                                                class="w-6 h-6 rounded-full border border-gray-200 dark:border-gray-600">
                                        </template>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            @click="editTask(task)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task)"
                                            class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="getTasksByStatus('done').length === 0" class="text-center py-8">
                            <i class="fas fa-check text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('tasks.no_tasks') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Canceled Column -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ __('tasks.status_canceled') }}
                            </h3>
                            <span class="bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-sm font-medium px-2.5 py-0.5 rounded-full" 
                                  x-text="getTasksByStatus('canceled').length"></span>
                        </div>
                    </div>
                    <div 
                        class="p-4 min-h-[400px]"
                        @drop="handleDrop($event, 'canceled')"
                        @dragover.prevent
                        @dragenter.prevent>
                        <template x-for="task in getTasksByStatus('canceled')" :key="task.id">
                            <div 
                                :draggable="true"
                                @dragstart="handleDragStart($event, task)"
                                class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg p-3 mb-3 cursor-move hover:shadow-md transition-shadow opacity-50">
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="task.name"></h4>
                                    <span 
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                        :class="getPriorityClass(task.priority)"
                                        x-text="getPriorityLabel(task.priority)"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 line-clamp-2" x-text="task.description"></p>
                                <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="task.project?.name"></span>
                                    <span x-text="formatDate(task.end_date)"></span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center space-x-1">
                                        <template x-if="task.assignee">
                                            <img 
                                                :src="task.assignee.avatar || '/images/default-avatar.png'" 
                                                :alt="task.assignee.name"
                                                class="w-6 h-6 rounded-full border border-gray-200 dark:border-gray-600">
                                        </template>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button 
                                            @click="editTask(task)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button 
                                            @click="deleteTask(task)"
                                            class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                        
                        <!-- Empty State -->
                        <div x-show="getTasksByStatus('canceled').length === 0" class="text-center py-8">
                            <i class="fas fa-times text-gray-300 dark:text-gray-600 text-3xl mb-2"></i>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">{{ __('tasks.no_tasks') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- List View (Fallback to existing index view) -->
        <div x-show="currentView === 'list'" class="space-y-6">
            <div class="text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">{{ __('tasks.switching_to_list_view') }}</p>
                <a 
                    href="{{ route('app.tasks.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors mt-4">
                    <i class="fas fa-list mr-2"></i>
                    {{ __('tasks.go_to_list_view') }}
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Alpine.js Component -->
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('taskKanban', () => ({
        // State
        tasks: @json($tasks),
        projects: @json($projects),
        users: @json($users),
        currentView: 'kanban',
        showFilters: false,
        filters: {
            project_id: '{{ $filters['project_id'] ?? '' }}',
            assignee_id: '{{ $filters['assignee_id'] ?? '' }}',
            priority: '{{ $filters['priority'] ?? '' }}',
            search: '{{ $filters['search'] ?? '' }}'
        },
        draggedTask: null,
        loading: false,
        error: null,

        // Computed
        get totalTasks() {
            return this.tasks.length;
        },

        // Methods
        async init() {
            // Data is already loaded from controller
            console.log('Kanban board initialized with', this.tasks.length, 'tasks');
        },

        async loadData() {
            this.loading = true;
            try {
                const [tasksResponse, projectsResponse, usersResponse] = await Promise.all([
                    this.fetchTasks(),
                    this.fetchProjects(),
                    this.fetchUsers()
                ]);

                this.tasks = tasksResponse.data || [];
                this.projects = projectsResponse.data || [];
                this.users = usersResponse.data || [];
            } catch (error) {
                console.error('Error loading data:', error);
                this.error = 'Failed to load data';
            } finally {
                this.loading = false;
            }
        },

        async fetchTasks() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.append(key, value);
            });

            const response = await fetch(`/api/tasks?${params}`);
            if (!response.ok) throw new Error('Failed to fetch tasks');
            return await response.json();
        },

        async fetchProjects() {
            const response = await fetch('/api/projects');
            if (!response.ok) throw new Error('Failed to fetch projects');
            return await response.json();
        },

        async fetchUsers() {
            const response = await fetch('/api/team');
            if (!response.ok) throw new Error('Failed to fetch users');
            return await response.json();
        },

        getTasksByStatus(status) {
            return this.tasks.filter(task => task.status === status);
        },

        getPriorityClass(priority) {
            const classes = {
                'low': 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
                'normal': 'bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300',
                'high': 'bg-orange-100 dark:bg-orange-900 text-orange-600 dark:text-orange-300',
                'urgent': 'bg-red-100 dark:bg-red-900 text-red-600 dark:text-red-300'
            };
            return classes[priority] || classes['normal'];
        },

        getPriorityLabel(priority) {
            const labels = {
                'low': '{{ __('tasks.priority_low') }}',
                'normal': '{{ __('tasks.priority_normal') }}',
                'high': '{{ __('tasks.priority_high') }}',
                'urgent': '{{ __('tasks.priority_urgent') }}'
            };
            return labels[priority] || priority;
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },

        async applyFilters() {
            // Redirect to refresh with new filters
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) params.append(key, value);
            });
            
            const url = `/app/tasks/kanban${params.toString() ? '?' + params.toString() : ''}`;
            window.location.href = url;
        },

        clearFilters() {
            this.filters = {
                project_id: '',
                assignee_id: '',
                priority: '',
                search: ''
            };
            this.applyFilters();
        },

        handleDragStart(event, task) {
            this.draggedTask = task;
            event.dataTransfer.effectAllowed = 'move';
        },

        async handleDrop(event, newStatus) {
            event.preventDefault();
            
            if (!this.draggedTask) return;

            const taskId = this.draggedTask.id;
            const oldStatus = this.draggedTask.status;

            // Optimistic update
            this.draggedTask.status = newStatus;

            try {
                const response = await fetch(`/api/tasks/${taskId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        status: newStatus
                    })
                });

                if (!response.ok) {
                    // Revert on error
                    this.draggedTask.status = oldStatus;
                    throw new Error('Failed to update task status');
                }

                // Show success message
                this.showNotification('Task status updated successfully', 'success');
            } catch (error) {
                console.error('Error updating task status:', error);
                this.showNotification('Failed to update task status', 'error');
            } finally {
                this.draggedTask = null;
            }
        },

        editTask(task) {
            window.location.href = `/app/tasks/${task.id}/edit`;
        },

        async deleteTask(task) {
            if (!confirm('Are you sure you want to delete this task?')) return;

            try {
                const response = await fetch(`/api/tasks/${task.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (!response.ok) throw new Error('Failed to delete task');

                // Remove from local array
                this.tasks = this.tasks.filter(t => t.id !== task.id);
                this.showNotification('Task deleted successfully', 'success');
            } catch (error) {
                console.error('Error deleting task:', error);
                this.showNotification('Failed to delete task', 'error');
            }
        },

        showNotification(message, type = 'info') {
            // Simple notification - can be enhanced with a proper notification system
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-md text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }));
});
</script>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
@endsection
