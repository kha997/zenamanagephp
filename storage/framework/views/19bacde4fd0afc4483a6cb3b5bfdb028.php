<?php $__env->startSection('title', 'Kanban Board (React)'); ?>

<?php $__env->startSection('content'); ?>
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Task Kanban Board</h1>
                    <p class="mt-2 text-gray-600">Drag and drop tasks to update their status</p>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="<?php echo e(route('app.tasks.index')); ?>" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-list mr-2"></i>
                        List View
                    </a>
                    <a href="<?php echo e(route('app.tasks.create')); ?>" 
                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>
                        New Task
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Filters</h2>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700">Project</label>
                    <select name="project_id" id="project_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Projects</option>
                        <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($project->id); ?>" <?php echo e(request('project_id') == $project->id ? 'selected' : ''); ?>>
                                <?php echo e($project->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                
                <div>
                    <label for="status-filter" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status-filter" 
                            data-testid="status-filter"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Statuses</option>
                        <option value="backlog" <?php echo e(request('status') == 'backlog' ? 'selected' : ''); ?>>Backlog</option>
                        <option value="todo" <?php echo e(request('status') == 'todo' ? 'selected' : ''); ?>>To Do</option>
                        <option value="in_progress" <?php echo e(request('status') == 'in_progress' ? 'selected' : ''); ?>>In Progress</option>
                        <option value="blocked" <?php echo e(request('status') == 'blocked' ? 'selected' : ''); ?>>Blocked</option>
                        <option value="done" <?php echo e(request('status') == 'done' ? 'selected' : ''); ?>>Done</option>
                    </select>
                </div>
                
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700">Priority</label>
                    <select name="priority" id="priority" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Priorities</option>
                        <option value="low" <?php echo e(request('priority') == 'low' ? 'selected' : ''); ?>>Low</option>
                        <option value="normal" <?php echo e(request('priority') == 'normal' ? 'selected' : ''); ?>>Normal</option>
                        <option value="high" <?php echo e(request('priority') == 'high' ? 'selected' : ''); ?>>High</option>
                        <option value="urgent" <?php echo e(request('priority') == 'urgent' ? 'selected' : ''); ?>>Urgent</option>
                    </select>
                </div>
                
                <div>
                    <label for="assignee_id" class="block text-sm font-medium text-gray-700">Assignee</label>
                    <select name="assignee_id" id="assignee_id" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">All Assignees</option>
                        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($user->id); ?>" <?php echo e(request('assignee_id') == $user->id ? 'selected' : ''); ?>>
                                <?php echo e($user->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                
                <div class="md:col-span-4 flex justify-end space-x-2">
                    <a href="/app/tasks" 
                       class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Reset Filters
                    </a>
                    <button type="submit" 
                            data-testid="apply-filters"
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Kanban Board -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-6" 
             data-testid="react-kanban-board"
             x-data="kanbanBoard()"
             @dragover.prevent
             @drop.prevent="handleDrop($event)">
            <!-- Backlog Column -->
            <div class="bg-white rounded-lg shadow-sm p-4" 
                 data-testid="kanban-column"
                 data-column="backlog"
                 @dragover.prevent
                 @drop.prevent="handleColumnDrop($event, 'backlog')">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Backlog</h3>
                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                        <?php echo e($tasks->where('status', 'backlog')->count()); ?>

                    </span>
                </div>
                <div class="space-y-3">
                    <?php $__currentLoopData = $tasks->where('status', 'backlog'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-move" 
                             data-testid="kanban-task" 
                             data-task-id="<?php echo e($task->id); ?>"
                             draggable="true"
                             @dragstart="handleDragStart($event, '<?php echo e($task->id); ?>', 'backlog')"
                             @dragend="handleDragEnd($event)"
                             @mousedown="handleMouseDown($event, '<?php echo e($task->id); ?>', 'backlog')">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo e($task->name); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo e(Str::limit($task->description, 60)); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                            <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                            <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                            <?php else: ?> bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst($task->priority)); ?>

                                        </span>
                                        <?php if($task->assignee): ?>
                                            <span class="text-xs text-gray-500"><?php echo e($task->assignee->name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button class="text-gray-400 hover:text-gray-600" 
                                            data-testid="edit-task-button"
                                            onclick="editTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600" 
                                            data-testid="delete-task-button"
                                            onclick="deleteTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- To Do Column -->
            <div class="bg-white rounded-lg shadow-sm p-4" 
                 data-testid="kanban-column"
                 data-column="todo"
                 @dragover.prevent
                 @drop.prevent="handleColumnDrop($event, 'todo')"
                 @mouseup="handleMouseUp($event, 'todo')">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">To Do</h3>
                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                        <?php echo e($tasks->where('status', 'todo')->count()); ?>

                    </span>
                </div>
                <div class="space-y-3">
                    <?php $__currentLoopData = $tasks->where('status', 'todo'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-move" 
                             data-testid="kanban-task" 
                             data-task-id="<?php echo e($task->id); ?>"
                             draggable="true"
                             @dragstart="handleDragStart($event, '<?php echo e($task->id); ?>', 'todo')"
                             @dragend="handleDragEnd($event)">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo e($task->name); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo e(Str::limit($task->description, 60)); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                            <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                            <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                            <?php else: ?> bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst($task->priority)); ?>

                                        </span>
                                        <?php if($task->assignee): ?>
                                            <span class="text-xs text-gray-500"><?php echo e($task->assignee->name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button class="text-gray-400 hover:text-gray-600" 
                                            data-testid="edit-task-button"
                                            onclick="editTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600" 
                                            data-testid="delete-task-button"
                                            onclick="deleteTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- In Progress Column -->
            <div class="bg-white rounded-lg shadow-sm p-4" 
                 data-testid="kanban-column"
                 data-column="in_progress"
                 @dragover.prevent
                 @drop.prevent="handleColumnDrop($event, 'in_progress')">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">In Progress</h3>
                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                        <?php echo e($tasks->where('status', 'in_progress')->count()); ?>

                    </span>
                </div>
                <div class="space-y-3">
                    <?php $__currentLoopData = $tasks->where('status', 'in_progress'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-move" 
                             data-testid="kanban-task" 
                             data-task-id="<?php echo e($task->id); ?>"
                             draggable="true"
                             @dragstart="handleDragStart($event, '<?php echo e($task->id); ?>', 'in_progress')"
                             @dragend="handleDragEnd($event)">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo e($task->name); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo e(Str::limit($task->description, 60)); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                            <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                            <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                            <?php else: ?> bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst($task->priority)); ?>

                                        </span>
                                        <?php if($task->assignee): ?>
                                            <span class="text-xs text-gray-500"><?php echo e($task->assignee->name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button class="text-gray-400 hover:text-gray-600" 
                                            data-testid="edit-task-button"
                                            onclick="editTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600" 
                                            data-testid="delete-task-button"
                                            onclick="deleteTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- Blocked Column -->
            <div class="bg-white rounded-lg shadow-sm p-4" 
                 data-testid="kanban-column"
                 data-column="blocked"
                 @dragover.prevent
                 @drop.prevent="handleColumnDrop($event, 'blocked')">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Blocked</h3>
                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                        <?php echo e($tasks->where('status', 'blocked')->count()); ?>

                    </span>
                </div>
                <div class="space-y-3">
                    <?php $__currentLoopData = $tasks->where('status', 'blocked'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-move" 
                             data-testid="kanban-task" 
                             data-task-id="<?php echo e($task->id); ?>"
                             draggable="true"
                             @dragstart="handleDragStart($event, '<?php echo e($task->id); ?>', 'blocked')"
                             @dragend="handleDragEnd($event)">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo e($task->name); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo e(Str::limit($task->description, 60)); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                            <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                            <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                            <?php else: ?> bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst($task->priority)); ?>

                                        </span>
                                        <?php if($task->assignee): ?>
                                            <span class="text-xs text-gray-500"><?php echo e($task->assignee->name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button class="text-gray-400 hover:text-gray-600" 
                                            data-testid="edit-task-button"
                                            onclick="editTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600" 
                                            data-testid="delete-task-button"
                                            onclick="deleteTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>

            <!-- Done Column -->
            <div class="bg-white rounded-lg shadow-sm p-4" 
                 data-testid="kanban-column"
                 data-column="done"
                 @dragover.prevent
                 @drop.prevent="handleColumnDrop($event, 'done')">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800">Done</h3>
                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-sm">
                        <?php echo e($tasks->where('status', 'done')->count()); ?>

                    </span>
                </div>
                <div class="space-y-3">
                    <?php $__currentLoopData = $tasks->where('status', 'done'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow cursor-move" 
                             data-testid="kanban-task" 
                             data-task-id="<?php echo e($task->id); ?>"
                             draggable="true"
                             @dragstart="handleDragStart($event, '<?php echo e($task->id); ?>', 'done')"
                             @dragend="handleDragEnd($event)">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900"><?php echo e($task->name); ?></h4>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo e(Str::limit($task->description, 60)); ?></p>
                                    <div class="flex items-center space-x-2 mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                            <?php if($task->priority === 'urgent'): ?> bg-red-100 text-red-800
                                            <?php elseif($task->priority === 'high'): ?> bg-orange-100 text-orange-800
                                            <?php elseif($task->priority === 'normal'): ?> bg-blue-100 text-blue-800
                                            <?php else: ?> bg-gray-100 text-gray-800
                                            <?php endif; ?>">
                                            <?php echo e(ucfirst($task->priority)); ?>

                                        </span>
                                        <?php if($task->assignee): ?>
                                            <span class="text-xs text-gray-500"><?php echo e($task->assignee->name); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <button class="text-gray-400 hover:text-gray-600" 
                                            data-testid="edit-task-button"
                                            onclick="editTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-edit text-xs"></i>
                                    </button>
                                    <button class="text-gray-400 hover:text-red-600" 
                                            data-testid="delete-task-button"
                                            onclick="deleteTask('<?php echo e($task->id); ?>')">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="edit-task-modal" 
     data-testid="edit-task-modal"
     class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Edit Task</h3>
                <form id="edit-task-form">
                    <div class="space-y-4">
                        <div>
                            <label for="edit-task-name" class="block text-sm font-medium text-gray-700">Task Name</label>
                            <input type="text" id="edit-task-name" name="name" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                        <div>
                            <label for="edit-task-description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="edit-task-description" name="description" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                        </div>
                        <div>
                            <label for="edit-task-status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="edit-task-status" name="status" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="backlog">Backlog</option>
                                <option value="todo">To Do</option>
                                <option value="in_progress">In Progress</option>
                                <option value="blocked">Blocked</option>
                                <option value="done">Done</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="saveTaskEdit()" 
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 sm:ml-3 sm:w-auto sm:text-sm">
                    Save Changes
                </button>
                <button type="button" onclick="closeEditModal()" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-task-modal" 
     class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Task</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">Are you sure you want to delete this task? This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="window.confirmDeleteTaskGlobal()" 
                        data-testid="confirm-delete-task"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 sm:ml-3 sm:w-auto sm:text-sm">
                    Delete
                </button>
                <button type="button" onclick="closeDeleteModal()" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Alpine.js Kanban Board Component
function kanbanBoard() {
    return {
        draggedTask: null,
        draggedFromColumn: null,
        
        handleDragStart(event, taskId, fromColumn) {
            this.draggedTask = taskId;
            this.draggedFromColumn = fromColumn;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/html', event.target.outerHTML);
            event.target.style.opacity = '0.5';
        },
        
        handleDragEnd(event) {
            event.target.style.opacity = '1';
            this.draggedTask = null;
            this.draggedFromColumn = null;
        },
        
        handleDrop(event) {
            event.preventDefault();
            // This method is called on the main board container
            // The actual drop handling is done in handleColumnDrop
            return false;
        },
        
        handleColumnDrop(event, targetColumn) {
            event.preventDefault();
            
            if (!this.draggedTask || this.draggedFromColumn === targetColumn) {
                return;
            }
            
            this.moveTaskToColumn(this.draggedTask, targetColumn);
        },
        
        // Handle mouse-based drag and drop for testing
        handleMouseDown(event, taskId, fromColumn) {
            console.log('Mouse down on task:', taskId, 'from column:', fromColumn);
            this.draggedTask = taskId;
            this.draggedFromColumn = fromColumn;
            event.target.style.opacity = '0.5';
        },
        
        handleMouseUp(event, targetColumn) {
            console.log('Mouse up on column:', targetColumn, 'dragged task:', this.draggedTask);
            if (this.draggedTask && this.draggedFromColumn !== targetColumn) {
                console.log('Moving task via mouse events');
                this.moveTaskToColumn(this.draggedTask, targetColumn);
            }
            this.draggedTask = null;
            this.draggedFromColumn = null;
            event.target.style.opacity = '1';
        },
        
        // Global mouse event handlers for Playwright compatibility
        handleGlobalMouseDown(event) {
            const taskElement = event.target.closest('[data-task-id]');
            if (taskElement) {
                const taskId = taskElement.getAttribute('data-task-id');
                const fromColumn = taskElement.getAttribute('data-column') || 'backlog';
                console.log('Global mouse down on task:', taskId, 'from column:', fromColumn);
                this.draggedTask = taskId;
                this.draggedFromColumn = fromColumn;
                taskElement.style.opacity = '0.5';
            }
        },
        
        handleGlobalMouseUp(event) {
            const columnElement = event.target.closest('[data-column]');
            if (columnElement && this.draggedTask) {
                const targetColumn = columnElement.getAttribute('data-column');
                console.log('Global mouse up on column:', targetColumn, 'dragged task:', this.draggedTask);
                if (this.draggedFromColumn !== targetColumn) {
                    console.log('Moving task via global mouse events');
                    this.moveTaskToColumn(this.draggedTask, targetColumn);
                }
            }
            
            // Reset dragged task
            if (this.draggedTask) {
                const taskElement = document.querySelector(`[data-task-id="${this.draggedTask}"]`);
                if (taskElement) {
                    taskElement.style.opacity = '1';
                }
            }
            this.draggedTask = null;
            this.draggedFromColumn = null;
        },
        
        moveTaskToColumn(taskId, targetColumn) {
            console.log(`Moving task ${taskId} to ${targetColumn}`);
            
            const taskElement = document.querySelector(`[data-task-id="${taskId}"]`);
            if (!taskElement) {
                console.error(`Task element not found for ID: ${taskId}`);
                return;
            }
            
            // Find the target column's task container
            const targetColumnElement = document.querySelector(`[data-column="${targetColumn}"] .space-y-3`);
            if (!targetColumnElement) {
                console.error(`Target column not found: ${targetColumn}`);
                return;
            }
            
            // Remove from current column
            taskElement.remove();
            
            // Add to target column
            targetColumnElement.appendChild(taskElement);
            
            // Update the task's data attributes to reflect new column
            taskElement.setAttribute('data-column', targetColumn);
            
            console.log(`Task ${taskId} successfully moved to ${targetColumn}`);
            this.showNotification(`Task moved to ${targetColumn.replace('_', ' ')}`);
        },
        
        showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 px-4 py-2 bg-green-500 text-white rounded-md shadow-lg transition-all duration-300';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    };
}

// Register with Alpine.js
if (window.Alpine) {
    Alpine.data('kanbanBoard', kanbanBoard);
} else {
    document.addEventListener('alpine:init', () => {
        Alpine.data('kanbanBoard', kanbanBoard);
    });
}

// Add global mouse event listeners for Playwright compatibility
document.addEventListener('mousedown', (event) => {
    const component = Alpine.$data(document.querySelector('[x-data*="kanbanBoard"]'));
    if (component && component.handleGlobalMouseDown) {
        component.handleGlobalMouseDown(event);
    }
});

document.addEventListener('mouseup', (event) => {
    const component = Alpine.$data(document.querySelector('[x-data*="kanbanBoard"]'));
    if (component && component.handleGlobalMouseUp) {
        component.handleGlobalMouseUp(event);
    }
});

let currentTaskId = null;

function editTask(taskId) {
    currentTaskId = taskId;
    document.getElementById('edit-task-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-task-modal').classList.add('hidden');
    currentTaskId = null;
}

function saveTaskEdit() {
    // Placeholder for save functionality
    console.log('Saving task:', currentTaskId);
    closeEditModal();
}

function deleteTask(taskId) {
    console.log('deleteTask called with ID:', taskId);
    currentTaskId = taskId;
    
    // Show the modal
    const modal = document.getElementById('delete-task-modal');
    if (modal) {
        modal.classList.remove('hidden');
        console.log('Delete modal shown');
    } else {
        console.error('Delete modal not found');
    }
}

function closeDeleteModal() {
    document.getElementById('delete-task-modal').classList.add('hidden');
    currentTaskId = null;
}

function confirmDeleteTask() {
    console.log('confirmDeleteTask called, currentTaskId:', currentTaskId);
    
    if (!currentTaskId) {
        console.error('No task ID to delete');
        return;
    }
    
    console.log('Deleting task:', currentTaskId);
    
    // Find and remove the task element from the DOM
    const taskElement = document.querySelector(`[data-task-id="${currentTaskId}"]`);
    console.log('Task element found:', !!taskElement);
    
    if (taskElement) {
        taskElement.remove();
        console.log(`Task ${currentTaskId} removed from DOM`);
        
        // Show success notification
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 z-50 px-4 py-2 bg-green-500 text-white rounded-md shadow-lg transition-all duration-300';
        notification.textContent = 'Task deleted successfully!';
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    } else {
        console.error(`Task element not found for ID: ${currentTaskId}`);
    }
    
    closeDeleteModal();
}

// Global function for Playwright compatibility
window.confirmDeleteTaskGlobal = function() {
    console.log('Global confirmDeleteTaskGlobal called');
    confirmDeleteTask();
};
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/tasks/kanban-react.blade.php ENDPATH**/ ?>