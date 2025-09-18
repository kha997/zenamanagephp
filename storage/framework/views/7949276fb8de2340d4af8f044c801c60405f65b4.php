<?php $__env->startSection('title', 'Edit Task - Simple Debug'); ?>
<?php $__env->startSection('page-title', 'Edit Task - Simple Debug'); ?>
<?php $__env->startSection('page-description', 'Simple debug version without console conflicts'); ?>
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
        'label' => 'Edit Task - Simple Debug',
        'url' => '/tasks/' . ($task->id ?? '1') . '/edit-simple-debug'
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
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="editTaskSimpleDebug()">
    <!-- Debug Console -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-gray-800">🔍 Simple Debug Console</h2>
            <button @click="clearDebugLog()" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                Clear Log
            </button>
        </div>
        <div class="bg-gray-900 text-green-400 p-4 rounded-lg font-mono text-sm h-64 overflow-y-auto" id="debug-console">
            <div>Simple debug console initialized...</div>
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
                <h2 class="text-2xl font-bold text-gray-900">Edit Task - Simple Debug</h2>
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
                <button 
                    @click="updateTask()"
                    class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors"
                >
                    Test Update
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
                        @input="logDebug('Name input changed: ' + $event.target.value)"
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
                        @input="logDebug('Description input changed: ' + $event.target.value)"
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
                            @change="logDebug('Status changed to: ' + $event.target.value)"
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
                            @change="logDebug('Priority changed to: ' + $event.target.value)"
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
                            @change="logDebug('Start date changed to: ' + $event.target.value)"
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
                            @change="logDebug('Due date changed to: ' + $event.target.value)"
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
                            @input="logDebug('Progress changed to: ' + $event.target.value)"
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
                            @input="logDebug('Estimated hours changed to: ' + $event.target.value)"
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
                        @input="logDebug('Tags changed to: ' + $event.target.value)"
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
function editTaskSimpleDebug() {
    return {
        isSubmitting: false,
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
            this.logDebug('Alpine.js editTaskSimpleDebug initialized');
            this.logDebug('Task ID: <?php echo e($task->id ?? "NO_ID"); ?>');
            this.logDebug('Task Name: <?php echo e($task->name ?? "NO_NAME"); ?>');
            this.logDebug('Task Status: <?php echo e($task->status ?? "NO_STATUS"); ?>');
            this.logDebug('Task Priority: <?php echo e($task->priority ?? "NO_PRIORITY"); ?>');
            this.logDebug('Task Assignee ID: <?php echo e($task->assignee_id ?? "NO_ASSIGNEE"); ?>');
            this.logDebug('Initial formData: ' + JSON.stringify(this.formData));
        },

        logDebug(message) {
            const timestamp = new Date().toLocaleTimeString();
            const debugConsole = document.getElementById('debug-console');
            if (debugConsole) {
                const logEntry = document.createElement('div');
                logEntry.textContent = `[${timestamp}] ${message}`;
                debugConsole.appendChild(logEntry);
                debugConsole.scrollTop = debugConsole.scrollHeight;
            }
        },

        clearDebugLog() {
            const debugConsole = document.getElementById('debug-console');
            if (debugConsole) {
                debugConsole.innerHTML = '<div>Debug log cleared...</div>';
            }
        },

        testAlpine() {
            this.logDebug('Testing Alpine.js functionality...');
            this.logDebug('Alpine.js is working: ' + (typeof Alpine !== 'undefined'));
            this.logDebug('Form data binding: ' + JSON.stringify(this.formData));
            this.logDebug('Alpine test completed');
        },

        testFormData() {
            this.logDebug('Testing form data...');
            this.logDebug('Form data keys: ' + Object.keys(this.formData).join(', '));
            this.logDebug('Form data values: ' + JSON.stringify(this.formData));
            
            // Test each field
            Object.keys(this.formData).forEach(key => {
                this.logDebug(`${key}: ${this.formData[key]} (${typeof this.formData[key]})`);
            });
            
            this.logDebug('Form data test completed');
        },

        async testNetworkRequest() {
            this.logDebug('=== TESTING NETWORK REQUEST ===');
            this.logDebug('Starting network request to /api/tasks...');
            
            try {
                // Check CSRF token first
                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                if (csrfToken) {
                    this.logDebug('CSRF token found: ' + csrfToken.getAttribute('content'));
                } else {
                    this.logDebug('WARNING: CSRF token not found!');
                }
                
                this.logDebug('Making fetch request...');
                const response = await fetch('/api/tasks', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : 'test-token'
                    }
                });
                
                this.logDebug('Response received!');
                this.logDebug('Response status: ' + response.status);
                this.logDebug('Response ok: ' + response.ok);
                this.logDebug('Response headers: ' + JSON.stringify([...response.headers.entries()]));
                
                if (response.ok) {
                    this.logDebug('Parsing response JSON...');
                    const data = await response.json();
                    this.logDebug('Response data parsed successfully!');
                    this.logDebug('Success: ' + data.success);
                    this.logDebug('Tasks found: ' + (data.data?.tasks?.length || 0));
                    this.logDebug('Total tasks: ' + (data.data?.total || 0));
                    this.logDebug('First task name: ' + (data.data?.tasks?.[0]?.name || 'N/A'));
                } else {
                    this.logDebug('Network request failed with status: ' + response.status);
                    const errorText = await response.text();
                    this.logDebug('Error response: ' + errorText);
                }
            } catch (error) {
                this.logDebug('Network request error: ' + error.message);
                this.logDebug('Error stack: ' + error.stack);
            }
            
            this.logDebug('=== NETWORK REQUEST TEST COMPLETED ===');
        },

        async updateTask() {
            this.isSubmitting = true;
            this.logDebug('Starting task update...');
            this.logDebug('Task ID: ' + this.formData.id);
            this.logDebug('Form data: ' + JSON.stringify(this.formData));
            this.logDebug('Status value: ' + this.formData.status);
            this.logDebug('Priority value: ' + this.formData.priority);
            
            try {
                // Get fresh CSRF token first
                this.logDebug('Getting fresh CSRF token...');
                const tokenResponse = await fetch('/tasks/' + this.formData.id + '/edit', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (tokenResponse.ok) {
                    const html = await tokenResponse.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const freshToken = doc.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    
                    if (freshToken) {
                        this.logDebug('Fresh CSRF token: ' + freshToken);
                        // Update meta tag with fresh token
                        document.querySelector('meta[name="csrf-token"]').setAttribute('content', freshToken);
                    } else {
                        this.logDebug('WARNING: Could not get fresh CSRF token');
                    }
                }
                
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
                this.logDebug('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    this.logDebug(`${key}: ${value}`);
                }
                
                // Submit to server
                this.logDebug('Submitting to: /tasks/' + this.formData.id);
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                this.logDebug('Response status: ' + response.status);
                this.logDebug('Response ok: ' + response.ok);
                
                if (response.ok) {
                    this.logDebug('Task updated successfully!');
                    alert('Task updated successfully!');
                    
                    // Redirect to tasks list
                    setTimeout(() => {
                        window.location.href = '/tasks';
                    }, 1500);
                } else {
                    const responseText = await response.text();
                    this.logDebug('Update failed: ' + response.status + ' ' + responseText);
                    
                    try {
                        const errorData = JSON.parse(responseText);
                        if (errorData.errors) {
                            this.logDebug('Validation errors: ' + JSON.stringify(errorData.errors));
                            alert('Validation errors: ' + JSON.stringify(errorData.errors));
                        } else {
                            alert('Failed to update task: ' + (errorData.message || 'Unknown error'));
                        }
                    } catch (e) {
                        alert('Failed to update task. Please try again.');
                    }
                }
                
            } catch (error) {
                this.logDebug('Update error: ' + error.message);
                alert('Failed to update task. Please try again.');
            } finally {
                this.isSubmitting = false;
            }
        },

        cancelEdit() {
            this.logDebug('Cancelling edit...');
            window.location.href = '/tasks';
        }
    }
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tasks/edit-simple-debug.blade.php ENDPATH**/ ?>