
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Signups Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">New Signups (30 days)</h3>
            <div class="flex items-center space-x-2">
                <select class="text-sm border border-gray-300 rounded-md px-3 py-1" 
                        x-model="signupsRange" @change="updateSignupsChart">
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                    <option value="365">Last year</option>
                </select>
                <button @click="exportChart('signups')" 
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>
        <div class="h-64">
            <canvas id="signupsChart"></canvas>
        </div>
    </div>
    
    <!-- Error Rate Chart -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900">Error Rate</h3>
            <div class="flex items-center space-x-2">
                <select class="text-sm border border-gray-300 rounded-md px-3 py-1" 
                        x-model="errorsRange" @change="updateErrorsChart">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
                <button @click="exportChart('errors')" 
                        class="px-3 py-1 bg-red-600 text-white text-sm rounded-md hover:bg-red-700 transition-colors">
                    <i class="fas fa-download mr-1"></i>Export
                </button>
            </div>
        </div>
        <div class="h-64">
            <canvas id="errorsChart"></canvas>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div x-show="showExportModal" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Export Data</h3>
                <button @click="showExportModal = false" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" x-model="exportFormat" value="csv" class="mr-2">
                            <span class="text-sm">CSV (Excel compatible)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" x-model="exportFormat" value="json" class="mr-2">
                            <span class="text-sm">JSON (Raw data)</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
                    <select x-model="exportRange" class="w-full border border-gray-300 rounded-md px-3 py-2">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="365">Last year</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-3 mt-6">
                <button @click="showExportModal = false" 
                        class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Cancel
                </button>
                <button @click="downloadExport" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-download mr-1"></i>Download
                </button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/admin/dashboard/_charts.blade.php ENDPATH**/ ?>