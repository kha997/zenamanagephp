{{-- Mobile FAB Component --}}
<div class="mobile-fab-container">
    <!-- FAB Button -->
    <button 
        @click="toggleFabMenu()"
        class="fixed bottom-6 right-6 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all duration-300 z-50"
        :class="{ 'rotate-45': fabMenuOpen }"
        aria-label="Quick Actions"
    >
        <i class="fas" :class="fabMenuOpen ? 'fa-times' : 'fa-plus'" class="text-xl"></i>
    </button>
    
    <!-- FAB Menu Items -->
    <div 
        x-show="fabMenuOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed bottom-20 right-6 space-y-3 z-40"
    >
        <!-- Quick Add Project -->
        <button 
            @click="quickAdd('project')"
            class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors"
        >
            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-project-diagram text-blue-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Project</span>
        </button>
        
        <!-- Quick Add Task -->
        <button 
            @click="quickAdd('task')"
            class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors"
        >
            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-tasks text-green-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Task</span>
        </button>
        
        <!-- Quick Add Document -->
        <button 
            @click="quickAdd('document')"
            class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors"
        >
            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fas fa-file-alt text-purple-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">New Document</span>
        </button>
        
        <!-- Quick Add User -->
        <button 
            @click="quickAdd('user')"
            class="flex items-center space-x-3 bg-white text-gray-700 px-4 py-3 rounded-lg shadow-lg hover:bg-gray-50 transition-colors"
        >
            <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fas fa-user-plus text-orange-600 text-sm"></i>
            </div>
            <span class="text-sm font-medium">Add User</span>
        </button>
    </div>
    
    <!-- Overlay -->
    <div 
        x-show="fabMenuOpen"
        @click="fabMenuOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-25 z-30"
    ></div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('mobileFab', () => ({
        fabMenuOpen: false,
        
        toggleFabMenu() {
            this.fabMenuOpen = !this.fabMenuOpen;
        },
        
        quickAdd(type) {
            this.fabMenuOpen = false;
            
            // Simulate quick add functionality
            const messages = {
                project: 'Quick Add Project - This would open a project creation form',
                task: 'Quick Add Task - This would open a task creation form',
                document: 'Quick Add Document - This would open a document upload form',
                user: 'Quick Add User - This would open a user invitation form'
            };
            
            alert(messages[type] || 'Quick add action triggered');
        }
    }));
});
</script>