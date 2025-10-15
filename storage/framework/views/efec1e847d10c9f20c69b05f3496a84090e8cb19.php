


<?php
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
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.layout-wrapper','data' => ['title' => 'Tasks','subtitle' => 'Track and manage your tasks','breadcrumbs' => $breadcrumbs,'actions' => $actions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.layout-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Tasks','subtitle' => 'Track and manage your tasks','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions)]); ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.filter-bar','data' => ['search' => true,'searchPlaceholder' => 'Search tasks...','filters' => $filters,'sortOptions' => $sortOptions,'viewModes' => ['table', 'kanban', 'list'],'currentViewMode' => 'table','bulkActions' => $bulkActions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['search' => true,'search-placeholder' => 'Search tasks...','filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'sort-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sortOptions),'view-modes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['table', 'kanban', 'list']),'current-view-mode' => 'table','bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions)]); ?>
        
        
         <?php $__env->slot('actions', null, []); ?> 
            <button onclick="refreshTasks()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-sync-alt mr-2"></i>Refresh
            </button>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
    
    
    <div class="mt-6">
        <?php if($tableData->count() > 0): ?>
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-standardized','data' => ['data' => $tableData,'columns' => $columns,'sortable' => true,'selectable' => true,'pagination' => true,'perPage' => 15,'search' => true,'export' => true,'bulkActions' => $bulkActions,'responsive' => true,'loading' => false,'emptyMessage' => 'No tasks found','emptyDescription' => 'Create your first task to get started','emptyActionText' => 'Create Task','emptyActionHandler' => 'openModal(\'create-task-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableData),'columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($columns),'sortable' => true,'selectable' => true,'pagination' => true,'per-page' => 15,'search' => true,'export' => true,'bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions),'responsive' => true,'loading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'empty-message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('No tasks found'),'empty-description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create your first task to get started'),'empty-action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create Task'),'empty-action-handler' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('openModal(\'create-task-modal\')')]); ?>
                
                
                 <?php $__env->slot('cell-status', null, []); ?> 
                    <?php
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
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                        <?php echo e(ucfirst(str_replace('_', ' ', $status))); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-priority', null, []); ?> 
                    <?php
                        $priority = $row['priority'] ?? 'medium';
                        $priorityClasses = [
                            'low' => 'bg-gray-100 text-gray-800',
                            'medium' => 'bg-blue-100 text-blue-800',
                            'high' => 'bg-orange-100 text-orange-800',
                            'urgent' => 'bg-red-100 text-red-800'
                        ];
                        $priorityClass = $priorityClasses[$priority] ?? $priorityClasses['medium'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($priorityClass); ?>">
                        <?php echo e(ucfirst($priority)); ?>

                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-progress', null, []); ?> 
                    <?php
                        $progress = $row['progress'] ?? 0;
                        $progressColor = $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    ?>
                    <div class="flex items-center">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="h-2 rounded-full <?php echo e($progressColor); ?>" style="width: <?php echo e($progress); ?>%"></div>
                        </div>
                        <span class="text-sm text-gray-600"><?php echo e($progress); ?>%</span>
                    </div>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-estimated_hours', null, []); ?> 
                    <span class="text-sm font-medium text-gray-900">
                        <?php echo e($row['estimated_hours']); ?>h
                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('cell-due_date', null, []); ?> 
                    <?php
                        $dueDate = $row['due_date'];
                        $isOverdue = $dueDate !== '-' && \Carbon\Carbon::parse($dueDate)->isPast() && $row['status'] !== 'completed';
                    ?>
                    <div class="flex items-center">
                        <span class="text-sm <?php echo e($isOverdue ? 'text-red-600 font-medium' : 'text-gray-900'); ?>">
                            <?php echo e($dueDate); ?>

                        </span>
                        <?php if($isOverdue): ?>
                            <i class="fas fa-exclamation-triangle text-red-500 ml-2" title="Overdue"></i>
                        <?php endif; ?>
                    </div>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('row-actions', null, []); ?> 
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo e(route('app.tasks.show', $row['id'])); ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View
                        </a>
                        <a href="<?php echo e(route('app.tasks.edit', $row['id'])); ?>" 
                           class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            Edit
                        </a>
                        <button onclick="deleteTask('<?php echo e($row['id']); ?>')" 
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Delete
                        </button>
                    </div>
                 <?php $__env->endSlot(); ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php else: ?>
            
            <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-tasks','title' => 'No tasks found','description' => 'Create your first task to start tracking your work.','actionText' => 'Create Task','actionIcon' => 'fas fa-plus','actionHandler' => 'openModal(\'create-task-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-tasks','title' => 'No tasks found','description' => 'Create your first task to start tracking your work.','action-text' => 'Create Task','action-icon' => 'fas fa-plus','action-handler' => 'openModal(\'create-task-modal\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'create-task-modal','title' => 'Create New Task','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'create-task-modal','title' => 'Create New Task','size' => 'lg']); ?>
        
        <form id="create-task-form" @submit.prevent="createTask()">
            <div class="space-y-6">
                
                <div>
                    <label for="task-title" class="form-label">Task Title *</label>
                    <input type="text" 
                           id="task-title" 
                           name="title" 
                           required
                           class="form-input"
                           placeholder="Enter task title">
                </div>
                
                
                <div>
                    <label for="task-description" class="form-label">Description</label>
                    <textarea id="task-description" 
                              name="description" 
                              rows="3"
                              class="form-textarea"
                              placeholder="Enter task description"></textarea>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="task-project" class="form-label">Project</label>
                        <select id="task-project" name="project_id" class="form-select">
                            <option value="">Select Project</option>
                            <?php $__currentLoopData = $projects ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($project->id); ?>"><?php echo e($project->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="task-assignee" class="form-label">Assignee</label>
                        <select id="task-assignee" name="assignee_id" class="form-select">
                            <option value="">Select Assignee</option>
                            <?php $__currentLoopData = $users ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
                
                
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
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>

<?php $__env->startPush('scripts'); ?>
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
    data.tenant_id = '<?php echo e($user->tenant_id); ?>';
    data.user_id = '<?php echo e($user->id); ?>';
    
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
<?php $__env->stopPush(); ?>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/tasks/index.blade.php ENDPATH**/ ?>