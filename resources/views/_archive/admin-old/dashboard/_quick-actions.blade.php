{{-- Admin Dashboard Quick Actions --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
    <div class="space-y-3">
        <button @click="openModal('addUser')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-user-plus mr-2"></i>
            Add User
        </button>
        <button @click="openModal('createTenant')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            <i class="fas fa-building mr-2"></i>
            Create Tenant
        </button>
        <button @click="openModal('backupSystem')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-download mr-2"></i>
            Backup System
        </button>
        <button @click="openModal('systemSettings')" 
                class="w-full flex items-center justify-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
            <i class="fas fa-cog mr-2"></i>
            System Settings
        </button>
    </div>
</div>
