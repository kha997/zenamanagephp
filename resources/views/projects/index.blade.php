@extends('layouts.app')

@section('title', 'Quản lý Dự án')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Quản lý Dự án</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="window.location.href='/projects/create'">
                <i class="icon-plus"></i> Tạo dự án mới
            </button>
        </div>
    </div>
</div>

<div class="content-wrapper">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <label for="status-filter">Trạng thái:</label>
                    <select id="status-filter" class="form-control">
                        <option value="">Tất cả</option>
                        <option value="planning">Lập kế hoạch</option>
                        <option value="active">Đang thực hiện</option>
                        <option value="on_hold">Tạm dừng</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search-input">Tìm kiếm:</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Tên dự án...">
                </div>
                <div class="col-md-3">
                    <label for="date-from">Từ ngày:</label>
                    <input type="date" id="date-from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="date-to">Đến ngày:</label>
                    <input type="date" id="date-to" class="form-control">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-12">
                    <button class="btn btn-secondary" onclick="applyFilters()">Áp dụng bộ lọc</button>
                    <button class="btn btn-outline-secondary" onclick="clearFilters()">Xóa bộ lọc</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="projects-table">
                    <thead>
                        <tr>
                            <th>Tên dự án</th>
                            <th>Trạng thái</th>
                            <th>Tiến độ</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Chi phí thực tế</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="projects-tbody">
                        <!-- Data will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination-wrapper" id="pagination-wrapper">
                <!-- Pagination will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
class ProjectsManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {};
        this.loadProjects();
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Search input with debounce
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.filters.search = e.target.value;
                this.loadProjects();
            }, 500);
        });
    }

    async loadProjects() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                ...this.filters
            });

            const response = await zenaApp.apiCall('GET', `/api/v1/projects?${params}`);
            
            if (response.status === 'success') {
                this.renderProjectsTable(response.data.data);
                this.renderPagination(response.data);
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách dự án', 'error');
        }
    }

    renderProjectsTable(projects) {
        const tbody = document.getElementById('projects-tbody');
        
        if (projects.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="empty-state">
                            <i class="icon-folder-open"></i>
                            <p>Không có dự án nào</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = projects.map(project => `
            <tr>
                <td>
                    <div class="project-info">
                        <h6 class="mb-1">${project.name}</h6>
                        <small class="text-muted">${project.description || 'Không có mô tả'}</small>
                    </div>
                </td>
                <td>
                    <span class="badge badge-${this.getStatusColor(project.status)}">
                        ${this.getStatusText(project.status)}
                    </span>
                </td>
                <td>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" style="width: ${project.progress}%"></div>
                        </div>
                        <span class="progress-text">${project.progress}%</span>
                    </div>
                </td>
                <td>${zenaApp.formatDate(project.start_date)}</td>
                <td>${zenaApp.formatDate(project.end_date)}</td>
                <td>${zenaApp.formatCurrency(project.actual_cost)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewProject(${project.id})" title="Xem chi tiết">
                            <i class="icon-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editProject(${project.id})" title="Chỉnh sửa">
                            <i class="icon-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteProject(${project.id})" title="Xóa">
                            <i class="icon-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderPagination(data) {
        const wrapper = document.getElementById('pagination-wrapper');
        
        if (data.last_page <= 1) {
            wrapper.innerHTML = '';
            return;
        }

        let paginationHtml = '<nav><ul class="pagination">';
        
        // Previous button
        if (data.current_page > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="projectsManager.goToPage(${data.current_page - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        for (let i = Math.max(1, data.current_page - 2); i <= Math.min(data.last_page, data.current_page + 2); i++) {
            paginationHtml += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" onclick="projectsManager.goToPage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="projectsManager.goToPage(${data.current_page + 1})">Sau</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        wrapper.innerHTML = paginationHtml;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadProjects();
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
}

// Global functions
function applyFilters() {
    projectsManager.filters = {
        status: document.getElementById('status-filter').value,
        search: document.getElementById('search-input').value,
        date_from: document.getElementById('date-from').value,
        date_to: document.getElementById('date-to').value
    };
    projectsManager.currentPage = 1;
    projectsManager.loadProjects();
}

function clearFilters() {
    document.getElementById('status-filter').value = '';
    document.getElementById('search-input').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    projectsManager.filters = {};
    projectsManager.currentPage = 1;
    projectsManager.loadProjects();
}

function viewProject(id) {
    window.location.href = `/projects/${id}`;
}

function editProject(id) {
    window.location.href = `/projects/${id}/edit`;
}

async function deleteProject(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa dự án này?')) {
        return;
    }

    try {
        const response = await zenaApp.apiCall('DELETE', `/api/v1/projects/${id}`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Xóa dự án thành công', 'success');
            projectsManager.loadProjects();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xóa dự án', 'error');
    }
}

// Initialize when page loads
let projectsManager;
document.addEventListener('DOMContentLoaded', () => {
    projectsManager = new ProjectsManager();
});
</script>
@endsection