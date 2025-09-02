@extends('layouts.app')

@section('title', 'Quản lý Thông báo')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Quản lý Thông báo</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="markAllAsRead()">
                <i class="icon-check-circle"></i> Đánh dấu tất cả đã đọc
            </button>
            <button class="btn btn-primary" onclick="openNotificationSettings()">
                <i class="icon-settings"></i> Cài đặt
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Notification Filters -->
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select" id="priority-filter">
                                <option value="">Tất cả mức độ</option>
                                <option value="critical">Khẩn cấp</option>
                                <option value="normal">Bình thường</option>
                                <option value="low">Thấp</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="status-filter">
                                <option value="">Tất cả trạng thái</option>
                                <option value="unread">Chưa đọc</option>
                                <option value="read">Đã đọc</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="channel-filter">
                                <option value="">Tất cả kênh</option>
                                <option value="inapp">Trong ứng dụng</option>
                                <option value="email">Email</option>
                                <option value="webhook">Webhook</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-primary w-100" onclick="applyFilters()">
                                <i class="icon-filter"></i> Lọc
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Danh sách thông báo</h5>
                </div>
                <div class="card-body p-0">
                    <div id="notifications-list">
                        <!-- Notifications will be loaded via AJAX -->
                    </div>
                    
                    <!-- Pagination -->
                    <div class="card-footer">
                        <div id="notifications-pagination">
                            <!-- Pagination will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Notification Stats -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thống kê</h5>
                </div>
                <div class="card-body" id="notification-stats">
                    <!-- Stats will be loaded via AJAX -->
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Thao tác nhanh</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="testNotification()">
                            <i class="icon-bell"></i> Gửi thông báo thử nghiệm
                        </button>
                        <button class="btn btn-outline-info" onclick="exportNotifications()">
                            <i class="icon-download"></i> Xuất báo cáo
                        </button>
                        <button class="btn btn-outline-success" onclick="manageRules()">
                            <i class="icon-settings"></i> Quản lý quy tắc
                        </button>
                        <button class="btn btn-outline-warning" onclick="clearOldNotifications()">
                            <i class="icon-trash-2"></i> Xóa thông báo cũ
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Hoạt động gần đây</h5>
                </div>
                <div class="card-body" id="recent-activity">
                    <!-- Recent activity will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification Settings Modal -->
<div class="modal fade" id="settingsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cài đặt thông báo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Cài đặt chung</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable-notifications">
                                <label class="form-check-label" for="enable-notifications">
                                    Bật thông báo
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable-email">
                                <label class="form-check-label" for="enable-email">
                                    Gửi email thông báo
                                </label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enable-sound">
                                <label class="form-check-label" for="enable-sound">
                                    Âm thanh thông báo
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Mức độ ưu tiên</h6>
                        <div class="mb-3">
                            <label class="form-label">Mức độ tối thiểu:</label>
                            <select class="form-select" id="min-priority">
                                <option value="low">Thấp</option>
                                <option value="normal">Bình thường</option>
                                <option value="critical">Khẩn cấp</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Thời gian im lặng:</label>
                            <div class="row">
                                <div class="col-6">
                                    <input type="time" class="form-control" id="quiet-start" placeholder="Từ">
                                </div>
                                <div class="col-6">
                                    <input type="time" class="form-control" id="quiet-end" placeholder="Đến">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <!-- Notification Rules -->
                <div class="notification-rules">
                    <h6>Quy tắc thông báo</h6>
                    <div id="notification-rules-list">
                        <!-- Rules will be loaded via AJAX -->
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="addNotificationRule()">
                        <i class="icon-plus"></i> Thêm quy tắc
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveNotificationSettings()">Lưu cài đặt</button>
            </div>
        </div>
    </div>
</div>

<!-- Test Notification Modal -->
<div class="modal fade" id="testNotificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gửi thông báo thử nghiệm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="test-notification-form">
                    <div class="mb-3">
                        <label class="form-label">Tiêu đề:</label>
                        <input type="text" class="form-control" id="test-title" placeholder="Nhập tiêu đề thông báo...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nội dung:</label>
                        <textarea class="form-control" id="test-body" rows="3" placeholder="Nhập nội dung thông báo..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mức độ ưu tiên:</label>
                        <select class="form-select" id="test-priority">
                            <option value="low">Thấp</option>
                            <option value="normal" selected>Bình thường</option>
                            <option value="critical">Khẩn cấp</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kênh gửi:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="test-inapp" checked>
                            <label class="form-check-label" for="test-inapp">
                                Trong ứng dụng
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="test-email">
                            <label class="form-check-label" for="test-email">
                                Email
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="sendTestNotification()">Gửi thông báo</button>
            </div>
        </div>
    </div>
</div>

<script>
class NotificationsManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {
            priority: '',
            status: '',
            channel: ''
        };
        this.loadNotifications();
        this.loadNotificationStats();
        this.loadRecentActivity();
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            this.loadNotifications();
            this.loadNotificationStats();
        }, 30000);
        
        // Real-time notifications via WebSocket (if available)
        if (window.Echo) {
            window.Echo.private(`user.${zenaApp.getCurrentUser().id}`)
                .notification((notification) => {
                    this.handleNewNotification(notification);
                });
        }
    }

    async loadNotifications() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                ...this.filters
            });
            
            const response = await zenaApp.apiCall('GET', `/api/v1/notifications?${params}`);
            
            if (response.status === 'success') {
                this.renderNotifications(response.data.data);
                this.renderPagination(response.data);
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách thông báo', 'error');
        }
    }

    renderNotifications(notifications) {
        const container = document.getElementById('notifications-list');
        
        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="icon-bell-off"></i>
                    </div>
                    <h6>Không có thông báo</h6>
                    <p class="text-muted">Bạn chưa có thông báo nào.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.read_at ? 'read' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon priority-${notification.priority}">
                    <i class="${this.getPriorityIcon(notification.priority)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-header">
                        <h6 class="notification-title">${notification.title}</h6>
                        <div class="notification-meta">
                            <span class="notification-time">${zenaApp.formatDateTime(notification.created_at)}</span>
                            <span class="badge badge-${this.getPriorityColor(notification.priority)}">
                                ${this.getPriorityText(notification.priority)}
                            </span>
                        </div>
                    </div>
                    <div class="notification-body">
                        ${notification.body}
                    </div>
                    ${notification.link_url ? `
                        <div class="notification-actions">
                            <a href="${notification.link_url}" class="btn btn-sm btn-outline-primary">Xem chi tiết</a>
                        </div>
                    ` : ''}
                </div>
                <div class="notification-controls">
                    ${!notification.read_at ? `
                        <button class="btn btn-sm btn-outline-success" onclick="markAsRead(${notification.id})" title="Đánh dấu đã đọc">
                            <i class="icon-check"></i>
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteNotification(${notification.id})" title="Xóa">
                        <i class="icon-trash-2"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }

    renderPagination(data) {
        const container = document.getElementById('notifications-pagination');
        
        if (data.last_page <= 1) {
            container.innerHTML = '';
            return;
        }
        
        let pagination = '<nav><ul class="pagination justify-content-center">';
        
        // Previous button
        if (data.current_page > 1) {
            pagination += `<li class="page-item"><a class="page-link" href="#" onclick="notificationsManager.goToPage(${data.current_page - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        for (let i = Math.max(1, data.current_page - 2); i <= Math.min(data.last_page, data.current_page + 2); i++) {
            pagination += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" onclick="notificationsManager.goToPage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            pagination += `<li class="page-item"><a class="page-link" href="#" onclick="notificationsManager.goToPage(${data.current_page + 1})">Sau</a></li>`;
        }
        
        pagination += '</ul></nav>';
        container.innerHTML = pagination;
    }

    async loadNotificationStats() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/notifications/stats');
            
            if (response.status === 'success') {
                this.renderNotificationStats(response.data);
            }
        } catch (error) {
            console.error('Error loading notification stats:', error);
        }
    }

    renderNotificationStats(stats) {
        const container = document.getElementById('notification-stats');
        container.innerHTML = `
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">${stats.total}</div>
                    <div class="stat-label">Tổng số</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${stats.unread}</div>
                    <div class="stat-label">Chưa đọc</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${stats.critical}</div>
                    <div class="stat-label">Khẩn cấp</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">${stats.today}</div>
                    <div class="stat-label">Hôm nay</div>
                </div>
            </div>
        `;
    }

    async loadRecentActivity() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/notifications/recent-activity');
            
            if (response.status === 'success') {
                this.renderRecentActivity(response.data);
            }
        } catch (error) {
            console.error('Error loading recent activity:', error);
        }
    }

    renderRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        
        if (activities.length === 0) {
            container.innerHTML = '<p class="text-muted">Không có hoạt động gần đây</p>';
            return;
        }
        
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-time">
                    <small class="text-muted">${zenaApp.formatDateTime(activity.created_at)}</small>
                </div>
                <div class="activity-content">
                    ${activity.description}
                </div>
            </div>
        `).join('');
    }

    handleNewNotification(notification) {
        // Add new notification to the top of the list
        this.loadNotifications();
        this.loadNotificationStats();
        
        // Show browser notification if supported
        if (Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.body,
                icon: '/favicon.ico'
            });
        }
        
        // Play sound if enabled
        if (this.isSoundEnabled()) {
            this.playNotificationSound();
        }
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadNotifications();
    }

    // Utility methods
    getPriorityIcon(priority) {
        const icons = {
            'low': 'icon-info',
            'normal': 'icon-bell',
            'critical': 'icon-alert-triangle'
        };
        return icons[priority] || 'icon-bell';
    }

    getPriorityColor(priority) {
        const colors = {
            'low': 'success',
            'normal': 'primary',
            'critical': 'danger'
        };
        return colors[priority] || 'primary';
    }

    getPriorityText(priority) {
        const texts = {
            'low': 'Thấp',
            'normal': 'Bình thường',
            'critical': 'Khẩn cấp'
        };
        return texts[priority] || priority;
    }

    isSoundEnabled() {
        return localStorage.getItem('notification-sound') === 'true';
    }

    playNotificationSound() {
        const audio = new Audio('/sounds/notification.mp3');
        audio.play().catch(() => {
            // Ignore errors if sound can't be played
        });
    }
}

// Global functions
function applyFilters() {
    notificationsManager.filters = {
        priority: document.getElementById('priority-filter').value,
        status: document.getElementById('status-filter').value,
        channel: document.getElementById('channel-filter').value
    };
    notificationsManager.currentPage = 1;
    notificationsManager.loadNotifications();
}

async function markAsRead(notificationId) {
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/notifications/${notificationId}/read`);
        
        if (response.status === 'success') {
            notificationsManager.loadNotifications();
            notificationsManager.loadNotificationStats();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi đánh dấu đã đọc', 'error');
    }
}

async function markAllAsRead() {
    try {
        const response = await zenaApp.apiCall('POST', '/api/v1/notifications/mark-all-read');
        
        if (response.status === 'success') {
            zenaApp.showNotification('Đã đánh dấu tất cả thông báo là đã đọc', 'success');
            notificationsManager.loadNotifications();
            notificationsManager.loadNotificationStats();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi đánh dấu tất cả đã đọc', 'error');
    }
}

async function deleteNotification(notificationId) {
    if (!confirm('Bạn có chắc chắn muốn xóa thông báo này?')) {
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('DELETE', `/api/v1/notifications/${notificationId}`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Đã xóa thông báo', 'success');
            notificationsManager.loadNotifications();
            notificationsManager.loadNotificationStats();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xóa thông báo', 'error');
    }
}

function openNotificationSettings() {
    loadNotificationSettings();
    new bootstrap.Modal(document.getElementById('settingsModal')).show();
}

async function loadNotificationSettings() {
    try {
        const response = await zenaApp.apiCall('GET', '/api/v1/notifications/settings');
        
        if (response.status === 'success') {
            const settings = response.data;
            
            // Populate form fields
            document.getElementById('enable-notifications').checked = settings.enabled;
            document.getElementById('enable-email').checked = settings.email_enabled;
            document.getElementById('enable-sound').checked = settings.sound_enabled;
            document.getElementById('min-priority').value = settings.min_priority;
            document.getElementById('quiet-start').value = settings.quiet_start || '';
            document.getElementById('quiet-end').value = settings.quiet_end || '';
            
            // Load notification rules
            loadNotificationRules();
        }
    } catch (error) {
        console.error('Error loading notification settings:', error);
    }
}

async function saveNotificationSettings() {
    const settings = {
        enabled: document.getElementById('enable-notifications').checked,
        email_enabled: document.getElementById('enable-email').checked,
        sound_enabled: document.getElementById('enable-sound').checked,
        min_priority: document.getElementById('min-priority').value,
        quiet_start: document.getElementById('quiet-start').value,
        quiet_end: document.getElementById('quiet-end').value
    };
    
    try {
        const response = await zenaApp.apiCall('POST', '/api/v1/notifications/settings', settings);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Đã lưu cài đặt thông báo', 'success');
            bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
            
            // Update local storage for sound setting
            localStorage.setItem('notification-sound', settings.sound_enabled);
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi lưu cài đặt', 'error');
    }
}

function testNotification() {
    new bootstrap.Modal(document.getElementById('testNotificationModal')).show();
}

async function sendTestNotification() {
    const title = document.getElementById('test-title').value;
    const body = document.getElementById('test-body').value;
    const priority = document.getElementById('test-priority').value;
    const channels = [];
    
    if (document.getElementById('test-inapp').checked) channels.push('inapp');
    if (document.getElementById('test-email').checked) channels.push('email');
    
    if (!title || !body) {
        zenaApp.showNotification('Vui lòng nhập tiêu đề và nội dung', 'warning');
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('POST', '/api/v1/notifications/test', {
            title: title,
            body: body,
            priority: priority,
            channels: channels
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification('Đã gửi thông báo thử nghiệm', 'success');
            bootstrap.Modal.getInstance(document.getElementById('testNotificationModal')).hide();
            document.getElementById('test-notification-form').reset();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi gửi thông báo thử nghiệm', 'error');
    }
}

function exportNotifications() {
    const params = new URLSearchParams(notificationsManager.filters);
    window.open(`/api/v1/notifications/export?${params}`, '_blank');
}

function manageRules() {
    window.location.href = '/notifications/rules';
}

async function clearOldNotifications() {
    if (!confirm('Bạn có chắc chắn muốn xóa các thông báo cũ (hơn 30 ngày)?')) {
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('POST', '/api/v1/notifications/clear-old');
        
        if (response.status === 'success') {
            zenaApp.showNotification(`Đã xóa ${response.data.deleted_count} thông báo cũ`, 'success');
            notificationsManager.loadNotifications();
            notificationsManager.loadNotificationStats();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xóa thông báo cũ', 'error');
    }
}

// Request notification permission on page load
document.addEventListener('DOMContentLoaded', function() {
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
    
    // Initialize notifications manager
    window.notificationsManager = new NotificationsManager();
});
</script>

<style>
.notification-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: #f0f8ff;
    border-left: 4px solid #0d6efd;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    flex-shrink: 0;
}

.notification-icon.priority-low {
    background-color: #d1e7dd;
    color: #0f5132;
}

.notification-icon.priority-normal {
    background-color: #cff4fc;
    color: #055160;
}

.notification-icon.priority-critical {
    background-color: #f8d7da;
    color: #721c24;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.5rem;
}

.notification-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.notification-meta {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notification-time {
    font-size: 0.875rem;
    color: #6c757d;
}

.notification-body {
    color: #495057;
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.notification-actions {
    margin-top: 0.5rem;
}

.notification-controls {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-left: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

.stat-item {
    text-align: center;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #0d6efd;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.activity-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #e9ecef;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-time {
    margin-bottom: 0.25rem;
}

.activity-content {
    font-size: 0.875rem;
    color: #495057;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 3rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.empty-state h6 {
    color: #6c757d;
    margin-bottom: 0.5rem;
}

.notification-rules {
    margin-top: 1rem;
}

.rule-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border: 1px solid #e9ecef;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
}

.rule-content {
    flex: 1;
}

.rule-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.rule-description {
    font-size: 0.875rem;
    color: #6c757d;
}

.rule-actions {
    display: flex;
    gap: 0.25rem;
}
</style>
@endsection