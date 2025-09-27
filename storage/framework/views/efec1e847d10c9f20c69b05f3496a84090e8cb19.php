<?php $__env->startSection('title', 'Tasks'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <?php echo $__env->make('app.tasks._focus-panel', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Tasks</h1>
            <p class="text-gray-600">Manage your tasks and stay focused</p>
        </div>
        <div class="flex space-x-3">
            <button @click="openModal('createTask')" 
                    class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>New Task
            </button>
            <button @click="toggleView" 
                    :class="viewMode === 'list' ? 'bg-gray-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-4 py-2 rounded-lg transition-colors">
                <i :class="viewMode === 'list' ? 'fas fa-list' : 'fas fa-th'"></i>
            </button>
        </div>
    </div>
    
    
    <?php echo $__env->make('app.tasks._filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    
    
    <div :class="viewMode === 'list' ? 'space-y-4' : 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'">
        <template x-for="task in filteredTasks" :key="task.id">
            <div :class="viewMode === 'list' ? 'bg-white rounded-lg shadow-sm border border-gray-200 p-6' : 'bg-white rounded-lg shadow-sm border border-gray-200 p-4'"
                 @click="selectTask(task)"
                 :class="currentTaskId === task.id ? 'ring-2 ring-blue-500' : 'hover:shadow-md'"
                 class="cursor-pointer transition-all">
                
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900" x-text="task.title"></h3>
                        <p class="text-sm text-gray-600 mt-1" x-text="task.description"></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span :class="task.priority === 'high' ? 'bg-red-100 text-red-800' : task.priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'" 
                              class="px-2 py-1 rounded-full text-xs font-medium" x-text="task.priority"></span>
                        <span :class="task.status === 'completed' ? 'bg-green-100 text-green-800' : task.status === 'in-progress' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'" 
                              class="px-2 py-1 rounded-full text-xs font-medium" x-text="task.status"></span>
                    </div>
                </div>
                
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center space-x-4">
                        <span><i class="fas fa-calendar mr-1"></i><span x-text="task.dueDate"></span></span>
                        <span><i class="fas fa-user mr-1"></i><span x-text="task.assignee"></span></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button @click.stop="startFocus(task)" class="text-blue-600 hover:text-blue-800">
                            <i class="fas fa-play"></i>
                        </button>
                        <button @click.stop="editTask(task)" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button @click.stop="deleteTask(task)" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    
    
    <div x-show="filteredTasks.length === 0" class="text-center py-12">
        <i class="fas fa-tasks text-gray-400 text-6xl mb-4"></i>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No tasks found</h3>
        <p class="text-gray-600 mb-4">Create your first task to get started</p>
        <button @click="openModal('createTask')" 
                class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>Create Task
        </button>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function tasksPage() {
        return {
            viewMode: 'list',
            currentTaskId: null,
            currentTask: {},
            filterStatus: 'all',
            filterPriority: 'all',
            filterAssignee: 'all',
            searchQuery: '',
            
            tasks: [
                {
                    id: 1,
                    title: 'Design Homepage Mockup',
                    description: 'Create wireframes and mockups for the new homepage design',
                    priority: 'high',
                    status: 'in-progress',
                    dueDate: '2024-10-01',
                    assignee: 'Jane Smith',
                    project: 'Website Redesign'
                },
                {
                    id: 2,
                    title: 'Update Documentation',
                    description: 'Update API documentation with new endpoints',
                    priority: 'medium',
                    status: 'pending',
                    dueDate: '2024-10-05',
                    assignee: 'John Doe',
                    project: 'API Development'
                },
                {
                    id: 3,
                    title: 'Fix Bug #123',
                    description: 'Fix login issue on mobile devices',
                    priority: 'high',
                    status: 'completed',
                    dueDate: '2024-09-25',
                    assignee: 'Mike Johnson',
                    project: 'Mobile App'
                },
                {
                    id: 4,
                    title: 'Code Review',
                    description: 'Review pull request #456 for new feature',
                    priority: 'low',
                    status: 'pending',
                    dueDate: '2024-10-03',
                    assignee: 'Sarah Wilson',
                    project: 'Feature Development'
                }
            ],
            
            focusStats: {
                today: '2h 30m',
                week: '12h 45m',
                streak: 7
            },
            
            timerDisplay: '25:00',
            timerMode: 'Focus Session',
            timerProgress: 0,
            isRunning: false,
            timerDuration: 25,
            sessionCount: 1,
            totalSessions: 4,
            nextBreak: 'Next break in 25 minutes',
            
            get filteredTasks() {
                let filtered = this.tasks;
                
                if (this.filterStatus !== 'all') {
                    filtered = filtered.filter(task => task.status === this.filterStatus);
                }
                
                if (this.filterPriority !== 'all') {
                    filtered = filtered.filter(task => task.priority === this.filterPriority);
                }
                
                if (this.filterAssignee !== 'all') {
                    filtered = filtered.filter(task => task.assignee === this.filterAssignee);
                }
                
                if (this.searchQuery) {
                    filtered = filtered.filter(task => 
                        task.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                        task.description.toLowerCase().includes(this.searchQuery.toLowerCase())
                    );
                }
                
                return filtered;
            },
            
            init() {
                // Initialize tasks page
                console.log('Tasks page initialized');
            },
            
            selectTask(task) {
                this.currentTaskId = task.id;
                this.currentTask = task;
            },
            
            startFocus(task) {
                this.currentTaskId = task.id;
                this.currentTask = task;
                // Start focus mode
                console.log('Starting focus mode for task:', task.title);
            },
            
            exitFocusMode() {
                this.currentTaskId = null;
                this.currentTask = {};
            },
            
            toggleView() {
                this.viewMode = this.viewMode === 'list' ? 'grid' : 'list';
            },
            
            editTask(task) {
                console.log('Editing task:', task.title);
            },
            
            deleteTask(task) {
                if (confirm('Are you sure you want to delete this task?')) {
                    this.tasks = this.tasks.filter(t => t.id !== task.id);
                }
            },
            
            openModal(type) {
                console.log('Opening modal:', type);
            },
            
            toggleTimer() {
                this.isRunning = !this.isRunning;
                if (this.isRunning) {
                    this.startTimer();
                } else {
                    this.pauseTimer();
                }
            },
            
            setTimer(minutes) {
                this.timerDuration = minutes;
                this.timerDisplay = `${minutes}:00`;
                this.timerProgress = 0;
            },
            
            startTimer() {
                // Timer logic would be implemented here
                console.log('Timer started');
            },
            
            pauseTimer() {
                console.log('Timer paused');
            },
            
            completeTask() {
                if (this.currentTaskId) {
                    const task = this.tasks.find(t => t.id === this.currentTaskId);
                    if (task) {
                        task.status = 'completed';
                        this.exitFocusMode();
                    }
                }
            }
        }
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/tasks/index.blade.php ENDPATH**/ ?>