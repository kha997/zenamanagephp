{{-- Alert Bar Component --}}
{{-- Up to 3 Critical/High alerts with actions --}}

<div x-show="alerts.length > 0" 
     x-transition
     class="alert-bar bg-red-50 border-b border-red-200 sticky top-40 z-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex items-center justify-between">
            <!-- Alert Content -->
            <div class="flex items-center space-x-3">
                <i class="fas fa-exclamation-triangle text-red-500"></i>
                <div class="flex-1">
                    <p class="text-sm font-medium text-red-800">
                        <span x-text="alerts.length"></span> 
                        <span x-text="alerts.length === 1 ? 'alert' : 'alerts'"></span> 
                        require attention
                    </p>
                </div>
            </div>
            
            <!-- Alert Actions -->
            <div class="flex items-center space-x-2">
                <!-- View All Button -->
                <button @click="showAllAlerts = !showAllAlerts" 
                        class="text-sm font-medium text-red-700 hover:text-red-900 transition-colors">
                    View All
                </button>
                
                <!-- Dismiss All Button -->
                <button @click="dismissAllAlerts" 
                        class="text-sm font-medium text-red-700 hover:text-red-900 transition-colors">
                    Dismiss All
                </button>
            </div>
        </div>
        
        <!-- Alert Details (Collapsible) -->
        <div x-show="showAllAlerts" 
             x-transition
             class="mt-3 space-y-2">
            <template x-for="alert in alerts.slice(0, 3)" :key="alert.id">
                <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
                    <div class="flex items-center space-x-3">
                        <!-- Alert Icon -->
                        <div class="flex-shrink-0">
                            <i :class="alert.level === 'critical' ? 'fas fa-exclamation-circle text-red-500' : 'fas fa-exclamation-triangle text-orange-500'"></i>
                        </div>
                        
                        <!-- Alert Content -->
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900" x-text="alert.message"></p>
                            <p class="text-xs text-gray-500" x-text="alert.time"></p>
                        </div>
                    </div>
                    
                    <!-- Alert Actions -->
                    <div class="flex items-center space-x-2">
                        <button @click="resolveAlert(alert.id)" 
                                class="px-3 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                            Resolve
                        </button>
                        <button @click="acknowledgeAlert(alert.id)" 
                                class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                            Acknowledge
                        </button>
                        <button @click="muteAlert(alert.id)" 
                                class="px-3 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                            Mute
                        </button>
                    </div>
                </div>
            </template>
            
            <!-- Show More Alerts -->
            <div x-show="alerts.length > 3" class="text-center">
                <button @click="showMoreAlerts = !showMoreAlerts" 
                        class="text-sm text-red-700 hover:text-red-900 font-medium">
                    <span x-text="showMoreAlerts ? 'Show Less' : `Show ${alerts.length - 3} More`"></span>
                    <i class="fas fa-chevron-down ml-1" :class="showMoreAlerts ? 'rotate-180' : ''"></i>
                </button>
            </div>
            
            <!-- Additional Alerts -->
            <div x-show="showMoreAlerts" 
                 x-transition
                 class="space-y-2">
                <template x-for="alert in alerts.slice(3)" :key="alert.id">
                    <div class="flex items-center justify-between p-3 bg-white rounded-lg border border-red-200">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <i :class="alert.level === 'critical' ? 'fas fa-exclamation-circle text-red-500' : 'fas fa-exclamation-triangle text-orange-500'"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900" x-text="alert.message"></p>
                                <p class="text-xs text-gray-500" x-text="alert.time"></p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button @click="resolveAlert(alert.id)" 
                                    class="px-3 py-1 text-xs font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                Resolve
                            </button>
                            <button @click="acknowledgeAlert(alert.id)" 
                                    class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                Ack
                            </button>
                            <button @click="muteAlert(alert.id)" 
                                    class="px-3 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                                Mute
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
    // Add to universalFrame Alpine.js data
    document.addEventListener('alpine:init', () => {
        Alpine.data('universalFrame', () => ({
            // ... existing code ...
            
            // Alert Management
            showAllAlerts: false,
            showMoreAlerts: false,
            
            // Alert Actions
            dismissAllAlerts() {
                this.alerts = [];
            },
            
            // ... rest of existing code ...
        }));
    });
</script>
