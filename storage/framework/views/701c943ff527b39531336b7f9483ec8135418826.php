<?php $__env->startSection('title', 'Quản lý Phân quyền User'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Quản lý Phân quyền User</h1>
                    <p class="text-muted">Gán và quản lý vai trò cho người dùng trong hệ thống</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignRoleModal">
                        <i class="fas fa-plus"></i> Gán vai trò
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="bulkActionsBtn" disabled>
                        <i class="fas fa-tasks"></i> Thao tác hàng loạt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Tìm kiếm User</label>
                            <input type="text" class="form-control" id="searchUser" placeholder="Tên, email...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vai trò</label>
                            <select class="form-select" id="filterRole">
                                <option value="">Tất cả vai trò</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phạm vi</label>
                            <select class="form-select" id="filterScope">
                                <option value="">Tất cả phạm vi</option>
                                <option value="system">Hệ thống</option>
                                <option value="project">Dự án</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Dự án</label>
                            <select class="form-select" id="filterProject">
                                <option value="">Tất cả dự án</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" id="applyFilters">
                                <i class="fas fa-search"></i> Tìm kiếm
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                                <i class="fas fa-undo"></i> Đặt lại
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Tổng Users</h6>
                            <h3 class="mb-0" id="totalUsers">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Có vai trò</h6>
                            <h3 class="mb-0" id="usersWithRoles">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Chưa có vai trò</h6>
                            <h3 class="mb-0" id="usersWithoutRoles">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-user-times fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Vai trò dự án</h6>
                            <h3 class="mb-0" id="projectRoleAssignments">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-project-diagram fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Roles Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Danh sách phân quyền</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="userRolesTable">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Vai trò hệ thống</th>
                                    <th>Vai trò dự án</th>
                                    <th>Trạng thái</th>
                                    <th>Cập nhật cuối</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav aria-label="User roles pagination">
                        <ul class="pagination justify-content-center" id="pagination">
                            <!-- Pagination will be generated dynamically -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Role Modal -->
<div class="modal fade" id="assignRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gán vai trò cho User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignRoleForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Chọn User *</label>
                                <select class="form-select" id="selectUser" required>
                                    <option value="">-- Chọn User --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Loại phân quyền *</label>
                                <select class="form-select" id="assignmentType" required>
                                    <option value="">-- Chọn loại --</option>
                                    <option value="system">Vai trò hệ thống</option>
                                    <option value="project">Vai trò dự án</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Role Assignment -->
                    <div id="systemRoleSection" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Vai trò hệ thống *</label>
                            <select class="form-select" id="systemRole">
                                <option value="">-- Chọn vai trò --</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Project Role Assignment -->
                    <div id="projectRoleSection" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Dự án *</label>
                                    <select class="form-select" id="projectSelect">
                                        <option value="">-- Chọn dự án --</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Vai trò dự án *</label>
                                    <select class="form-select" id="projectRole">
                                        <option value="">-- Chọn vai trò --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="assignmentNote" rows="3" placeholder="Ghi chú về việc gán vai trò này..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="saveAssignment">
                    <i class="fas fa-save"></i> Gán vai trò
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Roles Detail Modal -->
<div class="modal fade" id="userRolesDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết phân quyền User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Thông tin User</h6>
                            </div>
                            <div class="card-body">
                                <div id="userInfo">
                                    <!-- User info will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Danh sách vai trò</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Vai trò hệ thống</h6>
                                        <div id="systemRolesList">
                                            <!-- System roles will be loaded here -->
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Vai trò dự án</h6>
                                        <div id="projectRolesList">
                                            <!-- Project roles will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Quyền hạn tổng hợp</h6>
                            </div>
                            <div class="card-body">
                                <div id="effectivePermissions">
                                    <!-- Effective permissions will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" id="editUserRoles">
                    <i class="fas fa-edit"></i> Chỉnh sửa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thao tác hàng loạt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Đã chọn <span id="selectedCount">0</span> user(s)</p>
                <div class="mb-3">
                    <label class="form-label">Chọn thao tác</label>
                    <select class="form-select" id="bulkAction">
                        <option value="">-- Chọn thao tác --</option>
                        <option value="assign_role">Gán vai trò</option>
                        <option value="remove_role">Gỡ vai trò</option>
                        <option value="export">Xuất danh sách</option>
                    </select>
                </div>
                
                <div id="bulkRoleSection" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label">Vai trò</label>
                        <select class="form-select" id="bulkRole">
                            <option value="">-- Chọn vai trò --</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="executeBulkAction">
                    <i class="fas fa-play"></i> Thực hiện
                </button>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
.role-badge {
    font-size: 0.75rem;
    margin: 2px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.permission-tree {
    max-height: 300px;
    overflow-y: auto;
}

.permission-item {
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.permission-item:last-child {
    border-bottom: none;
}

.role-card {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 10px;
    margin-bottom: 10px;
}

.role-card.system-role {
    border-left: 4px solid #0d6efd;
}

.role-card.project-role {
    border-left: 4px solid #198754;
}

.stats-card {
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-2px);
}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
class UserRolesManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 20;
        this.filters = {};
        this.selectedUsers = new Set();
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadInitialData();
    }

    bindEvents() {
        // Filter events
        $('#applyFilters').on('click', () => this.applyFilters());
        $('#resetFilters').on('click', () => this.resetFilters());
        
        // Assignment type change
        $('#assignmentType').on('change', (e) => this.toggleAssignmentSections(e.target.value));
        
        // Save assignment
        $('#saveAssignment').on('click', () => this.saveAssignment());
        
        // Select all checkbox
        $('#selectAll').on('change', (e) => this.toggleSelectAll(e.target.checked));
        
        // Bulk actions
        $('#bulkActionsBtn').on('click', () => this.showBulkActionsModal());
        $('#bulkAction').on('change', (e) => this.toggleBulkActionSections(e.target.value));
        $('#executeBulkAction').on('click', () => this.executeBulkAction());
        
        // Search on enter
        $('#searchUser').on('keypress', (e) => {
            if (e.which === 13) this.applyFilters();
        });
    }

    async loadInitialData() {
        try {
            await Promise.all([
                this.loadUsers(),
                this.loadRoles(),
                this.loadProjects(),
                this.loadStatistics()
            ]);
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showError('Có lỗi xảy ra khi tải dữ liệu');
        }
    }

    async loadUsers() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                ...this.filters
            });

            const response = await fetch(`/api/v1/rbac/user-roles?${params}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load users');
            
            const data = await response.json();
            this.renderUsersTable(data.data.data);
            this.renderPagination(data.data);
            
        } catch (error) {
            console.error('Error loading users:', error);
            this.showError('Không thể tải danh sách users');
        }
    }

    renderUsersTable(users) {
        const tbody = $('#userRolesTable tbody');
        tbody.empty();

        if (users.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Không có dữ liệu</p>
                    </td>
                </tr>
            `);
            return;
        }

        users.forEach(user => {
            const systemRoles = user.system_roles || [];
            const projectRoles = user.project_roles || [];
            
            const systemRolesBadges = systemRoles.map(role => 
                `<span class="badge bg-primary role-badge">${role.name}</span>`
            ).join('');
            
            const projectRolesBadges = projectRoles.map(role => 
                `<span class="badge bg-success role-badge">${role.role_name} (${role.project_name})</span>`
            ).join('');

            const row = `
                <tr>
                    <td>
                        <input type="checkbox" class="form-check-input user-checkbox" 
                               value="${user.id}" ${this.selectedUsers.has(user.id) ? 'checked' : ''}>
                    </td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${user.avatar || '/images/default-avatar.png'}" 
                                 alt="${user.name}" class="user-avatar me-2">
                            <div>
                                <div class="fw-bold">${user.name}</div>
                                <small class="text-muted">ID: ${user.id}</small>
                            </div>
                        </div>
                    </td>
                    <td>${user.email}</td>
                    <td>${systemRolesBadges || '<span class="text-muted">Chưa có</span>'}</td>
                    <td>${projectRolesBadges || '<span class="text-muted">Chưa có</span>'}</td>
                    <td>
                        <span class="badge bg-${user.is_active ? 'success' : 'secondary'}">
                            ${user.is_active ? 'Hoạt động' : 'Không hoạt động'}
                        </span>
                    </td>
                    <td>${this.formatDate(user.updated_at)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-primary" 
                                    onclick="userRolesManager.viewUserRoles(${user.id})" 
                                    title="Xem chi tiết">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success" 
                                    onclick="userRolesManager.editUserRoles(${user.id})" 
                                    title="Chỉnh sửa">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" 
                                    onclick="userRolesManager.removeAllRoles(${user.id})" 
                                    title="Gỡ tất cả vai trò">
                                <i class="fas fa-user-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Bind checkbox events
        $('.user-checkbox').on('change', (e) => {
            const userId = parseInt(e.target.value);
            if (e.target.checked) {
                this.selectedUsers.add(userId);
            } else {
                this.selectedUsers.delete(userId);
            }
            this.updateBulkActionsButton();
        });
    }

    async loadRoles() {
        try {
            const response = await fetch('/api/v1/rbac/roles', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load roles');
            
            const data = await response.json();
            this.populateRoleSelects(data.data);
            
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    }

    populateRoleSelects(roles) {
        const systemRoles = roles.filter(role => role.scope === 'system');
        const projectRoles = roles.filter(role => role.scope === 'project');
        
        // Populate filter dropdown
        const filterRole = $('#filterRole');
        filterRole.find('option:not(:first)').remove();
        roles.forEach(role => {
            filterRole.append(`<option value="${role.id}">${role.name} (${role.scope})</option>`);
        });
        
        // Populate assignment dropdowns
        const systemRoleSelect = $('#systemRole');
        systemRoleSelect.find('option:not(:first)').remove();
        systemRoles.forEach(role => {
            systemRoleSelect.append(`<option value="${role.id}">${role.name}</option>`);
        });
        
        const projectRoleSelect = $('#projectRole');
        projectRoleSelect.find('option:not(:first)').remove();
        projectRoles.forEach(role => {
            projectRoleSelect.append(`<option value="${role.id}">${role.name}</option>`);
        });
        
        // Populate bulk action dropdown
        const bulkRole = $('#bulkRole');
        bulkRole.find('option:not(:first)').remove();
        roles.forEach(role => {
            bulkRole.append(`<option value="${role.id}">${role.name} (${role.scope})</option>`);
        });
    }

    async loadProjects() {
        try {
            const response = await fetch('/api/v1/projects', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load projects');
            
            const data = await response.json();
            this.populateProjectSelects(data.data);
            
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    populateProjectSelects(projects) {
        // Populate filter dropdown
        const filterProject = $('#filterProject');
        filterProject.find('option:not(:first)').remove();
        projects.forEach(project => {
            filterProject.append(`<option value="${project.id}">${project.name}</option>`);
        });
        
        // Populate assignment dropdown
        const projectSelect = $('#projectSelect');
        projectSelect.find('option:not(:first)').remove();
        projects.forEach(project => {
            projectSelect.append(`<option value="${project.id}">${project.name}</option>`);
        });
    }

    async loadStatistics() {
        try {
            const response = await fetch('/api/v1/rbac/user-roles/statistics', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load statistics');
            
            const data = await response.json();
            this.updateStatistics(data.data);
            
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    updateStatistics(stats) {
        $('#totalUsers').text(stats.total_users || 0);
        $('#usersWithRoles').text(stats.users_with_roles || 0);
        $('#usersWithoutRoles').text(stats.users_without_roles || 0);
        $('#projectRoleAssignments').text(stats.project_role_assignments || 0);
    }

    toggleAssignmentSections(type) {
        $('#systemRoleSection').toggle(type === 'system');
        $('#projectRoleSection').toggle(type === 'project');
    }

    async saveAssignment() {
        try {
            const formData = this.getAssignmentFormData();
            if (!this.validateAssignmentForm(formData)) return;

            const response = await fetch('/api/v1/rbac/user-roles', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Failed to assign role');
            }

            this.showSuccess('Gán vai trò thành công');
            $('#assignRoleModal').modal('hide');
            this.resetAssignmentForm();
            await this.loadUsers();
            await this.loadStatistics();
            
        } catch (error) {
            console.error('Error assigning role:', error);
            this.showError(error.message || 'Có lỗi xảy ra khi gán vai trò');
        }
    }

    getAssignmentFormData() {
        const type = $('#assignmentType').val();
        const data = {
            user_id: $('#selectUser').val(),
            type: type,
            note: $('#assignmentNote').val()
        };

        if (type === 'system') {
            data.role_id = $('#systemRole').val();
        } else if (type === 'project') {
            data.project_id = $('#projectSelect').val();
            data.role_id = $('#projectRole').val();
        }

        return data;
    }

    validateAssignmentForm(data) {
        if (!data.user_id) {
            this.showError('Vui lòng chọn user');
            return false;
        }
        
        if (!data.type) {
            this.showError('Vui lòng chọn loại phân quyền');
            return false;
        }
        
        if (!data.role_id) {
            this.showError('Vui lòng chọn vai trò');
            return false;
        }
        
        if (data.type === 'project' && !data.project_id) {
            this.showError('Vui lòng chọn dự án');
            return false;
        }
        
        return true;
    }

    resetAssignmentForm() {
        $('#assignRoleForm')[0].reset();
        $('#systemRoleSection, #projectRoleSection').hide();
    }

    async viewUserRoles(userId) {
        try {
            const response = await fetch(`/api/v1/rbac/user-roles/${userId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load user roles');
            
            const data = await response.json();
            this.renderUserRolesDetail(data.data);
            $('#userRolesDetailModal').modal('show');
            
        } catch (error) {
            console.error('Error loading user roles:', error);
            this.showError('Không thể tải thông tin phân quyền user');
        }
    }

    renderUserRolesDetail(userData) {
        // Render user info
        const userInfo = `
            <div class="text-center mb-3">
                <img src="${userData.avatar || '/images/default-avatar.png'}" 
                     alt="${userData.name}" class="rounded-circle" width="80" height="80">
                <h6 class="mt-2 mb-0">${userData.name}</h6>
                <small class="text-muted">${userData.email}</small>
            </div>
            <div class="row">
                <div class="col-6">
                    <small class="text-muted">ID:</small><br>
                    <strong>${userData.id}</strong>
                </div>
                <div class="col-6">
                    <small class="text-muted">Trạng thái:</small><br>
                    <span class="badge bg-${userData.is_active ? 'success' : 'secondary'}">
                        ${userData.is_active ? 'Hoạt động' : 'Không hoạt động'}
                    </span>
                </div>
            </div>
        `;
        $('#userInfo').html(userInfo);

        // Render system roles
        const systemRoles = userData.system_roles || [];
        let systemRolesHtml = '';
        if (systemRoles.length === 0) {
            systemRolesHtml = '<p class="text-muted">Chưa có vai trò hệ thống</p>';
        } else {
            systemRoles.forEach(role => {
                systemRolesHtml += `
                    <div class="role-card system-role">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${role.name}</strong>
                                <p class="mb-0 small text-muted">${role.description || 'Không có mô tả'}</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="userRolesManager.removeRole(${userData.id}, 'system', ${role.id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        $('#systemRolesList').html(systemRolesHtml);

        // Render project roles
        const projectRoles = userData.project_roles || [];
        let projectRolesHtml = '';
        if (projectRoles.length === 0) {
            projectRolesHtml = '<p class="text-muted">Chưa có vai trò dự án</p>';
        } else {
            projectRoles.forEach(role => {
                projectRolesHtml += `
                    <div class="role-card project-role">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${role.role_name}</strong>
                                <p class="mb-0 small text-muted">Dự án: ${role.project_name}</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="userRolesManager.removeRole(${userData.id}, 'project', ${role.role_id}, ${role.project_id})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        $('#projectRolesList').html(projectRolesHtml);

        // Render effective permissions
        this.renderEffectivePermissions(userData.effective_permissions || []);
    }

    renderEffectivePermissions(permissions) {
        if (permissions.length === 0) {
            $('#effectivePermissions').html('<p class="text-muted">Không có quyền hạn</p>');
            return;
        }

        // Group permissions by module
        const groupedPermissions = permissions.reduce((acc, permission) => {
            if (!acc[permission.module]) {
                acc[permission.module] = [];
            }
            acc[permission.module].push(permission);
            return acc;
        }, {});

        let permissionsHtml = '<div class="permission-tree">';
        Object.keys(groupedPermissions).forEach(module => {
            permissionsHtml += `
                <div class="mb-3">
                    <h6 class="text-primary">${module}</h6>
                    <div class="ms-3">
            `;
            
            groupedPermissions[module].forEach(permission => {
                permissionsHtml += `
                    <div class="permission-item">
                        <span class="badge bg-light text-dark">${permission.action}</span>
                        <small class="text-muted ms-2">${permission.description || ''}</small>
                    </div>
                `;
            });
            
            permissionsHtml += '</div></div>';
        });
        permissionsHtml += '</div>';

        $('#effectivePermissions').html(permissionsHtml);
    }

    async removeRole(userId, type, roleId, projectId = null) {
        if (!confirm('Bạn có chắc chắn muốn gỡ vai trò này?')) return;

        try {
            const url = projectId 
                ? `/api/v1/rbac/user-roles/${userId}/project/${projectId}/role/${roleId}`
                : `/api/v1/rbac/user-roles/${userId}/system/role/${roleId}`;

            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to remove role');

            this.showSuccess('Gỡ vai trò thành công');
            await this.loadUsers();
            await this.loadStatistics();
            
            // Refresh detail modal if open
            if ($('#userRolesDetailModal').hasClass('show')) {
                this.viewUserRoles(userId);
            }
            
        } catch (error) {
            console.error('Error removing role:', error);
            this.showError('Có lỗi xảy ra khi gỡ vai trò');
        }
    }

    applyFilters() {
        this.filters = {
            search: $('#searchUser').val(),
            role_id: $('#filterRole').val(),
            scope: $('#filterScope').val(),
            project_id: $('#filterProject').val()
        };
        
        // Remove empty filters
        Object.keys(this.filters).forEach(key => {
            if (!this.filters[key]) delete this.filters[key];
        });
        
        this.currentPage = 1;
        this.loadUsers();
    }

    resetFilters() {
        $('#searchUser, #filterRole, #filterScope, #filterProject').val('');
        this.filters = {};
        this.currentPage = 1;
        this.loadUsers();
    }

    toggleSelectAll(checked) {
        $('.user-checkbox').prop('checked', checked);
        this.selectedUsers.clear();
        
        if (checked) {
            $('.user-checkbox').each((index, checkbox) => {
                this.selectedUsers.add(parseInt(checkbox.value));
            });
        }
        
        this.updateBulkActionsButton();
    }

    updateBulkActionsButton() {
        const count = this.selectedUsers.size;
        $('#bulkActionsBtn').prop('disabled', count === 0);
        $('#selectedCount').text(count);
    }

    showBulkActionsModal() {
        if (this.selectedUsers.size === 0) return;
        $('#bulkActionsModal').modal('show');
    }

    toggleBulkActionSections(action) {
        $('#bulkRoleSection').toggle(['assign_role', 'remove_role'].includes(action));
    }

    async executeBulkAction() {
        const action = $('#bulkAction').val();
        if (!action) {
            this.showError('Vui lòng chọn thao tác');
            return;
        }

        try {
            switch (action) {
                case 'assign_role':
                    await this.bulkAssignRole();
                    break;
                case 'remove_role':
                    await this.bulkRemoveRole();
                    break;
                case 'export':
                    await this.exportSelectedUsers();
                    break;
            }
        } catch (error) {
            console.error('Error executing bulk action:', error);
            this.showError('Có lỗi xảy ra khi thực hiện thao tác');
        }
    }

    async bulkAssignRole() {
        const roleId = $('#bulkRole').val();
        if (!roleId) {
            this.showError('Vui lòng chọn vai trò');
            return;
        }

        const response = await fetch('/api/v1/rbac/user-roles/bulk-assign', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_ids: Array.from(this.selectedUsers),
                role_id: roleId
            })
        });

        if (!response.ok) throw new Error('Failed to bulk assign roles');

        this.showSuccess('Gán vai trò hàng loạt thành công');
        $('#bulkActionsModal').modal('hide');
        this.selectedUsers.clear();
        this.updateBulkActionsButton();
        await this.loadUsers();
        await this.loadStatistics();
    }

    async bulkRemoveRole() {
        const roleId = $('#bulkRole').val();
        if (!roleId) {
            this.showError('Vui lòng chọn vai trò');
            return;
        }

        if (!confirm(`Bạn có chắc chắn muốn gỡ vai trò này khỏi ${this.selectedUsers.size} user(s)?`)) return;

        const response = await fetch('/api/v1/rbac/user-roles/bulk-remove', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_ids: Array.from(this.selectedUsers),
                role_id: roleId
            })
        });

        if (!response.ok) throw new Error('Failed to bulk remove roles');

        this.showSuccess('Gỡ vai trò hàng loạt thành công');
        $('#bulkActionsModal').modal('hide');
        this.selectedUsers.clear();
        this.updateBulkActionsButton();
        await this.loadUsers();
        await this.loadStatistics();
    }

    async exportSelectedUsers() {
        const response = await fetch('/api/v1/rbac/user-roles/export', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('token')}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                user_ids: Array.from(this.selectedUsers)
            })
        });

        if (!response.ok) throw new Error('Failed to export users');

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `user-roles-${new Date().toISOString().split('T')[0]}.xlsx`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        this.showSuccess('Xuất file thành công');
        $('#bulkActionsModal').modal('hide');
    }

    renderPagination(data) {
        const pagination = $('#pagination');
        pagination.empty();

        if (data.last_page <= 1) return;

        // Previous button
        pagination.append(`
            <li class="page-item ${data.current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${data.current_page - 1}">Trước</a>
            </li>
        `);

        // Page numbers
        const startPage = Math.max(1, data.current_page - 2);
        const endPage = Math.min(data.last_page, data.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <li class="page-item ${i === data.current_page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Next button
        pagination.append(`
            <li class="page-item ${data.current_page === data.last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${data.current_page + 1}">Sau</a>
            </li>
        `);

        // Bind pagination events
        pagination.find('a.page-link').on('click', (e) => {
            e.preventDefault();
            const page = parseInt($(e.target).data('page'));
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.loadUsers();
            }
        });
    }

    formatDate(dateString) {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('vi-VN', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    showSuccess(message) {
        // Implementation depends on your notification system
        alert(message); // Replace with your preferred notification method
    }

    showError(message) {
        // Implementation depends on your notification system
        alert(message); // Replace with your preferred notification method
    }
}

// Initialize when document is ready
$(document).ready(function() {
    window.userRolesManager = new UserRolesManager();
});
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/rbac/user-roles.blade.php ENDPATH**/ ?>