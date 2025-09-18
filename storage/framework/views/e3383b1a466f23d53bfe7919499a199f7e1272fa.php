<?php $__env->startSection('title', 'Quản lý Yêu cầu Thay đổi'); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Quản lý Yêu cầu Thay đổi</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="window.location.href='/change-requests/create'">
                <i class="icon-plus"></i> Tạo yêu cầu mới
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
                        <option value="draft">Bản nháp</option>
                        <option value="awaiting_approval">Chờ phê duyệt</option>
                        <option value="approved">Đã phê duyệt</option>
                        <option value="rejected">Đã từ chối</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="project-filter">Dự án:</label>
                    <select id="project-filter" class="form-control">
                        <option value="">Tất cả dự án</option>
                        <!-- Projects will be loaded via AJAX -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search-input">Tìm kiếm:</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Tiêu đề, mã CR...">
                </div>
                <div class="col-md-3">
                    <label for="priority-filter">Mức độ ưu tiên:</label>
                    <select id="priority-filter" class="form-control">
                        <option value="">Tất cả</option>
                        <option value="low">Thấp</option>
                        <option value="medium">Trung bình</option>
                        <option value="high">Cao</option>
                        <option value="critical">Khẩn cấp</option>
                    </select>
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

    <!-- Change Requests Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="change-requests-table">
                    <thead>
                        <tr>
                            <th>Mã CR</th>
                            <th>Tiêu đề</th>
                            <th>Dự án</th>
                            <th>Trạng thái</th>
                            <th>Tác động</th>
                            <th>Người tạo</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="change-requests-tbody">
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

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Phê duyệt Yêu cầu Thay đổi</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="decision-note">Ghi chú quyết định:</label>
                    <textarea id="decision-note" class="form-control" rows="4" placeholder="Nhập ghi chú về quyết định phê duyệt/từ chối..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success" onclick="approveChangeRequest()">Phê duyệt</button>
                <button type="button" class="btn btn-danger" onclick="rejectChangeRequest()">Từ chối</button>
            </div>
        </div>
    </div>
</div>

<script>
class ChangeRequestsManager {
    constructor() {
        this.currentPage = 1;
        this.filters = {};
        this.currentCRId = null;
        this.loadChangeRequests();
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
                this.loadChangeRequests();
            }, 500);
        });
    }

    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects?per_page=100');
            
            if (response.status === 'success') {
                const projectSelect = document.getElementById('project-filter');
                const projects = response.data.data;
                
                projects.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name;
                    projectSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Lỗi khi tải danh sách dự án:', error);
        }
    }

    async loadChangeRequests() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                ...this.filters
            });

            const response = await zenaApp.apiCall('GET', `/api/v1/change-requests?${params}`);
            
            if (response.status === 'success') {
                this.renderChangeRequestsTable(response.data.data);
                this.renderPagination(response.data);
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách yêu cầu thay đổi', 'error');
        }
    }

    renderChangeRequestsTable(changeRequests) {
        const tbody = document.getElementById('change-requests-tbody');
        
        if (changeRequests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="empty-state">
                            <i class="icon-file-text"></i>
                            <p>Không có yêu cầu thay đổi nào</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = changeRequests.map(cr => `
            <tr>
                <td>
                    <span class="cr-code">${cr.code}</span>
                </td>
                <td>
                    <div class="cr-info">
                        <h6 class="mb-1">${cr.title}</h6>
                        <small class="text-muted">${cr.description ? cr.description.substring(0, 100) + '...' : 'Không có mô tả'}</small>
                    </div>
                </td>
                <td>
                    <span class="project-name">${cr.project ? cr.project.name : 'N/A'}</span>
                </td>
                <td>
                    <span class="badge badge-${this.getStatusColor(cr.status)}">
                        ${this.getStatusText(cr.status)}
                    </span>
                </td>
                <td>
                    <div class="impact-summary">
                        ${cr.impact_days ? `<div><i class="icon-clock"></i> ${cr.impact_days} ngày</div>` : ''}
                        ${cr.impact_cost ? `<div><i class="icon-dollar-sign"></i> ${zenaApp.formatCurrency(cr.impact_cost)}</div>` : ''}
                    </div>
                </td>
                <td>
                    <span class="creator-name">${cr.creator ? cr.creator.name : 'N/A'}</span>
                </td>
                <td>${zenaApp.formatDate(cr.created_at)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewChangeRequest(${cr.id})" title="Xem chi tiết">
                            <i class="icon-eye"></i>
                        </button>
                        ${cr.status === 'draft' ? `
                            <button class="btn btn-sm btn-outline-secondary" onclick="editChangeRequest(${cr.id})" title="Chỉnh sửa">
                                <i class="icon-edit"></i>
                            </button>
                        ` : ''}
                        ${cr.status === 'awaiting_approval' ? `
                            <button class="btn btn-sm btn-outline-success" onclick="showApprovalModal(${cr.id})" title="Phê duyệt">
                                <i class="icon-check"></i>
                            </button>
                        ` : ''}
                        ${cr.status === 'draft' ? `
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteChangeRequest(${cr.id})" title="Xóa">
                                <i class="icon-trash"></i>
                            </button>
                        ` : ''}
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
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changeRequestsManager.goToPage(${data.current_page - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        for (let i = Math.max(1, data.current_page - 2); i <= Math.min(data.last_page, data.current_page + 2); i++) {
            paginationHtml += `<li class="page-item ${i === data.current_page ? 'active' : ''}"><a class="page-link" href="#" onclick="changeRequestsManager.goToPage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" onclick="changeRequestsManager.goToPage(${data.current_page + 1})">Sau</a></li>`;
        }
        
        paginationHtml += '</ul></nav>';
        wrapper.innerHTML = paginationHtml;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadChangeRequests();
    }

    getStatusColor(status) {
        const colors = {
            'draft': 'secondary',
            'awaiting_approval': 'warning',
            'approved': 'success',
            'rejected': 'danger'
        };
        return colors[status] || 'secondary';
    }

    getStatusText(status) {
        const texts = {
            'draft': 'Bản nháp',
            'awaiting_approval': 'Chờ phê duyệt',
            'approved': 'Đã phê duyệt',
            'rejected': 'Đã từ chối'
        };
        return texts[status] || status;
    }
}

// Global functions
function applyFilters() {
    changeRequestsManager.filters = {
        status: document.getElementById('status-filter').value,
        project_id: document.getElementById('project-filter').value,
        search: document.getElementById('search-input').value,
        priority: document.getElementById('priority-filter').value
    };
    changeRequestsManager.currentPage = 1;
    changeRequestsManager.loadChangeRequests();
}

function clearFilters() {
    document.getElementById('status-filter').value = '';
    document.getElementById('project-filter').value = '';
    document.getElementById('search-input').value = '';
    document.getElementById('priority-filter').value = '';
    changeRequestsManager.filters = {};
    changeRequestsManager.currentPage = 1;
    changeRequestsManager.loadChangeRequests();
}

function viewChangeRequest(id) {
    window.location.href = `/change-requests/${id}`;
}

function editChangeRequest(id) {
    window.location.href = `/change-requests/${id}/edit`;
}

function showApprovalModal(id) {
    changeRequestsManager.currentCRId = id;
    $('#approvalModal').modal('show');
}

async function approveChangeRequest() {
    const note = document.getElementById('decision-note').value;
    
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/change-requests/${changeRequestsManager.currentCRId}/approve`, {
            decision_note: note
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification('Phê duyệt yêu cầu thay đổi thành công', 'success');
            $('#approvalModal').modal('hide');
            changeRequestsManager.loadChangeRequests();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi phê duyệt yêu cầu thay đổi', 'error');
    }
}

async function rejectChangeRequest() {
    const note = document.getElementById('decision-note').value;
    
    if (!note.trim()) {
        zenaApp.showNotification('Vui lòng nhập lý do từ chối', 'warning');
        return;
    }
    
    try {
        const response = await zenaApp.apiCall('POST', `/api/v1/change-requests/${changeRequestsManager.currentCRId}/reject`, {
            decision_note: note
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification('Từ chối yêu cầu thay đổi thành công', 'success');
            $('#approvalModal').modal('hide');
            changeRequestsManager.loadChangeRequests();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi từ chối yêu cầu thay đổi', 'error');
    }
}

async function deleteChangeRequest(id) {
    if (!confirm('Bạn có chắc chắn muốn xóa yêu cầu thay đổi này?')) {
        return;
    }

    try {
        const response = await zenaApp.apiCall('DELETE', `/api/v1/change-requests/${id}`);
        
        if (response.status === 'success') {
            zenaApp.showNotification('Xóa yêu cầu thay đổi thành công', 'success');
            changeRequestsManager.loadChangeRequests();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi xóa yêu cầu thay đổi', 'error');
    }
}

// Initialize when page loads
let changeRequestsManager;
document.addEventListener('DOMContentLoaded', () => {
    changeRequestsManager = new ChangeRequestsManager();
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/change-requests/index.blade.php ENDPATH**/ ?>