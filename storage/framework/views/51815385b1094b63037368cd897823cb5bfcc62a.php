
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-64">
            <input type="text" x-model="searchQuery" @input="filterUsers" 
                   placeholder="Search users..." 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        </div>
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Status:</label>
            <select x-model="statusFilter" @change="filterUsers" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="flex items-center space-x-2">
            <label class="text-sm font-medium text-gray-700">Role:</label>
            <select x-model="roleFilter" @change="filterUsers" 
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="all">All</option>
                <option value="Admin">Admin</option>
                <option value="Project Manager">Project Manager</option>
                <option value="Member">Member</option>
            </select>
        </div>
        <div x-show="selectedUsers.length > 0" class="flex items-center space-x-2">
            <span class="text-sm text-gray-600" x-text="selectedUsers.length + ' selected'"></span>
            <button @click="bulkAction('activate')" 
                    class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                Activate
            </button>
            <button @click="bulkAction('deactivate')" 
                    class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                Deactivate
            </button>
            <button @click="bulkAction('delete')" 
                    class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                Delete
            </button>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/users/_filters.blade.php ENDPATH**/ ?>