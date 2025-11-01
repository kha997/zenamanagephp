/**
 * Tenants API Service Layer
 * Handles all API calls for tenants management
 */

class TenantsApiService {
    constructor() {
        this.baseUrl = '/api/admin/tenants';
        this.defaultHeaders = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    /**
     * Get tenants list with search, filters, and pagination
     */
    async getTenants(params = {}) {
        const queryParams = new URLSearchParams();
        
        // Add all parameters
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                queryParams.append(key, params[key]);
            }
        });

        const url = `${this.baseUrl}?${queryParams.toString()}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                
                // Try to parse error details for 422
                if (response.status === 422) {
                    try {
                        const errorData = await response.json();
                        if (errorData.error?.message) {
                            errorMessage = errorData.error.message;
                        }
                        if (errorData.error?.details) {
                            console.warn('[tenants] 422 details:', errorData.error.details);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
                
                throw new Error(errorMessage);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to fetch tenants:', error);
            throw error;
        }
    }

    /**
     * Get tenant details by ID
     */
    async getTenant(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'GET',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Tenant not found');
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to fetch tenant:', error);
            throw error;
        }
    }

    /**
     * Create a new tenant
     */
    async createTenant(tenantData) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify(tenantData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to create tenant:', error);
            throw error;
        }
    }

    /**
     * Update tenant
     */
    async updateTenant(id, tenantData) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'PATCH',
                headers: this.defaultHeaders,
                body: JSON.stringify(tenantData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to update tenant:', error);
            throw error;
        }
    }

    /**
     * Delete tenant
     */
    async deleteTenant(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'DELETE',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to delete tenant:', error);
            throw error;
        }
    }

    /**
     * Enable tenant
     */
    async enableTenant(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:enable`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to enable tenant:', error);
            throw error;
        }
    }

    /**
     * Disable tenant
     */
    async disableTenant(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:disable`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to disable tenant:', error);
            throw error;
        }
    }

    /**
     * Change tenant plan
     */
    async changeTenantPlan(id, plan) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:change-plan`, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify({ plan })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to change tenant plan:', error);
            throw error;
        }
    }

    /**
     * Bulk actions
     */
    async bulkAction(action, ids, plan = null) {
        try {
            const payload = { action, ids };
            if (plan) {
                payload.plan = plan;
            }

            const response = await fetch(`${this.baseUrl}:bulk`, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to perform bulk action:', error);
            throw error;
        }
    }

    /**
     * Export tenants
     */
    async exportTenants(params = {}) {
        const queryParams = new URLSearchParams();
        
        // Add all parameters
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                queryParams.append(key, params[key]);
            }
        });

        const url = `${this.baseUrl}/export?${queryParams.toString()}`;
        
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    throw new Error(`Rate limit exceeded. Please try again in ${retryAfter} seconds.`);
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Handle file download
            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `tenants-export-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);

            return { success: true };
        } catch (error) {
            console.error('Failed to export tenants:', error);
            throw error;
        }
    }

    /**
     * Export selected tenants
     */
    async exportSelectedTenants(ids) {
        try {
            const response = await fetch(`${this.baseUrl}/export?ids=${ids.join(',')}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    throw new Error(`Rate limit exceeded. Please try again in ${retryAfter} seconds.`);
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Handle file download
            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `tenants-selected-export-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(downloadUrl);
            document.body.removeChild(a);

            return { success: true };
        } catch (error) {
            console.error('Failed to export selected tenants:', error);
            throw error;
        }
    }
}

// Create global instance
window.tenantsApi = new TenantsApiService();
