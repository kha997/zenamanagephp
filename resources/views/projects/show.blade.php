@extends('layouts.app')

@section('title', 'Chi tiết Dự án')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" id="project-name">Chi tiết Dự án</h1>
        <div class="page-actions">
            <button class="btn btn-outline-secondary" onclick="window.location.href='/projects'">
                <i class="icon-arrow-left"></i> Quay lại
            </button>
            <button class="btn btn-primary" id="edit-project-btn">
                <i class="icon-edit"></i> Chỉnh sửa
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Project Overview -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Tổng quan dự án</h5>
                </div>
                <div class="card-body" id="project-overview">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
            
            <!-- Project Timeline -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Timeline dự án</h5>
                </div>
                <div class="card-body">
                    <div id="project-timeline">
                        <!-- Timeline will be loaded via AJAX -->
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Hoạt động gần đây</h5>
                </div>
                <div class="card-body">
                    <div id="recent-activities">
                        <!-- Activities will be loaded via AJAX -->
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Project Statistics -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Thống kê</h5>
                </div>
                <div class="card-body" id="project-stats">
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
                        <button class="btn btn-outline-primary" onclick="manageComponents()">
                            <i class="icon-layers"></i> Quản lý Components
                        </button>
                        <button class="btn btn-outline-info" onclick="manageTasks()">
                            <i class="icon-check-square"></i> Quản lý Tasks
                        </button>
                        <button class="btn btn-outline-success" onclick="manageDocuments()">
                            <i class="icon-file-text"></i> Quản lý Tài liệu
                        </button>
                        <button class="btn btn-outline-warning" onclick="viewChangeRequests()">
                            <i class="icon-git-branch"></i> Change Requests
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Team Members -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Thành viên dự án</h5>
                </div>
                <div class="card-body" id="team-members">
                    <!-- Team members will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class ProjectDetailManager {
    constructor() {
        this.projectId = this.getProjectIdFromUrl();
        this.loadProjectDetails();
    }

    getProjectIdFromUrl() {
        const pathParts = window.location.pathname.split('/');
        return pathParts[pathParts.length - 1];
    }

    async loadProjectDetails() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${this.projectId}`);
            
            if (response.status === 'success') {
                this.renderProjectDetails(response.data);
                this.loadProjectStats();
                this.loadRecentActivities();
                this.loadTeamMembers();
                this.loadProjectTimeline();
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải thông tin dự án', 'error');
        }
    }

    renderProjectDetails(project) {
        // Update page title
        document.getElementById('project-name').textContent = project.name;
        document.getElementById('edit-project-btn').onclick = () => {
            window.location.href = `/projects/${project.id}/edit`;
        };
        
        // Render overview
        const overview = document.getElementById('project-overview');
        overview.innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Tên dự án:</label>
                        <span>${project.name}</span>
                    </div>
                    <div class="info-item">
                        <label>Trạng thái:</label>
                        <span class="badge badge-${this.getStatusColor(project.status)}">
                            ${this.getStatusText(project.status)}
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Ngày bắt đầu:</label>
                        <span>${zenaApp.formatDate(project.start_date)}</span>
                    </div>
                    <div class="info-item">
                        <label>Ngày kết thúc:</label>
                        <span>${zenaApp.formatDate(project.end_date)}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Tiến độ:</label>
                        <div class="progress-container">
                            <div class="progress">
                                <div class="progress-bar" style="width: ${project.progress}%"></div>
                            </div>
                            <span class="progress-text">${project.progress}%</span>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Chi phí dự kiến:</label>
                        <span>${zenaApp.formatCurrency(project.planned_cost)}</span>
                    </div>
                    <div class="info-item">
                        <label>Chi phí thực tế:</label>
                        <span>${zenaApp.formatCurrency(project.actual_cost)}</span>
                    </div>
                </div>
            </div>
            ${project.description ? `
                <div class="mt-3">
                    <label>Mô tả:</label>
                    <p class="text-muted">${project.description}</p>
                </div>
            ` : ''}
        `;
    }

    async loadProjectStats() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${this.projectId}/stats`);
            
            if (response.status === 'success') {
                const stats = response.data;
                const statsContainer = document.getElementById('project-stats');
                
                statsContainer.innerHTML = `
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="icon-layers"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.components_count}</div>
                            <div class="stat-label">Components</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="icon-check-square"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.tasks_count}</div>
                            <div class="stat-label">Tasks</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="icon-file-text"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.documents_count}</div>
                            <div class="stat-label">Tài liệu</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">
                            <i class="icon-git-branch"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.change_requests_count}</div>
                            <div class="stat-label">Change Requests</div>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading project stats:', error);
        }
    }

    async loadRecentActivities() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${this.projectId}/activities?limit=10`);
            
            if (response.status === 'success') {
                const activities = response.data;
                const container = document.getElementById('recent-activities');
                
                if (activities.length === 0) {
                    container.innerHTML = '<p class="text-muted">Chưa có hoạt động nào</p>';
                    return;
                }
                
                container.innerHTML = activities.map(activity => `
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="icon-${this.getActivityIcon(activity.type)}"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-title">${activity.description}</div>
                            <div class="activity-meta">
                                <span class="activity-user">${activity.user_name}</span>
                                <span class="activity-time">${zenaApp.formatDateTime(activity.created_at)}</span>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading recent activities:', error);
        }
    }

    async loadTeamMembers() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${this.projectId}/members`);
            
            if (response.status === 'success') {
                const members = response.data;
                const container = document.getElementById('team-members');
                
                if (members.length === 0) {
                    container.innerHTML = '<p class="text-muted">Chưa có thành viên nào</p>';
                    return;
                }
                
                container.innerHTML = members.map(member => `
                    <div class="member-item">
                        <div class="member-avatar">
                            <img src="${member.avatar || '/images/default-avatar.png'}" alt="${member.name}">
                        </div>
                        <div class="member-info">
                            <div class="member-name">${member.name}</div>
                            <div class="member-role">${member.role_name}</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading team members:', error);
        }
    }

    async loadProjectTimeline() {
        try {
            const response = await zenaApp.apiCall('GET', `/api/v1/projects/${this.projectId}/timeline`);
            
            if (response.status === 'success') {
                const timeline = response.data;
                const container = document.getElementById('project-timeline');
                
                if (timeline.length === 0) {
                    container.innerHTML = '<p class="text-muted">Chưa có dữ liệu timeline</p>';
                    return;
                }
                
                container.innerHTML = timeline.map(item => `
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-title">${item.title}</div>
                            <div class="timeline-description">${item.description}</div>
                            <div class="timeline-date">${zenaApp.formatDate(item.date)}</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading project timeline:', error);
        }
    }

    getStatusColor(status) {
        const colors = {
            'planning': 'info',
            'active': 'success',
            'on_hold': 'warning',
            'completed': 'primary',
            'cancelled': 'danger'
        };
        return colors[status] || 'secondary';
    }

    getStatusText(status) {
        const texts = {
            'planning': 'Lập kế hoạch',
            'active': 'Đang thực hiện',
            'on_hold': 'Tạm dừng',
            'completed': 'Hoàn thành',
            'cancelled': 'Đã hủy'
        };
        return texts[status] || status;
    }

    getActivityIcon(type) {
        const icons = {
            'task_created': 'plus',
            'task_completed': 'check',
            'document_uploaded': 'upload',
            'change_request': 'git-branch',
            'comment_added': 'message-circle'
        };
        return icons[type] || 'activity';
    }
}

// Global functions
function manageComponents() {
    window.location.href = `/projects/${projectDetailManager.projectId}/components`;
}

function manageTasks() {
    window.location.href = `/projects/${projectDetailManager.projectId}/tasks`;
}

function manageDocuments() {
    window.location.href = `/projects/${projectDetailManager.projectId}/documents`;
}

function viewChangeRequests() {
    window.location.href = `/projects/${projectDetailManager.projectId}/change-requests`;
}

// Initialize when page loads
let projectDetailManager;
document.addEventListener('DOMContentLoaded', () => {
    projectDetailManager = new ProjectDetailManager();
});
</script>
@endsection