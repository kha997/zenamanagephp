
<div x-show="currentTaskId" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Focus Mode</h3>
        <button @click="exitFocusMode" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <div class="space-y-4">
        
        <div class="bg-blue-50 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium text-gray-900" x-text="currentTask.title">Task Title</h4>
                    <p class="text-sm text-gray-600" x-text="currentTask.description">Task description</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-500">Priority</p>
                    <span :class="currentTask.priority === 'high' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'" 
                          class="px-2 py-1 rounded-full text-xs font-medium" x-text="currentTask.priority">High</span>
                </div>
            </div>
        </div>
        
        
        <?php echo $__env->make('components.timer.mini', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        
        
        <div class="flex space-x-3">
            <button @click="startFocus" 
                    class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-play mr-2"></i>Start Focus
            </button>
            <button @click="pauseFocus" 
                    class="flex-1 bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition-colors">
                <i class="fas fa-pause mr-2"></i>Pause
            </button>
            <button @click="completeTask" 
                    class="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                <i class="fas fa-check mr-2"></i>Complete
            </button>
        </div>
        
        
        <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-gray-900" x-text="focusStats.today">2h 30m</p>
                <p class="text-sm text-gray-600">Today</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-gray-900" x-text="focusStats.week">12h 45m</p>
                <p class="text-sm text-gray-600">This Week</p>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <p class="text-2xl font-bold text-gray-900" x-text="focusStats.streak">7</p>
                <p class="text-sm text-gray-600">Day Streak</p>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/tasks/_focus-panel.blade.php ENDPATH**/ ?>