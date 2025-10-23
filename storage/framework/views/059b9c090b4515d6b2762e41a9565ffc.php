
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
            </div>
        </div>
    </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/app/dashboard/_alerts.blade.php ENDPATH**/ ?>