
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
    <div class="text-center">
        
        <div class="mb-4">
            <div class="text-4xl font-bold text-gray-900 mb-2" x-text="timerDisplay">25:00</div>
            <div class="text-sm text-gray-600" x-text="timerMode">Focus Session</div>
        </div>
        
        
        <div class="relative w-24 h-24 mx-auto mb-4">
            <svg class="w-24 h-24 transform -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" stroke="#e5e7eb" stroke-width="8" fill="none"></circle>
                <circle cx="50" cy="50" r="45" stroke="#3b82f6" stroke-width="8" fill="none"
                        stroke-dasharray="283" 
                        :stroke-dashoffset="timerProgress"
                        stroke-linecap="round"></circle>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <button @click="toggleTimer" 
                        :class="isRunning ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                        class="w-12 h-12 rounded-full text-white flex items-center justify-center transition-colors">
                    <i :class="isRunning ? 'fas fa-pause' : 'fas fa-play'"></i>
                </button>
            </div>
        </div>
        
        
        <div class="flex justify-center space-x-2">
            <button @click="setTimer(25)" 
                    :class="timerDuration === 25 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                25m
            </button>
            <button @click="setTimer(15)" 
                    :class="timerDuration === 15 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                15m
            </button>
            <button @click="setTimer(5)" 
                    :class="timerDuration === 5 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                    class="px-3 py-1 rounded-full text-sm font-medium transition-colors">
                5m
            </button>
        </div>
        
        
        <div class="mt-4 text-sm text-gray-600">
            <p>Session <span x-text="sessionCount">1</span> of <span x-text="totalSessions">4</span></p>
            <p x-text="nextBreak">Next break in 25 minutes</p>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/timer/mini.blade.php ENDPATH**/ ?>