@extends('layouts.app')

@section('title', 'Quản lý Công việc')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Quản lý Công việc</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="window.location.href='/tasks/create'">
                <i class="icon-plus"></i> Tạo công việc mới
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
                    <label for="project-filter">Dự án:</label>
                    <select id="project-filter" class="form-control">
                        <option value="">Tất cả dự án</option>
                        <!-- Projects will be loaded via AJAX -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status-filter">Trạng thái:</label>
                    <select id="status-filter" class="form-control">
                        <option value="">Tất cả</option>
                        <option value="pending">Chờ thực hiện</option>
                        <option value="in_progress">Đang thực hiện</option>
                        <option value="completed">Hoàn thành</option>
                        <option value="cancelled">Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="assignee-filter">Người thực hiện:</label>
                    <select id="assignee-filter" class="form-control">
                        <option value="">Tất cả</option>
                        <!-- Users will be loaded via AJAX -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search-input">Tìm kiếm:</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Tên công việc...">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-6">
                    <label for="date-from">Từ ngày:</label>
                    <input type="date" id="date-from" class="form-control">
                </div>
                <div class="col-md-6">
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

    <!-- Tasks Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="tasks-table">
                    <thead>
                        <tr>
                            <th>Tên công việc</th>
                            <th>Dự án</th>
                            <th>Trạng thái</th>
                            <th>Người thực hiện</th>
                            <th>Ngày bắt đầu</th>
                            <th>Ngày kết thúc</th>
                            <th>Tiến độ</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="tasks-tbody">
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
class TasksManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {};
        this.loadTasks();
        this.loadProjects();
        this.loadUsers();
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Search input with debounce
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.filters.search = e.target.value;
                this.loadTasks();
            }, 500);
        });
    }

    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects?per_page=100');
            
            if (response.status === 'success') {
                const select = document.getElementById('project-filter');
                response.data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách dự án:', error);
        }
    }

    async loadUsers() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/users?per_page=100');
            
            if (response.status === 'success') {
                const select = document.getElementById('assignee-filter');
                response.data.data.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách người dùng:', error);
        }
    }

    async loadTasks() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                ...this.filters
            });

            const response = await zenaApp.apiCall('GET', `/api/v1/tasks?${params}`);
            
            if (response.status === 'success') {
                this.renderTasksTable(response.data.data);
                this.renderPagination(response.data);
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách công việc', 'error');
        }
    }

    renderTasksTable(tasks) {
        const tbody = document.getElementById('tasks-tbody');
        
        if (tasks.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="empty-state">
                            <i class="icon-clipboard"></i>
                            <p>Không có công việc nào</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = tasks.map(task => `
            <tr>
                <td>
                    <div class="task-info">
                        <h6 class="mb-1">${task.name}</h6>
                        <small class="text-muted">${task.description || 'Không có mô tả'}</small>
                    </div>
                </td>
                <td>
                    <span class="project-badge">${task.project?.name || 'N/A'}</span>
                </td>
                <td>
                    <span class="badge badge-${this.getStatusColor(task.status)}">
                        ${this.getStatusText(task.status)}
                    </span>
                </td>
                <td>
                    <div class="assignees">
                        ${task.assignments?.map(assignment => `
                            <span class="assignee-badge">
                                ${assignment.user.name} (${assignment.split_percentage}%)
                            </span>
                        `).join('') || 'Chưa phân công'}
                    </div>
                </td>
                <td>${zenaApp.formatDate(task.start_date)}</td>
                <td>${zenaApp.formatDate(task.end_date)}</td>
                <td>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" style="width: ${task.progress || 0}%"></div>
                        </div>
                        <span class="progress-text">${task.progress || 0}%</span>
                    </div>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewTask(${task.id})" title="Xem chi tiết">
                            <i class="icon-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editTask(${task.id})" title="Chỉnh sửa">
                            <i class="icon-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(${task.id})" title="Xóa">
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
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="tasksManager.goToPage(${data.current_page - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        for (let i = Math.max(1, data.current_page - 2); i <= Math.min(data.last_page, data.current_page + 2); i++) {
            paginationHtml += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" onclick="tasksManager.goToPage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="tasksManager.goToPage(${data.current_page + 1})">Sau</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        wrapper.innerHTML = paginationHtml;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadTasks();
    }

    getStatusColor(status) {
        const colors = {
            'pending': 'warning',
            'in_progress': 'info',
            'completed': 'success',
            'cancelled': 'danger'
        };
        return colors[status] || 'secondary';
    }

    getStatusText(status) {
        const texts = {
            'pending': 'Chờ thực hiện',
            'in_progress': 'Đang thực hiện',
            'completed': 'Hoàn thành',
            'cancelled': 'Đã hủy'
        };
        return texts[status] || status;
    }
}

// Global functions
function applyFilters() {
    tasksManager.filters = {
        project_id: document.getElementById('project-filter').value,
        status: document.getElementById('status-filter').value,
        assignee_id: document.getElementById('assignee-filter').value,
        date_from: document.getElementById('date-from').value,
        date_to: document.getElementById('date-to').value,
        search: document.getElementById('search-input').value
    };
    tasksManager.currentPage = 1;
    tasksManager.loadTasks();
}

function clearFilters() {
    document.getElementById('project-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('assignee-filter').value = '';
    document.getElementById('search-input').value = '';
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    tasksManager.filters = {};
    tasksManager.currentPage = 1;
    tasksManager.loadTasks();
}

function viewTask(taskId) {
    window.location.href = `/tasks/${taskId}`;
}

function editTask(taskId) {
    window.location.href = `/tasks/${taskId}/edit`;
}

function deleteTask(taskId) {
    if (confirm('Bạn có chắc chắn muốn xóa công việc này?')) {
        zenaApp.apiCall('DELETE', `/api/v1/tasks/${taskId}`)
            .then(response => {
                if (response.status === 'success') {
                    zenaApp.showNotification('Xóa công việc thành công', 'success');
                    tasksManager.loadTasks();
                }
            })
            .catch(error => {
                zenaApp.showNotification('Lỗi khi xóa công việc', 'error');
            });
    }
}

// Initialize when page loads
let tasksManager;
document.addEventListener('DOMContentLoaded', function() {
    tasksManager = new TasksManager();
});
</script>
@endsection