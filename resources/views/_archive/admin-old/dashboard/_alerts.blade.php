{{-- Admin Dashboard Alerts --}}
<section x-show="alerts.length > 0" class="bg-yellow-50 border-b border-yellow-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                <span class="text-yellow-800 font-medium" x-text="alerts.length + ' alerts require attention'"></span>
            </div>
            <div class="flex items-center space-x-2">
                <button @click="dismissAllAlerts" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                    Dismiss All
                </button>
                <button @click="showAlerts = !showAlerts" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium">
                    <i :class="showAlerts ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                </button>
            </div>
        </div>
        <div x-show="showAlerts" class="mt-3 space-y-2">
            <template x-for="alert in alerts" :key="alert.id">
                <div class="bg-white rounded-lg p-3 border border-yellow-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i :class="alert.icon" class="text-yellow-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-900" x-text="alert.title"></span>
                        </div>
                        <button @click="dismissAlert(alert.id)" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 mt-1" x-text="alert.message"></p>
                </div>
            </template>
        </div>
    </div>
</section>
