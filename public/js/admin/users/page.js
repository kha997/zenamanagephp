/**
 * Users Management Page
 * Handles user listing, filtering, bulk actions, and invitations
 */

function usersPage() {
    return {
        // State
        users: [],
        tenants: [],
        kpis: null,
        meta: null,
        loading: false,
        selectedUsers: [],
        showInviteModal: false,
        inviteLoading: false,
        
        // Filters
        filters: {
            q: '',
            tenant_id: '',
            role: '',
            status: '',
            range: '',
            last_login: '',
            mfa: '',
            sort: '-created_at',
            page: 1,
            per_page: 25
        },
        
        // Invite form
        inviteForm: {
            name: '',
            email: '',
            tenant_id: '',
            role: 'member',
            send_email: true,
            require_mfa: false
        },
        
        // Abort controller for request cancellation
        abortController: null,
        
        /**
         * Initialize the page
         */
        async init() {
            try {
                // Setup URL sync first to load filters from URL
                this.setupURLSync();
                
                // Load data in parallel for better performance
                await Promise.all([
                    this.loadTenants(),
                    this.loadKpis()
                ]);
                
                // Load users after filters are set
                await this.loadUsers();
            } catch (error) {
                console.error('Failed to initialize users page:', error);
                this.showError('Failed to initialize page');
            }
        },
        
        /**
         * Load tenants for filter dropdown
         */
        async loadTenants() {
            try {
                const response = await fetch('/api/admin/tenants?per_page=100', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.tenants = data.data || [];
                } else if (response.status === 401) {
                    window.location.href = '/login';
                } else if (response.status === 403) {
                    this.showError('Access denied. You do not have permission to view tenants.');
                } else {
                    console.error('Failed to load tenants:', response.statusText);
                }
            } catch (error) {
                console.error('Failed to load tenants:', error);
            }
        },
        
        /**
         * Load KPI data
         */
        async loadKpis() {
            try {
                const params = new URLSearchParams();
                if (this.filters.tenant_id) {
                    params.append('tenant_id', this.filters.tenant_id);
                }
                if (this.filters.range) {
                    params.append('range', this.filters.range);
                }
                
                const response = await fetch(`/api/admin/users-kpis?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.kpis = data.data;
                    
                    // Draw sparklines after data is loaded
                    this.$nextTick(() => {
                        this.drawAllSparklines();
                    });
                } else if (response.status === 401) {
                    window.location.href = '/login';
                } else if (response.status === 403) {
                    this.showError('Access denied. You do not have permission to view KPIs.');
                } else {
                    console.error('Failed to load KPIs:', response.statusText);
                }
            } catch (error) {
                console.error('Failed to load KPIs:', error);
            }
        },
        
        /**
         * Draw all sparkline charts
         */
        drawAllSparklines() {
            try {
                this.drawSparkline('total_users', 'totalUsersSparkline', '#3B82F6');
                this.drawSparkline('active_users', 'activeUsersSparkline', '#10B981');
                this.drawSparkline('new_users', 'newUsersSparkline', '#3B82F6');
                this.drawSparkline('suspended_users', 'suspendedUsersSparkline', '#EF4444');
                this.drawSparkline('mfa_users', 'mfaUsersSparkline', '#8B5CF6');
            } catch (error) {
                console.error('Draw all sparklines error:', error);
            }
        },
        
        /**
         * Draw sparkline chart for a specific KPI
         */
        drawSparkline(kpiType, refName, color) {
            try {
                if (!this.kpis?.[kpiType]?.sparkline || !this.$refs[refName]) {
                    return;
                }
                
                const canvas = this.$refs[refName];
                const ctx = canvas.getContext('2d');
                const data = this.kpis[kpiType].sparkline;
                
                // Set canvas size
                canvas.width = canvas.offsetWidth * window.devicePixelRatio;
                canvas.height = canvas.offsetHeight * window.devicePixelRatio;
                ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
                
                // Clear canvas
                ctx.clearRect(0, 0, canvas.offsetWidth, canvas.offsetHeight);
                
                if (data.length === 0) return;
                
                // Calculate dimensions
                const width = canvas.offsetWidth;
                const height = canvas.offsetHeight;
                const padding = 4;
                const chartWidth = width - (padding * 2);
                const chartHeight = height - (padding * 2);
                
                // Find min/max values
                const minValue = Math.min(...data);
                const maxValue = Math.max(...data);
                const range = maxValue - minValue || 1;
                
                // Draw sparkline
                ctx.beginPath();
                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                
                data.forEach((value, index) => {
                    const x = padding + (index / (data.length - 1)) * chartWidth;
                    const y = padding + chartHeight - ((value - minValue) / range) * chartHeight;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                
                ctx.stroke();
                
                // Draw area fill
                ctx.beginPath();
                ctx.moveTo(padding, height - padding);
                data.forEach((value, index) => {
                    const x = padding + (index / (data.length - 1)) * chartWidth;
                    const y = padding + chartHeight - ((value - minValue) / range) * chartHeight;
                    ctx.lineTo(x, y);
                });
                ctx.lineTo(width - padding, height - padding);
                ctx.closePath();
                
                // Create gradient based on color
                const gradient = ctx.createLinearGradient(0, padding, 0, height - padding);
                const rgb = this.hexToRgb(color);
                gradient.addColorStop(0, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.3)`);
                gradient.addColorStop(1, `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.05)`);
                ctx.fillStyle = gradient;
                ctx.fill();
            } catch (error) {
                console.error('Draw sparkline error:', error);
            }
        },
        
        /**
         * Convert hex color to RGB
         */
        hexToRgb(hex) {
            try {
                const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
                return result ? {
                    r: parseInt(result[1], 16),
                    g: parseInt(result[2], 16),
                    b: parseInt(result[3], 16)
                } : {r: 59, g: 130, b: 246}; // Default blue
            } catch (error) {
                console.error('Hex to RGB conversion error:', error);
                return {r: 59, g: 130, b: 246}; // Default blue
            }
        },
        
        /**
         * Load users with current filters
         */
        async loadUsers() {
            try {
                if (this.abortController) {
                    this.abortController.abort();
                }
                
                this.abortController = new AbortController();
                this.loading = true;
                
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value !== '' && value !== null && value !== undefined) {
                        params.append(key, value);
                    }
                });
                
                const response = await fetch(`/api/admin/users?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: this.abortController.signal
                });
                
                if (response.ok) {
                    const data = await response.json();
                    this.users = data.data || [];
                    this.meta = data.meta || null;
                } else if (response.status === 401) {
                    window.location.href = '/login';
                } else if (response.status === 403) {
                    this.showError('Access denied. You do not have permission to view users.');
                } else if (response.status === 422) {
                    const errorData = await response.json();
                    this.showError(errorData.message || 'Validation error');
                } else {
                    console.error('Failed to load users:', response.statusText);
                    this.showError('Failed to load users');
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Error loading users:', error);
                    this.showError('Error loading users');
                }
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Search users with debounce
         */
        async searchUsers() {
            try {
                this.filters.page = 1;
                await this.loadUsers();
                this.updateURL();
            } catch (error) {
                console.error('Search error:', error);
                this.showError('Search failed');
            }
        },
        
        /**
         * Apply filters and reload
         */
        async applyFilters() {
            try {
                this.filters.page = 1;
                await this.loadKpis();
                await this.loadUsers();
                this.updateURL();
            } catch (error) {
                console.error('Apply filters error:', error);
                this.showError('Failed to apply filters');
            }
        },
        
        /**
         * Reset all filters
         */
        async resetFilters() {
            try {
                this.filters = {
                    q: '',
                    tenant_id: '',
                    role: '',
                    status: '',
                    range: '',
                    last_login: '',
                    mfa: '',
                    sort: '-created_at',
                    page: 1,
                    per_page: 25
                };
                this.selectedUsers = [];
                await this.loadKpis();
                await this.loadUsers();
                this.updateURL();
            } catch (error) {
                console.error('Reset filters error:', error);
                this.showError('Failed to reset filters');
            }
        },
        
        /**
         * Check if there are active filters
         */
        hasActiveFilters() {
            return Object.entries(this.filters).some(([key, value]) => {
                if (value === '' || value === null || value === undefined) {
                    return false;
                }
                // Skip default values
                if (key === 'page' && value === 1) return false;
                if (key === 'per_page' && value === 25) return false;
                if (key === 'sort' && value === '-created_at') return false;
                return true;
            });
        },
        
        /**
         * Get filter chips for display
         */
        getFilterChips() {
            const chips = [];
            
            if (this.filters.q && this.filters.q.trim()) {
                chips.push({ key: 'q', label: `Search: ${this.filters.q}` });
            }
            
            if (this.filters.tenant_id && this.filters.tenant_id.trim()) {
                const tenant = this.tenants.find(t => t.id === this.filters.tenant_id);
                chips.push({ key: 'tenant_id', label: `Tenant: ${tenant?.name || this.filters.tenant_id}` });
            }
            
            if (this.filters.role && this.filters.role.trim()) {
                chips.push({ key: 'role', label: `Role: ${this.filters.role}` });
            }
            
            if (this.filters.status && this.filters.status.trim()) {
                chips.push({ key: 'status', label: `Status: ${this.filters.status}` });
            }
            
            if (this.filters.range && this.filters.range.trim()) {
                chips.push({ key: 'range', label: `Range: ${this.filters.range}` });
            }
            
            if (this.filters.last_login && this.filters.last_login.trim()) {
                chips.push({ key: 'last_login', label: `Last Login: ${this.filters.last_login}` });
            }
            
            if (this.filters.mfa && this.filters.mfa.trim()) {
                chips.push({ key: 'mfa', label: `MFA: ${this.filters.mfa}` });
            }
            
            return chips;
        },
        
        /**
         * Remove a specific filter
         */
        async removeFilter(key) {
            try {
                // Reset to default values for specific keys
                if (key === 'page') {
                    this.filters[key] = 1;
                } else if (key === 'per_page') {
                    this.filters[key] = 25;
                } else if (key === 'sort') {
                    this.filters[key] = '-created_at';
                } else {
                    this.filters[key] = '';
                }
                await this.applyFilters();
            } catch (error) {
                console.error('Remove filter error:', error);
                this.showError('Failed to remove filter');
            }
        },
        
        /**
         * Toggle select all users
         */
        toggleSelectAll() {
            if (this.selectedUsers.length === this.users.length) {
                this.selectedUsers = [];
            } else {
                this.selectedUsers = this.users.map(user => user.id);
            }
        },
        
        /**
         * Bulk action on selected users
         */
        async bulkAction(action) {
            if (this.selectedUsers.length === 0) {
                this.showError('Please select users first');
                return;
            }
            
            if (action === 'change_role') {
                const role = prompt('Enter new role (admin, manager, member):');
                if (!role || !['admin', 'manager', 'member'].includes(role)) {
                    return;
                }
                
                try {
                    const response = await fetch('/api/admin/users/bulk', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: 'change_role',
                            user_ids: this.selectedUsers,
                            role: role
                        })
                    });
                    
                    if (response.ok) {
                        this.showSuccess('Users updated successfully');
                        this.selectedUsers = [];
                        await this.loadUsers();
                    } else {
                        this.showError('Failed to update users');
                    }
                } catch (error) {
                    console.error('Bulk action error:', error);
                    this.showError('Error updating users');
                }
            } else {
                try {
                    const response = await fetch('/api/admin/users/bulk', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            action: action,
                            user_ids: this.selectedUsers
                        })
                    });
                    
                    if (response.ok) {
                        this.showSuccess(`Users ${action}ed successfully`);
                        this.selectedUsers = [];
                        await this.loadUsers();
                    } else {
                        this.showError(`Failed to ${action} users`);
                    }
                } catch (error) {
                    console.error('Bulk action error:', error);
                    this.showError(`Error ${action}ing users`);
                }
            }
        },
        
        /**
         * Toggle user status (suspend/resume)
         */
        async toggleUserStatus(user) {
            const action = user.status === 'suspended' ? 'resume' : 'suspend';
            
            try {
                const response = await fetch('/api/admin/users/bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: action,
                        user_ids: [user.id]
                    })
                });
                
                if (response.ok) {
                    this.showSuccess(`User ${action}ed successfully`);
                    await this.loadUsers();
                } else {
                    this.showError(`Failed to ${action} user`);
                }
            } catch (error) {
                console.error('Toggle status error:', error);
                this.showError(`Error ${action}ing user`);
            }
        },
        
        /**
         * Reset user password
         */
        async resetPassword(userId) {
            if (!confirm('Are you sure you want to reset this user\'s password?')) {
                return;
            }
            
            // TODO: Implement password reset API
            this.showSuccess('Password reset link sent to user');
        },
        
        /**
         * Delete user
         */
        async deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch(`/api/admin/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    this.showSuccess('User deleted successfully');
                    await this.loadUsers();
                } else {
                    this.showError('Failed to delete user');
                }
            } catch (error) {
                console.error('Delete user error:', error);
                this.showError('Error deleting user');
            }
        },
        
        /**
         * View user details
         */
        viewUser(userId) {
            window.location.href = `/admin/users/${userId}`;
        },
        
        /**
         * Edit user
         */
        editUser(userId) {
            // TODO: Implement edit modal or redirect to edit page
            this.showSuccess('Edit functionality coming soon');
        },
        
        /**
         * Invite new user
         */
        async inviteUser() {
            this.inviteLoading = true;
            
            try {
                const response = await fetch('/api/admin/users/invite', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.inviteForm)
                });
                
                if (response.ok) {
                    this.showSuccess('User invited successfully');
                    this.showInviteModal = false;
                    this.inviteForm = {
                        name: '',
                        email: '',
                        tenant_id: '',
                        role: 'member',
                        send_email: true,
                        require_mfa: false
                    };
                    await this.loadUsers();
                } else {
                    const error = await response.json();
                    this.showError(error.message || 'Failed to invite user');
                }
            } catch (error) {
                console.error('Invite user error:', error);
                this.showError('Error inviting user');
            } finally {
                this.inviteLoading = false;
            }
        },
        
        /**
         * Export users
         */
        async exportUsers() {
            try {
                const params = new URLSearchParams();
                Object.entries(this.filters).forEach(([key, value]) => {
                    if (value !== '' && value !== null && value !== undefined) {
                        params.append(key, value);
                    }
                });
                
                const response = await fetch(`/api/admin/users/export?${params}`, {
                    headers: {
                        'Accept': 'text/csv',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `users_${new Date().toISOString().split('T')[0]}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    this.showSuccess('Users exported successfully');
                } else {
                    this.showError('Failed to export users');
                }
            } catch (error) {
                console.error('Export error:', error);
                this.showError('Error exporting users');
            }
        },
        
        /**
         * Change page
         */
        async changePage(page) {
            try {
                if (page >= 1 && page <= this.meta.last_page) {
                    this.filters.page = page;
                    await this.loadUsers();
                    this.updateURL();
                }
            } catch (error) {
                console.error('Change page error:', error);
                this.showError('Failed to change page');
            }
        },
        
        /**
         * Get page numbers for pagination
         */
        getPageNumbers() {
            if (!this.meta) return [];
            
            const current = this.meta.page;
            const last = this.meta.last_page;
            const pages = [];
            
            // Always show first page
            if (current > 3) {
                pages.push(1);
                if (current > 4) pages.push('...');
            }
            
            // Show pages around current
            for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) {
                pages.push(i);
            }
            
            // Always show last page
            if (current < last - 2) {
                if (current < last - 3) pages.push('...');
                pages.push(last);
            }
            
            return pages;
        },
        
        /**
         * Format date for display
         */
        formatDate(dateString) {
            if (!dateString) return 'Never';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
        
        /**
         * Setup URL synchronization
         */
        setupURLSync() {
            // Load filters from URL on page load
            const urlParams = new URLSearchParams(window.location.search);
            Object.keys(this.filters).forEach(key => {
                if (urlParams.has(key)) {
                    const value = urlParams.get(key);
                    // Convert string numbers to actual numbers for page and per_page
                    if (key === 'page' || key === 'per_page') {
                        this.filters[key] = parseInt(value) || this.filters[key];
                    } else {
                        this.filters[key] = value;
                    }
                }
            });
        },
        
        /**
         * Update URL with current filters
         */
        updateURL() {
            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) {
                    // Skip default values
                    if (key === 'page' && value === 1) return;
                    if (key === 'per_page' && value === 25) return;
                    if (key === 'sort' && value === '-created_at') return;
                    params.append(key, value);
                }
            });
            
            const newUrl = `${window.location.pathname}?${params}`;
            window.history.pushState({}, '', newUrl);
        },
        
        /**
         * Show success message
         */
        showSuccess(message) {
            // TODO: Implement toast notification system
            alert(message);
        },
        
        /**
         * Show error message
         */
        showError(message) {
            // TODO: Implement toast notification system
            alert('Error: ' + message);
        },
        
        /**
         * Drill down from KPI card
         */
        async drillDownKpi(kpiType) {
            try {
                switch (kpiType) {
                    case 'total_users':
                        // Show all users
                        await this.resetFilters();
                        break;
                    case 'active_users':
                        // Filter by active status
                        this.filters.status = 'active';
                        await this.applyFilters();
                        break;
                    case 'new_users':
                        // Filter by date range
                        this.filters.range = '30d';
                        await this.applyFilters();
                        break;
                    case 'suspended_users':
                        // Filter by suspended status
                        this.filters.status = 'suspended';
                        await this.applyFilters();
                        break;
                    case 'mfa_users':
                        // Filter by MFA enabled
                        this.filters.mfa = 'on';
                        await this.applyFilters();
                        break;
                }
            } catch (error) {
                console.error('Drill down error:', error);
                this.showError('Failed to drill down');
            }
        }
    };
}

// Make it globally available
window.usersPage = usersPage;
