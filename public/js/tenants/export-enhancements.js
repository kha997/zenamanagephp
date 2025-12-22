/**
 * Enhanced Export Module for Tenants Page
 */
class ExportEnhancements {
    constructor() {
        this.exportHistory = [];
        this.scheduledExports = [];
        this.exportTemplates = [];
        this.init();
    }

    init() {
        this.loadExportHistory();
        this.loadScheduledExports();
        this.loadExportTemplates();
        this.createExportModal();
        this.createScheduledExportModal();
        this.attachEventListeners();
    }

    /**
     * Create enhanced export modal
     */
    createExportModal() {
        const modal = document.createElement('div');
        modal.id = 'enhanced-export-modal';
        modal.className = 'modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white max-h-screen overflow-y-auto">
                <div class="modal-header flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Enhanced Export</h2>
                    <button id="close-enhanced-export" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="export-content space-y-6">
                    <!-- Export Type Selection -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Export Type</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <label class="export-type-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_type" value="current" checked class="mr-2">
                                <div class="text-sm font-medium">Current View</div>
                                <div class="text-xs text-gray-500">Export filtered results</div>
                            </label>
                            <label class="export-type-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_type" value="selected" class="mr-2">
                                <div class="text-sm font-medium">Selected Items</div>
                                <div class="text-xs text-gray-500">Export selected tenants</div>
                            </label>
                            <label class="export-type-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_type" value="all" class="mr-2">
                                <div class="text-sm font-medium">All Tenants</div>
                                <div class="text-xs text-gray-500">Export all tenants</div>
                            </label>
                            <label class="export-type-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_type" value="template" class="mr-2">
                                <div class="text-sm font-medium">Template</div>
                                <div class="text-xs text-gray-500">Use saved template</div>
                            </label>
                        </div>
                    </div>

                    <!-- Format Selection -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Export Format</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <label class="format-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_format" value="csv" checked class="mr-2">
                                <div class="text-sm font-medium">CSV</div>
                                <div class="text-xs text-gray-500">Comma separated</div>
                            </label>
                            <label class="format-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_format" value="excel" class="mr-2">
                                <div class="text-sm font-medium">Excel</div>
                                <div class="text-xs text-gray-500">.xlsx format</div>
                            </label>
                            <label class="format-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_format" value="pdf" class="mr-2">
                                <div class="text-sm font-medium">PDF</div>
                                <div class="text-xs text-gray-500">Portable document</div>
                            </label>
                            <label class="format-option cursor-pointer p-3 border rounded-lg hover:bg-gray-50">
                                <input type="radio" name="export_format" value="json" class="mr-2">
                                <div class="text-sm font-medium">JSON</div>
                                <div class="text-xs text-gray-500">Structured data</div>
                            </label>
                        </div>
                    </div>

                    <!-- Column Selection -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Columns to Export</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="id" checked class="mr-2">
                                <span class="text-sm">ID</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="name" checked class="mr-2">
                                <span class="text-sm">Name</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="domain" checked class="mr-2">
                                <span class="text-sm">Domain</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="status" checked class="mr-2">
                                <span class="text-sm">Status</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="plan" checked class="mr-2">
                                <span class="text-sm">Plan</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="users_count" checked class="mr-2">
                                <span class="text-sm">Users</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="projects_count" class="mr-2">
                                <span class="text-sm">Projects</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="storage_used" class="mr-2">
                                <span class="text-sm">Storage</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="region" class="mr-2">
                                <span class="text-sm">Region</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="created_at" checked class="mr-2">
                                <span class="text-sm">Created</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="updated_at" class="mr-2">
                                <span class="text-sm">Updated</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="columns" value="trial_ends_at" class="mr-2">
                                <span class="text-sm">Trial Ends</span>
                            </label>
                        </div>
                        <div class="mt-2 flex space-x-2">
                            <button type="button" id="select-all-columns" class="text-sm text-blue-600 hover:text-blue-800">Select All</button>
                            <button type="button" id="deselect-all-columns" class="text-sm text-blue-600 hover:text-blue-800">Deselect All</button>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Export Options</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="include_headers" checked class="mr-2">
                                <span class="text-sm">Include column headers</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="include_metadata" class="mr-2">
                                <span class="text-sm">Include export metadata</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="compress_file" class="mr-2">
                                <span class="text-sm">Compress file (ZIP)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="email_delivery" class="mr-2">
                                <span class="text-sm">Email delivery</span>
                            </label>
                        </div>
                    </div>

                    <!-- Email Configuration -->
                    <div id="email-config" class="export-section hidden">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Email Configuration</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                <input type="email" name="email_address" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter email address">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                                <input type="text" name="email_subject" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Export subject">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                <textarea name="email_message" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Optional message"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduled Export -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Scheduling</h3>
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="schedule_export" class="mr-2">
                                <span class="text-sm">Schedule recurring export</span>
                            </label>
                            <div id="schedule-config" class="hidden space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
                                        <select name="schedule_frequency" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="daily">Daily</option>
                                            <option value="weekly">Weekly</option>
                                            <option value="monthly">Monthly</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                                        <input type="time" name="schedule_time" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Name</label>
                                    <input type="text" name="schedule_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter schedule name">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Templates -->
                    <div class="export-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Export Templates</h3>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Save current settings as template</span>
                                <button type="button" id="save-template" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                    Save Template
                                </button>
                            </div>
                            <div id="template-list" class="space-y-1">
                                <!-- Templates will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                    <div class="text-sm text-gray-500">
                        <span id="export-preview">Ready to export</span>
                    </div>
                    <div class="flex space-x-3">
                        <button id="cancel-enhanced-export" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button id="export-now" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i class="fas fa-download mr-2"></i>Export Now
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    /**
     * Create scheduled export modal
     */
    createScheduledExportModal() {
        const modal = document.createElement('div');
        modal.id = 'scheduled-export-modal';
        modal.className = 'modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 shadow-lg rounded-md bg-white">
                <div class="modal-header flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Scheduled Exports</h2>
                    <button id="close-scheduled-export" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="scheduled-exports-content">
                    <div id="scheduled-exports-list" class="space-y-3">
                        <!-- Scheduled exports will be loaded here -->
                    </div>
                </div>

                <div class="modal-footer flex items-center justify-end mt-6 pt-4 border-t border-gray-200">
                    <button id="close-scheduled-export-btn" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Close
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Export modal events
        document.addEventListener('click', (e) => {
            if (e.target.id === 'enhanced-export-btn' || e.target.closest('#enhanced-export-btn')) {
                this.openExportModal();
            }
            if (e.target.id === 'scheduled-exports-btn' || e.target.closest('#scheduled-exports-btn')) {
                this.openScheduledExportModal();
            }
            if (e.target.id === 'close-enhanced-export' || e.target.id === 'cancel-enhanced-export') {
                this.closeExportModal();
            }
            if (e.target.id === 'export-now') {
                this.startExport();
            }
            if (e.target.id === 'select-all-columns') {
                this.selectAllColumns();
            }
            if (e.target.id === 'deselect-all-columns') {
                this.deselectAllColumns();
            }
            if (e.target.id === 'save-template') {
                this.saveTemplate();
            }
        });

        // Email delivery toggle
        document.addEventListener('change', (e) => {
            if (e.target.name === 'email_delivery') {
                const emailConfig = document.getElementById('email-config');
                if (emailConfig) {
                    emailConfig.classList.toggle('hidden', !e.target.checked);
                }
            }
            if (e.target.name === 'schedule_export') {
                const scheduleConfig = document.getElementById('schedule-config');
                if (scheduleConfig) {
                    scheduleConfig.classList.toggle('hidden', !e.target.checked);
                }
            }
        });

        // Scheduled export modal events
        document.addEventListener('click', (e) => {
            if (e.target.id === 'close-scheduled-export' || e.target.id === 'close-scheduled-export-btn') {
                this.closeScheduledExportModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeExportModal();
                this.closeScheduledExportModal();
            }
        });
    }

    /**
     * Open export modal
     */
    openExportModal() {
        const modal = document.getElementById('enhanced-export-modal');
        if (modal) {
            modal.classList.remove('hidden');
            this.updateExportPreview();
            this.loadTemplates();
        }
    }

    /**
     * Close export modal
     */
    closeExportModal() {
        const modal = document.getElementById('enhanced-export-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    /**
     * Open scheduled export modal
     */
    openScheduledExportModal() {
        const modal = document.getElementById('scheduled-export-modal');
        if (modal) {
            modal.classList.remove('hidden');
            this.loadScheduledExports();
        }
    }

    /**
     * Close scheduled export modal
     */
    closeScheduledExportModal() {
        const modal = document.getElementById('scheduled-export-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    /**
     * Start export process
     */
    async startExport() {
        const formData = this.getExportFormData();
        
        try {
            this.showExportProgress();
            
            if (formData.schedule_export) {
                await this.scheduleExport(formData);
            } else {
                await this.executeExport(formData);
            }
            
            this.closeExportModal();
        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Export failed: ' + error.message, 'error');
        }
    }

    /**
     * Execute export
     */
    async executeExport(formData) {
        const response = await fetch('/api/admin/tenants/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Export failed');
        }

        const result = await response.json();
        
        if (result.download_url) {
            window.open(result.download_url, '_blank');
        }
        
        this.addToExportHistory(result);
        this.showToast('Export completed successfully', 'success');
    }

    /**
     * Schedule export
     */
    async scheduleExport(formData) {
        const response = await fetch('/api/admin/tenants/export/schedule', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(formData)
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to schedule export');
        }

        const result = await response.json();
        this.scheduledExports.push(result);
        this.saveScheduledExports();
        this.showToast('Export scheduled successfully', 'success');
    }

    /**
     * Get export form data
     */
    getExportFormData() {
        const form = document.getElementById('enhanced-export-modal');
        const formData = new FormData(form);
        const data = {};

        // Basic export settings
        data.export_type = formData.get('export_type');
        data.export_format = formData.get('export_format');
        data.include_headers = formData.has('include_headers');
        data.include_metadata = formData.has('include_metadata');
        data.compress_file = formData.has('compress_file');
        data.email_delivery = formData.has('email_delivery');

        // Selected columns
        data.columns = formData.getAll('columns');

        // Email settings
        if (data.email_delivery) {
            data.email_address = formData.get('email_address');
            data.email_subject = formData.get('email_subject');
            data.email_message = formData.get('email_message');
        }

        // Schedule settings
        data.schedule_export = formData.has('schedule_export');
        if (data.schedule_export) {
            data.schedule_frequency = formData.get('schedule_frequency');
            data.schedule_time = formData.get('schedule_time');
            data.schedule_name = formData.get('schedule_name');
        }

        return data;
    }

    /**
     * Update export preview
     */
    updateExportPreview() {
        const preview = document.getElementById('export-preview');
        if (!preview) return;

        const formData = this.getExportFormData();
        const selectedColumns = formData.columns.length;
        const format = formData.export_format.toUpperCase();
        
        preview.textContent = `Export ${selectedColumns} columns in ${format} format`;
    }

    /**
     * Select all columns
     */
    selectAllColumns() {
        const checkboxes = document.querySelectorAll('input[name="columns"]');
        checkboxes.forEach(checkbox => checkbox.checked = true);
        this.updateExportPreview();
    }

    /**
     * Deselect all columns
     */
    deselectAllColumns() {
        const checkboxes = document.querySelectorAll('input[name="columns"]');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        this.updateExportPreview();
    }

    /**
     * Save export template
     */
    saveTemplate() {
        const templateName = prompt('Enter template name:');
        if (!templateName) return;

        const formData = this.getExportFormData();
        const template = {
            name: templateName,
            settings: formData,
            created_at: new Date().toISOString()
        };

        this.exportTemplates.push(template);
        this.saveExportTemplates();
        this.loadTemplates();
        this.showToast('Template saved successfully', 'success');
    }

    /**
     * Load templates
     */
    loadTemplates() {
        const container = document.getElementById('template-list');
        if (!container) return;

        if (this.exportTemplates.length === 0) {
            container.innerHTML = '<div class="text-sm text-gray-500">No templates saved</div>';
            return;
        }

        container.innerHTML = this.exportTemplates.map((template, index) => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <div>
                    <div class="text-sm font-medium">${template.name}</div>
                    <div class="text-xs text-gray-500">${template.settings.export_format.toUpperCase()} • ${template.settings.columns.length} columns</div>
                </div>
                <div class="flex space-x-2">
                    <button type="button" onclick="window.ExportEnhancements.loadTemplate(${index})" class="text-sm text-blue-600 hover:text-blue-800">
                        Load
                    </button>
                    <button type="button" onclick="window.ExportEnhancements.deleteTemplate(${index})" class="text-sm text-red-600 hover:text-red-800">
                        Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Load template
     */
    loadTemplate(index) {
        const template = this.exportTemplates[index];
        if (!template) return;

        const settings = template.settings;
        
        // Set export type
        const exportType = document.querySelector(`input[name="export_type"][value="${settings.export_type}"]`);
        if (exportType) exportType.checked = true;

        // Set format
        const format = document.querySelector(`input[name="export_format"][value="${settings.export_format}"]`);
        if (format) format.checked = true;

        // Set columns
        const checkboxes = document.querySelectorAll('input[name="columns"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = settings.columns.includes(checkbox.value);
        });

        // Set options
        if (settings.include_headers) {
            const headers = document.querySelector('input[name="include_headers"]');
            if (headers) headers.checked = true;
        }

        this.updateExportPreview();
        this.showToast('Template loaded successfully', 'success');
    }

    /**
     * Delete template
     */
    deleteTemplate(index) {
        if (!confirm('Are you sure you want to delete this template?')) return;

        this.exportTemplates.splice(index, 1);
        this.saveExportTemplates();
        this.loadTemplates();
        this.showToast('Template deleted successfully', 'success');
    }

    /**
     * Load scheduled exports
     */
    loadScheduledExports() {
        const container = document.getElementById('scheduled-exports-list');
        if (!container) return;

        if (this.scheduledExports.length === 0) {
            container.innerHTML = '<div class="text-sm text-gray-500">No scheduled exports</div>';
            return;
        }

        container.innerHTML = this.scheduledExports.map((export_, index) => `
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                <div>
                    <div class="text-sm font-medium">${export_.name}</div>
                    <div class="text-xs text-gray-500">${export_.frequency} at ${export_.time} • ${export_.format.toUpperCase()}</div>
                </div>
                <div class="flex space-x-2">
                    <button type="button" onclick="window.ExportEnhancements.deleteScheduledExport(${index})" class="text-sm text-red-600 hover:text-red-800">
                        Delete
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Delete scheduled export
     */
    deleteScheduledExport(index) {
        if (!confirm('Are you sure you want to delete this scheduled export?')) return;

        this.scheduledExports.splice(index, 1);
        this.saveScheduledExports();
        this.loadScheduledExports();
        this.showToast('Scheduled export deleted successfully', 'success');
    }

    /**
     * Show export progress
     */
    showExportProgress() {
        this.showToast('Export in progress...', 'info');
    }

    /**
     * Add to export history
     */
    addToExportHistory(exportResult) {
        this.exportHistory.unshift({
            ...exportResult,
            timestamp: new Date().toISOString()
        });
        
        // Keep only last 50 exports
        if (this.exportHistory.length > 50) {
            this.exportHistory = this.exportHistory.slice(0, 50);
        }
        
        this.saveExportHistory();
    }

    /**
     * Load export history
     */
    loadExportHistory() {
        const saved = localStorage.getItem('tenant_export_history');
        if (saved) {
            try {
                this.exportHistory = JSON.parse(saved);
            } catch (e) {
                console.error('Failed to load export history:', e);
                this.exportHistory = [];
            }
        }
    }

    /**
     * Save export history
     */
    saveExportHistory() {
        localStorage.setItem('tenant_export_history', JSON.stringify(this.exportHistory));
    }

    /**
     * Load scheduled exports
     */
    loadScheduledExports() {
        const saved = localStorage.getItem('tenant_scheduled_exports');
        if (saved) {
            try {
                this.scheduledExports = JSON.parse(saved);
            } catch (e) {
                console.error('Failed to load scheduled exports:', e);
                this.scheduledExports = [];
            }
        }
    }

    /**
     * Save scheduled exports
     */
    saveScheduledExports() {
        localStorage.setItem('tenant_scheduled_exports', JSON.stringify(this.scheduledExports));
    }

    /**
     * Load export templates
     */
    loadExportTemplates() {
        const saved = localStorage.getItem('tenant_export_templates');
        if (saved) {
            try {
                this.exportTemplates = JSON.parse(saved);
            } catch (e) {
                console.error('Failed to load export templates:', e);
                this.exportTemplates = [];
            }
        }
    }

    /**
     * Save export templates
     */
    saveExportTemplates() {
        localStorage.setItem('tenant_export_templates', JSON.stringify(this.exportTemplates));
    }

    /**
     * Get auth token
     */
    getAuthToken() {
        const token = document.querySelector('meta[name="api-token"]')?.content || 
                     localStorage.getItem('auth_token') || 
                     '5|uGddv7wdYNtoCu9RACfpytV7LrLQQODBdvi4PBce2f517aac';
        return token;
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        if (window.Tenants && typeof window.Tenants.showToast === 'function') {
            window.Tenants.showToast(message, type);
        } else {
            console.log(`${type.toUpperCase()}: ${message}`);
        }
    }
}

// Initialize export enhancements
window.ExportEnhancements = new ExportEnhancements();
