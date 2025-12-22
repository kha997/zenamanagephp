class TenantDetailPage {
    constructor(tenantId) {
        this.tenantId = tenantId;
        this.tenant = null;
    }

    async init() {
        this.initEventListeners();
        await this.loadTenantDetails();
    }

    initEventListeners() {
        // Back to list
        document.getElementById('back-to-list-btn').addEventListener('click', () => {
            window.location.href = '/admin/tenants';
        });

        // Edit tenant
        document.getElementById('edit-tenant-btn').addEventListener('click', () => {
            window.location.href = `/admin/tenants/${this.tenantId}/edit`;
        });

        // Action buttons
        document.getElementById('suspend-tenant-btn').addEventListener('click', () => this.suspendTenant());
        document.getElementById('resume-tenant-btn').addEventListener('click', () => this.resumeTenant());
        document.getElementById('impersonate-tenant-btn').addEventListener('click', () => this.impersonateTenant());
        document.getElementById('delete-tenant-btn').addEventListener('click', () => this.deleteTenant());
    }

    async loadTenantDetails() {
        try {
            const response = await fetch(`/api/admin/tenants/${this.tenantId}`, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            this.tenant = result.data;
            this.renderTenantDetails();
            this.hideLoadingState();

        } catch (error) {
            console.error('Failed to load tenant details:', error);
            this.showErrorState(error.message);
        }
    }

    renderTenantDetails() {
        if (!this.tenant) return;

        // Basic info
        document.getElementById('tenant-name').textContent = this.tenant.name;
        document.getElementById('tenant-name-value').textContent = this.tenant.name;
        document.getElementById('tenant-domain-value').textContent = this.tenant.domain || 'N/A';
        document.getElementById('tenant-code-value').textContent = this.tenant.code || this.tenant.slug || 'N/A';

        // Status
        const statusEl = document.getElementById('tenant-status-value');
        const status = this.tenant.status || 'unknown';
        statusEl.innerHTML = this.getStatusBadge(status);

        // Plan
        const plan = this.tenant.settings?.plan || 'unknown';
        document.getElementById('tenant-plan-value').textContent = this.capitalizeFirst(plan);

        // Region
        document.getElementById('tenant-region-value').textContent = this.tenant.region || 'N/A';

        // Dates
        document.getElementById('tenant-created-value').textContent = this.formatDate(this.tenant.created_at);
        document.getElementById('tenant-updated-value').textContent = this.formatDate(this.tenant.updated_at);

        // Trial ends
        if (this.tenant.trial_ends_at) {
            document.getElementById('trial-ends-container').classList.remove('hidden');
            document.getElementById('tenant-trial-ends-value').textContent = this.formatDate(this.tenant.trial_ends_at);
        }

        // Statistics
        document.getElementById('users-count').textContent = this.tenant.users_count || 0;
        document.getElementById('projects-count').textContent = this.tenant.projects_count || 0;
        document.getElementById('tasks-count').textContent = this.tenant.tasks_count || 0;
        document.getElementById('storage-used').textContent = this.formatStorage(this.tenant.storage_used || 0);

        // Update subtitle
        document.getElementById('tenant-subtitle').textContent = `${this.capitalizeFirst(status)} tenant • ${this.capitalizeFirst(plan)} plan`;

        // Update action buttons visibility
        this.updateActionButtons();
    }

    getStatusBadge(status) {
        const badges = {
            active: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>',
            suspended: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Suspended</span>',
            trial: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Trial</span>',
            archived: '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Archived</span>'
        };
        return badges[status] || `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">${this.capitalizeFirst(status)}</span>`;
    }

    updateActionButtons() {
        const status = this.tenant.status;
        const suspendBtn = document.getElementById('suspend-tenant-btn');
        const resumeBtn = document.getElementById('resume-tenant-btn');

        if (status === 'suspended') {
            suspendBtn.style.display = 'none';
            resumeBtn.style.display = 'inline-flex';
        } else {
            suspendBtn.style.display = 'inline-flex';
            resumeBtn.style.display = 'none';
        }
    }

    async suspendTenant() {
        if (!confirm('Are you sure you want to suspend this tenant?')) return;

        try {
            this.showToast('Suspending tenant...', 'info');
            
            const response = await fetch(`/api/admin/tenants/${this.tenantId}/suspend`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    reason: 'Manual suspension from tenant detail page'
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to suspend tenant');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            await this.loadTenantDetails(); // Refresh data

        } catch (error) {
            console.error('Suspend tenant failed:', error);
            this.showToast(error.message || 'Failed to suspend tenant', 'error');
        }
    }

    async resumeTenant() {
        if (!confirm('Are you sure you want to resume this tenant?')) return;

        try {
            this.showToast('Resuming tenant...', 'info');
            
            const response = await fetch(`/api/admin/tenants/${this.tenantId}/resume`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    reason: 'Manual resume from tenant detail page'
                })
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to resume tenant');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            await this.loadTenantDetails(); // Refresh data

        } catch (error) {
            console.error('Resume tenant failed:', error);
            this.showToast(error.message || 'Failed to resume tenant', 'error');
        }
    }

    async impersonateTenant() {
        if (!confirm('Are you sure you want to impersonate this tenant? You will be logged in as them.')) return;

        try {
            this.showToast('Starting impersonation...', 'info');
            
            const response = await fetch(`/api/admin/tenants/${this.tenantId}/impersonate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to start impersonation');
            }

            const result = await response.json();
            this.showToast(result.message, 'success');
            
            // Redirect to impersonation URL
            if (result.impersonation_url) {
                window.location.href = result.impersonation_url;
            }

        } catch (error) {
            console.error('Impersonate tenant failed:', error);
            this.showToast(error.message || 'Failed to start impersonation', 'error');
        }
    }

    async deleteTenant() {
        if (!confirm('Are you sure you want to delete this tenant? This action cannot be undone.')) return;

        try {
            this.showToast('Deleting tenant...', 'info');
            
            const response = await fetch(`/api/admin/tenants/${this.tenantId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to delete tenant');
            }

            const result = await response.json();
            this.showToast(result.message || 'Tenant deleted successfully', 'success');
            
            // Redirect to tenants list
            setTimeout(() => {
                window.location.href = '/admin/tenants';
            }, 1500);

        } catch (error) {
            console.error('Delete tenant failed:', error);
            this.showToast(error.message || 'Failed to delete tenant', 'error');
        }
    }

    hideLoadingState() {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('main-content').classList.remove('hidden');
    }

    showErrorState(message) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-message').textContent = message;
        document.getElementById('error-state').classList.remove('hidden');
    }

    getAuthToken() {
        const token = document.querySelector('meta[name="api-token"]')?.content || 
                     localStorage.getItem('auth_token') || 
                     '5|uGddv7wdYNtoCu9RACfpytV7LrLQQODBdvi4PBce2f517aac';
        return token;
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    formatStorage(bytes) {
        if (!bytes) return '0 MB';
        const mb = bytes / (1024 * 1024);
        return `${mb.toFixed(1)} MB`;
    }

    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast px-4 py-3 rounded-lg shadow-lg text-white max-w-sm transform transition-all duration-300 ${
            type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' :
            type === 'warning' ? 'bg-yellow-500' :
            'bg-blue-500'
        }`;
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas ${
                    type === 'success' ? 'fa-check' :
                    type === 'error' ? 'fa-exclamation-triangle' :
                    type === 'warning' ? 'fa-exclamation' :
                    'fa-info-circle'
                } mr-2"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.getElementById('toast-container').appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}
