<?php $__env->startSection('title', 'Quản lý Permissions'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Quản lý Permissions</h1>
            <p class="text-muted">Quản lý các quyền hạn trong hệ thống Z.E.N.A</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="permissionsManager.createPermission()">
                <i class="fas fa-plus"></i> Tạo Permission mới
            </button>
            <button class="btn btn-outline-secondary" onclick="permissionsManager.syncPermissions()">
                <i class="fas fa-sync"></i> Đồng bộ từ Code
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Module</label>
                    <select class="form-select" id="filterModule" onchange="permissionsManager.applyFilters()">
                        <option value="">Tất cả modules</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="filterAction" onchange="permissionsManager.applyFilters()">
                        <option value="">Tất cả actions</option>
                        <option value="create">Create</option>
                        <option value="read">Read</option>
                        <option value="update">Update</option>
                        <option value="delete">Delete</option>
                        <option value="manage">Manage</option>
                        <option value="approve">Approve</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="searchPermissions" 
                           placeholder="Tìm theo code hoặc mô tả..." 
                           onkeyup="permissionsManager.applyFilters()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button class="btn btn-outline-secondary w-100" onclick="permissionsManager.resetFilters()">
                            <i class="fas fa-times"></i> Reset
                        </button>
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
                            <h6 class="card-title">Tổng Permissions</h6>
                            <h3 class="mb-0" id="totalPermissions">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-key fa-2x opacity-75"></i>
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
                            <h6 class="card-title">Modules</h6>
                            <h3 class="mb-0" id="totalModules">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-cubes fa-2x opacity-75"></i>
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
                            <h6 class="card-title">Được sử dụng</h6>
                            <h3 class="mb-0" id="usedPermissions">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
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
                            <h6 class="card-title">Chưa sử dụng</h6>
                            <h3 class="mb-0" id="unusedPermissions">0</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Permissions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Danh sách Permissions</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" onclick="permissionsManager.exportPermissions()">
                    <i class="fas fa-download"></i> Xuất Excel
                </button>
                <button class="btn btn-sm btn-outline-info" onclick="permissionsManager.bulkAssignToRole()">
                    <i class="fas fa-users"></i> Gán hàng loạt
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Bulk Actions -->
            <div class="d-none" id="bulkActions">
                <div class="alert alert-info d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-info-circle"></i>
                        Đã chọn <span id="selectedCount">0</span> permissions
                    </span>
                    <div>
                        <button class="btn btn-sm btn-outline-danger" onclick="permissionsManager.bulkDelete()">
                            <i class="fas fa-trash"></i> Xóa đã chọn
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="permissionsManager.clearSelection()">
                            <i class="fas fa-times"></i> Bỏ chọn
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="40">
                                <input type="checkbox" class="form-check-input" id="selectAll" 
                                       onchange="permissionsManager.toggleSelectAll()">
                            </th>
                            <th>Code</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Mô tả</th>
                            <th>Roles sử dụng</th>
                            <th>Ngày tạo</th>
                            <th width="120">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="permissionsTableBody">
                        <!-- Permissions sẽ được load bằng JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Permissions pagination">
                <ul class="pagination justify-content-center" id="permissionsPagination">
                    <!-- Pagination sẽ được tạo bằng JavaScript -->
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Create/Edit Permission Modal -->
<div class="modal fade" id="createPermissionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="permissionModalTitle">Tạo Permission mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="permissionForm" onsubmit="permissionsManager.savePermission(event)">
                <div class="modal-body">
                    <input type="hidden" id="permissionId">
                    
                    <div class="mb-3">
                        <label for="permissionCode" class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="permissionCode" required
                               placeholder="e.g., project.create, task.update">
                        <div class="form-text">Format: module.action (e.g., project.create)</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label for="permissionModule" class="form-label">Module <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="permissionModule" required
                                   placeholder="e.g., project, task, document">
                        </div>
                        <div class="col-md-6">
                            <label for="permissionAction" class="form-label">Action <span class="text-danger">*</span></label>
                            <select class="form-select" id="permissionAction" required>
                                <option value="">Chọn action</option>
                                <option value="create">Create</option>
                                <option value="read">Read</option>
                                <option value="update">Update</option>
                                <option value="delete">Delete</option>
                                <option value="manage">Manage</option>
                                <option value="approve">Approve</option>
                                <option value="export">Export</option>
                                <option value="import">Import</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="permissionDescription" class="form-label">Mô tả <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="permissionDescription" rows="3" required
                                  placeholder="Mô tả chi tiết về quyền hạn này..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Gán cho Roles</label>
                        <div id="rolesAssignment" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <!-- Roles sẽ được load bằng JavaScript -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Lưu Permission
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign to Role Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gán Permissions cho Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="targetRole" class="form-label">Chọn Role</label>
                    <select class="form-select" id="targetRole" required>
                        <option value="">Chọn role...</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Permissions được chọn:</label>
                    <div id="selectedPermissionsList" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                        <!-- Selected permissions sẽ được hiển thị ở đây -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="permissionsManager.executeBulkAssign()">
                    <i class="fas fa-check"></i> Gán Permissions
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Sync Permissions Modal -->
<div class="modal fade" id="syncPermissionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đồng bộ Permissions từ Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Tính năng này sẽ quét code và tự động tạo permissions dựa trên các middleware và gates được định nghĩa.
                </div>
                
                <div id="syncResults" class="d-none">
                    <h6>Kết quả đồng bộ:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="text-success h4" id="syncCreated">0</div>
                                <small class="text-muted">Permissions mới</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="text-warning h4" id="syncUpdated">0</div>
                                <small class="text-muted">Đã cập nhật</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div class="text-danger h4" id="syncObsolete">0</div>
                                <small class="text-muted">Không còn sử dụng</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Chi tiết:</h6>
                        <div id="syncDetails" style="max-height: 300px; overflow-y: auto;">
                            <!-- Sync details sẽ được hiển thị ở đây -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary" onclick="permissionsManager.executeSyncPermissions()">
                    <i class="fas fa-sync"></i> Bắt đầu đồng bộ
                </button>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
/**
 * Quản lý trang Permissions
 * Xử lý CRUD permissions, đồng bộ từ code, gán hàng loạt
 */
class PermissionsManager {
    constructor() {
        this.permissions = [];
        this.roles = [];
        this.filteredPermissions = [];
        this.selectedPermissionIds = new Set();
        this.currentPage = 1;
        this.perPage = 20;
        this.selectedPermissionId = null;
        
        this.init();
    }

    /**
     * Khởi tạo manager
     */
    async init() {
        try {
            await Promise.all([
                this.loadPermissions(),
                this.loadRoles()
            ]);
            this.updateStatistics();
            this.populateModuleFilter();
        } catch (error) {
            console.error('Error initializing permissions manager:', error);
            this.showAlert('Lỗi khi khởi tạo trang quản lý permissions', 'danger');
        }
    }

    /**
     * Load danh sách permissions từ API
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
            this.permissions = data.data || [];
            this.filteredPermissions = [...this.permissions];
            this.renderPermissions();
        } catch (error) {
            console.error('Error loading permissions:', error);
            this.showAlert('Lỗi khi tải danh sách permissions', 'danger');
        }
    }

    /**
     * Load danh sách roles từ API
     */
    async loadRoles() {
        try {
            const response = await fetch('/api/v1/rbac/roles', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load roles');

            const data = await response.json();
            this.roles = data.data || [];
            this.populateRoleSelects();
        } catch (error) {
            console.error('Error loading roles:', error);
        }
    }

    /**
     * Populate module filter dropdown
     */
    populateModuleFilter() {
        const modules = [...new Set(this.permissions.map(p => p.module))].sort();
        const filterModule = document.getElementById('filterModule');
        
        // Clear existing options except "Tất cả modules"
        filterModule.innerHTML = '<option value="">Tất cả modules</option>';
        
        modules.forEach(module => {
            const option = document.createElement('option');
            option.value = module;
            option.textContent = module;
            filterModule.appendChild(option);
        });
    }

    /**
     * Populate role select dropdowns
     */
    populateRoleSelects() {
        // Populate roles assignment in create/edit modal
        const rolesAssignment = document.getElementById('rolesAssignment');
        rolesAssignment.innerHTML = this.roles.map(role => `
            <div class="form-check">
                <input class="form-check-input role-checkbox" type="checkbox" 
                       value="${role.id}" id="role_${role.id}">
                <label class="form-check-label" for="role_${role.id}">
                    <strong>${role.name}</strong>
                    <small class="text-muted d-block">${role.description || ''}</small>
                </label>
            </div>
        `).join('');

        // Populate target role in bulk assign modal
        const targetRole = document.getElementById('targetRole');
        targetRole.innerHTML = '<option value="">Chọn role...</option>' + 
            this.roles.map(role => `<option value="${role.id}">${role.name}</option>`).join('');
    }

    /**
     * Apply filters
     */
    applyFilters() {
        const moduleFilter = document.getElementById('filterModule').value;
        const actionFilter = document.getElementById('filterAction').value;
        const searchTerm = document.getElementById('searchPermissions').value.toLowerCase();

        this.filteredPermissions = this.permissions.filter(permission => {
            const matchesModule = !moduleFilter || permission.module === moduleFilter;
            const matchesAction = !actionFilter || permission.action === actionFilter;
            const matchesSearch = !searchTerm || 
                permission.code.toLowerCase().includes(searchTerm) ||
                permission.description.toLowerCase().includes(searchTerm);

            return matchesModule && matchesAction && matchesSearch;
        });

        this.currentPage = 1;
        this.renderPermissions();
        this.updateStatistics();
    }

    /**
     * Reset filters
     */
    resetFilters() {
        document.getElementById('filterModule').value = '';
        document.getElementById('filterAction').value = '';
        document.getElementById('searchPermissions').value = '';
        this.applyFilters();
    }

    /**
     * Update statistics
     */
    updateStatistics() {
        const totalPermissions = this.permissions.length;
        const totalModules = new Set(this.permissions.map(p => p.module)).size;
        const usedPermissions = this.permissions.filter(p => p.roles_count > 0).length;
        const unusedPermissions = totalPermissions - usedPermissions;

        document.getElementById('totalPermissions').textContent = totalPermissions;
        document.getElementById('totalModules').textContent = totalModules;
        document.getElementById('usedPermissions').textContent = usedPermissions;
        document.getElementById('unusedPermissions').textContent = unusedPermissions;
    }

    /**
     * Render permissions table
     */
    renderPermissions() {
        const startIndex = (this.currentPage - 1) * this.perPage;
        const endIndex = startIndex + this.perPage;
        const pagePermissions = this.filteredPermissions.slice(startIndex, endIndex);

        const tbody = document.getElementById('permissionsTableBody');
        
        if (pagePermissions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="fas fa-search fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Không tìm thấy permissions nào</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = pagePermissions.map(permission => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input permission-select" 
                           value="${permission.id}" onchange="permissionsManager.togglePermissionSelection(${permission.id})">
                </td>
                <td>
                    <code>${permission.code}</code>
                </td>
                <td>
                    <span class="badge bg-primary">${permission.module}</span>
                </td>
                <td>
                    <span class="badge bg-secondary">${permission.action}</span>
                </td>
                <td>
                    <span title="${permission.description}">
                        ${permission.description.length > 50 ? permission.description.substring(0, 50) + '...' : permission.description}
                    </span>
                </td>
                <td>
                    <span class="badge bg-info">${permission.roles_count || 0}</span>
                </td>
                <td>
                    <small class="text-muted">${this.formatDate(permission.created_at)}</small>
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="permissionsManager.editPermission(${permission.id})" title="Chỉnh sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="permissionsManager.viewPermissionRoles(${permission.id})" title="Xem roles">
                            <i class="fas fa-users"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="permissionsManager.deletePermission(${permission.id})" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        this.renderPagination();
        this.updateSelectionUI();
    }

    /**
     * Render pagination
     */
    renderPagination() {
        const totalPages = Math.ceil(this.filteredPermissions.length / this.perPage);
        const pagination = document.getElementById('permissionsPagination');
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let paginationHTML = '';
        
        // Previous button
        paginationHTML += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="permissionsManager.goToPage(${this.currentPage - 1})">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;
        
        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                paginationHTML += `
                    <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="permissionsManager.goToPage(${i})">${i}</a>
                    </li>
                `;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }
        
        // Next button
        paginationHTML += `
            <li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="permissionsManager.goToPage(${this.currentPage + 1})">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;
        
        pagination.innerHTML = paginationHTML;
    }

    /**
     * Go to specific page
     */
    goToPage(page) {
        const totalPages = Math.ceil(this.filteredPermissions.length / this.perPage);
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page;
            this.renderPermissions();
        }
    }

    /**
     * Toggle permission selection
     */
    togglePermissionSelection(permissionId) {
        if (this.selectedPermissionIds.has(permissionId)) {
            this.selectedPermissionIds.delete(permissionId);
        } else {
            this.selectedPermissionIds.add(permissionId);
        }
        this.updateSelectionUI();
    }

    /**
     * Toggle select all permissions
     */
    toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.permission-select');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
            const permissionId = parseInt(checkbox.value);
            if (selectAll.checked) {
                this.selectedPermissionIds.add(permissionId);
            } else {
                this.selectedPermissionIds.delete(permissionId);
            }
        });
        
        this.updateSelectionUI();
    }

    /**
     * Update selection UI
     */
    updateSelectionUI() {
        const selectedCount = this.selectedPermissionIds.size;
        const bulkActions = document.getElementById('bulkActions');
        const selectedCountSpan = document.getElementById('selectedCount');
        
        if (selectedCount > 0) {
            bulkActions.classList.remove('d-none');
            selectedCountSpan.textContent = selectedCount;
        } else {
            bulkActions.classList.add('d-none');
        }
        
        // Update checkboxes state
        document.querySelectorAll('.permission-select').forEach(checkbox => {
            checkbox.checked = this.selectedPermissionIds.has(parseInt(checkbox.value));
        });
        
        // Update select all checkbox
        const selectAll = document.getElementById('selectAll');
        const visibleCheckboxes = document.querySelectorAll('.permission-select');
        const checkedCount = document.querySelectorAll('.permission-select:checked').length;
        
        if (checkedCount === 0) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        } else if (checkedCount === visibleCheckboxes.length) {
            selectAll.checked = true;
            selectAll.indeterminate = false;
        } else {
            selectAll.checked = false;
            selectAll.indeterminate = true;
        }
    }

    /**
     * Clear selection
     */
    clearSelection() {
        this.selectedPermissionIds.clear();
        this.updateSelectionUI();
    }

    /**
     * Mở modal để tạo permission mới
     */
    createPermission() {
        this.selectedPermissionId = null;
        document.getElementById('permissionModalTitle').textContent = 'Tạo Permission mới';
        document.getElementById('permissionForm').reset();
        this.clearRoleSelections();
        
        const modal = new bootstrap.Modal(document.getElementById('createPermissionModal'));
        modal.show();
    }

    /**
     * Mở modal để chỉnh sửa permission
     */
    async editPermission(permissionId) {
        try {
            const response = await fetch(`/api/v1/rbac/permissions/${permissionId}`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load permission');

            const data = await response.json();
            const permission = data.data;

            this.selectedPermissionId = permissionId;
            document.getElementById('permissionModalTitle').textContent = 'Chỉnh sửa Permission';
            document.getElementById('permissionId').value = permission.id;
            document.getElementById('permissionCode').value = permission.code;
            document.getElementById('permissionModule').value = permission.module;
            document.getElementById('permissionAction').value = permission.action;
            document.getElementById('permissionDescription').value = permission.description;

            // Set selected roles
            this.clearRoleSelections();
            permission.roles.forEach(role => {
                const checkbox = document.getElementById(`role_${role.id}`);
                if (checkbox) checkbox.checked = true;
            });

            const modal = new bootstrap.Modal(document.getElementById('createPermissionModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading permission:', error);
            this.showAlert('Lỗi khi tải thông tin permission', 'danger');
        }
    }

    /**
     * Lưu permission (tạo mới hoặc cập nhật)
     */
    async savePermission(event) {
        event.preventDefault();
        
        try {
            const formData = {
                code: document.getElementById('permissionCode').value,
                module: document.getElementById('permissionModule').value,
                action: document.getElementById('permissionAction').value,
                description: document.getElementById('permissionDescription').value,
                roles: Array.from(document.querySelectorAll('.role-checkbox:checked')).map(cb => cb.value)
            };

            const url = this.selectedPermissionId 
                ? `/api/v1/rbac/permissions/${this.selectedPermissionId}`
                : '/api/v1/rbac/permissions';
            
            const method = this.selectedPermissionId ? 'PUT' : 'POST';

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
                throw new Error(errorData.message || 'Failed to save permission');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('createPermissionModal'));
            modal.hide();

            this.showAlert(
                this.selectedPermissionId ? 'Permission đã được cập nhật thành công' : 'Permission đã được tạo thành công',
                'success'
            );
            
            this.loadPermissions();
        } catch (error) {
            console.error('Error saving permission:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * Xóa permission
     */
    async deletePermission(permissionId) {
        if (!confirm('Bạn có chắc chắn muốn xóa permission này?')) return;

        try {
            const response = await fetch(`/api/v1/rbac/permissions/${permissionId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to delete permission');
            }

            this.showAlert('Permission đã được xóa thành công', 'success');
            this.loadPermissions();
        } catch (error) {
            console.error('Error deleting permission:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * Bulk delete selected permissions
     */
    async bulkDelete() {
        if (this.selectedPermissionIds.size === 0) return;
        
        if (!confirm(`Bạn có chắc chắn muốn xóa ${this.selectedPermissionIds.size} permissions đã chọn?`)) return;

        try {
            const response = await fetch('/api/v1/rbac/permissions/bulk-delete', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    permission_ids: Array.from(this.selectedPermissionIds)
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to delete permissions');
            }

            this.showAlert(`Đã xóa ${this.selectedPermissionIds.size} permissions thành công`, 'success');
            this.clearSelection();
            this.loadPermissions();
        } catch (error) {
            console.error('Error bulk deleting permissions:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * View permission roles
     */
    async viewPermissionRoles(permissionId) {
        try {
            const permission = this.permissions.find(p => p.id === permissionId);
            if (!permission) return;

            const response = await fetch(`/api/v1/rbac/permissions/${permissionId}/roles`, {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) throw new Error('Failed to load permission roles');

            const data = await response.json();
            const roles = data.data || [];

            let message = `<strong>Permission:</strong> <code>${permission.code}</code><br><br>`;
            
            if (roles.length === 0) {
                message += '<em>Permission này chưa được gán cho role nào.</em>';
            } else {
                message += '<strong>Được sử dụng bởi các roles:</strong><ul>';
                roles.forEach(role => {
                    message += `<li><strong>${role.name}</strong> (${role.scope})</li>`;
                });
                message += '</ul>';
            }

            this.showAlert(message, 'info', false);
        } catch (error) {
            console.error('Error loading permission roles:', error);
            this.showAlert('Lỗi khi tải thông tin roles của permission', 'danger');
        }
    }

    /**
     * Bulk assign to role
     */
    bulkAssignToRole() {
        if (this.selectedPermissionIds.size === 0) {
            this.showAlert('Vui lòng chọn ít nhất một permission', 'warning');
            return;
        }

        // Show selected permissions
        const selectedPermissions = this.permissions.filter(p => this.selectedPermissionIds.has(p.id));
        const selectedList = document.getElementById('selectedPermissionsList');
        selectedList.innerHTML = selectedPermissions.map(permission => `
            <div class="d-flex justify-content-between align-items-center py-1">
                <span><code>${permission.code}</code></span>
                <small class="text-muted">${permission.module}.${permission.action}</small>
            </div>
        `).join('');

        const modal = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
        modal.show();
    }

    /**
     * Execute bulk assign
     */
    async executeBulkAssign() {
        const roleId = document.getElementById('targetRole').value;
        if (!roleId) {
            this.showAlert('Vui lòng chọn role', 'warning');
            return;
        }

        try {
            const response = await fetch('/api/v1/rbac/roles/bulk-assign-permissions', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    role_id: roleId,
                    permission_ids: Array.from(this.selectedPermissionIds)
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to assign permissions');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('bulkAssignModal'));
            modal.hide();

            this.showAlert(`Đã gán ${this.selectedPermissionIds.size} permissions cho role thành công`, 'success');
            this.clearSelection();
            this.loadPermissions();
        } catch (error) {
            console.error('Error bulk assigning permissions:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * Sync permissions from code
     */
    syncPermissions() {
        const modal = new bootstrap.Modal(document.getElementById('syncPermissionsModal'));
        modal.show();
    }

    /**
     * Execute sync permissions
     */
    async executeSyncPermissions() {
        try {
            const response = await fetch('/api/v1/rbac/permissions/sync', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to sync permissions');
            }

            const data = await response.json();
            const results = data.data;

            // Show results
            document.getElementById('syncCreated').textContent = results.created || 0;
            document.getElementById('syncUpdated').textContent = results.updated || 0;
            document.getElementById('syncObsolete').textContent = results.obsolete || 0;

            const syncDetails = document.getElementById('syncDetails');
            let detailsHTML = '';
            
            if (results.details) {
                if (results.details.created && results.details.created.length > 0) {
                    detailsHTML += '<h6 class="text-success">Permissions mới:</h6><ul>';
                    results.details.created.forEach(item => {
                        detailsHTML += `<li><code>${item.code}</code> - ${item.description}</li>`;
                    });
                    detailsHTML += '</ul>';
                }
                
                if (results.details.updated && results.details.updated.length > 0) {
                    detailsHTML += '<h6 class="text-warning">Đã cập nhật:</h6><ul>';
                    results.details.updated.forEach(item => {
                        detailsHTML += `<li><code>${item.code}</code> - ${item.description}</li>`;
                    });
                    detailsHTML += '</ul>';
                }
                
                if (results.details.obsolete && results.details.obsolete.length > 0) {
                    detailsHTML += '<h6 class="text-danger">Không còn sử dụng:</h6><ul>';
                    results.details.obsolete.forEach(item => {
                        detailsHTML += `<li><code>${item.code}</code> - ${item.description}</li>`;
                    });
                    detailsHTML += '</ul>';
                }
            }
            
            syncDetails.innerHTML = detailsHTML || '<em>Không có thay đổi nào.</em>';
            document.getElementById('syncResults').classList.remove('d-none');

            this.showAlert('Đồng bộ permissions thành công', 'success');
            this.loadPermissions();
        } catch (error) {
            console.error('Error syncing permissions:', error);
            this.showAlert(error.message, 'danger');
        }
    }

    /**
     * Export permissions to Excel
     */
    async exportPermissions() {
        try {
            const response = await fetch('/api/v1/rbac/permissions/export', {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
                    'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                }
            });

            if (!response.ok) throw new Error('Failed to export permissions');

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `permissions_${new Date().toISOString().split('T')[0]}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            this.showAlert('Đã xuất file Excel thành công', 'success');
        } catch (error) {
            console.error('Error exporting permissions:', error);
            this.showAlert('Lỗi khi xuất file Excel', 'danger');
        }
    }

    /**
     * Clear role selections
     */
    clearRoleSelections() {
        document.querySelectorAll('.role-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
    }

    /**
     * Format date
     */
    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleDateString('vi-VN');
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info', autoHide = true) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        if (autoHide) {
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    }
}

// Initialize permissions manager when page loads
let permissionsManager;
document.addEventListener('DOMContentLoaded', function() {
    permissionsManager = new PermissionsManager();
});
</script>
<?php $__env->stopPush(); ?>
<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/zenamanage/resources/views/rbac/permissions.blade.php ENDPATH**/ ?>