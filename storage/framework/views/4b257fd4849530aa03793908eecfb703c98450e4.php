<?php $__env->startSection('title', 'Edit Task - Debug Mode'); ?>
<?php $__env->startSection('page-title', 'Edit Task - Debug Mode'); ?>
<?php $__env->startSection('page-description', 'Debug version with extensive logging'); ?>
<?php $__env->startSection('user-initials', 'PM'); ?>
<?php $__env->startSection('user-name', 'Project Manager'); ?>
<?php $__env->startSection('current-route', 'tasks'); ?>

<?php
$breadcrumb = [
    [
        'label' => 'Dashboard',
        'url' => '/dashboard',
        'icon' => 'fas fa-home'
    ],
    [
        'label' => 'Tasks Management',
        'url' => '/tasks'
    ],
    [
        'label' => 'Edit Task - Debug',
        'url' => '/tasks/' . ($task->id ?? '1') . '/edit-debug'
    ]
];
$currentRoute = 'tasks';
?>

<?php $__env->startSection('content'); ?>
<?php if(isset($error)): ?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-red-50 border border-red-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-red-800 mb-2">Error Loading Task</h3>
        <p class="text-red-700"><?php echo e($error); ?></p>
        <div class="mt-4">
            <a href="/tasks" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                Back to Tasks
            </a>
        </div>
    </div>
</div>
<?php elseif(!$task): ?>
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
        <h3 class="text-lg font-medium text-yellow-800 mb-2">Task Not Found</h3>
        <p class="text-yellow-700">The requested task could not be found.</p>
        <div class="mt-4">
            <a href="/tasks" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                Back to Tasks
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="editTaskDebug()">
    <!-- Debug Console -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">🔍 Debug Console</h2>
            <button @click="clearDebugLog()" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                Clear Log
            </button>
        </div>
        <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm h-64 overflow-y-auto" id="debug-console">
            <div>Debug console initialized...</div>
        </div>
    </div>

    <!-- Task Data Display -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">📊 Task Data</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-3">Server Data</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <pre class="text-sm text-gray-700"><?php echo e(json_encode($task->toArray(), JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-medium text-gray-700 mb-3">Form Data (Live)</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <pre x-text="JSON.stringify(formData, null, 2)" class="text-sm text-gray-700"></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Edit Task - Debug Mode</h2>
                <p class="text-gray-600 mt-1">Task ID: <?php echo e($task->id); ?></p>
            </div>
            <div class="flex space-x-3">
                <button 
                    @click="testAlpine()"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors"
                >
                    Test Alpine
                </button>
                <button 
                    @click="testFormData()"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Test Form Data
                </button>
                <button 
                    @click="testNetworkRequest()"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                >
                    Test Network
                </button>
            </div>
        </div>

        <form method="POST" action="/tasks/<?php echo e($task->id); ?>" @submit.prevent="updateTask()">
            <input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">
            
            <div class="space-y-6">
                <!-- Task Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tasks text-gray-400 mr-1"></i>
                        Task Name
                    </label>
                    <input 
                        type="text" 
                        x-model="formData.name"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        placeholder="Enter task title"
                        required
                        @input="debugLog('Name input changed: ' + $event.target.value)"
                    >
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-align-left text-gray-400 mr-1"></i>
                        Description
                    </label>
                    <textarea 
                        x-model="formData.description"
                        rows="4"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        placeholder="Enter task description"
                        @input="debugLog('Description input changed: ' + $event.target.value)"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag text-gray-400 mr-1"></i>
                            Status
                        </label>
                        <select 
                            x-model="formData.status"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            required
                            @change="debugLog('Status changed to: ' + $event.target.value)"
                        >
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="review">Review</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-exclamation-triangle text-gray-400 mr-1"></i>
                            Priority
                        </label>
                        <select 
                            x-model="formData.priority"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            required
                            @change="debugLog('Priority changed to: ' + $event.target.value)"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-alt text-gray-400 mr-1"></i>
                            Start Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.start_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            required
                            @change="debugLog('Start date changed to: ' + $event.target.value)"
                        >
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar-check text-gray-400 mr-1"></i>
                            Due Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.end_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            required
                            @change="debugLog('Due date changed to: ' + $event.target.value)"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Progress -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-percentage text-gray-400 mr-1"></i>
                            Progress (%)
                        </label>
                        <input 
                            type="number" 
                            x-model="formData.progress_percent"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            min="0" max="100"
                            @input="debugLog('Progress changed to: ' + $event.target.value)"
                        >
                    </div>

                    <!-- Estimated Hours -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-clock text-gray-400 mr-1"></i>
                            Estimated Hours
                        </label>
                        <input 
                            type="number" 
                            x-model="formData.estimated_hours"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            min="0" step="0.5"
                            @input="debugLog('Estimated hours changed to: ' + $event.target.value)"
                        >
                    </div>
                </div>

                <!-- Tags -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-tags text-gray-400 mr-1"></i>
                        Tags
                    </label>
                    <input 
                        type="text" 
                        x-model="formData.tags"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        placeholder="Enter tags separated by commas"
                        @input="debugLog('Tags changed to: ' + $event.target.value)"
                    >
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                    <button 
                        type="button"
                        @click="cancelEdit()"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button"
                        @click="updateTask()"
                        :disabled="isSubmitting"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!isSubmitting">Update Task</span>
                        <span x-show="isSubmitting">Updating...</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function editTaskDebug() {
    return {
        isSubmitting: false,
        newTag: '',
        formData: {
            id: '<?php echo e($task->id ?? ""); ?>',
            name: '<?php echo e($task->name ?? ""); ?>',
            description: '<?php echo e($task->description ?? ""); ?>',
            project_id: '<?php echo e($task->project_id ?? ""); ?>',
            assignee_id: '<?php echo e($task->assignee_id ?? ""); ?>',
            status: '<?php echo e($task->status ?? "pending"); ?>',
            priority: '<?php echo e($task->priority ?? "medium"); ?>',
            start_date: '<?php echo e($task->start_date ? \Carbon\Carbon::parse($task->start_date)->format('Y-m-d') : ""); ?>',
            end_date: '<?php echo e($task->end_date ? \Carbon\Carbon::parse($task->end_date)->format('Y-m-d') : ""); ?>',
            progress_percent: <?php echo e($task->progress_percent ?? '0'); ?>,
            estimated_hours: <?php echo e($task->estimated_hours ?? '0'); ?>,
            tags: '<?php echo e($task->tags ?? ""); ?>'
        },

        init() {
            this.debugLog('Alpine.js editTaskDebug initialized');
            this.debugLog('Task ID: <?php echo e($task->id ?? "NO_ID"); ?>');
            this.debugLog('Task Name: <?php echo e($task->name ?? "NO_NAME"); ?>');
            this.debugLog('Task Status: <?php echo e($task->status ?? "NO_STATUS"); ?>');
            this.debugLog('Task Priority: <?php echo e($task->priority ?? "NO_PRIORITY"); ?>');
            this.debugLog('Task Assignee ID: <?php echo e($task->assignee_id ?? "NO_ASSIGNEE"); ?>');
            this.debugLog('Initial formData: ' + JSON.stringify(this.formData));
            
            // Test Alpine.js functionality
            this.testAlpine = 'Alpine.js is working!';
            this.debugLog('Alpine test: ' + this.testAlpine);
        },

        debugLog(message) {
            const timestamp = new Date().toLocaleTimeString();
            const debugConsole = document.getElementById('debug-console');
            const logEntry = document.createElement('div');
            logEntry.textContent = `[${timestamp}] ${message}`;
            debugConsole.appendChild(logEntry);
            debugConsole.scrollTop = debugConsole.scrollHeight;
            
            // Also log to browser console (use original console.log)
            if (typeof console !== 'undefined' && console.log) {
                console.log(`[DEBUG] ${message}`);
            }
        },

        clearDebugLog() {
            const console = document.getElementById('debug-console');
            console.innerHTML = '<div>Debug log cleared...</div>';
        },

        testAlpine() {
            this.debugLog('Testing Alpine.js functionality...');
            this.debugLog('Alpine.js is working: ' + (typeof Alpine !== 'undefined'));
            this.debugLog('Form data binding: ' + JSON.stringify(this.formData));
            this.debugLog('Alpine test completed');
        },

        testFormData() {
            this.debugLog('Testing form data...');
            this.debugLog('Form data keys: ' + Object.keys(this.formData).join(', '));
            this.debugLog('Form data values: ' + JSON.stringify(this.formData));
            
            // Test each field
            Object.keys(this.formData).forEach(key => {
                this.debugLog(`${key}: ${this.formData[key]} (${typeof this.formData[key]})`);
            });
            
            this.debugLog('Form data test completed');
        },

        async testNetworkRequest() {
            this.debugLog('Testing network request...');
            
            try {
                const response = await fetch('/api/tasks', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                this.debugLog('Network request response: ' + response.status);
                this.debugLog('Response ok: ' + response.ok);
                
                if (response.ok) {
                    const data = await response.json();
                    this.debugLog('Response data: ' + JSON.stringify(data));
                    this.debugLog('Tasks found: ' + (data.data?.tasks?.length || 0));
                } else {
                    this.debugLog('Network request failed: ' + response.status);
                }
            } catch (error) {
                this.debugLog('Network request error: ' + error.message);
            }
        },

        async updateTask() {
            this.isSubmitting = true;
            this.debugLog('Starting task update...');
            this.debugLog('Task ID: ' + this.formData.id);
            this.debugLog('Form data: ' + JSON.stringify(this.formData));
            this.debugLog('Status value: ' + this.formData.status);
            this.debugLog('Priority value: ' + this.formData.priority);
            
            try {
                // Prepare form data
                const formData = new FormData();
                formData.append('_method', 'PUT');
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('name', this.formData.name);
                formData.append('description', this.formData.description);
                formData.append('project_id', this.formData.project_id);
                const assigneeId = this.formData.assignee_id && this.formData.assignee_id !== '' ? this.formData.assignee_id : '';
                formData.append('assignee_id', assigneeId);
                formData.append('status', this.formData.status);
                formData.append('priority', this.formData.priority);
                formData.append('start_date', this.formData.start_date);
                formData.append('end_date', this.formData.end_date);
                formData.append('progress_percent', this.formData.progress_percent);
                formData.append('estimated_hours', this.formData.estimated_hours);
                formData.append('tags', this.formData.tags);
                
                // Debug: Log form data being sent
                this.debugLog('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    this.debugLog(`${key}: ${value}`);
                }
                
                // Submit to server
                this.debugLog('Submitting to: /tasks/' + this.formData.id);
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                this.debugLog('Response status: ' + response.status);
                this.debugLog('Response ok: ' + response.ok);
                
                if (response.ok) {
                    this.debugLog('Task updated successfully!');
                    this.showNotification('Task updated successfully!', 'success');
                    
                    // Redirect to tasks list
                    setTimeout(() => {
                        window.location.href = '/tasks';
                    }, 1500);
                } else {
                    const responseText = await response.text();
                    this.debugLog('Update failed: ' + response.status + ' ' + responseText);
                    
                    try {
                        const errorData = JSON.parse(responseText);
                        if (errorData.errors) {
                            this.debugLog('Validation errors: ' + JSON.stringify(errorData.errors));
                            this.showNotification('Validation errors: ' + JSON.stringify(errorData.errors), 'error');
                        } else {
                            this.showNotification('Failed to update task: ' + (errorData.message || 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to update task. Please try again.', 'error');
                    }
                }
                
            } catch (error) {
                this.debugLog('Update error: ' + error.message);
                this.showNotification('Failed to update task. Please try again.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        cancelEdit() {
            this.debugLog('Cancelling edit...');
            window.location.href = '/tasks';
        },

        showNotification(message, type) {
            this.debugLog(`Notification: ${type} - ${message}`);
            // You can implement a toast notification here
            alert(message);
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tasks/edit-debug.blade.php ENDPATH**/ ?>