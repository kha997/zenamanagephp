
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Team Status</h2>
    <div class="space-y-4">
        <template x-for="member in teamStatus" :key="'team-' + member.name">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div :class="member.status === 'online' ? 'bg-green-500' : member.status === 'away' ? 'bg-yellow-500' : 'bg-gray-500'" 
                         class="w-2 h-2 rounded-full mr-3"></div>
                    <div>
                        <span class="text-sm font-medium text-gray-900" x-text="member.name"></span>
                        <span class="text-xs text-gray-500 ml-2" x-text="member.role"></span>
                    </div>
                </div>
                <span :class="member.status === 'online' ? 'text-green-600' : member.status === 'away' ? 'text-yellow-600' : 'text-gray-600'" 
                      class="text-sm font-medium" x-text="member.status"></span>
            </div>
        </template>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/_team-status.blade.php ENDPATH**/ ?>