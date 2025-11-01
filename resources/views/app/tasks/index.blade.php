@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
<div class="min-h-screen bg-gray-50" x-data="taskManagement()" data-testid="tasks-page">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tasks</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage and track your tasks</p>
                </div>
                <div class="flex space-x-3">
                    <!-- View Toggle -->
                    <div class="flex bg-gray-100 rounded-lg p-1">
                        <a href="{{ route('app.tasks.index') }}" 
                           class="px-3 py-1.5 text-sm font-medium rounded-md bg-white text-gray-900 shadow-sm">
                            <i class="fas fa-list mr-1"></i>
                            List
                        </a>
                        <a href="{{ route('app.tasks.kanban') }}" 
                           class="px-3 py-1.5 text-sm font-medium rounded-md text-gray-500 hover:text-gray-900 transition-colors">
                            <i class="fas fa-columns mr-1"></i>
                            Board
                        </a>
                    </div>
                    
                    <a href="{{ route('app.tasks.create') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <i class="fas fa-plus mr-2"></i>
                        New Task
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Stats -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tasks text-blue-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Tasks</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-play text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">In Progress</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['in_progress'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check text-green-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Completed</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['done'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Overdue</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['overdue'] ?? 0 }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg mb-6" data-testid="task-filters">
            <div class="px-6 py-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Project Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                        <select x-model="filters.project_id" @change="applyFilters()" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Projects</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select x-model="filters.status" @change="applyFilters()" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Status</option>
                            <option value="backlog">Backlog</option>
                            <option value="in_progress">In Progress</option>
                            <option value="blocked">Blocked</option>
                            <option value="done">Done</option>
                            <option value="canceled">Canceled</option>
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <select x-model="filters.priority" @change="applyFilters()" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Priority</option>
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <!-- Assignee Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                        <select x-model="filters.assignee_id" @change="applyFilters()" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Assignees</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Search -->
                <div class="mt-4">
                    <div class="relative">
                        <input type="text" 
                               x-model="filters.search" 
                               @keyup.enter="applyFilters()"
                               placeholder="Search tasks..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                               data-testid="task-search-input">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks Table -->
        <div class="bg-white shadow rounded-lg" data-testid="tasks-table">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Tasks</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="createTask()" 
                                data-testid="create-task-button"
                                class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <i class="fas fa-plus mr-2"></i>Create Task
                        </button>
                        <span class="text-sm text-gray-500">|</span>
                        <button @click="selectAll()" 
                                class="text-sm text-blue-600 hover:text-blue-800">
                            Select All
                        </button>
                        <span class="text-sm text-gray-500">|</span>
                        <button @click="clearSelection()" 
                                class="text-sm text-gray-600 hover:text-gray-800">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" 
                                       @change="toggleAll()" 
                                       x-model="selectAllChecked"
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($tasks as $task)
                        <tr class="hover:bg-gray-50 cursor-pointer" 
                            data-testid="task-item" 
                            data-task-id="{{ $task->id }}"
                            @click="openTask('{{ $task->id }}')"
                            @keyup.enter="openTask('{{ $task->id }}')"
                            role="link"
                            tabindex="0">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input type="checkbox" 
                                       value="{{ $task->id }}" 
                                       x-model="selectedTasks"
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-tasks text-blue-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <a href="{{ route('app.tasks.show', $task->id) }}" 
                                               class="hover:text-blue-600" 
                                               data-testid="task-name-link">{{ $task->name }}</a>
                                        </div>
                                        @if($task->description)
                                        <div class="text-sm text-gray-500 truncate max-w-xs">{{ $task->description }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $task->project->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if($task->status === 'done') bg-green-100 text-green-800
                                    @elseif($task->status === 'in_progress') bg-blue-100 text-blue-800
                                    @elseif($task->status === 'blocked') bg-red-100 text-red-800
                                    @elseif($task->status === 'backlog') bg-gray-100 text-gray-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $task->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    @if($task->priority === 'urgent') bg-red-100 text-red-800
                                    @elseif($task->priority === 'high') bg-orange-100 text-orange-800
                                    @elseif($task->priority === 'normal') bg-blue-100 text-blue-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($task->priority) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $task->assignee->name ?? 'Unassigned' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    @if($task->end_date)
                                        {{ \Carbon\Carbon::parse($task->end_date)->format('M d, Y') }}
                                    @else
                                        No due date
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-blue-600 h-2 rounded-full" 
                                             style="width: {{ $task->progress_percent ?? 0 }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ $task->progress_percent ?? 0 }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="{{ route('app.tasks.edit', $task->id) }}" 
                                       class="text-blue-600 hover:text-blue-900"
                                       data-testid="edit-task-button">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button @click="deleteTask('{{ $task->id }}')" 
                                            class="text-red-600 hover:text-red-900"
                                            data-testid="delete-task-button">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-tasks text-4xl text-gray-300 mb-4"></i>
                                <p class="text-lg font-medium">No tasks found</p>
                                <p class="text-sm">Get started by creating your first task.</p>
                                <a href="{{ route('app.tasks.create') }}" 
                                   class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    <i class="fas fa-plus mr-2"></i>
                                    Create Task
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Bulk Actions -->
            <div x-show="selectedTasks.length > 0" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="px-6 py-4 bg-blue-50 border-t border-blue-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <span class="text-sm text-blue-700">
                            <span x-text="selectedTasks.length"></span> task(s) selected
                        </span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <select x-model="bulkAction" 
                                class="px-3 py-1 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Action</option>
                            <option value="delete">Delete</option>
                            <option value="status">Change Status</option>
                            <option value="assign">Assign</option>
                        </select>
                        
                        <div x-show="bulkAction === 'status'" class="flex items-center space-x-2">
                            <select x-model="bulkStatus" 
                                    class="px-3 py-1 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="backlog">Backlog</option>
                                <option value="in_progress">In Progress</option>
                                <option value="blocked">Blocked</option>
                                <option value="done">Done</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                        
                        <div x-show="bulkAction === 'assign'" class="flex items-center space-x-2">
                            <select x-model="bulkAssignee" 
                                    class="px-3 py-1 border border-blue-300 rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select Assignee</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <button @click="executeBulkAction()" 
                                :disabled="loading"
                                class="px-4 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!loading">Execute</span>
                            <span x-show="loading">Processing...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            @if(isset($meta) && $meta['last_page'] > 1)
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ $meta['from'] ?? 0 }} to {{ $meta['to'] ?? 0 }} of {{ $meta['total'] ?? 0 }} results
                    </div>
                    <div class="flex items-center space-x-2">
                        @if($meta['current_page'] > 1)
                            <button @click="navigateToPage({{ $meta['current_page'] - 1 }})" 
                                    class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                Previous
                            </button>
                        @endif
                        
                        @for($i = max(1, $meta['current_page'] - 2); $i <= min($meta['last_page'], $meta['current_page'] + 2); $i++)
                            <button @click="navigateToPage({{ $i }})" 
                                    class="px-3 py-1 text-sm border border-gray-300 rounded-md {{ $i === $meta['current_page'] ? 'bg-blue-600 text-white' : 'hover:bg-gray-50' }}">
                                {{ $i }}
                            </button>
                        @endfor
                        
                        @if($meta['current_page'] < $meta['last_page'])
                            <button @click="navigateToPage({{ $meta['current_page'] + 1 }})" 
                                    class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-50">
                                Next
                            </button>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Task Creation Modal -->
<div x-show="showCreateModal" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Task</h3>
            
            <form @submit.prevent="saveTask()">
                <div class="mb-4">
                    <label for="task-name" class="block text-sm font-medium text-gray-700 mb-2">Task Name</label>
                    <input type="text" 
                           id="task-name"
                           data-testid="task-name"
                           x-model="newTask.name"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter task name"
                           required>
                </div>
                
                <div class="mb-4">
                    <label for="task-description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea id="task-description"
                              data-testid="task-description"
                              x-model="newTask.description"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Enter task description"
                              rows="3"></textarea>
                </div>
                
                <div class="mb-4">
                    <label for="task-priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select id="task-priority"
                            data-testid="task-priority"
                            x-model="newTask.priority"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="task-project" class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                    <select id="task-project"
                            data-testid="task-project"
                            x-model="newTask.project"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="task-assignee" class="block text-sm font-medium text-gray-700 mb-2">Assignee</label>
                    <select id="task-assignee"
                            data-testid="task-assignee"
                            x-model="newTask.assignee"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Select Assignee</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button"
                            @click="cancelCreate()"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="submit"
                            data-testid="save-task"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('taskManagement', () => ({
        filters: {
            project_id: '{{ $filters['project_id'] ?? '' }}',
            status: '{{ $filters['status'] ?? '' }}',
            priority: '{{ $filters['priority'] ?? '' }}',
            assignee_id: '{{ $filters['assignee_id'] ?? '' }}',
            search: '{{ $filters['search'] ?? '' }}'
        },
        selectedTasks: [],
        selectAllChecked: false,
        bulkAction: '',
        showCreateModal: false,
        newTask: {
            name: '',
            description: '',
            priority: 'medium',
            project: '',
            assignee: ''
        },
        bulkStatus: 'backlog',
        bulkAssignee: '',
        loading: false,
        error: null,

        init() {
            // Initialize any required setup
        },

        openTask(taskId) {
            window.location.href = `/app/tasks/${taskId}`;
        },

        applyFilters() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value) {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });
            params.set('page', '1');
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },

        navigateToPage(page) {
            const params = new URLSearchParams(window.location.search);
            params.set('page', page);
            window.location.href = `${window.location.pathname}?${params.toString()}`;
        },

        selectAll() {
            this.selectedTasks = @json($tasks->pluck('id')->toArray());
            this.selectAllChecked = true;
        },

        clearSelection() {
            this.selectedTasks = [];
            this.selectAllChecked = false;
        },

        toggleAll() {
            if (this.selectAllChecked) {
                this.selectAll();
            } else {
                this.clearSelection();
            }
        },

        async executeBulkAction() {
            if (!this.bulkAction || this.selectedTasks.length === 0) {
                return;
            }

            this.loading = true;
            this.error = null;

            const payload = {
                action: this.bulkAction,
                task_ids: this.selectedTasks
            };

            if (this.bulkAction === 'status' && this.bulkStatus) {
                payload.status = this.bulkStatus;
            }

            if (this.bulkAction === 'assign' && this.bulkAssignee) {
                payload.assignee_id = this.bulkAssignee;
            }

            try {
                const response = await fetch('{{ route('app.tasks.bulk-action') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok) {
                    this.showNotification('success', result.message || 'Action completed successfully');
                    this.clearSelection();
                    this.bulkAction = '';
                    window.location.reload();
                } else {
                    this.error = result.message || 'Failed to perform action';
                    this.showNotification('error', this.error);
                }
            } catch (error) {
                this.error = 'An error occurred while performing the action';
                this.showNotification('error', this.error);
            } finally {
                this.loading = false;
            }
        },

        async deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }

            try {
                const response = await fetch(`/app/tasks/${taskId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });

                if (response.ok) {
                    this.showNotification('success', 'Task deleted successfully');
                    window.location.reload();
                } else {
                    this.showNotification('error', 'Failed to delete task');
                }
            } catch (error) {
                this.showNotification('error', 'An error occurred while deleting the task');
            }
        },

        showNotification(type, message) {
            // Simple notification - you can enhance this with a proper notification system
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-md text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        },

        getStatusClass(status) {
            const classes = {
                'backlog': 'bg-gray-100 text-gray-800',
                'in_progress': 'bg-blue-100 text-blue-800',
                'blocked': 'bg-red-100 text-red-800',
                'done': 'bg-green-100 text-green-800',
                'canceled': 'bg-gray-100 text-gray-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getPriorityClass(priority) {
            const classes = {
                'low': 'bg-gray-100 text-gray-800',
                'normal': 'bg-blue-100 text-blue-800',
                'high': 'bg-orange-100 text-orange-800',
                'urgent': 'bg-red-100 text-red-800'
            };
            return classes[priority] || 'bg-gray-100 text-gray-800';
        },

        createTask() {
            // Open task creation modal
            this.showCreateModal = true;
            console.log('Create task modal opened');
        },
        
        saveTask() {
            // Simulate task creation
            this.showNotification('success', 'Task created successfully!');
            this.showCreateModal = false;
            
            // Reset form
            this.newTask = {
                name: '',
                description: '',
                priority: 'medium',
                project: '',
                assignee: ''
            };
        },
        
        cancelCreate() {
            this.showCreateModal = false;
            this.newTask = {
                name: '',
                description: '',
                priority: 'medium',
                project: '',
                assignee: ''
            };
        }
    }));
});
</script>
@endsection