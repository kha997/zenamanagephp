@extends('layouts.app')

@section('title', 'Quản lý Tài liệu')

@section('content')
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">Quản lý Tài liệu</h1>
        <div class="page-actions">
            <button class="btn btn-primary" onclick="showUploadModal()">
                <i class="icon-upload"></i> Tải lên tài liệu
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
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type-filter">Loại tài liệu:</label>
                    <select id="type-filter" class="form-control">
                        <option value="">Tất cả loại</option>
                        <option value="task">Công việc</option>
                        <option value="diary">Nhật ký</option>
                        <option value="cr">Change Request</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="search-input">Tìm kiếm:</label>
                    <input type="text" id="search-input" class="form-control" placeholder="Tên tài liệu...">
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div class="d-flex">
                        <button class="btn btn-primary mr-2" onclick="applyFilters()">
                            <i class="icon-search"></i> Tìm kiếm
                        </button>
                        <button class="btn btn-secondary" onclick="resetFilters()">
                            <i class="icon-refresh-cw"></i> Đặt lại
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tên tài liệu</th>
                            <th>Dự án</th>
                            <th>Loại</th>
                            <th>Phiên bản hiện tại</th>
                            <th>Kích thước</th>
                            <th>Ngày tạo</th>
                            <th>Người tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="documents-table-body">
                        <!-- Content will be loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="pagination-container" class="d-flex justify-content-center mt-4">
                <!-- Pagination will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tải lên tài liệu</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="upload-form" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="document-title">Tên tài liệu <span class="text-danger">*</span></label>
                                <input type="text" id="document-title" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="document-project">Dự án <span class="text-danger">*</span></label>
                                <select id="document-project" name="project_id" class="form-control" required>
                                    <option value="">Chọn dự án</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="linked-entity-type">Liên kết với:</label>
                                <select id="linked-entity-type" name="linked_entity_type" class="form-control">
                                    <option value="">Không liên kết</option>
                                    <option value="task">Công việc</option>
                                    <option value="diary">Nhật ký</option>
                                    <option value="cr">Change Request</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="linked-entity-id">ID liên kết:</label>
                                <input type="number" id="linked-entity-id" name="linked_entity_id" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="document-file">Chọn file <span class="text-danger">*</span></label>
                        <input type="file" id="document-file" name="file" class="form-control-file" required>
                        <small class="form-text text-muted">Hỗ trợ: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG (Tối đa 50MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="upload-comment">Ghi chú:</label>
                        <textarea id="upload-comment" name="comment" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" onclick="uploadDocument()">Tải lên</button>
            </div>
        </div>
    </div>
</div>

<!-- Version History Modal -->
<div class="modal fade" id="versionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lịch sử phiên bản</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="version-history">
                    <!-- Version history will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
class DocumentsManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 20;
        this.filters = {
            project_id: '',
            linked_entity_type: '',
            search: ''
        };
        
        this.loadProjects();
        this.loadDocuments();
    }

    async loadProjects() {
        try {
            const response = await zenaApp.apiCall('GET', '/api/v1/projects?per_page=1000');
            
            if (response.status === 'success') {
                const projectSelects = ['#project-filter', '#document-project'];
                
                projectSelects.forEach(selector => {
                    const select = document.querySelector(selector);
                    select.innerHTML = '<option value="">Tất cả dự án</option>';
                    
                    response.data.data.forEach(project => {
                        select.innerHTML += `<option value="${project.id}">${project.name}</option>`;
                    });
                });
            }
        } catch (error) {
            console.error('Error loading projects:', error);
        }
    }

    async loadDocuments() {
        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: this.perPage,
                ...this.filters
            });
            
            const response = await zenaApp.apiCall('GET', `/api/v1/documents?${params}`);
            
            if (response.status === 'success') {
                this.renderDocumentsTable(response.data.data);
                this.renderPagination(response.data);
            }
        } catch (error) {
            zenaApp.showNotification('Lỗi khi tải danh sách tài liệu', 'error');
        }
    }

    renderDocumentsTable(documents) {
        const tbody = document.getElementById('documents-table-body');
        
        if (documents.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">Không có tài liệu nào</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = documents.map(doc => `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="icon-${this.getFileIcon(doc.current_version.file_path)} mr-2"></i>
                        <span>${doc.title}</span>
                    </div>
                </td>
                <td>${doc.project.name}</td>
                <td>
                    <span class="badge badge-${this.getTypeColor(doc.linked_entity_type)}">
                        ${this.getTypeText(doc.linked_entity_type)}
                    </span>
                </td>
                <td>v${doc.current_version.version_number}</td>
                <td>${this.formatFileSize(doc.current_version.file_size)}</td>
                <td>${zenaApp.formatDate(doc.created_at)}</td>
                <td>${doc.created_by_user.name}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="downloadDocument(${doc.id})" title="Tải xuống">
                            <i class="icon-download"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="viewVersionHistory(${doc.id})" title="Lịch sử phiên bản">
                            <i class="icon-clock"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="uploadNewVersion(${doc.id})" title="Tải lên phiên bản mới">
                            <i class="icon-upload"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${doc.id})" title="Xóa">
                            <i class="icon-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    renderPagination(data) {
        const container = document.getElementById('pagination-container');
        
        if (data.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let pagination = '<nav><ul class="pagination">';
        
        // Previous button
        if (data.current_page > 1) {
            pagination += `<li class="page-item"><a class="page-link" href="#" onclick="documentsManager.goToPage(${data.current_page - 1})">Trước</a></li>`;
        }
        
        // Page numbers
        for (let i = Math.max(1, data.current_page - 2); i <= Math.min(data.last_page, data.current_page + 2); i++) {
            const active = i === data.current_page ? 'active' : '';
            pagination += `<li class="page-item ${active}"><a class="page-link" href="#" onclick="documentsManager.goToPage(${i})">${i}</a></li>`;
        }
        
        // Next button
        if (data.current_page < data.last_page) {
            pagination += `<li class="page-item"><a class="page-link" href="#" onclick="documentsManager.goToPage(${data.current_page + 1})">Sau</a></li>`;
        }
        
        pagination += '</ul></nav>';
        container.innerHTML = pagination;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadDocuments();
    }

    getFileIcon(filePath) {
        const extension = filePath.split('.').pop().toLowerCase();
        const iconMap = {
            'pdf': 'file-text',
            'doc': 'file-text',
            'docx': 'file-text',
            'xls': 'file-text',
            'xlsx': 'file-text',
            'ppt': 'file-text',
            'pptx': 'file-text',
            'txt': 'file-text',
            'jpg': 'image',
            'jpeg': 'image',
            'png': 'image',
            'gif': 'image'
        };
        return iconMap[extension] || 'file';
    }

    getTypeColor(type) {
        const colors = {
            'task': 'primary',
            'diary': 'info',
            'cr': 'warning'
        };
        return colors[type] || 'secondary';
    }

    getTypeText(type) {
        const texts = {
            'task': 'Công việc',
            'diary': 'Nhật ký',
            'cr': 'Change Request'
        };
        return texts[type] || 'Khác';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Global functions
function showUploadModal() {
    document.getElementById('upload-form').reset();
    $('#uploadModal').modal('show');
}

function applyFilters() {
    documentsManager.filters = {
        project_id: document.getElementById('project-filter').value,
        linked_entity_type: document.getElementById('type-filter').value,
        search: document.getElementById('search-input').value
    };
    documentsManager.currentPage = 1;
    documentsManager.loadDocuments();
}

function resetFilters() {
    document.getElementById('project-filter').value = '';
    document.getElementById('type-filter').value = '';
    document.getElementById('search-input').value = '';
    applyFilters();
}

async function uploadDocument() {
    const form = document.getElementById('upload-form');
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }

    const formData = new FormData(form);
    
    try {
        const response = await zenaApp.apiCall('POST', '/api/v1/documents', formData, {
            'Content-Type': 'multipart/form-data'
        });
        
        if (response.status === 'success') {
            zenaApp.showNotification('Tải lên tài liệu thành công', 'success');
            $('#uploadModal').modal('hide');
            documentsManager.loadDocuments();
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi tải lên tài liệu', 'error');
    }
}

function downloadDocument(documentId) {
    window.open(`/api/v1/documents/${documentId}/download`, '_blank');
}

async function viewVersionHistory(documentId) {
    try {
        const response = await zenaApp.apiCall('GET', `/api/v1/documents/${documentId}/versions`);
        
        if (response.status === 'success') {
            const container = document.getElementById('version-history');
            
            container.innerHTML = response.data.map(version => `
                <div class="version-item border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">Phiên bản ${version.version_number}</h6>
                            <p class="text-muted mb-1">${version.comment || 'Không có ghi chú'}</p>
                            <small class="text-muted">
                                Tạo bởi ${version.created_by_user.name} - ${zenaApp.formatDateTime(version.created_at)}
                            </small>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadVersion(${version.id})">
                                <i class="icon-download"></i> Tải xuống
                            </button>
                            ${version.version_number !== 1 ? `
                                <button class="btn btn-sm btn-outline-warning" onclick="revertToVersion(${documentId}, ${version.version_number})">
                                    <i class="icon-rotate-ccw"></i> Khôi phục
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
            
            $('#versionModal').modal('show');
        }
    } catch (error) {
        zenaApp.showNotification('Lỗi khi tải lịch sử phiên bản', 'error');
    }
}

function uploadNewVersion(documentId) {
    // This would open a modal for uploading new version
    zenaApp.showNotification('Chức năng upload phiên bản mới sẽ được triển khai', 'info');
}

function deleteDocument(documentId) {
    if (confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) {
        zenaApp.apiCall('DELETE', `/api/v1/documents/${documentId}`)
            .then(response => {
                if (response.status === 'success') {
                    zenaApp.showNotification('Xóa tài liệu thành công', 'success');
                    documentsManager.loadDocuments();
                }
            })
            .catch(error => {
                zenaApp.showNotification('Lỗi khi xóa tài liệu', 'error');
            });
    }
}

function downloadVersion(versionId) {
    window.open(`/api/v1/document-versions/${versionId}/download`, '_blank');
}

function revertToVersion(documentId, versionNumber) {
    if (confirm(`Bạn có chắc chắn muốn khôi phục về phiên bản ${versionNumber}?`)) {
        zenaApp.apiCall('POST', `/api/v1/documents/${documentId}/revert`, { version_number: versionNumber })
            .then(response => {
                if (response.status === 'success') {
                    zenaApp.showNotification('Khôi phục phiên bản thành công', 'success');
                    $('#versionModal').modal('hide');
                    documentsManager.loadDocuments();
                }
            })
            .catch(error => {
                zenaApp.showNotification('Lỗi khi khôi phục phiên bản', 'error');
            });
    }
}

// Initialize when page loads
let documentsManager;
document.addEventListener('DOMContentLoaded', function() {
    documentsManager = new DocumentsManager();
});
</script>
@endsection