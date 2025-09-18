<?php $__env->startSection('title', 'Edit Task'); ?>
<?php $__env->startSection('page-title', 'Edit Task'); ?>
<?php $__env->startSection('page-description', 'Update task details and information'); ?>
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
        'label' => 'Edit Task',
        'url' => '/tasks/' . ($task->id ?? '1') . '/edit'
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
<div x-data="{
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
            tags: <?php echo e(json_encode(array_filter(explode(',', $task->tags ?? '')))); ?>

        },
        testAlpine: 'Alpine.js is working!',
        
        init() {
            console.log('=== ALPINE.JS INITIALIZATION ===');
            console.log('Alpine.js initialized!');
            console.log('Raw task data from server:');
            console.log('Task ID:', '<?php echo e($task->id ?? "NO_ID"); ?>');
            console.log('Task Name:', '<?php echo e($task->name ?? "NO_NAME"); ?>');
            console.log('Task Status:', '<?php echo e($task->status ?? "NO_STATUS"); ?>');
            console.log('Task Priority:', '<?php echo e($task->priority ?? "NO_PRIORITY"); ?>');
            console.log('Task Assignee ID:', '<?php echo e($task->assignee_id ?? "NO_ASSIGNEE"); ?>');
            console.log('Task Description:', '<?php echo e($task->description ?? "NO_DESCRIPTION"); ?>');
            console.log('Task Project ID:', '<?php echo e($task->project_id ?? "NO_PROJECT"); ?>');
            console.log('Task Start Date:', '<?php echo e($task->start_date ?? "NO_START_DATE"); ?>');
            console.log('Task End Date:', '<?php echo e($task->end_date ?? "NO_END_DATE"); ?>');
            console.log('Task Progress:', '<?php echo e($task->progress_percent ?? "NO_PROGRESS"); ?>');
            console.log('Task Estimated Hours:', '<?php echo e($task->estimated_hours ?? "NO_HOURS"); ?>');
            console.log('Task Tags:', '<?php echo e($task->tags ?? "NO_TAGS"); ?>');
            
            console.log('FormData after initialization:');
            console.log('formData.id:', this.formData.id);
            console.log('formData.name:', this.formData.name);
            console.log('formData.status:', this.formData.status);
            console.log('formData.priority:', this.formData.priority);
            console.log('formData.description:', this.formData.description);
            
            console.log('=== ALPINE.JS INITIALIZATION COMPLETED ===');
        },
        
        async testUpdate() {
            console.log('Testing update...');
            try {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'));
                formData.append('name', 'Test Task');
                formData.append('description', 'Test Description');
                formData.append('status', 'pending');
                formData.append('priority', 'medium');
                
                console.log('Test update data prepared');
            } catch (error) {
                console.error('Test update error:', error);
            }
        },
        
        addTag() {
            if (this.newTag.trim() && !this.formData.tags.includes(this.newTag.trim())) {
                this.formData.tags.push(this.newTag.trim());
                this.newTag = '';
            }
        },
        
        removeTag(tag) {
            this.formData.tags = this.formData.tags.filter(t => t !== tag);
        },
        
        async updateTask() {
            this.isSubmitting = true;
            console.log('Starting task update...');
            console.log('Task ID:', this.formData.id);
            console.log('Form data:', this.formData);
            console.log('Status value:', this.formData.status);
            console.log('Priority value:', this.formData.priority);
            
            try {
                // Get fresh CSRF token first
                console.log('Getting fresh CSRF token...');
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
                    const freshToken = doc.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content');
                    
                    if (freshToken) {
                        console.log('Fresh CSRF token:', freshToken);
                        document.querySelector('meta[name=\"csrf-token\"]').setAttribute('content', freshToken);
                    } else {
                        console.log('WARNING: Could not get fresh CSRF token');
                    }
                }
                
                // Prepare form data
                const formData = new FormData();
                formData.append('_method', 'PUT');
                formData.append('_token', document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'));
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
                console.log('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ':', value);
                }
                
                // Submit to server
                console.log('Submitting to:', `/tasks/${this.formData.id}`);
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content')
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                if (response.ok) {
                    console.log('Task updated successfully!');
                    alert('Task updated successfully!');
                    window.location.href = '/tasks';
                } else {
                    const errorText = await response.text();
                    console.log('Update failed:', response.status, errorText);
                    alert('Failed to update task. Please try again.');
                }
            } catch (error) {
                console.error('Update error:', error);
                alert('An error occurred while updating the task.');
            } finally {
                this.isSubmitting = false;
            }
        },
        
        showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    }"
    <!-- Task Information Card -->
    <div class="dashboard-card p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                Task Information
            </h3>
            <div class="flex space-x-2">
                <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                    ID: <?php echo e($task->id ?? 'TASK-001'); ?>

                </span>
                <span class="px-3 py-1 bg-green-100 text-green-800 text-sm rounded-full">
                    <?php echo e($task->status ?? 'In Progress'); ?>

                </span>
            </div>
                    </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="flex items-center">
                <i class="fas fa-calendar-plus text-gray-400 mr-2"></i>
                <span class="text-gray-600">Created:</span>
                <span class="ml-2 font-medium"><?php echo e($task->created_at ?? date('Y-m-d H:i:s')); ?></span>
                </div>
            <div class="flex items-center">
                <i class="fas fa-clock text-gray-400 mr-2"></i>
                <span class="text-gray-600">Last Updated:</span>
                <span class="ml-2 font-medium"><?php echo e($task->updated_at ?? date('Y-m-d H:i:s')); ?></span>
            </div>
            <div class="flex items-center">
                <i class="fas fa-user text-gray-400 mr-2"></i>
                <span class="text-gray-600">Assignee:</span>
                <span class="ml-2 font-medium"><?php echo e($task->assignee ?? 'Mike Wilson'); ?></span>
            </div>
        </div>
            </div>
            
    <!-- Edit Form -->
    <div class="dashboard-card p-6">
                <form>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Task Title -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-heading text-gray-400 mr-1"></i>
                            Task Title
                        </label>
                        <input 
                            type="text" 
                            x-model="formData.name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            placeholder="Enter task title"
                            required
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
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-vertical text-gray-900 bg-white"
                            placeholder="Enter task description"
                        ></textarea>
                    </div>

                    <!-- Project Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-project-diagram text-gray-400 mr-1"></i>
                            Project
                        </label>
                        <select 
                            x-model="formData.project_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            required
                        >
                            <option value="">Select Project</option>
                            <?php
                                $projects = \Src\CoreProject\Models\Project::all();
                            ?>
                            <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($project->id); ?>"><?php echo e($project->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <!-- Assignee -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-gray-400 mr-1"></i>
                            Assignee
                        </label>
                        <select 
                            x-model="formData.assignee_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        >
                            <option value="">Select Assignee</option>
                            <?php
                                $users = \App\Models\User::all();
                            ?>
                            <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
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
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <!-- Start Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            Start Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.start_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        >
                    </div>

                    <!-- Due Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-gray-400 mr-1"></i>
                            Due Date
                        </label>
                        <input 
                            type="date" 
                            x-model="formData.end_date"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                        >
                    </div>

                    <!-- Progress -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-percentage text-gray-400 mr-1"></i>
                            Progress (%)
                        </label>
                        <div class="space-y-2">
                            <input 
                                type="range" 
                                x-model="formData.progress_percent"
                                min="0" 
                                max="100" 
                                class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                            >
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>0%</span>
                                <span class="font-medium" x-text="formData.progress_percent + '%'"></span>
                                <span>100%</span>
                            </div>
                        </div>
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
                            min="0"
                            step="0.5"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-gray-900 bg-white"
                            placeholder="Enter estimated hours"
                        >
                    </div>
                </div>
            </div>

            <!-- Tags Section -->
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-tags text-gray-400 mr-1"></i>
                    Tags
                </label>
                <div class="flex flex-wrap gap-2 mb-3">
                    <template x-for="tag in formData.tags" :key="tag">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full flex items-center">
                            <span x-text="tag"></span>
                            <button type="button" @click="removeTag(tag)" class="ml-2 text-blue-600 hover:text-blue-800">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </span>
                    </template>
                </div>
                <div class="flex space-x-2">
                    <input 
                        type="text" 
                        x-model="newTag"
                        @keydown.enter.prevent="addTag()"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-gray-900 bg-white"
                        placeholder="Add a tag and press Enter"
                    >
                    <button type="button" @click="addTag()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                <div class="flex space-x-3">
                    <button 
                        type="button" 
                        @click="saveDraft()"
                        class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-save mr-2"></i>
                        Save Draft
                    </button>
                    <button 
                        type="button" 
                        @click="previewTask()"
                        class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        Preview
                    </button>
                </div>
                
                <div class="flex space-x-3">
                    <a 
                        href="/tasks" 
                        class="px-6 py-3 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors flex items-center"
                    >
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </a>
                    <a 
                        :href="`/tasks/${formData.id}`"
                        class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center"
                    >
                        <i class="fas fa-eye mr-2"></i>
                        View Task
                    </a>
                    <button 
                        type="button" 
                        @click="alert('Alpine.js is working!')"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center mr-2"
                    >
                        <i class="fas fa-check mr-2"></i>
                        Test Alpine
                    </button>
                    <button 
                        type="button" 
                        @click="testUpdate()"
                        class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors flex items-center mr-2"
                    >
                        <i class="fas fa-bug mr-2"></i>
                        Test Update
                    </button>
                    <button 
                        type="button" 
                        @click="updateTask()"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center"
                        :disabled="isSubmitting"
                    >
                        <i class="fas fa-check mr-2" x-show="!isSubmitting"></i>
                        <i class="fas fa-spinner fa-spin mr-2" x-show="isSubmitting"></i>
                        <span x-text="isSubmitting ? 'Updating...' : 'Update Task'"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

    <script>
document.addEventListener('alpine:init', () => {
    Alpine.data('editTask', () => ({
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
            tags: <?php echo e(json_encode(array_filter(explode(',', $task->tags ?? '')))); ?>

        },

        init() {
            // Debug: Log task data
            console.log('=== ALPINE.JS INITIALIZATION ===');
            console.log('Alpine.js initialized!');
            console.log('Raw task data from server:');
            console.log('Task ID:', '<?php echo e($task->id ?? "NO_ID"); ?>');
            console.log('Task Name:', '<?php echo e($task->name ?? "NO_NAME"); ?>');
            console.log('Task Status:', '<?php echo e($task->status ?? "NO_STATUS"); ?>');
            console.log('Task Priority:', '<?php echo e($task->priority ?? "NO_PRIORITY"); ?>');
            console.log('Task Assignee ID:', '<?php echo e($task->assignee_id ?? "NO_ASSIGNEE"); ?>');
            console.log('Task Description:', '<?php echo e($task->description ?? "NO_DESCRIPTION"); ?>');
            console.log('Task Project ID:', '<?php echo e($task->project_id ?? "NO_PROJECT"); ?>');
            console.log('Task Start Date:', '<?php echo e($task->start_date ?? "NO_START_DATE"); ?>');
            console.log('Task End Date:', '<?php echo e($task->end_date ?? "NO_END_DATE"); ?>');
            console.log('Task Progress:', '<?php echo e($task->progress_percent ?? "NO_PROGRESS"); ?>');
            console.log('Task Estimated Hours:', '<?php echo e($task->estimated_hours ?? "NO_HOURS"); ?>');
            console.log('Task Tags:', '<?php echo e($task->tags ?? "NO_TAGS"); ?>');
            
            console.log('FormData after initialization:');
            console.log('formData.id:', this.formData.id);
            console.log('formData.name:', this.formData.name);
            console.log('formData.status:', this.formData.status);
            console.log('formData.priority:', this.formData.priority);
            console.log('formData.description:', this.formData.description);
            
            // Test Alpine.js functionality
            this.testAlpine = 'Alpine.js is working!';
            console.log('Alpine test:', this.testAlpine);
            
            // Load saved draft if exists
            const draft = localStorage.getItem('taskDraft');
            if (draft) {
                const draftData = JSON.parse(draft);
                if (confirm('A draft was found. Would you like to load it?')) {
                    this.formData = { ...this.formData, ...draftData };
                }
            }
            
            console.log('=== ALPINE.JS INITIALIZATION COMPLETED ===');
        },

        async testUpdate() {
            console.log('Testing update...');
            try {
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('name', 'Test Task');
                formData.append('description', 'Test Description');
                
                const response = await fetch('/test-task-update', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                const result = await response.json();
                console.log('Test response:', result);
                alert('Test successful! Check console for details.');
            } catch (error) {
                console.error('Test failed:', error);
                alert('Test failed: ' + error.message);
            }
        },

        addTag() {
            if (this.newTag.trim() && !this.formData.tags.includes(this.newTag.trim())) {
                this.formData.tags.push(this.newTag.trim());
                this.newTag = '';
            }
        },

        removeTag(tag) {
            this.formData.tags = this.formData.tags.filter(t => t !== tag);
        },

        async updateTask() {
            this.isSubmitting = true;
            
            try {
                console.log('Starting task update...');
                console.log('Task ID:', this.formData.id);
                console.log('Form data:', this.formData);
                console.log('Status value:', this.formData.status);
                console.log('Priority value:', this.formData.priority);
                // Prepare form data
                const formData = new FormData();
                formData.append('_method', 'PUT');
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                formData.append('name', this.formData.name);
                formData.append('description', this.formData.description);
                formData.append('project_id', this.formData.project_id);
                // Handle assignee_id properly - send empty string if not selected
                const assigneeId = this.formData.assignee_id && this.formData.assignee_id !== '' ? this.formData.assignee_id : '';
                formData.append('assignee_id', assigneeId);
                formData.append('status', this.formData.status);
                formData.append('priority', this.formData.priority);
                formData.append('start_date', this.formData.start_date);
                formData.append('end_date', this.formData.end_date);
                formData.append('progress_percent', this.formData.progress_percent);
                formData.append('estimated_hours', this.formData.estimated_hours);
                formData.append('tags', this.formData.tags.join(','));
                
                // Debug: Log form data being sent
                console.log('Form data being sent:');
                for (let [key, value] of formData.entries()) {
                    console.log(key + ':', value);
                }
                
                // Submit to server
                console.log('Submitting to:', `/tasks/${this.formData.id}`);
                const response = await fetch(`/tasks/${this.formData.id}`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                if (response.ok) {
                    this.showNotification('Task updated successfully!', 'success');
                    
                    // Redirect to tasks list
                    setTimeout(() => {
            window.location.href = '/tasks';
                    }, 1500);
                } else {
                    // Log response details for debugging
                    const responseText = await response.text();
                    console.error('Update failed:', response.status, responseText);
                    
                    // Try to parse as JSON for validation errors
                    try {
                        const errorData = JSON.parse(responseText);
                        if (errorData.errors) {
                            console.error('Validation errors:', errorData.errors);
                            this.showNotification('Validation errors: ' + JSON.stringify(errorData.errors), 'error');
                        } else {
                            this.showNotification('Failed to update task: ' + (errorData.message || 'Unknown error'), 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to update task. Please try again.', 'error');
                    }
                }
                
            } catch (error) {
                console.error('Update error:', error);
                this.showNotification('Failed to update task. Please try again.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        saveDraft() {
            // Save form data to localStorage as draft
            localStorage.setItem('taskDraft', JSON.stringify(this.formData));
            this.showNotification('Draft saved successfully!', 'info');
        },

        previewTask() {
            // Open task preview in new window
            window.open(`/tasks/${this.formData.id}`, '_blank');
        },

        showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg text-white shadow-lg transition-all duration-300 ${
                type === 'success' ? 'bg-green-600' : 
                type === 'error' ? 'bg-red-600' : 
                'bg-blue-600'
            }`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        },

    }));
        });
    </script>

<style>
.slider::-webkit-slider-thumb {
    appearance: none;
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.slider::-moz-range-thumb {
    height: 20px;
    width: 20px;
    border-radius: 50%;
    background: #3b82f6;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
</style>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.dashboard', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/tasks/edit.blade.php ENDPATH**/ ?>