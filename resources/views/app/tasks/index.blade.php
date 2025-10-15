{{-- Tasks Index - Phase 2 Implementation --}}
{{-- Using standardized components for consistent UI/UX --}}

@php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Prepare filter options
    $statusOptions = [
        ['value' => 'pending', 'label' => 'Pending'],
        ['value' => 'in_progress', 'label' => 'In Progress'],
        ['value' => 'completed', 'label' => 'Completed'],
        ['value' => 'cancelled', 'label' => 'Cancelled'],
        ['value' => 'on_hold', 'label' => 'On Hold']
    ];
    
    $priorityOptions = [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'medium', 'label' => 'Medium'],
        ['value' => 'high', 'label' => 'High'],
        ['value' => 'urgent', 'label' => 'Urgent']
    ];
    
    $assigneeOptions = collect($users ?? [])->map(function($user) {
        return ['value' => $user->id ?? '', 'label' => $user->name ?? 'Unknown'];
    })->toArray();
    
    $projectOptions = collect($projects ?? [])->map(function($project) {
        return ['value' => $project->id ?? '', 'label' => $project->name ?? 'Unknown'];
    })->toArray();
    
    // Filter configuration
    $filters = [
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'options' => $statusOptions,
            'placeholder' => 'All Statuses'
        ],
        [
            'key' => 'priority',
            'label' => 'Priority',
            'type' => 'select',
            'options' => $priorityOptions,
            'placeholder' => 'All Priorities'
        ],
        [
            'key' => 'assignee_id',
            'label' => 'Assignee',
            'type' => 'select',
            'options' => $assigneeOptions,
            'placeholder' => 'All Assignees'
        ],
        [
            'key' => 'project_id',
            'label' => 'Project',
            'type' => 'select',
            'options' => $projectOptions,
            'placeholder' => 'All Projects'
        ],
        [
            'key' => 'due_date',
            'label' => 'Due Date',
            'type' => 'date-range'
        ]
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'title', 'label' => 'Task Name'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'priority', 'label' => 'Priority'],
        ['value' => 'due_date', 'label' => 'Due Date'],
        ['value' => 'estimated_hours', 'label' => 'Estimated Hours'],
        ['value' => 'created_at', 'label' => 'Created Date'],
        ['value' => 'updated_at', 'label' => 'Last Updated']
    ];
    
    // Bulk actions
    $bulkActions = [
        [
            'label' => 'Change Status',
            'icon' => 'fas fa-edit',
            'handler' => 'bulkChangeStatus()'
        ],
        [
            'label' => 'Assign Tasks',
            'icon' => 'fas fa-user-plus',
            'handler' => 'bulkAssign()'
        ],
        [
            'label' => 'Export',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ],
        [
            'label' => 'Archive',
            'icon' => 'fas fa-archive',
            'handler' => 'bulkArchive()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Tasks', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportTasks()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'create-task-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>New Task
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($tasks ?? [])->map(function($task) {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'project' => $task->project->name ?? 'No Project',
            'assignee' => $task->assignee->name ?? 'Unassigned',
            'status' => $task->status,
            'priority' => $task->priority ?? 'medium',
            'due_date' => $task->due_date ? $task->due_date->format('M d, Y') : '-',
            'estimated_hours' => $task->estimated_hours ?? 0,
            'actual_hours' => $task->actual_hours ?? 0,
            'progress' => $task->progress ?? 0,
            'created_at' => $task->created_at->format('M d, Y'),
            'updated_at' => $task->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration
    $columns = [
        ['key' => 'title', 'label' => 'Task Name', 'sortable' => true, 'primary' => true],
        ['key' => 'project', 'label' => 'Project', 'sortable' => true],
        ['key' => 'assignee', 'label' => 'Assignee', 'sortable' => true],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'due_date', 'label' => 'Due Date', 'sortable' => true, 'type' => 'date'],
        ['key' => 'estimated_hours', 'label' => 'Est. Hours', 'sortable' => true, 'type' => 'number'],
        ['key' => 'progress', 'label' => 'Progress', 'sortable' => true, 'type' => 'progress'],
        ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true, 'type' => 'date']
    ];
@endphp

<x-shared.layout-wrapper 
    title="Tasks"
    subtitle="Track and manage your tasks"
    :breadcrumbs="$breadcrumbs"
    :actions="$actions">
    
    {{-- Filter Bar --}}
    <x-shared.filter-bar 
        :search="true"
        search-placeholder="Search tasks..."
        :filters="$filters"
        :sort-options="$sortOptions"
        :view-modes="['table', 'kanban', 'list']"
        current-view-mode="table"
        :bulk-actions="$bulkActions">
        
        {{-- Custom Actions Slot --}}
        <x-slot name="actions">
            <button onclick="refreshTasks()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
        </x-slot>
    </x-shared.filter-bar>
    
    {{-- Tasks Table --}}
    <div class="mt-6">
        @if($tableData->count() > 0)
            <x-shared.table-standardized 
                :data="$tableData"
                :columns="$columns"
                :sortable="true"
                :selectable="true"
                :pagination="true"
                :per-page="15"
                :search="true"
                :export="true"
                :bulk-actions="$bulkActions"
                :responsive="true"
                :loading="false"
                :empty-message="'No tasks found'"
                :empty-description="'Create your first task to get started'"
                :empty-action-text="'Create Task'"
                :empty-action-handler="'openModal(\'create-task-modal\')'">
                
                {{-- Custom cell content for status --}}
                <x-slot name="cell-status">
                    @php
                        $status = $row['status'] ?? 'unknown';
                        $statusClasses = [
                            'pending' => 'bg-gray-100 text-gray-800',
                            'in_progress' => 'bg-blue-100 text-blue-800',
                            'completed' => 'bg-green-100 text-green-800',
                            'cancelled' => 'bg-red-100 text-red-800',
                            'on_hold' => 'bg-yellow-100 text-yellow-800',
                            'unknown' => 'bg-gray-100 text-gray-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['unknown'];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for priority --}}
                <x-slot name="cell-priority">
                    @php
                        $priority = $row['priority'] ?? 'medium';
                        $priorityClasses = [
                            'low' => 'bg-gray-100 text-gray-800',
                            'medium' => 'bg-blue-100 text-blue-800',
                            'high' => 'bg-orange-100 text-orange-800',
                            'urgent' => 'bg-red-100 text-red-800'
                        ];
                        $priorityClass = $priorityClasses[$priority] ?? $priorityClasses['medium'];
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $priorityClass }}">
                        {{ ucfirst($priority) }}
                    </span>
                </x-slot>
                
                {{-- Custom cell content for progress --}}
                <x-slot name="cell-progress">
                    @php
                        $progress = $row['progress'] ?? 0;
                        $progressColor = $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    @endphp
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="h-2 rounded-full {{ $progressColor }}" style="width: {{ $progress }}%"></div>
                        </div>
                        <span class="text-sm text-gray-600">{{ $progress }}%</span>
                    </div>
                </x-slot>
                
                {{-- Custom cell content for estimated hours --}}
                <x-slot name="cell-estimated_hours">
                    <span class="text-sm font-medium text-gray-900">
                        {{ $row['estimated_hours'] }}h
                    </span>
                </x-slot>
                
                {{-- Custom cell content for due date with overdue warning --}}
                <x-slot name="cell-due_date">
                    @php
                        $dueDate = $row['due_date'];
                        $isOverdue = $dueDate !== '-' && \Carbon\Carbon::parse($dueDate)->isPast() && $row['status'] !== 'completed';
                    @endphp
                    <div class="flex items-center">
                        <span class="text-sm {{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-900' }}">
                            {{ $dueDate }}
                        </span>
                        @if($isOverdue)
                            <i class="fas fa-exclamation-triangle text-red-500 ml-2" title="Overdue"></i>
                        @endif
                    </div>
                </x-slot>
                
                {{-- Row actions --}}
                <x-slot name="row-actions">
                    <div class="flex items-center space-x-2">
                        <a href="{{ route('app.tasks.show', $row['id']) }}" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View
                        </a>
                        <a href="{{ route('app.tasks.edit', $row['id']) }}" 
                           class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            Edit
                        </a>
                        <button onclick="deleteTask('{{ $row['id'] }}')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Delete
                        </button>
                    </div>
                </x-slot>
            </x-shared.table-standardized>
        @else
            {{-- Empty State --}}
            <x-shared.empty-state 
                icon="fas fa-tasks"
                title="No tasks found"
                description="Create your first task to start tracking your work."
                action-text="Create Task"
                action-icon="fas fa-plus"
                action-handler="openModal('create-task-modal')" />
        @endif
    </div>
    
    {{-- Create Task Modal --}}
    <x-shared.modal 
        id="create-task-modal"
        title="Create New Task"
        size="lg">
        
        <form id="create-task-form" @submit.prevent="createTask()">
            <div class="space-y-6">
                {{-- Task Title --}}
                <div>
                    <label for="task-title" class="form-label">Task Title *</label>
                    <input type="text" 
                           id="task-title" 
                           name="title" 
                           required
                           class="form-input"
                           placeholder="Enter task title">
                </div>
                
                {{-- Description --}}
                <div>
                    <label for="task-description" class="form-label">Description</label>
                    <textarea id="task-description" 
                              name="description" 
                              rows="3"
                              class="form-textarea"
                              placeholder="Enter task description"></textarea>
                </div>
                
                {{-- Project & Assignee --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="task-project" class="form-label">Project</label>
                        <select id="task-project" name="project_id" class="form-select">
                            <option value="">Select Project</option>
                            @foreach($projects ?? [] as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="task-assignee" class="form-label">Assignee</label>
                        <select id="task-assignee" name="assignee_id" class="form-select">
                            <option value="">Select Assignee</option>
                            @foreach($users ?? [] as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                {{-- Status & Priority --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="task-status" class="form-label">Status</label>
                        <select id="task-status" name="status" class="form-select">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="task-priority" class="form-label">Priority</label>
                        <select id="task-priority" name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                {{-- Due Date & Estimated Hours --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="task-due-date" class="form-label">Due Date</label>
                        <input type="date" 
                               id="task-due-date" 
                               name="due_date"
                               class="form-input">
                    </div>
                    
                    <div>
                        <label for="task-estimated-hours" class="form-label">Estimated Hours</label>
                        <input type="number" 
                               id="task-estimated-hours" 
                               name="estimated_hours"
                               min="0"
                               step="0.5"
                               class="form-input"
                               placeholder="Enter estimated hours">
                    </div>
                </div>
                
                {{-- Progress --}}
                <div>
                    <label for="task-progress" class="form-label">Progress (%)</label>
                    <input type="number" 
                           id="task-progress" 
                           name="progress"
                           min="0"
                           max="100"
                           step="1"
                           class="form-input"
                           placeholder="Enter progress percentage">
                </div>
            </div>
            
            {{-- Form Actions --}}
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('create-task-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Create Task
                </button>
            </div>
        </form>
    </x-shared.modal>
</x-shared.layout-wrapper>

@push('scripts')
<script>
function refreshTasks() {
    window.location.reload();
}

function exportTasks() {
    // Export tasks functionality
    alert('Export tasks functionality would be implemented here');
}

function createTask() {
    const form = document.getElementById('create-task-form');
    const formData = new FormData(form);
    
    // Convert FormData to object
    const data = Object.fromEntries(formData.entries());
    
    // Add tenant_id
    data.tenant_id = '{{ $user->tenant_id }}';
    data.user_id = '{{ $user->id }}';
    
    // Submit via API
    fetch('/api/v1/app/tasks', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Authorization': 'Bearer ' + getAuthToken()
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal('create-task-modal');
            window.location.reload();
        } else {
            alert('Error creating task: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating task');
    });
}

function deleteTask(taskId) {
    if (confirm('Are you sure you want to delete this task?')) {
        fetch(`/api/v1/app/tasks/${taskId}`, {
            method: 'DELETE',
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
                alert('Error deleting task: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting task');
        });
    }
}

function bulkChangeStatus() {
    alert('Bulk change status functionality would be implemented here');
}

function bulkAssign() {
    alert('Bulk assign functionality would be implemented here');
}

function bulkExport() {
    alert('Bulk export functionality would be implemented here');
}

function bulkArchive() {
    alert('Bulk archive functionality would be implemented here');
}

function getAuthToken() {
    // Get auth token from localStorage or session
    return localStorage.getItem('auth_token') || '';
}

// Listen for filter events
document.addEventListener('filter-search', (e) => {
    console.log('Search:', e.detail.query);
    // Implement search functionality
});

document.addEventListener('filter-apply', (e) => {
    console.log('Filters:', e.detail.filters);
    // Implement filter functionality
});

document.addEventListener('filter-sort', (e) => {
    console.log('Sort:', e.detail.sortBy, e.detail.sortDirection);
    // Implement sort functionality
});

document.addEventListener('filter-view-mode', (e) => {
    console.log('View mode:', e.detail.viewMode);
    // Implement view mode functionality
});
</script>
@endpush
