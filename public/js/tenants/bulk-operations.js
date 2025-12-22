/**
 * Enhanced Bulk Operations Module for Tenants Page
 */
class BulkOperations {
    constructor() {
        this.batchSize = 10; // Default batch size
        this.maxBatchSize = 50; // Maximum batch size
        this.operationHistory = []; // For undo functionality
        this.currentOperation = null;
        this.progress = {
            total: 0,
            completed: 0,
            failed: 0,
            errors: []
        };
        this.init();
    }

    init() {
        this.createProgressModal();
        this.createBatchSizeModal();
        this.attachEventListeners();
        this.loadSettings();
    }

    /**
     * Create progress modal
     */
    createProgressModal() {
        const modal = document.createElement('div');
        modal.id = 'bulk-progress-modal';
        modal.className = 'modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
                <div class="modal-header flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900" id="progress-title">Processing...</h2>
                    <button id="close-progress-modal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="progress-content">
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span id="progress-text">0 of 0 completed</span>
                            <span id="progress-percentage">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div id="progress-bar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Operation Details -->
                    <div class="mb-4">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="bg-green-50 p-3 rounded-lg">
                                <div class="text-2xl font-bold text-green-600" id="completed-count">0</div>
                                <div class="text-sm text-green-700">Completed</div>
                            </div>
                            <div class="bg-red-50 p-3 rounded-lg">
                                <div class="text-2xl font-bold text-red-600" id="failed-count">0</div>
                                <div class="text-sm text-red-700">Failed</div>
                            </div>
                            <div class="bg-blue-50 p-3 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600" id="remaining-count">0</div>
                                <div class="text-sm text-blue-700">Remaining</div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Operation -->
                    <div class="mb-4">
                        <div class="text-sm text-gray-600 mb-2">Current Operation:</div>
                        <div id="current-operation" class="text-sm text-gray-900 bg-gray-50 p-2 rounded">Preparing...</div>
                    </div>

                    <!-- Error Log -->
                    <div id="error-log" class="mb-4 hidden">
                        <div class="text-sm text-gray-600 mb-2">Errors:</div>
                        <div class="max-h-32 overflow-y-auto bg-red-50 border border-red-200 rounded p-2">
                            <div id="error-list" class="text-sm text-red-700"></div>
                        </div>
                    </div>

                    <!-- Batch Size Configuration -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">Batch Size:</label>
                            <div class="flex items-center space-x-2">
                                <input type="range" id="batch-size-slider" min="1" max="50" value="10" class="w-20">
                                <span id="batch-size-value" class="text-sm text-gray-600 w-8">10</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                    <div class="flex space-x-2">
                        <button id="pause-operation" class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500">
                            <i class="fas fa-pause mr-2"></i>Pause
                        </button>
                        <button id="cancel-operation" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <i class="fas fa-stop mr-2"></i>Cancel
                        </button>
                    </div>
                    <div class="flex space-x-2">
                        <button id="undo-operation" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 hidden">
                            <i class="fas fa-undo mr-2"></i>Undo
                        </button>
                        <button id="close-progress" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    /**
     * Create batch size configuration modal
     */
    createBatchSizeModal() {
        const modal = document.createElement('div');
        modal.id = 'batch-size-modal';
        modal.className = 'modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = `
            <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-1/3 shadow-lg rounded-md bg-white">
                <div class="modal-header flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Batch Size Configuration</h2>
                    <button id="close-batch-size-modal" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Batch Size (1-50)</label>
                        <input type="range" id="batch-size-config" min="1" max="50" value="10" class="w-full">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>1 (Slow)</span>
                            <span id="batch-size-display" class="font-medium">10</span>
                            <span>50 (Fast)</span>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-3 rounded-lg">
                        <div class="text-sm text-blue-800">
                            <strong>Recommendation:</strong> Use smaller batches (5-10) for better error handling and progress tracking. 
                            Larger batches (20-50) are faster but may timeout on slow connections.
                        </div>
                    </div>

                    <div class="bg-yellow-50 p-3 rounded-lg">
                        <div class="text-sm text-yellow-800">
                            <strong>Note:</strong> Batch size affects memory usage and API rate limits. 
                            Adjust based on your system performance and network conditions.
                        </div>
                    </div>
                </div>

                <div class="modal-footer flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button id="cancel-batch-size" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button id="save-batch-size" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Save
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
        // Progress modal events
        document.addEventListener('click', (e) => {
            if (e.target.id === 'close-progress-modal' || e.target.id === 'close-progress') {
                this.closeProgressModal();
            }
            if (e.target.id === 'pause-operation') {
                this.pauseOperation();
            }
            if (e.target.id === 'cancel-operation') {
                this.cancelOperation();
            }
            if (e.target.id === 'undo-operation') {
                this.undoOperation();
            }
        });

        // Batch size modal events
        document.addEventListener('click', (e) => {
            if (e.target.id === 'batch-size-btn' || e.target.closest('#batch-size-btn')) {
                this.openBatchSizeModal();
            }
            if (e.target.id === 'close-batch-size-modal' || e.target.id === 'cancel-batch-size') {
                this.closeBatchSizeModal();
            }
            if (e.target.id === 'save-batch-size') {
                this.saveBatchSize();
            }
        });

        // Batch size slider events
        document.addEventListener('input', (e) => {
            if (e.target.id === 'batch-size-slider') {
                this.updateBatchSizeDisplay(e.target.value);
            }
            if (e.target.id === 'batch-size-config') {
                this.updateBatchSizeConfigDisplay(e.target.value);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeProgressModal();
                this.closeBatchSizeModal();
            }
        });
    }

    /**
     * Load settings from localStorage
     */
    loadSettings() {
        const savedBatchSize = localStorage.getItem('tenant_bulk_batch_size');
        if (savedBatchSize) {
            this.batchSize = parseInt(savedBatchSize);
        }
    }

    /**
     * Save settings to localStorage
     */
    saveSettings() {
        localStorage.setItem('tenant_bulk_batch_size', this.batchSize.toString());
    }

    /**
     * Start bulk operation
     */
    async startBulkOperation(operation, tenantIds, options = {}) {
        this.currentOperation = {
            type: operation,
            tenantIds: [...tenantIds],
            options: options,
            startTime: Date.now(),
            paused: false,
            cancelled: false
        };

        this.progress = {
            total: tenantIds.length,
            completed: 0,
            failed: 0,
            errors: []
        };

        this.openProgressModal();
        this.updateProgressTitle(operation);
        
        try {
            await this.processBatches();
            this.completeOperation();
        } catch (error) {
            this.handleOperationError(error);
        }
    }

    /**
     * Process batches
     */
    async processBatches() {
        const batches = this.chunkArray(this.currentOperation.tenantIds, this.batchSize);
        
        for (let i = 0; i < batches.length; i++) {
            if (this.currentOperation.cancelled) {
                break;
            }

            // Wait if paused
            while (this.currentOperation.paused && !this.currentOperation.cancelled) {
                await this.sleep(100);
            }

            if (this.currentOperation.cancelled) {
                break;
            }

            const batch = batches[i];
            this.updateCurrentOperation(`Processing batch ${i + 1} of ${batches.length} (${batch.length} items)`);
            
            try {
                await this.processBatch(batch);
            } catch (error) {
                this.handleBatchError(batch, error);
            }
        }
    }

    /**
     * Process a single batch
     */
    async processBatch(tenantIds) {
        const promises = tenantIds.map(async (tenantId) => {
            try {
                await this.executeSingleOperation(tenantId);
                this.progress.completed++;
            } catch (error) {
                this.progress.failed++;
                this.progress.errors.push({
                    tenantId,
                    error: error.message
                });
            }
            this.updateProgress();
        });

        await Promise.allSettled(promises);
    }

    /**
     * Execute single operation
     */
    async executeSingleOperation(tenantId) {
        const { type, options } = this.currentOperation;
        
        let url, method, body;
        
        switch (type) {
            case 'suspend':
                url = `/api/admin/tenants/bulk/suspend`;
                method = 'POST';
                body = { tenant_ids: [tenantId], reason: options.reason || 'Bulk operation' };
                break;
            case 'resume':
                url = `/api/admin/tenants/bulk/resume`;
                method = 'POST';
                body = { tenant_ids: [tenantId], reason: options.reason || 'Bulk operation' };
                break;
            case 'change-plan':
                url = `/api/admin/tenants/bulk/change-plan`;
                method = 'POST';
                body = { tenant_ids: [tenantId], plan: options.plan, reason: options.reason || 'Bulk operation' };
                break;
            case 'delete':
                url = `/api/admin/tenants/${tenantId}`;
                method = 'DELETE';
                body = null;
                break;
            default:
                throw new Error(`Unknown operation: ${type}`);
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${this.getAuthToken()}`,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body ? JSON.stringify(body) : null
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || `HTTP ${response.status}`);
        }

        return response.json();
    }

    /**
     * Update progress
     */
    updateProgress() {
        const percentage = Math.round((this.progress.completed / this.progress.total) * 100);
        const remaining = this.progress.total - this.progress.completed - this.progress.failed;

        // Update progress bar
        const progressBar = document.getElementById('progress-bar');
        if (progressBar) {
            progressBar.style.width = `${percentage}%`;
        }

        // Update text
        const progressText = document.getElementById('progress-text');
        if (progressText) {
            progressText.textContent = `${this.progress.completed} of ${this.progress.total} completed`;
        }

        const progressPercentage = document.getElementById('progress-percentage');
        if (progressPercentage) {
            progressPercentage.textContent = `${percentage}%`;
        }

        // Update counts
        const completedCount = document.getElementById('completed-count');
        if (completedCount) {
            completedCount.textContent = this.progress.completed;
        }

        const failedCount = document.getElementById('failed-count');
        if (failedCount) {
            failedCount.textContent = this.progress.failed;
        }

        const remainingCount = document.getElementById('remaining-count');
        if (remainingCount) {
            remainingCount.textContent = remaining;
        }

        // Show error log if there are errors
        if (this.progress.errors.length > 0) {
            this.showErrorLog();
        }
    }

    /**
     * Show error log
     */
    showErrorLog() {
        const errorLog = document.getElementById('error-log');
        const errorList = document.getElementById('error-list');
        
        if (errorLog && errorList) {
            errorLog.classList.remove('hidden');
            errorList.innerHTML = this.progress.errors.map(error => 
                `<div class="mb-1">Tenant ${error.tenantId}: ${error.error}</div>`
            ).join('');
        }
    }

    /**
     * Update progress title
     */
    updateProgressTitle(operation) {
        const title = document.getElementById('progress-title');
        if (title) {
            const operationNames = {
                suspend: 'Suspending Tenants',
                resume: 'Resuming Tenants',
                'change-plan': 'Changing Plans',
                delete: 'Deleting Tenants'
            };
            title.textContent = operationNames[operation] || 'Processing...';
        }
    }

    /**
     * Update current operation
     */
    updateCurrentOperation(text) {
        const currentOp = document.getElementById('current-operation');
        if (currentOp) {
            currentOp.textContent = text;
        }
    }

    /**
     * Complete operation
     */
    completeOperation() {
        this.updateCurrentOperation('Operation completed');
        
        // Save to history for undo
        this.operationHistory.push({
            ...this.currentOperation,
            completedAt: Date.now(),
            results: {
                completed: this.progress.completed,
                failed: this.progress.failed,
                errors: [...this.progress.errors]
            }
        });

        // Show undo button
        const undoBtn = document.getElementById('undo-operation');
        if (undoBtn) {
            undoBtn.classList.remove('hidden');
        }

        // Track analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'bulk_operation_completed', {
                operation_type: this.currentOperation.type,
                total_items: this.progress.total,
                completed: this.progress.completed,
                failed: this.progress.failed,
                batch_size: this.batchSize
            });
        }
    }

    /**
     * Handle operation error
     */
    handleOperationError(error) {
        this.updateCurrentOperation(`Operation failed: ${error.message}`);
        console.error('Bulk operation failed:', error);
    }

    /**
     * Handle batch error
     */
    handleBatchError(batch, error) {
        batch.forEach(tenantId => {
            this.progress.failed++;
            this.progress.errors.push({
                tenantId,
                error: error.message
            });
        });
        this.updateProgress();
    }

    /**
     * Pause operation
     */
    pauseOperation() {
        if (this.currentOperation) {
            this.currentOperation.paused = !this.currentOperation.paused;
            const pauseBtn = document.getElementById('pause-operation');
            if (pauseBtn) {
                pauseBtn.innerHTML = this.currentOperation.paused 
                    ? '<i class="fas fa-play mr-2"></i>Resume'
                    : '<i class="fas fa-pause mr-2"></i>Pause';
                pauseBtn.className = this.currentOperation.paused
                    ? 'px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500'
                    : 'px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500';
            }
        }
    }

    /**
     * Cancel operation
     */
    cancelOperation() {
        if (this.currentOperation) {
            this.currentOperation.cancelled = true;
            this.updateCurrentOperation('Operation cancelled');
        }
    }

    /**
     * Undo operation
     */
    async undoOperation() {
        if (this.operationHistory.length === 0) return;

        const lastOperation = this.operationHistory.pop();
        if (!confirm(`Are you sure you want to undo the last ${lastOperation.type} operation?`)) {
            this.operationHistory.push(lastOperation);
            return;
        }

        try {
            // Reverse the operation
            const reverseOperation = this.getReverseOperation(lastOperation.type);
            await this.startBulkOperation(reverseOperation, lastOperation.tenantIds, lastOperation.options);
        } catch (error) {
            console.error('Undo operation failed:', error);
            this.showToast('Undo operation failed', 'error');
        }
    }

    /**
     * Get reverse operation
     */
    getReverseOperation(operation) {
        const reverseOperations = {
            suspend: 'resume',
            resume: 'suspend',
            'change-plan': 'change-plan', // Would need original plan
            delete: null // Cannot undo delete
        };
        return reverseOperations[operation];
    }

    /**
     * Open progress modal
     */
    openProgressModal() {
        const modal = document.getElementById('bulk-progress-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    /**
     * Close progress modal
     */
    closeProgressModal() {
        const modal = document.getElementById('bulk-progress-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    /**
     * Open batch size modal
     */
    openBatchSizeModal() {
        const modal = document.getElementById('batch-size-modal');
        const slider = document.getElementById('batch-size-config');
        if (modal && slider) {
            slider.value = this.batchSize;
            this.updateBatchSizeConfigDisplay(this.batchSize);
            modal.classList.remove('hidden');
        }
    }

    /**
     * Close batch size modal
     */
    closeBatchSizeModal() {
        const modal = document.getElementById('batch-size-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    /**
     * Update batch size display
     */
    updateBatchSizeDisplay(value) {
        const display = document.getElementById('batch-size-value');
        if (display) {
            display.textContent = value;
        }
    }

    /**
     * Update batch size config display
     */
    updateBatchSizeConfigDisplay(value) {
        const display = document.getElementById('batch-size-display');
        if (display) {
            display.textContent = value;
        }
    }

    /**
     * Save batch size
     */
    saveBatchSize() {
        const slider = document.getElementById('batch-size-config');
        if (slider) {
            this.batchSize = parseInt(slider.value);
            this.saveSettings();
            this.closeBatchSizeModal();
            this.showToast(`Batch size updated to ${this.batchSize}`, 'success');
        }
    }

    /**
     * Chunk array into batches
     */
    chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    /**
     * Sleep utility
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
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

// Initialize bulk operations
window.BulkOperations = new BulkOperations();
