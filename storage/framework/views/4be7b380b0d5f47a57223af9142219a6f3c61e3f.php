
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Activity Feed</h2>
        <button @click="refreshActivity" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-sync-alt"></i>
        </button>
    </div>
    <div class="space-y-3">
        <template x-for="activity in activityFeed" :key="'feed-' + activity.id">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <img :src="activity.avatar" :alt="activity.user" 
                         class="w-6 h-6 rounded-full">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900">
                        <span class="font-medium" x-text="activity.user"></span>
                        <span x-text="activity.action"></span>
                    </p>
                    <p class="text-xs text-gray-500" x-text="activity.time"></p>
                </div>
            </div>
        </template>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/_activity-feed.blade.php ENDPATH**/ ?>