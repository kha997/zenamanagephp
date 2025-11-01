{{-- Advanced Filters Modal --}}
<div id="advanced-filters-modal" class="modal hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="modal-content relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="modal-header flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Advanced Filters</h2>
            <button id="close-advanced-filters" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="advanced-filters-form" class="space-y-6">
            {{-- Date Range Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Date Range</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="created-from" class="block text-sm font-medium text-gray-700 mb-1">Created From</label>
                        <input type="date" id="created-from" name="created_from" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="created-to" class="block text-sm font-medium text-gray-700 mb-1">Created To</label>
                        <input type="date" id="created-to" name="created_to" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            {{-- Status & Plan Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Status & Plan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status-filter" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="trial">Trial</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="plan-filter" class="block text-sm font-medium text-gray-700 mb-1">Plan</label>
                        <select id="plan-filter" name="plan" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Plans</option>
                            <option value="free">Free</option>
                            <option value="pro">Pro</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- User & Project Count Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Usage Metrics</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="min-users" class="block text-sm font-medium text-gray-700 mb-1">Min Users</label>
                        <input type="number" id="min-users" name="min_users" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="max-users" class="block text-sm font-medium text-gray-700 mb-1">Max Users</label>
                        <input type="number" id="max-users" name="max_users" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="min-projects" class="block text-sm font-medium text-gray-700 mb-1">Min Projects</label>
                        <input type="number" id="min-projects" name="min_projects" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="max-projects" class="block text-sm font-medium text-gray-700 mb-1">Max Projects</label>
                        <input type="number" id="max-projects" name="max_projects" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            {{-- Storage & Region Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Storage & Location</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="min-storage" class="block text-sm font-medium text-gray-700 mb-1">Min Storage (MB)</label>
                        <input type="number" id="min-storage" name="min_storage" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="max-storage" class="block text-sm font-medium text-gray-700 mb-1">Max Storage (MB)</label>
                        <input type="number" id="max-storage" name="max_storage" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="form-group">
                        <label for="region-filter" class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                        <select id="region-filter" name="region" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Regions</option>
                            <option value="us-east-1">US East</option>
                            <option value="us-west-2">US West</option>
                            <option value="eu-west-1">Europe</option>
                            <option value="ap-southeast-1">Asia Pacific</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Trial & Subscription Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Trial & Subscription</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="trial-expiring" class="block text-sm font-medium text-gray-700 mb-1">Trial Expiring</label>
                        <select id="trial-expiring" name="trial_expiring" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All</option>
                            <option value="7d">Within 7 days</option>
                            <option value="30d">Within 30 days</option>
                            <option value="expired">Expired</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subscription-status" class="block text-sm font-medium text-gray-700 mb-1">Subscription Status</label>
                        <select id="subscription-status" name="subscription_status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All</option>
                            <option value="active">Active</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="past_due">Past Due</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Custom Fields --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Custom Fields</h3>
                <div class="space-y-4">
                    <div class="form-group">
                        <label for="owner-email" class="block text-sm font-medium text-gray-700 mb-1">Owner Email</label>
                        <input type="email" id="owner-email" name="owner_email" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Filter by owner email">
                    </div>
                    <div class="form-group">
                        <label for="tags" class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                        <input type="text" id="tags" name="tags" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter tags separated by commas">
                    </div>
                </div>
            </div>

            {{-- Sort Options --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Sort Options</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="form-group">
                        <label for="sort-by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select id="sort-by" name="sort_by" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="created_at">Created Date</option>
                            <option value="updated_at">Updated Date</option>
                            <option value="name">Name</option>
                            <option value="status">Status</option>
                            <option value="users_count">User Count</option>
                            <option value="projects_count">Project Count</option>
                            <option value="storage_used">Storage Used</option>
                            <option value="trial_ends_at">Trial End Date</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sort-direction" class="block text-sm font-medium text-gray-700 mb-1">Direction</label>
                        <select id="sort-direction" name="sort_direction" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="desc">Descending</option>
                            <option value="asc">Ascending</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Saved Filters --}}
            <div class="filter-section">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Saved Filters</h3>
                <div class="flex items-center space-x-2">
                    <input type="text" id="filter-name" name="filter_name" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter filter name">
                    <button type="button" id="save-filter" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save
                    </button>
                </div>
                <div id="saved-filters" class="mt-2 space-y-1">
                    <!-- Saved filters will be loaded here -->
                </div>
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
</div>
