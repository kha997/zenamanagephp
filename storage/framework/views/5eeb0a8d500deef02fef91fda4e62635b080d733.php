


<?php
    $user = Auth::user();
    $tenant = $user->tenant ?? null;
    
    // Prepare filter options
    $statusOptions = [
        ['value' => 'active', 'label' => 'Active'],
        ['value' => 'completed', 'label' => 'Completed'],
        ['value' => 'on_hold', 'label' => 'On Hold'],
        ['value' => 'cancelled', 'label' => 'Cancelled']
    ];
    
    $priorityOptions = [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'medium', 'label' => 'Medium'],
        ['value' => 'high', 'label' => 'High'],
        ['value' => 'urgent', 'label' => 'Urgent']
    ];
    
    $ownerOptions = collect($projects ?? [])->pluck('owner')->unique()->map(function($owner) {
        return ['value' => $owner->id ?? '', 'label' => $owner->name ?? 'Unknown'];
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
            'key' => 'owner_id',
            'label' => 'Owner',
            'type' => 'select',
            'options' => $ownerOptions,
            'placeholder' => 'All Owners'
        ],
        [
            'key' => 'start_date',
            'label' => 'Start Date',
            'type' => 'date-range'
        ]
    ];
    
    // Sort options
    $sortOptions = [
        ['value' => 'name', 'label' => 'Name'],
        ['value' => 'status', 'label' => 'Status'],
        ['value' => 'priority', 'label' => 'Priority'],
        ['value' => 'start_date', 'label' => 'Start Date'],
        ['value' => 'end_date', 'label' => 'End Date'],
        ['value' => 'created_at', 'label' => 'Created Date'],
        ['value' => 'updated_at', 'label' => 'Last Updated']
    ];
    
    // Bulk actions
    $bulkActions = [
        [
            'label' => 'Archive',
            'icon' => 'fas fa-archive',
            'handler' => 'bulkArchive()'
        ],
        [
            'label' => 'Change Status',
            'icon' => 'fas fa-edit',
            'handler' => 'bulkChangeStatus()'
        ],
        [
            'label' => 'Export',
            'icon' => 'fas fa-download',
            'handler' => 'bulkExport()'
        ]
    ];
    
    // Breadcrumbs
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('app.dashboard')],
        ['label' => 'Projects', 'url' => null]
    ];
    
    // Page actions
    $actions = '
        <div class="flex items-center space-x-3">
            <button onclick="exportProjects()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                <i class="fas fa-download mr-2"></i>Export
            </button>
            <button onclick="openModal(\'create-project-modal\')" class="btn bg-blue-600 text-white hover:bg-blue-700">
                <i class="fas fa-plus mr-2"></i>New Project
            </button>
        </div>
    ';
    
    // Prepare table data
    $tableData = collect($projects ?? [])->map(function($project) {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'priority' => $project->priority ?? 'medium',
            'owner' => $project->owner->name ?? 'Unknown',
            'start_date' => $project->start_date ? $project->start_date->format('M d, Y') : '-',
            'end_date' => $project->end_date ? $project->end_date->format('M d, Y') : '-',
            'budget' => $project->budget_total ?? 0,
            'progress' => $project->progress ?? 0,
            'created_at' => $project->created_at->format('M d, Y'),
            'updated_at' => $project->updated_at->format('M d, Y')
        ];
    });
    
    // Table columns configuration
    $columns = [
        ['key' => 'name', 'label' => 'Project Name', 'sortable' => true, 'primary' => true],
        ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'priority', 'label' => 'Priority', 'sortable' => true, 'type' => 'badge'],
        ['key' => 'owner', 'label' => 'Owner', 'sortable' => true],
        ['key' => 'start_date', 'label' => 'Start Date', 'sortable' => true, 'type' => 'date'],
        ['key' => 'end_date', 'label' => 'End Date', 'sortable' => true, 'type' => 'date'],
        ['key' => 'budget', 'label' => 'Budget', 'sortable' => true, 'type' => 'currency'],
        ['key' => 'progress', 'label' => 'Progress', 'sortable' => true, 'type' => 'progress'],
        ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true, 'type' => 'date']
    ];
?>

<?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.layout-wrapper','data' => ['title' => 'Projects','subtitle' => 'Manage and track your projects','breadcrumbs' => $breadcrumbs,'actions' => $actions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.layout-wrapper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Projects','subtitle' => 'Manage and track your projects','breadcrumbs' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($breadcrumbs),'actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($actions)]); ?>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.filter-bar','data' => ['search' => true,'searchPlaceholder' => 'Search projects...','filters' => $filters,'sortOptions' => $sortOptions,'viewModes' => ['table', 'grid', 'list'],'currentViewMode' => 'table','bulkActions' => $bulkActions]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.filter-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['search' => true,'search-placeholder' => 'Search projects...','filters' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($filters),'sort-options' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($sortOptions),'view-modes' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(['table', 'grid', 'list']),'current-view-mode' => 'table','bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions)]); ?>
        
        
         <?php $__env->slot('actions', null, []); ?> 
            <button onclick="refreshProjects()" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.table-standardized','data' => ['data' => $tableData,'columns' => $columns,'sortable' => true,'selectable' => true,'pagination' => true,'perPage' => 15,'search' => true,'export' => true,'bulkActions' => $bulkActions,'responsive' => true,'loading' => false,'emptyMessage' => 'No projects found','emptyDescription' => 'Create your first project to get started','emptyActionText' => 'Create Project','emptyActionHandler' => 'openModal(\'create-project-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.table-standardized'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['data' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($tableData),'columns' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($columns),'sortable' => true,'selectable' => true,'pagination' => true,'per-page' => 15,'search' => true,'export' => true,'bulk-actions' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($bulkActions),'responsive' => true,'loading' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'empty-message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('No projects found'),'empty-description' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create your first project to get started'),'empty-action-text' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Create Project'),'empty-action-handler' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('openModal(\'create-project-modal\')')]); ?>
                
                
                 <?php $__env->slot('cell-status', null, []); ?> 
                    <?php
                        $status = $row['status'] ?? 'unknown';
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'completed' => 'bg-blue-100 text-blue-800',
                            'on_hold' => 'bg-yellow-100 text-yellow-800',
                            'cancelled' => 'bg-red-100 text-red-800',
                            'unknown' => 'bg-gray-100 text-gray-800'
                        ];
                        $statusClass = $statusClasses[$status] ?? $statusClasses['unknown'];
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo e($statusClass); ?>">
                        <?php echo e(ucfirst($status)); ?>

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
                
                
                 <?php $__env->slot('cell-budget', null, []); ?> 
                    <span class="text-sm font-medium text-gray-900">
                        <?php echo e(number_format($row['budget'], 0)); ?> VND
                    </span>
                 <?php $__env->endSlot(); ?>
                
                
                 <?php $__env->slot('row-actions', null, []); ?> 
                    <div class="flex items-center space-x-2">
                        <a href="<?php echo e(route('app.projects.show', $row['id'])); ?>" 
                           class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            View
                        </a>
                        <a href="<?php echo e(route('app.projects.edit', $row['id'])); ?>" 
                           class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                            Edit
                        </a>
                        <button onclick="deleteProject('<?php echo e($row['id']); ?>')" 
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.empty-state','data' => ['icon' => 'fas fa-project-diagram','title' => 'No projects found','description' => 'Create your first project to start managing your work.','actionText' => 'Create Project','actionIcon' => 'fas fa-plus','actionHandler' => 'openModal(\'create-project-modal\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['icon' => 'fas fa-project-diagram','title' => 'No projects found','description' => 'Create your first project to start managing your work.','action-text' => 'Create Project','action-icon' => 'fas fa-plus','action-handler' => 'openModal(\'create-project-modal\')']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4)): ?>
<?php $component = $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4; ?>
<?php unset($__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4); ?>
<?php endif; ?>
        <?php endif; ?>
    </div>
    
    
    <?php if (isset($component)) { $__componentOriginalc254754b9d5db91d5165876f9d051922ca0066f4 = $component; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.shared.modal','data' => ['id' => 'create-project-modal','title' => 'Create New Project','size' => 'lg']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('shared.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'create-project-modal','title' => 'Create New Project','size' => 'lg']); ?>
        
        <form id="create-project-form" @submit.prevent="createProject()">
            <div class="space-y-6">
                
                <div>
                    <label for="project-name" class="form-label">Project Name *</label>
                    <input type="text" 
                           id="project-name" 
                           name="name" 
                           required
                           class="form-input"
                           placeholder="Enter project name">
                </div>
                
                
                <div>
                    <label for="project-description" class="form-label">Description</label>
                    <textarea id="project-description" 
                              name="description" 
                              rows="3"
                              class="form-textarea"
                              placeholder="Enter project description"></textarea>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="project-status" class="form-label">Status</label>
                        <select id="project-status" name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="project-priority" class="form-label">Priority</label>
                        <select id="project-priority" name="priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="project-start-date" class="form-label">Start Date</label>
                        <input type="date" 
                               id="project-start-date" 
                               name="start_date"
                               class="form-input">
                    </div>
                    
                    <div>
                        <label for="project-end-date" class="form-label">End Date</label>
                        <input type="date" 
                               id="project-end-date" 
                               name="end_date"
                               class="form-input">
                    </div>
                </div>
                
                
                <div>
                    <label for="project-budget" class="form-label">Budget (VND)</label>
                    <input type="number" 
                           id="project-budget" 
                           name="budget_total"
                           min="0"
                           step="1000"
                           class="form-input"
                           placeholder="Enter project budget">
                </div>
            </div>
            
            
            <div class="flex items-center justify-end space-x-3 mt-6 pt-6 border-t border-gray-200">
                <button type="button" 
                        onclick="closeModal('create-project-modal')"
                        class="btn bg-gray-100 text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit" 
                        class="btn bg-blue-600 text-white hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i>Create Project
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
function refreshProjects() {
    window.location.reload();
}

function exportProjects() {
    // Export projects functionality
    alert('Export projects functionality would be implemented here');
}

function createProject() {
    const form = document.getElementById('create-project-form');
    const formData = new FormData(form);
    
    // Convert FormData to object
    const data = Object.fromEntries(formData.entries());
    
    // Add tenant_id
    data.tenant_id = '<?php echo e($user->tenant_id); ?>';
    data.user_id = '<?php echo e($user->id); ?>';
    
    // Submit via API
    fetch('/api/v1/app/projects', {
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
            closeModal('create-project-modal');
            window.location.reload();
        } else {
            alert('Error creating project: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error creating project');
    });
}

function deleteProject(projectId) {
    if (confirm('Are you sure you want to delete this project?')) {
        fetch(`/api/v1/app/projects/${projectId}`, {
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
                alert('Error deleting project: ' + (result.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting project');
        });
    }
}

function bulkArchive() {
    alert('Bulk archive functionality would be implemented here');
}

function bulkChangeStatus() {
    alert('Bulk change status functionality would be implemented here');
}

function bulkExport() {
    alert('Bulk export functionality would be implemented here');
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/projects/index.blade.php ENDPATH**/ ?>