


<div class="activity-panel bg-white border-t border-gray-200 sticky bottom-0 z-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Activity Header -->
        <div class="flex items-center justify-between py-3">
            <button @click="toggleActivity()" 
                    class="flex items-center space-x-2 text-sm font-medium text-gray-700 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-history"></i>
                <span>Recent Activity</span>
                <i class="fas fa-chevron-up text-xs transition-transform" 
                   :class="activityCollapsed ? 'rotate-180' : ''"></i>
            </button>
            
            <div class="flex items-center space-x-2">
                <!-- Activity Count -->
                <span class="text-xs text-gray-500" x-text="activities.length + ' items'"></span>
                
                <!-- Audit Link -->
                <a href="/admin/activities" 
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    View All
                </a>
            </div>
        </div>
        
        <!-- Activity Content (Collapsible) -->
        <div x-show="!activityCollapsed" 
             x-transition
             class="border-t border-gray-200 py-3">
            <div class="space-y-3 max-h-64 overflow-y-auto">
                <template x-for="activity in activities.slice(0, 10)" :key="activity.id">
                    <div class="flex items-start space-x-3 p-2 hover:bg-gray-50 rounded-lg transition-colors">
                        <!-- Activity Icon -->
                        <div class="flex-shrink-0 mt-1">
                            <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                <i :class="activity.icon" class="text-blue-600 text-xs"></i>
                            </div>
                        </div>
                        
                        <!-- Activity Content -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-900" x-text="activity.description"></p>
                            <div class="flex items-center space-x-2 mt-1">
                                <span class="text-xs text-gray-500" x-text="activity.user"></span>
                                <span class="text-xs text-gray-400">•</span>
                                <span class="text-xs text-gray-500" x-text="activity.time"></span>
                            </div>
                        </div>
                        
                        <!-- Activity Actions -->
                        <div class="flex-shrink-0">
                            <button @click="viewActivityDetails(activity.id)" 
                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                View
                            </button>
                        </div>
                    </div>
                </template>
                
                <!-- Empty State -->
                <div x-show="activities.length === 0" class="text-center py-8">
                    <i class="fas fa-history text-gray-300 text-2xl mb-2"></i>
                    <p class="text-sm text-gray-500">No recent activity</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Add to universalFrame Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('universalFrame', () => ({
            // ... existing code ...
            
            // Activity Management
            viewActivityDetails(activityId) {
                // Navigate to activity details or open modal
                console.log('Viewing activity:', activityId);
            },
            
            // ... rest of existing code ...
        }));
    });
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/feedback/activity-panel.blade.php ENDPATH**/ ?>