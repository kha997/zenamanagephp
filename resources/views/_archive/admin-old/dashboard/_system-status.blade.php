{{-- Admin Dashboard System Status --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">System Status</h2>
    <div class="space-y-4">
        <template x-for="status in systemStatus" :key="'admin-' + status.name">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div :class="status.status === 'online' ? 'bg-green-500' : 'bg-red-500'" 
                         class="w-2 h-2 rounded-full mr-3"></div>
                    <span class="text-sm font-medium text-gray-900" x-text="status.name"></span>
                </div>
                <span :class="status.status === 'online' ? 'text-green-600' : 'text-red-600'" 
                      class="text-sm font-medium" x-text="status.status"></span>
            </div>
        </template>
    </div>
</div>
