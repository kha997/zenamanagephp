@extends('layouts.app')

@section('title', 'Quản lý Roles')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Quản lý Roles</h1>
            <p class="mb-0 text-muted">Quản lý vai trò và phân quyền trong hệ thống</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
            <i class="fas fa-plus me-2"></i>Tạo Role mới
        </button>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Phạm vi</label>
                    <select class="form-select" id="scopeFilter">
                        <option value="">Tất cả phạm vi</option>
                        <option value="system">System</option>
                        <option value="custom">Custom</option>
                        <option value="project">Project</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Tìm theo tên role...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" onclick="applyFilters()">
                            <i class="fas fa-search me-1"></i>Lọc
                        </button>
                        <button class="btn btn-outline-secondary" onclick="clearFilters()">
                            <i class="fas fa-times me-1"></i>Xóa
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Roles Table -->
    <div class="card">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">Danh sách Roles</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="rolesTable">
                    <thead>
                        <tr>
                            <th>Tên Role</th>
                            <th>Phạm vi</th>
                            <th>Mô tả</th>
                            <th>Số Permissions</th>
                            <th>Số Users</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                        <!-- Data will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <nav aria-label="Roles pagination">
                <ul class="pagination justify-content-center" id="rolesPagination">
                    <!-- Pagination will be loaded here -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Create/Edit Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">Tạo Role mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                    <input type="hidden" id="roleId">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tên Role <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="roleName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phạm vi <span class="text-danger">*</span></label>
                                <select class="form-select" id="roleScope" required>
                                    <option value="">Chọn phạm vi</option>
                                    <option value="system">System</option>
                                    <option value="custom">Custom</option>
                                    <option value="project">Project</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" id="roleDescription" rows="3"></textarea>
                    </div>
                    
                    <!-- Permissions Section -->
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <div id="permissionsTree">
                                <!-- Permissions tree will be loaded here -->
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="saveRole()">Lưu Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa role này không?</p>
                <p class="text-danger"><strong>Lưu ý:</strong> Việc xóa role sẽ ảnh hưởng đến tất cả users được gán role này.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeleteRole()">Xóa</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
/**
 * Class quản lý giao diện Roles
 * Xử lý CRUD operations, filtering, pagination cho roles
 */
class RolesManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 10;
        this.filters = {};
        this.selectedRoleId = null;
        this.permissions = [];
        
        this.init();
    }

    /**
     * Khởi tạo manager và load dữ liệu ban đầu
     */
    init() {
        this.loadRoles();
        this.loadPermissions();
        this.setupEventListeners();
    }

    /**
     * Thiết lập event listeners cho các elements
     */
    setupEventListeners() {
        // Search input với debounce
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.filters.search = e.target.value;
                this.currentPage = 1;
                this.loadRoles();
            }, 500);
        });

        // Scope filter
        document.getElementById('scopeFilter').addEventListener('change', (e) => {
            this.filters.scope = e.target.value;
            this.currentPage = 1;
            this.loadRoles();
        });
    }

    /**
     * Load danh sách roles từ API
     */
    async loadRoles() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                ...this.filters
            });

            const response = await fetch(`/api/v1/rbac/roles?${params}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load roles');

            const data = await response.json();
            this.renderRolesTable(data.data.data);
            this.renderPagination(data.data);
        } catch (error) {
            console.error('Error loading roles:', error);
            this.showAlert('Lỗi khi tải danh sách roles', 'danger');
        }
    }

    /**
     * Load danh sách permissions để hiển thị trong form
     */
    async loadPermissions() {
        try {
            const response = await fetch('/api/v1/rbac/permissions', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load permissions');

            const data = await response.json();
            this.permissions = data.data;
            this.renderPermissionsTree();
        } catch (error) {
            console.error('Error loading permissions:', error);
        }
    }

    /**
     * Render bảng roles
     */
    renderRolesTable(roles) {
        const tbody = document.getElementById('rolesTableBody');
        
        if (roles.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Không có roles nào</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = roles.map(role => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="role-icon me-2">
                            <i class="fas fa-user-tag text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold">${role.name}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${this.getScopeBadgeColor(role.scope)}">
                        ${this.getScopeLabel(role.scope)}
                    </span>
                </td>
                <td>
                    <span class="text-muted">${role.description || 'Không có mô tả'}</span>
                </td>
                <td>
                    <span class="badge bg-info">${role.permissions_count || 0}</span>
                </td>
                <td>
                    <span class="badge bg-secondary">${role.users_count || 0}</span>
                </td>
                <td>
                    <small class="text-muted">${this.formatDate(role.created_at)}</small>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="rolesManager.editRole(${role.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="rolesManager.viewRolePermissions(${role.id})" title="Xem permissions">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="rolesManager.deleteRole(${role.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Render permissions tree trong modal
     */
    renderPermissionsTree() {
        const container = document.getElementById('permissionsTree');
        
        // Group permissions by module
        const groupedPermissions = this.permissions.reduce((groups, permission) => {
            const module = permission.module || 'Other';
            if (!groups[module]) groups[module] = [];
            groups[module].push(permission);
            return groups;
        }, {});

        container.innerHTML = Object.entries(groupedPermissions).map(([module, permissions]) => `
            <div class="permission-module mb-3">
                <div class="form-check">
                    <input class="form-check-input module-checkbox" type="checkbox" 
                           id="module_${module}" onchange="rolesManager.toggleModule('${module}')">
                    <label class="form-check-label fw-bold" for="module_${module}">
                        ${module}
                    </label>
                </div>
                <div class="ms-4 mt-2">
                    ${permissions.map(permission => `
                        <div class="form-check">
                            <input class="form-check-input permission-checkbox" type="checkbox" 
                                   value="${permission.id}" id="perm_${permission.id}" 
                                   data-module="${module}" onchange="rolesManager.updateModuleCheckbox('${module}')">
                            <label class="form-check-label" for="perm_${permission.id}">
                                <code>${permission.code}</code> - ${permission.description}
                            </label>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
    }

    /**
     * Toggle tất cả permissions trong một module
     */
    toggleModule(module) {
        const moduleCheckbox = document.getElementById(`module_${module}`);
        const permissionCheckboxes = document.querySelectorAll(`input[data-module="${module}"]`);
        
        permissionCheckboxes.forEach(checkbox => {
            checkbox.checked = moduleCheckbox.checked;
        });
    }

    /**
     * Update trạng thái module checkbox dựa trên permissions
     */
    updateModuleCheckbox(module) {
        const moduleCheckbox = document.getElementById(`module_${module}`);
        const permissionCheckboxes = document.querySelectorAll(`input[data-module="${module}"]`);
        const checkedCount = document.querySelectorAll(`input[data-module="${module}"]:checked`).length;
        
        if (checkedCount === 0) {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = false;
        } else if (checkedCount === permissionCheckboxes.length) {
            moduleCheckbox.checked = true;
            moduleCheckbox.indeterminate = false;
        } else {
            moduleCheckbox.checked = false;
            moduleCheckbox.indeterminate = true;
        }
    }

    /**
     * Mở modal để tạo role mới
     */
    createRole() {
        this.selectedRoleId = null;
        document.getElementById('roleModalTitle').textContent = 'Tạo Role mới';
        document.getElementById('roleForm').reset();
        this.clearPermissionSelections();
        
        const modal = new bootstrap.Modal(document.getElementById('createRoleModal'));
        modal.show();
    }

    /**
     * Mở modal để chỉnh sửa role
     */
    async editRole(roleId) {
        try {
            const response = await fetch(`/api/v1/rbac/roles/${roleId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load role');

            const data = await response.json();
            const role = data.data;

            this.selectedRoleId = roleId;
            document.getElementById('roleModalTitle').textContent = 'Chỉnh sửa Role';
            document.getElementById('roleId').value = role.id;
            document.getElementById('roleName').value = role.name;
            document.getElementById('roleScope').value = role.scope;
            document.getElementById('roleDescription').value = role.description || '';

            // Set selected permissions
            this.clearPermissionSelections();
            role.permissions.forEach(permission => {
                const checkbox = document.getElementById(`perm_${permission.id}`);
                if (checkbox) {
                    checkbox.checked = true;
                    this.updateModuleCheckbox(permission.module);
                }
            });

            const modal = new bootstrap.Modal(document.getElementById('createRoleModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading role:', error);
            this.showAlert('Lỗi khi tải thông tin role', 'danger');
        }
    }

    /**
     * Lưu role (tạo mới hoặc cập nhật)
     */
    async saveRole() {
        try {
            const formData = {
                name: document.getElementById('roleName').value,
                scope: document.getElementById('roleScope').value,
                description: document.getElementById('roleDescription').value,
                permissions: Array.from(document.querySelectorAll('.permission-checkbox:checked')).map(cb => cb.value)
            };

            const url = this.selectedRoleId 
                ? `/api/v1/rbac/roles/${this.selectedRoleId}`
                : '/api/v1/rbac/roles';
            
            const method = this.selectedRoleId ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to save role');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('createRoleModal'));
            modal.hide();

            this.showAlert(
                this.selectedRoleId ? 'Role đã được cập nhật thành công' : 'Role đã được tạo thành công',
                'success'
            );
            
            this.loadRoles();
        } catch (error) {
            console.error('Error saving role:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * Xóa role
     */
    deleteRole(roleId) {
        this.selectedRoleId = roleId;
        const modal = new bootstrap.Modal(document.getElementById('deleteRoleModal'));
        modal.show();
    }

    /**
     * Xác nhận xóa role
     */
    async confirmDeleteRole() {
        try {
            const response = await fetch(`/api/v1/rbac/roles/${this.selectedRoleId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to delete role');

            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteRoleModal'));
            modal.hide();

            this.showAlert('Role đã được xóa thành công', 'success');
            this.loadRoles();
        } catch (error) {
            console.error('Error deleting role:', error);
            this.showAlert('Lỗi khi xóa role', 'danger');
        }
    }

    /**
     * Clear tất cả permission selections
     */
    clearPermissionSelections() {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.module-checkbox').forEach(cb => {
            cb.checked = false;
            cb.indeterminate = false;
        });
    }

    /**
     * Render pagination
     */
    renderPagination(paginationData) {
        const pagination = document.getElementById('rolesPagination');
        const { current_page, last_page, per_page, total } = paginationData;

        if (last_page <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let paginationHTML = '';
        
        // Previous button
        paginationHTML += `
            <li class="page-item ${current_page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="rolesManager.goToPage(${current_page - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            paginationHTML += `
                <li class="page-item ${i === current_page ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="rolesManager.goToPage(${i})">${i}</a>
                </li>
            `;
        }

        // Next button
        paginationHTML += `
            <li class="page-item ${current_page === last_page ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="rolesManager.goToPage(${current_page + 1})">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

        pagination.innerHTML = paginationHTML;
    }

    /**
     * Chuyển đến trang cụ thể
     */
    goToPage(page) {
        this.currentPage = page;
        this.loadRoles();
    }

    /**
     * Utility functions
     */
    getScopeBadgeColor(scope) {
        const colors = {
            'system': 'danger',
            'custom': 'warning', 
            'project': 'info'
        };
        return colors[scope] || 'secondary';
    }

    getScopeLabel(scope) {
        const labels = {
            'system': 'System',
            'custom': 'Custom',
            'project': 'Project'
        };
        return labels[scope] || scope;
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleDateString('vi-VN');
    }

    showAlert(message, type = 'info') {
        // Implement alert system
        console.log(`${type.toUpperCase()}: ${message}`);
    }
}

// Global functions
function applyFilters() {
    rolesManager.loadRoles();
}

function clearFilters() {
    document.getElementById('scopeFilter').value = '';
    document.getElementById('searchInput').value = '';
    rolesManager.filters = {};
    rolesManager.currentPage = 1;
    rolesManager.loadRoles();
}

function saveRole() {
    rolesManager.saveRole();
}

function confirmDeleteRole() {
    rolesManager.confirmDeleteRole();
}

// Initialize manager when page loads
let rolesManager;
document.addEventListener('DOMContentLoaded', function() {
    rolesManager = new RolesManager();
});
</script>
@endsection