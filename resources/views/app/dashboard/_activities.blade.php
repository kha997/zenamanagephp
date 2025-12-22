{{-- App Dashboard Activities --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-gray-900">Recent Activity</h2>
        <a href="/app/activities" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            View All
        </a>
    </div>
    <div class="space-y-4">
        <template x-for="activity in recentActivities" :key="'recent-' + activity.id">
            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                <div class="flex-shrink-0">
                    <div :class="activity.iconBg" class="w-8 h-8 rounded-full flex items-center justify-center">
                        <i :class="activity.icon" :class="activity.iconColor"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900" x-text="activity.title"></p>
                    <p class="text-sm text-gray-500" x-text="activity.description"></p>
                    <p class="text-xs text-gray-400 mt-1" x-text="activity.time"></p>
                </div>
            </div>
        </template>
    </div>
</div>
