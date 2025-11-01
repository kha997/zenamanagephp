/**
 * Advanced Filters Module for Tenants Page
 */
class AdvancedFilters {
    constructor() {
        this.filters = {};
        this.savedFilters = [];
        this.init();
    }

    init() {
        this.loadSavedFilters();
        this.attachEventListeners();
        this.createModal();
    }

    /**
     * Create advanced filters modal dynamically
     */
    createModal() {
        const modal = document.createElement('div');
        modal.id = 'advanced-filters-modal';
        modal.className = 'modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
        modal.innerHTML = this.getModalHTML();
        document.body.appendChild(modal);
    }

    /**
     * Get modal HTML
     */
    getModalHTML() {
        return `
            <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white max-h-screen overflow-y-auto">
                <div class="modal-header flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Advanced Filters</h2>
                    <button id="close-advanced-filters" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="advanced-filters-form" class="space-y-6">
                    <!-- Date Range Filters -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Date Range</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Created From</label>
                                <input type="date" name="created_from" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Created To</label>
                                <input type="date" name="created_to" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Usage Metrics -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Usage Metrics</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Users</label>
                                <input type="number" name="min_users" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Users</label>
                                <input type="number" name="max_users" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Projects</label>
                                <input type="number" name="min_projects" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Projects</label>
                                <input type="number" name="max_projects" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Storage & Region -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Storage & Location</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Min Storage (MB)</label>
                                <input type="number" name="min_storage" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Max Storage (MB)</label>
                                <input type="number" name="max_storage" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                                <select name="region" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Regions</option>
                                    <option value="us-east-1">US East</option>
                                    <option value="us-west-2">US West</option>
                                    <option value="eu-west-1">Europe</option>
                                    <option value="ap-southeast-1">Asia Pacific</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Trial & Subscription -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Trial & Subscription</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Trial Expiring</label>
                                <select name="trial_expiring" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">All</option>
                                    <option value="7d">Within 7 days</option>
                                    <option value="30d">Within 30 days</option>
                                    <option value="expired">Expired</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Fields -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Custom Fields</h3>
                        <div class="space-y-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Owner Email</label>
                                <input type="email" name="owner_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Filter by owner email">
                            </div>
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                                <input type="text" name="tags" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter tags separated by commas">
                            </div>
                        </div>
                    </div>

                    <!-- Saved Filters -->
                    <div class="filter-section">
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Saved Filters</h3>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="filter-name" placeholder="Enter filter name" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="button" id="save-filter" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-2"></i>Save
                            </button>
                        </div>
                        <div id="saved-filters" class="mt-2 space-y-1"></div>
                    </div>
                </form>

                <div class="modal-footer flex items-center justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" id="clear-advanced-filters" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        <i class="fas fa-eraser mr-2"></i>Clear All
                    </button>
                    <button type="button" id="cancel-advanced-filters" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="button" id="apply-advanced-filters" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Open modal
        document.addEventListener('click', (e) => {
            if (e.target.id === 'advanced-filters-btn' || e.target.closest('#advanced-filters-btn')) {
                this.openModal();
            }
        });

        // Close modal
        document.addEventListener('click', (e) => {
            if (e.target.id === 'close-advanced-filters' || e.target.id === 'cancel-advanced-filters') {
                this.closeModal();
            }
        });

        // Apply filters
        document.addEventListener('click', (e) => {
            if (e.target.id === 'apply-advanced-filters') {
                this.applyFilters();
            }
        });

        // Clear filters
        document.addEventListener('click', (e) => {
            if (e.target.id === 'clear-advanced-filters') {
                this.clearFilters();
            }
        });

        // Save filter
        document.addEventListener('click', (e) => {
            if (e.target.id === 'save-filter') {
                this.saveFilter();
            }
        });
    }

    /**
     * Open modal
     */
    openModal() {
        const modal = document.getElementById('advanced-filters-modal');
        if (modal) {
            modal.classList.remove('hidden');
            this.populateCurrentFilters();
        }
    }

    /**
     * Close modal
     */
    closeModal() {
        const modal = document.getElementById('advanced-filters-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    /**
     * Populate current filters
     */
    populateCurrentFilters() {
        const form = document.getElementById('advanced-filters-form');
        if (!form) return;

        Object.entries(this.filters).forEach(([key, value]) => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = value;
            }
        });
    }

    /**
     * Apply filters
     */
    applyFilters() {
        const form = document.getElementById('advanced-filters-form');
        if (!form) return;

        const formData = new FormData(form);
        this.filters = {};

        for (const [key, value] of formData.entries()) {
            if (value) {
                this.filters[key] = value;
            }
        }

        // Apply filters to main page
        if (window.Tenants && typeof window.Tenants.applyAdvancedFilters === 'function') {
            window.Tenants.applyAdvancedFilters(this.filters);
        }

        // Track analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'advanced_filters_applied', {
                filter_count: Object.keys(this.filters).length
            });
        }

        this.closeModal();
    }

    /**
     * Clear filters
     */
    clearFilters() {
        const form = document.getElementById('advanced-filters-form');
        if (form) {
            form.reset();
        }
        this.filters = {};
    }

    /**
     * Save filter
     */
    saveFilter() {
        const filterName = document.getElementById('filter-name').value.trim();
        if (!filterName) {
            alert('Please enter a filter name');
            return;
        }

        const savedFilter = {
            name: filterName,
            filters: {...this.filters},
            created_at: new Date().toISOString()
        };

        this.savedFilters.push(savedFilter);
        localStorage.setItem('tenant_saved_filters', JSON.stringify(this.savedFilters));

        this.renderSavedFilters();
        document.getElementById('filter-name').value = '';

        // Track analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'filter_saved', {
                filter_name: filterName
            });
        }
    }

    /**
     * Load saved filters
     */
    loadSavedFilters() {
        const saved = localStorage.getItem('tenant_saved_filters');
        if (saved) {
            try {
                this.savedFilters = JSON.parse(saved);
            } catch (e) {
                console.error('Failed to load saved filters:', e);
                this.savedFilters = [];
            }
        }
    }

    /**
     * Render saved filters
     */
    renderSavedFilters() {
        const container = document.getElementById('saved-filters');
        if (!container) return;

        if (this.savedFilters.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-500">No saved filters</p>';
            return;
        }

        container.innerHTML = this.savedFilters.map((filter, index) => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                <button type="button" class="text-sm text-blue-600 hover:text-blue-800" onclick="window.AdvancedFilters.loadFilter(${index})">
                    <i class="fas fa-filter mr-2"></i>${filter.name}
                </button>
                <button type="button" class="text-sm text-red-600 hover:text-red-800" onclick="window.AdvancedFilters.deleteFilter(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `).join('');
    }

    /**
     * Load saved filter
     */
    loadFilter(index) {
        const filter = this.savedFilters[index];
        if (!filter) return;

        this.filters = {...filter.filters};
        this.populateCurrentFilters();
        this.applyFilters();

        // Track analytics
        if (typeof gtag !== 'undefined') {
            gtag('event', 'filter_loaded', {
                filter_name: filter.name
            });
        }
    }

    /**
     * Delete saved filter
     */
    deleteFilter(index) {
        if (!confirm('Are you sure you want to delete this saved filter?')) return;

        this.savedFilters.splice(index, 1);
        localStorage.setItem('tenant_saved_filters', JSON.stringify(this.savedFilters));
        this.renderSavedFilters();
    }

    /**
     * Get current filters
     */
    getFilters() {
        return {...this.filters};
    }
}

// Initialize advanced filters
window.AdvancedFilters = new AdvancedFilters();
