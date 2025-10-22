
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
    <div class="space-y-3">
        <button @click="openModal('createProject')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus mr-2"></i>
            New Project
        </button>
        <button @click="openModal('createTask')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-tasks mr-2"></i>
            New Task
        </button>
        <button @click="openModal('inviteMember')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-user-plus mr-2"></i>
            Invite Member
        </button>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/_quick-actions.blade.php ENDPATH**/ ?>