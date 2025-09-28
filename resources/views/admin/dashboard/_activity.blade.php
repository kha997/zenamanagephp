{{-- Admin Dashboard Activity --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Recent Activity</h3>
        <a href="/admin/activity" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
            View All
        </a>
    </div>
    
    <div class="space-y-4">
        <template x-for="item in activity" :key="item.id">
            <div class="flex items-start space-x-3 p-3 hover:bg-gray-50 rounded-lg">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                        <i :class="getSeverityIcon(item.severity)" :class="getSeverityColor(item.severity)"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900" x-text="item.message"></p>
                    <p class="text-xs text-gray-500" x-text="formatTimeAgo(item.ts)"></p>
                </div>
            </div>
        </template>
    </div>
    
    <!-- Empty State -->
    <div x-show="activity.length === 0" class="text-center py-8">
        <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
        <p class="text-gray-500">No recent activity</p>
        <a href="/admin/settings" class="text-blue-600 hover:text-blue-800 text-sm font-medium mt-2 inline-block">
            View Settings / Integrations
        </a>
    </div>
</div>
