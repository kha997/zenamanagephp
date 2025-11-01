/**
 * Users API Service Layer
 * Handles all API calls for users management
 */

class UsersApiService {
    constructor() {
        this.baseUrl = '/api/admin/users';
        this.defaultHeaders = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    /**
     * Get users list with search, filters, and pagination
     */
    async getUsers(params = {}) {
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
                            console.warn('[users] 422 details:', errorData.error.details);
                        }
                    } catch (e) {
                        // Ignore JSON parse errors
                    }
                }
                
                throw new Error(errorMessage);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to fetch users:', error);
            throw error;
        }
    }

    /**
     * Get user details by ID
     */
    async getUser(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'GET',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('User not found');
                }
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to fetch user:', error);
            throw error;
        }
    }

    /**
     * Create a new user
     */
    async createUser(userData) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify(userData)
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to create user:', error);
            throw error;
        }
    }

    /**
     * Update user
     */
    async updateUser(id, userData) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'PATCH',
                headers: this.defaultHeaders,
                body: JSON.stringify(userData)
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to update user:', error);
            throw error;
        }
    }

    /**
     * Delete user
     */
    async deleteUser(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}`, {
                method: 'DELETE',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to delete user:', error);
            throw error;
        }
    }

    /**
     * Enable user
     */
    async enableUser(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:enable`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to enable user:', error);
            throw error;
        }
    }

    /**
     * Disable user
     */
    async disableUser(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:disable`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to disable user:', error);
            throw error;
        }
    }

    /**
     * Unlock user
     */
    async unlockUser(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:unlock`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to unlock user:', error);
            throw error;
        }
    }

    /**
     * Change user role
     */
    async changeUserRole(id, role) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:change-role`, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify({ role })
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to change user role:', error);
            throw error;
        }
    }

    /**
     * Force MFA
     */
    async forceMfa(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:force-mfa`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to force MFA:', error);
            throw error;
        }
    }

    /**
     * Send reset password link
     */
    async sendResetLink(id) {
        try {
            const response = await fetch(`${this.baseUrl}/${id}:send-reset-link`, {
                method: 'POST',
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to send reset link:', error);
            throw error;
        }
    }

    /**
     * Bulk actions
     */
    async bulkAction(action, ids, options = {}) {
        try {
            const payload = { action, ids, ...options };

            const response = await fetch(`${this.baseUrl}:bulk`, {
                method: 'POST',
                headers: this.defaultHeaders,
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid parameters';
                    const details = errorData.error?.details;
                    throw new Error(details ? `${message}: ${details}` : message);
                }
                
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.error?.message || `HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Failed to perform bulk action:', error);
            throw error;
        }
    }

    /**
     * Export users
     */
    async exportUsers(params = {}) {
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
                
                // Handle 404/422 with friendly messages
                if (response.status === 404) {
                    throw new Error('Export endpoint not found. Please contact support.');
                }
                
                if (response.status === 422) {
                    const errorData = await response.json().catch(() => ({}));
                    const message = errorData.error?.message || 'Invalid export parameters';
                    throw new Error(`Export failed: ${message}`);
                }
                
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Handle file download
            const blob = await response.blob();
            const downloadUrl = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = downloadUrl;
            a.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(downloadUrl);

            return { success: true };
        } catch (error) {
            console.error('Failed to export users:', error);
            throw error;
        }
    }
}

// Create global instance
window.usersApi = new UsersApiService();
