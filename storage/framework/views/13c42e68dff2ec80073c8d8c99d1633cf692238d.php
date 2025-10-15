


<div class="export-component" x-data="exportComponent()">
    <!-- Export Button -->
    <button @click="showExportModal = true" 
            class="flex items-center space-x-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500">
        <i class="fas fa-download"></i>
        <span>Export</span>
    </button>
    
    <!-- Export Modal -->
    <div x-show="showExportModal" 
         x-transition
         class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div class="p-6">
                <!-- Modal Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Export Data</h3>
                    <button @click="showExportModal = false" 
                            class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Export Options -->
                <div class="space-y-4">
                    <!-- Data Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data Type</label>
                        <select x-model="exportType" 
                                @change="updateExportOptions()"
                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="projects">Projects</option>
                            <option value="tasks">Tasks</option>
                            <option value="documents">Documents</option>
                            <option value="users" x-show="isAdmin">Users</option>
                            <option value="tenants" x-show="isAdmin">Tenants</option>
                        </select>
                    </div>
                    
                    <!-- Format Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Export Format</label>
                        <div class="grid grid-cols-3 gap-3">
                            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50"
                                   :class="exportFormat === 'csv' ? 'border-blue-500 bg-blue-50' : ''">
                                <input type="radio" 
                                       x-model="exportFormat" 
                                       value="csv" 
                                       class="sr-only">
                                <div class="text-center">
                                    <i class="fas fa-file-csv text-2xl text-blue-600 mb-1"></i>
                                    <p class="text-xs font-medium text-gray-900">CSV</p>
                                    <p class="text-xs text-gray-500">Spreadsheet</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50"
                                   :class="exportFormat === 'excel' ? 'border-blue-500 bg-blue-50' : ''">
                                <input type="radio" 
                                       x-model="exportFormat" 
                                       value="excel" 
                                       class="sr-only">
                                <div class="text-center">
                                    <i class="fas fa-file-excel text-2xl text-green-600 mb-1"></i>
                                    <p class="text-xs font-medium text-gray-900">Excel</p>
                                    <p class="text-xs text-gray-500">Workbook</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50"
                                   :class="exportFormat === 'pdf' ? 'border-blue-500 bg-blue-50' : ''">
                                <input type="radio" 
                                       x-model="exportFormat" 
                                       value="pdf" 
                                       class="sr-only">
                                <div class="text-center">
                                    <i class="fas fa-file-pdf text-2xl text-red-600 mb-1"></i>
                                    <p class="text-xs font-medium text-gray-900">PDF</p>
                                    <p class="text-xs text-gray-500">Report</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Filter Options -->
                    <div x-show="hasFilters">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Include Filters</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       x-model="includeFilters" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Apply current filters to export</span>
                            </label>
                            <div x-show="includeFilters" class="ml-6 text-xs text-gray-500">
                                <p>Current filters will be applied to the exported data</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column Selection -->
                    <div x-show="availableColumns.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Columns to Export</label>
                        <div class="max-h-32 overflow-y-auto border border-gray-300 rounded-lg p-2">
                            <template x-for="(column, index) in availableColumns" :key="'export-' + (column.key || index)">
                                <label class="flex items-center py-1">
                                    <input type="checkbox" 
                                           x-model="selectedColumns" 
                                           :value="column.key"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2 text-sm text-gray-700" x-text="column.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    
                    <!-- Export History -->
                    <div x-show="exportHistory.length > 0">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Recent Exports</label>
                        <div class="max-h-24 overflow-y-auto border border-gray-300 rounded-lg">
                            <template x-for="export in exportHistory.slice(0, 3)" :key="export.filename">
                                <div class="flex items-center justify-between p-2 hover:bg-gray-50">
                                    <div class="flex items-center space-x-2">
                                        <i :class="getFileIcon(export.filename)" class="text-gray-400"></i>
                                        <div>
                                            <p class="text-xs font-medium text-gray-900" x-text="export.filename"></p>
                                            <p class="text-xs text-gray-500" x-text="formatDate(export.created_at)"></p>
                                        </div>
                                    </div>
                                    <a :href="export.download_url" 
                                       class="text-xs text-blue-600 hover:text-blue-800">
                                        Download
                                    </a>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                
                <!-- Modal Actions -->
                <div class="flex items-center justify-end space-x-3 mt-6">
                    <button @click="showExportModal = false" 
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button @click="performExport()" 
                            :disabled="exporting"
                            class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                        <span x-show="!exporting">Export</span>
                        <span x-show="exporting">Exporting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('exportComponent', () => ({
            // State
            showExportModal: false,
            exportType: 'projects',
            exportFormat: 'csv',
            includeFilters: true,
            selectedColumns: [],
            availableColumns: [],
            exportHistory: [],
            exporting: false,
            isAdmin: false,
            hasFilters: false,
            
            // Initialize
            async init() {
                this.isAdmin = await this.checkAdminRole();
                await this.loadExportHistory();
                this.updateExportOptions();
            },
            
            // Check Admin Role
            async checkAdminRole() {
                try {
                    const response = await fetch('/api/universal-frame/user/role');
                    const data = await response.json();
                    return data.isAdmin || false;
                } catch (error) {
                    return false;
                }
            },
            
            // Load Export History
            async loadExportHistory() {
                try {
                    const response = await fetch('/api/universal-frame/export/history');
                    const data = await response.json();
                    
                    if (data.success) {
                        this.exportHistory = data.data;
                    }
                } catch (error) {
                    console.error('Load export history error:', error);
                }
            },
            
            // Update Export Options
            updateExportOptions() {
                const columnMap = {
                    projects: [
                        { key: 'name', label: 'Project Name' },
                        { key: 'code', label: 'Project Code' },
                        { key: 'status', label: 'Status' },
                        { key: 'health', label: 'Health' },
                        { key: 'progress', label: 'Progress (%)' },
                        { key: 'budget', label: 'Budget' },
                        { key: 'start_date', label: 'Start Date' },
                        { key: 'due_date', label: 'Due Date' },
                        { key: 'project_manager', label: 'Project Manager' },
                        { key: 'team_size', label: 'Team Size' }
                    ],
                    tasks: [
                        { key: 'title', label: 'Task Title' },
                        { key: 'project', label: 'Project' },
                        { key: 'status', label: 'Status' },
                        { key: 'priority', label: 'Priority' },
                        { key: 'assigned_to', label: 'Assigned To' },
                        { key: 'due_date', label: 'Due Date' },
                        { key: 'estimated_hours', label: 'Estimated Hours' },
                        { key: 'actual_hours', label: 'Actual Hours' },
                        { key: 'progress', label: 'Progress (%)' },
                        { key: 'created_at', label: 'Created Date' }
                    ],
                    documents: [
                        { key: 'title', label: 'Document Title' },
                        { key: 'filename', label: 'Filename' },
                        { key: 'type', label: 'Type' },
                        { key: 'size', label: 'Size' },
                        { key: 'status', label: 'Status' },
                        { key: 'project', label: 'Project' },
                        { key: 'uploaded_by', label: 'Uploaded By' },
                        { key: 'uploaded_at', label: 'Upload Date' },
                        { key: 'version', label: 'Version' },
                        { key: 'description', label: 'Description' }
                    ],
                    users: [
                        { key: 'name', label: 'Name' },
                        { key: 'email', label: 'Email' },
                        { key: 'role', label: 'Role' },
                        { key: 'status', label: 'Status' },
                        { key: 'tenant', label: 'Tenant' },
                        { key: 'last_login', label: 'Last Login' },
                        { key: 'created_at', label: 'Created Date' },
                        { key: 'phone', label: 'Phone' },
                        { key: 'department', label: 'Department' }
                    ],
                    tenants: [
                        { key: 'name', label: 'Tenant Name' },
                        { key: 'domain', label: 'Domain' },
                        { key: 'plan', label: 'Plan' },
                        { key: 'status', label: 'Status' },
                        { key: 'users_count', label: 'Users Count' },
                        { key: 'projects_count', label: 'Projects Count' },
                        { key: 'storage_used', label: 'Storage Used' },
                        { key: 'created_at', label: 'Created Date' },
                        { key: 'last_activity', label: 'Last Activity' }
                    ]
                };
                
                this.availableColumns = columnMap[this.exportType] || [];
                this.selectedColumns = this.availableColumns.map(col => col.key);
            },
            
            // Perform Export
            async performExport() {
                this.exporting = true;
                
                try {
                    const response = await fetch('/api/universal-frame/export', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            type: this.exportType,
                            format: this.exportFormat,
                            includeFilters: this.includeFilters,
                            columns: this.selectedColumns
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Download file
                        window.open(data.download_url, '_blank');
                        
                        // Close modal
                        this.showExportModal = false;
                        
                        // Reload export history
                        await this.loadExportHistory();
                    } else {
                        console.error('Export failed:', data.error);
                        alert('Export failed: ' + data.error.message);
                    }
                } catch (error) {
                    console.error('Export error:', error);
                    alert('Export failed. Please try again.');
                } finally {
                    this.exporting = false;
                }
            },
            
            // Utility Functions
            getFileIcon(filename) {
                const extension = filename.split('.').pop().toLowerCase();
                const icons = {
                    'csv': 'fas fa-file-csv text-blue-600',
                    'xlsx': 'fas fa-file-excel text-green-600',
                    'pdf': 'fas fa-file-pdf text-red-600'
                };
                return icons[extension] || 'fas fa-file text-gray-600';
            },
            
            formatDate(timestamp) {
                const date = new Date(timestamp);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            }
        }));
    });
</script>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/components/shared/export.blade.php ENDPATH**/ ?>