import './bootstrap';
import './alpine-data-functions';

// Z.E.N.A Project Management - Main JavaScript
class ZenaApp {
    constructor() {
        this.apiBaseUrl = '/api/v1';
        this.token = localStorage.getItem('auth_token');
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkAuth();
        this.setupAjaxDefaults();
        this.setupAxiosInterceptors();
    }

    setupEventListeners() {
        // Mobile menu toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        
        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        // Logout functionality
        const logoutBtn = document.getElementById('logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.logout();
            });
        }

        // Form submissions
        const forms = document.querySelectorAll('form[data-ajax]');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleFormSubmit(form);
            });
        });

        // Dropdown toggles
        this.setupDropdowns();
    }

    setupDropdowns() {
        const dropdownToggles = document.querySelectorAll('[id$="-toggle"]');
        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const menuId = toggle.id.replace('-toggle', '-menu');
                const menu = document.getElementById(menuId);
                if (menu) {
                    menu.classList.toggle('show');
                }
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        });
    }

    setupAjaxDefaults() {
        // Setup default headers for all AJAX requests
        const token = this.getToken();
        const tenantId = this.getTenantId();
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
        if (tenantId) {
            axios.defaults.headers.common['X-Tenant-ID'] = tenantId;
        }
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.headers.common['Content-Type'] = 'application/json';
    }

    setupAxiosInterceptors() {
        // Request interceptor
        axios.interceptors.request.use(
            (config) => {
                const token = this.getToken();
                const tenantId = this.getTenantId();
                if (token) {
                    config.headers.Authorization = `Bearer ${token}`;
                }
                if (tenantId) {
                    config.headers['X-Tenant-ID'] = tenantId;
                }
                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );

        // Response interceptor
        axios.interceptors.response.use(
            (response) => {
                return response;
            },
            (error) => {
                if (error.response?.status === 401) {
                    this.removeToken();
                    window.location.href = '/login';
                } else if (error.response?.status === 403) {
                    this.showError('Bạn không có quyền thực hiện thao tác này');
                } else if (error.response?.status === 404) {
                    this.showError('Không tìm thấy tài nguyên yêu cầu');
                } else if (error.response?.status >= 500) {
                    this.showError('Lỗi máy chủ, vui lòng thử lại sau');
                }
                return Promise.reject(error);
            }
        );
    }

    checkAuth() {
        const token = this.getToken();
        const currentPath = window.location.pathname;
        
        if (!token && !currentPath.includes('/login')) {
            window.location.href = '/login';
        }
    }

    getToken() {
        return localStorage.getItem('auth_token');
    }

    getTenantId() {
        const raw = localStorage.getItem('user_data');
        if (!raw) {
            return '';
        }

        try {
            const user = JSON.parse(raw);
            return user?.tenant_id || '';
        } catch (error) {
            return '';
        }
    }

    setToken(token) {
        localStorage.setItem('auth_token', token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        const tenantId = this.getTenantId();
        if (tenantId) {
            axios.defaults.headers.common['X-Tenant-ID'] = tenantId;
        }
    }

    removeToken() {
        localStorage.removeItem('auth_token');
        delete axios.defaults.headers.common['Authorization'];
        delete axios.defaults.headers.common['X-Tenant-ID'];
    }

    // Generic API call method
    async apiCall(method, url, data = null, config = {}) {
        try {
            const response = await axios({
                method,
                url: url.startsWith('http') ? url : `${this.apiBaseUrl}${url}`,
                data,
                ...config
            });
            return response.data;
        } catch (error) {
            console.error('API call error:', error);
            throw error;
        }
    }

    async login(email, password) {
        try {
            const response = await this.apiCall('POST', '/auth/login', {
                email,
                password
            });

            if (response.status === 'success') {
                this.setToken(response.data.token);
                window.location.href = '/dashboard';
                return { success: true };
            } else {
                return { success: false, message: response.message };
            }
        } catch (error) {
            console.error('Login error:', error);
            return { 
                success: false, 
                message: error.response?.data?.message || 'Đăng nhập thất bại' 
            };
        }
    }

    async logout() {
        try {
            await this.apiCall('POST', '/auth/logout');
        } catch (error) {
            console.error('Logout error:', error);
        } finally {
            this.removeToken();
            window.location.href = '/login';
        }
    }

    async handleFormSubmit(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const action = form.getAttribute('action');
        const method = form.getAttribute('method') || 'POST';

        try {
            this.showLoading(form);
            
            const response = await this.apiCall(method, action, data);

            if (response.status === 'success') {
                this.showSuccess('Thao tác thành công!');
                
                // Redirect if specified
                const redirect = form.getAttribute('data-redirect');
                if (redirect) {
                    setTimeout(() => {
                        window.location.href = redirect;
                    }, 1000);
                }
            } else {
                this.showError(response.message || 'Có lỗi xảy ra');
            }
        } catch (error) {
            console.error('Form submission error:', error);
            this.showError(error.response?.data?.message || 'Có lỗi xảy ra');
        } finally {
            this.hideLoading(form);
        }
    }

    showLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('data-original-text', submitBtn.innerHTML);
            submitBtn.innerHTML = '<span class="loading-spinner"></span> Đang xử lý...';
        }
    }

    hideLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'Gửi';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type = 'info') {
        // Remove existing notifications of the same type
        document.querySelectorAll(`.notification-${type}`).forEach(n => n.remove());

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span>${message}</span>
                <button class="notification-close">&times;</button>
            </div>
        `;

        // Add to page
        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);

        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }

    // Utility methods
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('vi-VN');
    }

    formatCurrency(amount) {
        if (!amount) return '0 ₫';
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }

    formatProgress(progress) {
        return `${Math.round(progress || 0)}%`;
    }

    // Real-time notifications
    async loadNotifications() {
        try {
            const response = await this.apiCall('GET', '/notifications');
            if (response.status === 'success') {
                this.updateNotificationBadge(response.data.unread_count);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    updateNotificationBadge(count) {
        const badges = document.querySelectorAll('.badge-danger');
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        });
    }

    // File upload với progress tracking
    async uploadFile(file, options = {}) {
        const {
            disk = 'documents',
            directory = null,
            filename = null,
            onProgress = null
        } = options;

        const formData = new FormData();
        formData.append('file', file);
        if (disk) formData.append('disk', disk);
        if (directory) formData.append('directory', directory);
        if (filename) formData.append('filename', filename);

        try {
            const response = await this.apiCall('POST', '/files/upload', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                onUploadProgress: (progressEvent) => {
                    if (onProgress) {
                        const percentCompleted = Math.round(
                            (progressEvent.loaded * 100) / progressEvent.total
                        );
                        onProgress(percentCompleted);
                    }
                }
            });
            return response;
        } catch (error) {
            console.error('File upload error:', error);
            throw error;
        }
    }

    // Xóa file
    async deleteFile(path, disk = 'documents') {
        try {
            const response = await this.apiCall('DELETE', '/files/delete', {
                path,
                disk
            });
            return response;
        } catch (error) {
            console.error('File delete error:', error);
            throw error;
        }
    }

    // Lấy thông tin file
    async getFileInfo(path, disk = 'documents') {
        try {
            const response = await this.apiCall('GET', '/files/info', null, {
                params: { path, disk }
            });
            return response;
        } catch (error) {
            console.error('Get file info error:', error);
            throw error;
        }
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.zenaApp = new ZenaApp();
    
    // Load notifications every 30 seconds
    setInterval(() => {
        if (window.zenaApp.getToken()) {
            window.zenaApp.loadNotifications();
        }
    }, 30000);
});

// Export for use in other modules
window.ZenaApp = ZenaApp;
